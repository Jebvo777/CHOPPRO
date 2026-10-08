<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Worker
{
    public function __construct(public Db $db,public array $config) {}
    public function tick(): array
    {
        (new Platform($this->db,$this->config))->ensure();$lock='choppro:worker:'.$this->config['database']['name'];if((int)$this->db->scalar('SELECT GET_LOCK(?,0)',[$lock])!==1)return ['busy'=>true];$counts=['documents'=>0,'reminders'=>0,'no_shows'=>0,'scans'=>0];
        try{
            foreach($this->db->all("SELECT * FROM cp_tenants WHERE status='ACTIVE' AND deleted_at IS NULL")as$t){
                $settings=Support::decode($t['settings']);$fake=['id'=>null,'tenant_id'=>$t['id'],'role'=>'tenant_admin','scopes'=>'[]'];$r=new Resources(new Access($this->db,$fake),$this->config);
                foreach($this->db->all("SELECT * FROM cp_documents WHERE tenant_id=? AND deleted_at IS NULL AND status IN ('VALID','EXPIRING','EXPIRED')",[$t['id']])as$d){$days=(int)floor((strtotime($d['expires_at']??'+100 years')-strtotime(gmdate('Y-m-d')))/86400);$status=$days<0?'EXPIRED':($days<=(int)($settings['expiring_days']??30)?'EXPIRING':'VALID');if($status!==$d['status']){$r->write('documents',$d['id'],['status'=>$status]);$r->access->audit('document.status_changed','documents',$d['id'],['old'=>$d['status'],'new'=>$status]);$counts['documents']++;}foreach($settings['reminder_days']??[30,14,7,1]as$step)if($days>=0&&$days<=(int)$step){$key='doc:'.$d['id'].':'.$d['expires_at'].':'.$step;$counts['reminders']+=$this->notify($t['id'],$key,'Документ истекает: '.$d['name'],'documents',$d['id'],['days_left'=>$days,'threshold'=>$step]);}}
                foreach($this->db->all("SELECT * FROM cp_documents WHERE tenant_id=? AND scan_status='PENDING_SCAN' AND deleted_at IS NULL LIMIT 20",[$t['id']])as$d){$path=$this->config['storage'].'/uploads/'.$t['id'].'/'.basename($d['file_path']??'');if(is_file($path)){$scan=(new Documents($r))->scan($path);if($scan!=='PENDING_SCAN'){$r->write('documents',$d['id'],['scan_status'=>$scan,'status'=>$scan==='INFECTED'?'REJECTED':'PENDING_REVIEW']);$counts['scans']++;}}}
                $this->db->run("UPDATE cp_compliance_tasks SET status='OVERDUE',updated_at=UTC_TIMESTAMP() WHERE tenant_id=? AND status IN ('DUE','DRAFT') AND deadline<UTC_TIMESTAMP() AND deleted_at IS NULL",[$t['id']]);
                foreach($this->db->all("SELECT * FROM cp_files WHERE tenant_id=? AND scan_status='PENDING_SCAN' AND deleted_at IS NULL LIMIT 20",[$t['id']])as$file){$path=$this->config['storage'].'/uploads/'.$t['id'].'/'.basename($file['file_path']);if(is_file($path)){$scan=(new Documents($r))->scan($path);if($scan!=='PENDING_SCAN')$this->db->run('UPDATE cp_files SET scan_status=? WHERE id=?',[$scan,$file['id']]);}}
                $late=(int)($settings['late_minutes']??15);
                $missing=$this->db->all("SELECT a.id,a.name FROM cp_assignments a JOIN cp_shifts s ON s.id=a.shift_id WHERE a.tenant_id=? AND a.status IN ('ASSIGNED','CONFIRMED') AND a.deleted_at IS NULL AND s.published=1 AND s.status<>'CANCELLED' AND s.starts_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ".$late." MINUTE) AND s.ends_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY) AND NOT EXISTS(SELECT 1 FROM cp_presence e WHERE e.assignment_id=a.id)",[$t['id']]);
                foreach($missing as$m){$n=$this->db->run("INSERT IGNORE INTO cp_no_shows(id,tenant_id,name,assignment_id,status,created_at,updated_at,version)VALUES(?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)",[Support::uuid(),$t['id'],'Неявка / опоздание',$m['id'],'ACTIVE'])->rowCount();if($n){$counts['no_shows']++;$this->notify($t['id'],'no-show:'.$m['id'],'Нет заступления: '.$m['name'],'assignments',$m['id'],[]);}}
            }
            $this->db->run("UPDATE cp_outbox SET status='DONE',updated_at=UTC_TIMESTAMP() WHERE topic='notification.created' AND status='PENDING'");
            foreach($this->db->all("SELECT p.*,m.started_at,m.deadline FROM cp_patrols p JOIN cp_mobile_patrol_runs m ON m.patrol_id=p.id WHERE p.status='IN_PROGRESS' AND m.deadline<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR) LIMIT 100")as$patrol){$status=(int)$patrol['completed_points']>0?'PARTIAL':'MISSED';$this->db->run('UPDATE cp_patrols SET status=?,finished_at=?,updated_at=UTC_TIMESTAMP(),version=version+1 WHERE id=? AND status=?',[$status,$patrol['deadline'],$patrol['id'],'IN_PROGRESS']);$this->db->run('UPDATE cp_mobile_patrol_runs SET auto_closed=1 WHERE patrol_id=?',[$patrol['id']]);$counts['patrols_expired']=($counts['patrols_expired']??0)+1;}
            $counts['reports']=Reporting::work($this->db,$this->config);
            $counts['retention']=(new Retention($this->db,$this->config))->run();if((new Backup($this->db,$this->config))->due())try{$counts['backup']=(new Backup($this->db,$this->config))->create();}catch(Problem $e){$counts['backup']=['status'=>$e->codeName];}
            Support::atomic($this->config['storage'].'/worker.json',Support::json(['last_run'=>Support::now(),'result'=>$counts]));return $counts;
        }finally{$this->db->run('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    public function notify(string $tenant,string $key,string $name,string $kind,string $id,array $payload): int
    {
        return $this->db->transaction(function()use($tenant,$key,$name,$kind,$id,$payload){$n=$this->db->run("INSERT IGNORE INTO cp_notifications(id,tenant_id,name,entity_type,entity_id,dedupe_key,status,payload,created_at,updated_at,version)VALUES(?,?,?,?,?,?,'NEW',?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)",[Support::uuid(),$tenant,$name,$kind,$id,$key,Support::json($payload)])->rowCount();if($n)$this->db->run("INSERT IGNORE INTO cp_outbox(id,tenant_id,name,topic,dedupe_key,payload,status,attempts,available_at,created_at,updated_at,version)VALUES(?,?,?,'notification.created',?,?,'PENDING',0,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)",[Support::uuid(),$tenant,$name,$key,Support::json($payload)]);return $n;});
    }
}
