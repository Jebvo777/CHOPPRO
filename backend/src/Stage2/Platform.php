<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Platform
{
    public const DEFAULTS=['max_users'=>1000,'max_employees'=>10000,'max_facilities'=>1000,'storage_mb'=>2048];
    public function __construct(public Db $db,public array $config){}
    public function ensure():void
    {
        $this->db->run('INSERT IGNORE INTO cp_tenant_limits(tenant_id,updated_at) SELECT id,UTC_TIMESTAMP() FROM cp_tenants');
    }
    public function usage(string $tenant):array
    {
        $out=[];foreach(['users','employees','facilities']as$kind)$out[$kind]=(int)$this->db->scalar('SELECT COUNT(*) FROM cp_'.$kind.' WHERE tenant_id=? AND deleted_at IS NULL',[$tenant]);
        $out['storage_bytes']=(int)$this->db->scalar('SELECT COALESCE(SUM(bytes),0) FROM cp_file_sizes WHERE tenant_id=?',[$tenant]);return$out;
    }
    public function limits(string $tenant):array
    {
        if(!$this->db->scalar('SELECT id FROM cp_tenants WHERE id=? AND deleted_at IS NULL',[$tenant]))throw new Problem(404,'NOT_FOUND','Организация не найдена');
        $row=$this->db->one('SELECT * FROM cp_tenant_limits WHERE tenant_id=?',[$tenant])??['tenant_id'=>$tenant,...self::DEFAULTS,'version'=>1];return['limits'=>$row,'usage'=>$this->usage($tenant)];
    }
    public function save(Access $a,string $tenant,array $input):array
    {
        if($a->user['role']!=='platform_admin')throw new Problem(403,'PLATFORM_ONLY','Действие доступно оператору платформы');$this->ensure();return$this->db->transaction(function()use($a,$tenant,$input){$this->db->run('SELECT id FROM cp_tenants WHERE id=? FOR UPDATE',[$tenant]);$old=$this->limits($tenant);$data=[];
        foreach(self::DEFAULTS as$k=>$default){$v=$input[$k]??$old['limits'][$k];if(filter_var($v,FILTER_VALIDATE_INT)===false||$v<1||$v>1000000)throw new Problem(422,'LIMIT_INVALID','Укажите положительный лимит',['field'=>$k]);$data[$k]=(int)$v;}
        if((int)($input['version']??0)!==(int)$old['limits']['version'])throw new Problem(409,'VERSION_CONFLICT','Лимиты уже изменены');
        $changed=$this->db->run('UPDATE cp_tenant_limits SET max_users=?,max_employees=?,max_facilities=?,storage_mb=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE tenant_id=? AND version=?',[...array_values($data),$tenant,$old['limits']['version']])->rowCount();if(!$changed)throw new Problem(409,'VERSION_CONFLICT','Лимиты уже изменены');
        $a->audit('tenant.limits_changed','tenants',$tenant,['old'=>$old['limits'],'new'=>$data]);return$this->limits($tenant);});
    }
    public function room(string $kind,string $tenant,int $increment=1):void
    {
        $this->db->run('SELECT id FROM cp_tenants WHERE id=? FOR UPDATE',[$tenant]);$key=match($kind){'users'=>'max_users','employees'=>'max_employees','facilities'=>'max_facilities','storage'=>'storage_mb',default=>null};if(!$key)return;
        $limit=(int)($this->db->scalar('SELECT '.$key.' FROM cp_tenant_limits WHERE tenant_id=?',[$tenant])?:self::DEFAULTS[$key]);$used=$kind==='storage'?(int)$this->db->scalar('SELECT COALESCE(SUM(bytes),0) FROM cp_file_sizes WHERE tenant_id=?',[$tenant]):(int)$this->db->scalar('SELECT COUNT(*) FROM cp_'.$kind.' WHERE tenant_id=? AND deleted_at IS NULL',[$tenant]);if($kind==='storage')$limit*=1048576;
        if($used+$increment>$limit)throw new Problem(409,'TENANT_LIMIT','Достигнут лимит организации',['resource'=>$kind,'used'=>$used,'limit'=>$limit]);
    }
    public function file(string $id,string $tenant,string $kind,int $bytes):void{$this->db->run('INSERT INTO cp_file_sizes(id,tenant_id,kind,bytes)VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE bytes=VALUES(bytes)',[$id,$tenant,$kind,$bytes]);}
    public function status():array
    {
        $this->ensure();$rows=$this->db->all('SELECT id,name,slug,status,features FROM cp_tenants WHERE deleted_at IS NULL ORDER BY name');foreach($rows as&$row){$row['features']=Support::decode($row['features']);$row+=$this->limits($row['id']);}unset($row);
        $workerFile=$this->config['storage'].'/worker.json';$worker=is_file($workerFile)?Support::decode(file_get_contents($workerFile)):null;
        $pending=(int)$this->db->scalar("SELECT COUNT(*) FROM cp_outbox WHERE status='PENDING'");$failed=(int)$this->db->scalar("SELECT COUNT(*) FROM cp_outbox WHERE status='FAILED'");$quarantine=(int)$this->db->scalar("SELECT COUNT(*) FROM cp_documents WHERE scan_status='PENDING_SCAN' AND deleted_at IS NULL")+(int)$this->db->scalar("SELECT COUNT(*) FROM cp_files WHERE scan_status='PENDING_SCAN' AND deleted_at IS NULL");
        $latestBackup=$this->db->scalar("SELECT MAX(finished_at) FROM cp_backup_runs WHERE status='READY'");$degraded=!$latestBackup||strtotime($latestBackup)<time()-900||$failed>0||!$worker||strtotime($worker['last_run']??'1970-01-01')<time()-180;
        return['status'=>$degraded?'DEGRADED':'HEALTHY','database'=>'OK','tenants'=>count($rows),'queue'=>['pending'=>$pending,'failed'=>$failed,'oldest_pending'=>$this->db->scalar("SELECT MIN(created_at) FROM cp_outbox WHERE status='PENDING'")?:null],'limits'=>$rows,'worker'=>$worker,'quarantine'=>$quarantine,'integrations'=>['notifications'=>['status'=>'EXCLUDED','description'=>'Уведомления, SMS, Telegram и другие внешние сервисы не подключены по текущему объему работ'],'scanner'=>['status'=>$this->config['scanner']['host']?'CONFIGURED':'NOT_CONNECTED']],'monitoring'=>RequestMetrics::get($this->config),'backups'=>(new Backup($this->db,$this->config))->listing(),'report_queue'=>['pending'=>(int)$this->db->scalar("SELECT COUNT(*) FROM cp_report_jobs WHERE status='GENERATING'"),'failed'=>(int)$this->db->scalar("SELECT COUNT(*) FROM cp_report_jobs WHERE status='FAILED'")],'retention'=>['years'=>3,'runs'=>$this->db->all('SELECT r.id,t.name tenant_name,r.cutoff,r.counts,r.created_at FROM cp_retention_runs r JOIN cp_tenants t ON t.id=r.tenant_id ORDER BY r.created_at DESC LIMIT 10')]];
    }
    public function support(Access $a,array $input):array
    {
        if($a->user['role']!=='platform_admin')throw new Problem(403,'PLATFORM_ONLY','Действие доступно оператору платформы');$tenant=$a->tenant();if(!$tenant||mb_strlen(trim($input['reason']??''))<10)throw new Problem(422,'REASON_REQUIRED','Выберите организацию и укажите причину доступа');$minutes=(int)($input['minutes']??15);if($minutes<1||$minutes>30)throw new Problem(422,'DURATION_INVALID','Доступ открывается на 1–30 минут');
        $until=gmdate('Y-m-d H:i:s',time()+$minutes*60);$r=new Resources($a,$this->config);$this->db->run('UPDATE cp_break_glass SET expires_at=UTC_TIMESTAMP(),deleted_at=UTC_TIMESTAMP() WHERE user_id=? AND tenant_id=? AND deleted_at IS NULL',[$a->user['id'],$tenant]);$row=$r->insert('break_glass',['name'=>'Доступ поддержки','user_id'=>$a->user['id'],'reason'=>trim($input['reason']),'expires_at'=>$until]);$a->audit('support.access','break_glass',$row['id'],['reason'=>$input['reason'],'expires_at'=>$until]);(new Worker($this->db,$this->config))->notify($tenant,'support:'.$row['id'],'Открыт временный доступ поддержки','break_glass',$row['id'],['reason'=>$input['reason'],'expires_at'=>$until]);return['expires_at'=>$until,'id'=>$row['id']];
    }
    public function endSupport(Access $a):array
    {
        if($a->user['role']!=='platform_admin'||!$a->tenant())throw new Problem(403,'PLATFORM_ONLY','Выберите организацию');$this->db->run('UPDATE cp_break_glass SET expires_at=UTC_TIMESTAMP(),deleted_at=UTC_TIMESTAMP() WHERE user_id=? AND tenant_id=? AND deleted_at IS NULL',[$a->user['id'],$a->tenant()]);$a->audit('support.closed','tenants',$a->tenant());return['ok'=>true];
    }
}
