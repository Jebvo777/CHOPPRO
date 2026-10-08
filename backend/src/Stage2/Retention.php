<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Retention
{
    public function __construct(public Db $db,public array $config){}
    public function cutoff():string{return(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->modify('-3 years')->format('Y-m-d H:i:s');}
    public function summary(?string $tenant=null):array
    {
        return['years'=>3,'cutoff'=>$this->cutoff(),'policy'=>['attendance'=>'3 года с момента события','audit'=>'3 года с момента события','reports'=>'3 года с последнего изменения опубликованного отчёта','documents'=>'3 года после окончания срока или удаления','contracts'=>'3 года после окончания договора','personal_data'=>'3 года после удаления или деактивации','files'=>'3 года; действующие договоры и лицензии сохраняются до окончания срока и ещё 3 года'],'pending_files'=>(int)$this->db->scalar('SELECT COUNT(*) FROM cp_retention_pending_files'.($tenant?' WHERE tenant_id=?':''),$tenant?[$tenant]:[]),'runs'=>$this->db->all('SELECT * FROM cp_retention_runs'.($tenant?' WHERE tenant_id=?':'').' ORDER BY created_at DESC LIMIT 20',$tenant?[$tenant]:[])];
    }
    private function file(string $tenant,string $id,string $name):void
    {
        if($name!=='')$this->db->run('INSERT INTO cp_retention_pending_files(id,tenant_id,file_name,created_at)VALUES(?,?,?,?)',[Support::uuid(),$tenant,basename($name),Support::now()]);
        $this->db->run('DELETE FROM cp_file_sizes WHERE id=? AND tenant_id=?',[$id,$tenant]);
    }
    public function unlinkPending(?string $tenant=null):int
    {
        $count=0;foreach($this->db->all('SELECT * FROM cp_retention_pending_files'.($tenant?' WHERE tenant_id=?':'').' LIMIT 500',$tenant?[$tenant]:[])as$row){$refs=(int)$this->db->scalar('SELECT COUNT(*) FROM cp_documents WHERE tenant_id=? AND file_path=?',[$row['tenant_id'],$row['file_name']])+(int)$this->db->scalar('SELECT COUNT(*) FROM cp_files WHERE tenant_id=? AND file_path=?',[$row['tenant_id'],$row['file_name']]);if($refs){$this->db->run('DELETE FROM cp_retention_pending_files WHERE id=?',[$row['id']]);continue;}$path=$this->config['storage'].'/uploads/'.$row['tenant_id'].'/'.basename($row['file_name']);if(is_file($path)&&!unlink($path))continue;$this->db->run('DELETE FROM cp_retention_pending_files WHERE id=?',[$row['id']]);$count++;}return$count;
    }
    private function purge(string $tenant,string $cutoff):array
    {
        $counts=[];
        foreach(['mobile_events'=>'created_at','patrol_scans'=>'client_time','attendance_explanations'=>'created_at','policy_receipts'=>'created_at','report_jobs'=>'updated_at']as$table=>$field)$counts[$table]=$this->db->run('DELETE FROM cp_'.$table.' WHERE tenant_id=? AND '.$field.'<?',[$tenant,$cutoff])->rowCount();

        $employees=array_column($this->db->all("SELECT id FROM cp_employees WHERE tenant_id=? AND (deleted_at<? OR (status='DISMISSED' AND updated_at<?))",[$tenant,$cutoff,$cutoff]),'id');
        $docs=$this->db->all('SELECT id,file_path,employee_id,expires_at,deleted_at FROM cp_documents WHERE tenant_id=?',[$tenant]);
        foreach($docs as$d)if(($d['expires_at']&&$d['expires_at']<substr($cutoff,0,10))||($d['deleted_at']&&$d['deleted_at']<$cutoff)||in_array($d['employee_id'],$employees,true)){$this->file($tenant,$d['id'],$d['file_path']??'');$this->db->run('DELETE FROM cp_document_reviews WHERE document_id=? AND tenant_id=?',[$d['id'],$tenant]);$this->db->run('DELETE FROM cp_documents WHERE id=? AND tenant_id=?',[$d['id'],$tenant]);$counts['documents']=($counts['documents']??0)+1;}
        $contracts=array_column($this->db->all('SELECT id FROM cp_contracts WHERE tenant_id=? AND ends_at<? AND (legal_hold_until IS NULL OR legal_hold_until<?)',[$tenant,substr($cutoff,0,10),gmdate('Y-m-d')]),'id');
        foreach($this->db->all('SELECT * FROM cp_files WHERE tenant_id=?',[$tenant])as$f){
            $expired=$f['deleted_at']&&$f['deleted_at']<$cutoff;
            if($f['entity_type']==='contracts')$expired=$expired||in_array($f['entity_id'],$contracts,true);
            elseif($f['entity_type']==='licenses'){$license=$this->db->one('SELECT expires_at,deleted_at FROM cp_licenses WHERE id=? AND tenant_id=?',[$f['entity_id'],$tenant]);$expired=$expired||($license&&(($license['expires_at']&&$license['expires_at']<substr($cutoff,0,10))||($license['deleted_at']&&$license['deleted_at']<$cutoff)));}
            else $expired=$expired||$f['created_at']<$cutoff;
            if($expired){$this->file($tenant,$f['id'],$f['file_path']);$this->db->run('DELETE FROM cp_files WHERE id=? AND tenant_id=?',[$f['id'],$tenant]);$counts['files']=($counts['files']??0)+1;}
        }
        foreach($contracts as$id){foreach(['contract_versions','compliance_tasks']as$table)$this->db->run('DELETE FROM cp_'.$table.' WHERE tenant_id=? AND contract_id=?',[$tenant,$id]);$this->db->run('UPDATE cp_facilities SET contract_id=NULL WHERE tenant_id=? AND contract_id=?',[$tenant,$id]);$this->db->run('DELETE FROM cp_contracts WHERE tenant_id=? AND id=?',[$tenant,$id]);}$counts['contracts']=count($contracts);
        foreach(['attendance'=>'server_time','audit'=>'created_at','report_receipts'=>'created_at','report_versions'=>'created_at','assignment_history'=>'created_at','document_reviews'=>'created_at','instruction_receipts'=>'acknowledged_at','notifications'=>'created_at','outbox'=>'created_at','break_glass'=>'expires_at','retention_runs'=>'created_at']as$table=>$field)$counts[$table]=$this->db->run('DELETE FROM cp_'.$table.' WHERE tenant_id=? AND '.$field.'<?',[$tenant,$cutoff])->rowCount();
        $counts['presence']=$this->db->run('DELETE FROM cp_presence WHERE tenant_id=? AND COALESCE(finished_at,started_at,updated_at)<?',[$tenant,$cutoff])->rowCount();
        foreach($this->db->all('SELECT id,file_path FROM cp_reports WHERE tenant_id=? AND updated_at<? AND published=1',[$tenant,$cutoff])as$report){if($report['file_path'])@unlink($this->config['storage'].'/reports/'.$tenant.'/'.basename($report['file_path']));$this->db->run('DELETE FROM cp_file_sizes WHERE id=? AND tenant_id=?',[$report['id'],$tenant]);}
        foreach(['reports'=>"published=1",'patrols'=>"status IN ('COMPLETED','PARTIAL','MISSED')",'incidents'=>"status='RESOLVED'",'applications'=>"1=1",'no_shows'=>"status='RESOLVED'"]as$table=>$condition)$counts[$table]=$this->db->run('DELETE FROM cp_'.$table.' WHERE tenant_id=? AND updated_at<? AND '.$condition,[$tenant,$cutoff])->rowCount();
        $this->db->run('DELETE m FROM cp_mobile_patrol_runs m LEFT JOIN cp_patrols p ON p.id=m.patrol_id WHERE m.tenant_id=? AND (p.id IS NULL OR m.started_at<?)',[$tenant,$cutoff]);
        $this->db->run('DELETE m FROM cp_mobile_incidents m LEFT JOIN cp_incidents i ON i.id=m.incident_id WHERE m.tenant_id=? AND i.id IS NULL',[$tenant]);
        $this->db->run('DELETE m FROM cp_mobile_uploads m LEFT JOIN cp_files f ON f.id=m.file_id WHERE m.tenant_id=? AND f.id IS NULL',[$tenant]);
        $oldShifts=array_column($this->db->all('SELECT id FROM cp_shifts WHERE tenant_id=? AND ends_at<?',[$tenant,$cutoff]),'id');
        foreach($oldShifts as$id){$this->db->run('DELETE p FROM cp_presence p JOIN cp_assignments a ON a.id=p.assignment_id WHERE a.tenant_id=? AND a.shift_id=?',[$tenant,$id]);$this->db->run('DELETE n FROM cp_no_shows n JOIN cp_assignments a ON a.id=n.assignment_id WHERE a.tenant_id=? AND a.shift_id=?',[$tenant,$id]);$this->db->run('DELETE FROM cp_assignments WHERE tenant_id=? AND shift_id=?',[$tenant,$id]);$this->db->run('DELETE FROM cp_shifts WHERE tenant_id=? AND id=?',[$tenant,$id]);}$counts['shifts']=count($oldShifts);
        $counts['sessions']=$this->db->run('DELETE s FROM cp_sessions s JOIN cp_users u ON u.id=s.user_id WHERE u.tenant_id=? AND s.refresh_expires_at<?',[$tenant,$cutoff])->rowCount();
        $users=array_column($this->db->all("SELECT id FROM cp_users WHERE tenant_id=? AND (deleted_at<? OR (status IN ('BLOCKED','DISMISSED') AND updated_at<?))",[$tenant,$cutoff,$cutoff]),'id');
        foreach($users as$id){foreach(['sessions','challenges']as$table)$this->db->run('DELETE FROM cp_'.$table.' WHERE user_id=?',[$id]);$this->db->run('DELETE FROM cp_users WHERE id=? AND tenant_id=?',[$id,$tenant]);}$counts['users']=count($users);
        foreach($employees as$id){foreach(['personal_cards','instruction_receipts']as$table)$this->db->run('DELETE FROM cp_'.$table.' WHERE tenant_id=? AND employee_id=?',[$tenant,$id]);$this->db->run('DELETE FROM cp_employees WHERE tenant_id=? AND id=?',[$tenant,$id]);}$counts['employees']=count($employees);
        $this->db->run('INSERT INTO cp_retention_runs(id,tenant_id,cutoff,counts,created_at)VALUES(?,?,?,?,?)',[Support::uuid(),$tenant,$cutoff,Support::json($counts),Support::now()]);
        (new Access($this->db,['id'=>null,'tenant_id'=>$tenant,'role'=>'tenant_admin','scopes'=>'[]']))->audit('retention.completed','system',null,['years'=>3,'counts'=>$counts]);return$counts;
    }
    public function run(bool $force=false,?string $tenant=null):array
    {
        $statePath=$this->config['storage'].'/retention'.($tenant?'-'.$tenant:'').'.json';$state=is_file($statePath)?Support::decode(file_get_contents($statePath)):[];
        if(!$force&&($state['day']??'')===gmdate('Y-m-d'))return['skipped'=>true,'pending_cleaned'=>$this->unlinkPending($tenant)];
        $lock='choppro:retention:'.$this->config['database']['name'];if((int)$this->db->scalar('SELECT GET_LOCK(?,0)',[$lock])!==1)return['busy'=>true];$cutoff=$this->cutoff();$runs=[];
        try{foreach($this->db->all('SELECT id FROM cp_tenants WHERE deleted_at IS NULL'.($tenant?' AND id=?':''),$tenant?[$tenant]:[])as$t)$runs[$t['id']]=$this->db->transaction(fn()=>$this->purge($t['id'],$cutoff));$pending=$this->unlinkPending($tenant);Support::atomic($statePath,Support::json(['day'=>gmdate('Y-m-d'),'last_run'=>Support::now(),'years'=>3,'counts'=>$runs]));return['years'=>3,'tenants'=>count($runs),'counts'=>$runs,'files_cleaned'=>$pending];}
        finally{$this->db->run('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
}
