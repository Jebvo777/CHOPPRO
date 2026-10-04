<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Kernel
{
    public Auth $auth;
    public function __construct(public Db $db,public array $config){$this->auth=new Auth($db,$config);}
    public function dispatch(string $method,string $path,array $input=[],array $query=[],array $files=[]): array
    {
        $path='/'.trim($path,'/');
        if($method!=='GET')$this->auth->csrf();
        if($path==='/v1/health'||$path==='/v1/ready')return ['status'=>'ready','version'=>'2.0.0','database'=>$this->db->scalar('SELECT 1')==1?'ok':'error'];
        if($path==='/v1/auth/login'&&$method==='POST')return $this->auth->login($input);
        if($path==='/v1/auth/mfa/verify'&&$method==='POST'){ $out=$this->auth->verifyMfa($input);if(isset($out['_error']))throw$out['_error'];return$out; }
        if($path==='/v1/auth/otp/request'&&$method==='POST')return $this->auth->requestOtp($input);
        if($path==='/v1/auth/otp/verify'&&$method==='POST'){ $out=$this->auth->verifyOtp($input);if(isset($out['_error']))throw$out['_error'];return$out; }
        if($path==='/v1/auth/refresh'&&$method==='POST')return $this->auth->refresh($input);
        if($path==='/v1/auth/invite'&&$method==='POST'){ $this->auth->limit('invite',10);$dummy=['id'=>null,'tenant_id'=>null,'role'=>'guard','scopes'=>'[]'];return(new Administration(new Resources(new Access($this->db,$dummy),$this->config)))->activateInvite($input); }
        if($path==='/v1/public/jobs'&&$method==='GET')return $this->jobs($query);
        if(preg_match('~^/v1/public/jobs/([a-f0-9-]+)/apply$~',$path,$m)&&$method==='POST')return $this->applyJob($m[1],$input);
        $u=$this->auth->user();$a=new Access($this->db,$u,$u['role']==='platform_admin'?($query['tenant']??null):null);$r=new Resources($a,$this->config);$admin=new Administration($r);$ops=new Operations($r);
        if($path==='/v1/auth/logout'&&$method==='POST')return $this->auth->logout($u);
        if($path==='/v1/me')return ['user'=>$a->safe('users',$u),'tenant'=>$u['tenant_id']?$this->db->one('SELECT id,name,slug,settings,features FROM cp_tenants WHERE id=?',[$u['tenant_id']]):null,'permissions'=>$a->permissions(),'csrf'=>$_SESSION['csrf']??'','version'=>'2.0.0'];
        if($path==='/v1/schema'){ $fields=Schema::all();$fields['users']=['name'=>'varchar(200)','email'=>'varchar(200)','phone'=>'varchar(32)','role'=>'varchar(64)','status'=>'varchar(32)','customer_id'=>'char(36)','employee_id'=>'char(36)','scopes'=>'json'];return ['resources'=>array_filter($fields,fn($v,$k)=>$a->allows($k.'.read'),ARRAY_FILTER_USE_BOTH),'names'=>Schema::names(),'labels'=>Schema::labels(),'references'=>Schema::references(),'statuses'=>array_combine(array_keys($fields),array_map([Schema::class,'statuses'],array_keys($fields))),'defaults'=>array_combine(array_keys($fields),array_map([Schema::class,'defaults'],array_keys($fields))),'required'=>array_combine(array_keys($fields),array_map([Schema::class,'required'],array_keys($fields))),'roles'=>array_keys(Access::ROLES)]; }
        if($path==='/v1/worker'&&$method==='POST'){$a->need('settings.update');return(new Worker($this->db,$this->config))->tick();}
        if($path==='/v1/compliance-events'&&$method==='POST')return(new Extensions($r))->event($input);
        if(preg_match('~^/v1/([a-z_]+)/export$~',$path,$m)&&$method==='GET')(new Extensions($r))->export($m[1],$query);
        if(preg_match('~^/v1/(service_types|compliance_rules|contract_templates)/([a-f0-9-]+)/new-version$~',$path,$m)&&$method==='POST')return(new Extensions($r))->newVersion($m[1],$m[2],$input);
        if(preg_match('~^/v1/users/([a-f0-9-]+)/invite$~',$path,$m)&&$method==='POST'){$a->need('users.update');$a->find('users',$m[1],false);return['invitation_token'=>$admin->invitation($m[1])];}
        if(preg_match('~^/v1/reports/([a-f0-9-]+)/history$~',$path,$m)&&$method==='GET'){$a->find('reports',$m[1]);return['items'=>$this->db->all('SELECT revision,snapshot,created_at FROM cp_report_versions WHERE report_id=? AND tenant_id=? ORDER BY revision DESC',[$m[1],$a->tenant()])];}
        if($path==='/v1/dashboard')return $this->dashboard($a,$r);
        if($path==='/v1/settings')return $admin->settings($method==='GET'?[]:$input);
        if($path==='/v1/tenants'){if($method==='GET')return $admin->tenants();if($method==='POST')return $admin->tenants($input);}
        if(preg_match('~^/v1/tenants/([a-f0-9-]+)$~',$path,$m)&&$method==='PUT')return $admin->tenants($input,$m[1]);
        if($path==='/v1/users'&&$method==='POST')return $admin->saveUser($input);
        if(preg_match('~^/v1/users/([a-f0-9-]+)$~',$path,$m)&&$method==='PUT')return $admin->saveUser($input,$m[1]);
        if($path==='/v1/audit')return $this->audit($a,$query);
        if($path==='/v1/health/platform'){ $admin->platform();return ['tenants'=>(int)$this->db->scalar('SELECT COUNT(*) FROM cp_tenants WHERE deleted_at IS NULL'),'queue'=>['pending'=>(int)$this->db->scalar("SELECT COUNT(*) FROM cp_outbox WHERE status='PENDING'"),'failed'=>(int)$this->db->scalar("SELECT COUNT(*) FROM cp_outbox WHERE status='FAILED'")],'limits'=>$this->db->all('SELECT name,slug,status FROM cp_tenants WHERE deleted_at IS NULL'),'worker'=>is_file($this->config['storage'].'/worker.json')?Support::decode(file_get_contents($this->config['storage'].'/worker.json')):null,'quarantine'=>(int)$this->db->scalar("SELECT COUNT(*) FROM cp_documents WHERE scan_status='PENDING_SCAN'")]; }
        if($path==='/v1/support/access'&&$method==='POST'){ $admin->platform();if(!$a->tenant()||mb_strlen(trim($input['reason']??''))<10)throw new Problem(422,'REASON_REQUIRED','Выберите организацию и укажите причину доступа');$until=gmdate('Y-m-d H:i:s',time()+min(30,max(1,(int)($input['minutes']??15)))*60);$row=$r->insert('break_glass',['name'=>'Доступ поддержки','user_id'=>$u['id'],'reason'=>$input['reason'],'expires_at'=>$until]);$a->audit('support.access','break_glass',$row['id'],['reason'=>$input['reason'],'expires_at'=>$until]);(new Worker($this->db,$this->config))->notify($a->tenant(),'support:'.$row['id'],'Открыт доступ поддержки','break_glass',$row['id'],['reason'=>$input['reason'],'expires_at'=>$until]);return ['expires_at'=>$until]; }
        if(preg_match('~^/v1/imports/(employees|facilities|customers)/(preview|apply)$~',$path,$m)&&$method==='POST')return $admin->import($m[1],$input,$m[2]==='apply');
        if(preg_match('~^/v1/imports/([a-f0-9-]+)/rollback$~',$path,$m)&&$method==='POST')return $admin->rollbackImport($m[1],$input);
        if($path==='/v1/documents/upload'&&$method==='POST')return (new Documents($r))->upload($input,$files['file']??[]);
        if(preg_match('~^/v1/documents/([a-f0-9-]+)/(review|download|history)$~',$path,$m)){
            if($m[2]==='download'&&$method==='GET')(new Documents($r))->download($m[1]);
            if($m[2]==='review'&&$method==='POST')return(new Documents($r))->review($m[1],$input);
            if($m[2]==='history'&&$method==='GET'){ $a->need('documents.review');$a->find('documents',$m[1],false);return ['items'=>$this->db->all('SELECT name,decision,reason,actor_id,created_at FROM cp_document_reviews WHERE tenant_id=? AND document_id=? ORDER BY created_at',[$a->tenant(),$m[1]])]; }
        }
        if(preg_match('~^/v1/contracts/([a-f0-9-]+)/(checks|activate|history)$~',$path,$m)){
            if($m[2]==='checks'&&$method==='GET')return(new Compliance($r))->checks($m[1]);
            if($m[2]==='activate'&&$method==='POST')return(new Compliance($r))->activate($m[1],$input);
            if($m[2]==='history'&&$method==='GET'){$a->find('contracts',$m[1]);return ['items'=>$this->db->all('SELECT * FROM cp_contract_versions WHERE contract_id=? AND tenant_id=? ORDER BY created_at DESC',[$m[1],$a->tenant()])];}
        }
        if(preg_match('~^/v1/compliance_tasks/([a-f0-9-]+)/draft$~',$path,$m))return(new Compliance($r))->draft($m[1]);
        if(preg_match('~^/v1/vacancies/([a-f0-9-]+)/pin$~',$path,$m)&&$method==='POST')return $admin->pin($m[1],$input);
        if(preg_match('~^/v1/shifts/([a-f0-9-]+)/(publish|cancel)$~',$path,$m)&&$method==='POST'){
            if($m[2]==='publish')return$ops->publish($m[1]);$a->need('shifts.update');$a->find('shifts',$m[1],false);if(empty($input['reason']))throw new Problem(422,'REASON_REQUIRED','Укажите причину отмены');$r->write('shifts',$m[1],['status'=>'CANCELLED']);$this->db->run("UPDATE cp_assignments SET status='CANCELLED',updated_at=UTC_TIMESTAMP(),version=version+1 WHERE shift_id=? AND tenant_id=?",[$m[1],$a->tenant()]);$a->audit('shift.cancelled','shifts',$m[1],['reason'=>$input['reason']]);return['ok'=>true];
        }
        if(preg_match('~^/v1/shift_templates/([a-f0-9-]+)/generate$~',$path,$m)&&$method==='POST')return $ops->repeat($m[1],$input);
        if(preg_match('~^/v1/assignments/([a-f0-9-]+)/(replace|confirm|history)$~',$path,$m)){
            if($m[2]==='replace'&&$method==='POST')return$ops->replace($m[1],$input);if($m[2]==='confirm'&&$method==='POST')return$ops->confirm($m[1],$input);
            if($m[2]==='history'&&$method==='GET'){$a->find('assignments',$m[1]);return['items'=>$this->db->all('SELECT * FROM cp_assignment_history WHERE assignment_id=? AND tenant_id=?',[$m[1],$a->tenant()])];}
        }
        if($path==='/v1/attendance'&&$method==='POST')return$ops->attendance($input);
        if(preg_match('~^/v1/attendance/([a-f0-9-]+)/override$~',$path,$m)&&$method==='POST')return$ops->override($m[1],$input);
        if(preg_match('~^/v1/qr_points/([a-f0-9-]+)/rotate$~',$path,$m)&&$method==='POST'){$a->need('qr_points.update');$old=$a->find('qr_points',$m[1],false);$r->write('qr_points',$old['id'],['status'=>'INACTIVE']);$new=$r->create('qr_points',['name'=>$old['name'],'facility_id'=>$old['facility_id'],'post_id'=>$old['post_id']]);$a->audit('qr.rotated','qr_points',$old['id'],['new_id'=>$new['id']]);return$new;}
        if(preg_match('~^/v1/instructions/([a-f0-9-]+)/acknowledge$~',$path,$m)&&$method==='POST'){$a->need('instructions.acknowledge');$i=$a->find('instructions',$m[1],false);$latest=(int)$this->db->scalar('SELECT MAX(revision) FROM cp_instructions WHERE tenant_id=? AND post_id=? AND deleted_at IS NULL',[$a->tenant(),$i['post_id']]);if((int)$i['revision']!==$latest)throw new Problem(409,'INSTRUCTION_SUPERSEDED','Ознакомьтесь с новой версией');$employee=$u['employee_id']??($input['employee_id']??'');$a->reference('employees',$employee);$exists=$this->db->one('SELECT id FROM cp_instruction_receipts WHERE instruction_id=? AND employee_id=?',[$i['id'],$employee]);if(!$exists)$r->insert('instruction_receipts',['name'=>'Ознакомление с инструкцией','instruction_id'=>$i['id'],'employee_id'=>$employee,'acknowledged_at'=>Support::now()]);$a->audit('instruction.acknowledged','instructions',$i['id'],['revision'=>$latest]);return['ok'=>true];}
        if(preg_match('~^/v1/reports/([a-f0-9-]+)/acknowledge$~',$path,$m)&&$method==='POST'){$a->need('reports.acknowledge');$report=$a->find('reports',$m[1],false);$r->insert('report_receipts',['name'=>'Ознакомление с отчётом','report_id'=>$report['id'],'user_id'=>$u['id'],'report_version'=>$report['version'],'ip'=>$_SERVER['REMOTE_ADDR']??'cli']);$a->audit('report.acknowledged','reports',$report['id'],['version'=>$report['version']]);return['ok'=>true];}
        if(preg_match('~^/v1/([a-z_]+)(?:/([a-f0-9-]+))?$~',$path,$m)){
            $kind=$m[1];$id=$m[2]??null;if($method==='GET')return$id?['item'=>$a->safe($kind,$a->find($kind,$id))]:$r->list($kind,$query);
            if($method==='POST'&&!$id)return$r->create($kind,$input);if($method==='PUT'&&$id)return$r->update($kind,$id,$input);if($method==='DELETE'&&$id)return$r->remove($kind,$id);
        }
        throw new Problem(404,'NOT_FOUND','Метод не найден');
    }
    public function jobs(array $query): array
    {
        $where="v.status='PUBLISHED' AND v.deleted_at IS NULL AND t.status='ACTIVE' AND t.deleted_at IS NULL AND COALESCE(JSON_EXTRACT(t.features,'$.jobs'),true)=true";$args=[];
        if(!empty($query['city'])){$where.=' AND v.city=?';$args[]=$query['city'];}if(!empty($query['q'])){$where.=' AND (v.name LIKE ? OR v.description LIKE ? OR t.name LIKE ?)';$q='%'.mb_substr($query['q'],0,100).'%';array_push($args,$q,$q,$q);}if(!empty($query['salary'])){$where.=' AND v.salary_to>=?';$args[]=(float)$query['salary'];}
        $rows=$this->db->all('SELECT v.id,v.name,v.city,v.salary_from,v.salary_to,v.schedule,v.qualification,v.description,v.pinned_rank,t.name tenant_name FROM cp_vacancies v JOIN cp_tenants t ON t.id=v.tenant_id WHERE '.$where.' ORDER BY v.pinned_rank IS NULL,v.pinned_rank,v.created_at DESC LIMIT 200',$args);
        return ['items'=>$rows,'total'=>count($rows),'cities'=>array_column($this->db->all("SELECT DISTINCT city FROM cp_vacancies WHERE status='PUBLISHED' AND deleted_at IS NULL ORDER BY city"),'city')];
    }
    public function applyJob(string $id,array $input): array
    {
        $this->auth->limit('job-application',8,600);$v=$this->db->one("SELECT v.id,v.tenant_id FROM cp_vacancies v JOIN cp_tenants t ON t.id=v.tenant_id WHERE v.id=? AND v.status='PUBLISHED' AND v.deleted_at IS NULL AND t.status='ACTIVE'",[$id]);if(!$v)throw new Problem(404,'NOT_FOUND','Вакансия недоступна');if(mb_strlen(trim($input['name']??''))<2||!preg_match('/^\+\d{10,15}$/',Support::phone($input['phone']??'')))throw new Problem(422,'APPLICATION_INVALID','Укажите имя и телефон');
        $r=new Resources(new Access($this->db,['id'=>null,'role'=>'tenant_admin','tenant_id'=>$v['tenant_id'],'scopes'=>'[]']),$this->config);$data=$r->normalize('applications',['name'=>$input['name'],'phone'=>$input['phone'],'email'=>$input['email']??null,'message'=>$input['message']??'','vacancy_id'=>$id,'status'=>'ACTIVE']);$row=$r->insert('applications',$data);return ['ok'=>true,'id'=>$row['id']];
    }
    public function dashboard(Access $a,Resources $r): array
    {
        $a->need('dashboard.read');$metrics=[];
        foreach(['employees','facilities','posts','shifts','documents','compliance_tasks','vacancies','patrols','incidents','reports']as$kind)if($a->allows($kind.'.read')){[$w,$args]=$a->where($kind);$metrics[$kind]=(int)$this->db->scalar('SELECT COUNT(*) FROM cp_'.$kind.' t WHERE '.$w,$args);}
        $today=gmdate('Y-m-d');$series=[];
        if($a->allows('attendance.read')){[$w,$args]=$a->where('attendance');$series=$this->db->all("SELECT DATE(t.server_time) date,COUNT(*) count FROM cp_attendance t WHERE ".$w." AND t.event_type='CHECK_IN' AND t.server_time>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 14 DAY) GROUP BY DATE(t.server_time) ORDER BY date",$args);}
        $upcoming=$a->allows('shifts.read')?$r->list('shifts',['from'=>$today.' 00:00:00','sort'=>'starts_at','direction'=>'asc','limit'=>8]):['items'=>[]];
        $notices=$a->allows('notifications.read')?$r->list('notifications',['limit'=>6]):['items'=>[]];
        $facilities=$a->allows('facilities.read')?$r->list('facilities',['limit'=>8,'sort'=>'name','direction'=>'asc']):['items'=>[]];
        $tasks=$a->allows('compliance_tasks.read')?$r->list('compliance_tasks',['status'=>'OVERDUE','limit'=>5]):['items'=>[]];
        $open=[];if($a->allows('shifts.read'))$open=$r->list('shifts',['status'=>'UNFILLED','limit'=>5,'sort'=>'starts_at','direction'=>'asc']);
        return ['metrics'=>$metrics,'series'=>$series,'upcoming'=>$upcoming['items'],'notices'=>$notices['items'],'facilities'=>$facilities['items'],'tasks'=>$tasks['items'],'unfilled'=>$open['items']??[],'time'=>Support::now(),'tenant'=>$a->tenant()?$this->db->one('SELECT name,slug,settings,features FROM cp_tenants WHERE id=?',[$a->tenant()]):null];
    }
    public function audit(Access $a,array $query): array
    {
        $a->need('audit.read');$where='1=1';$args=[];if($a->tenant()){$where.=' AND tenant_id=?';$args[]=$a->tenant();}if($a->user['role']==='customer')$where.=" AND action='support.access'";
        foreach(['action','entity_type','actor_id']as$f)if(!empty($query[$f])){$where.=' AND '.$f.'=?';$args[]=$query[$f];}foreach(['from','to']as$f)if(!empty($query[$f])){$where.=' AND created_at'.($f==='from'?'>=':'<=').'?';$args[]=$query[$f];}if(!empty($query['entity_id'])){$where.=' AND entity_id=?';$args[]=$query['entity_id'];}
        $rows=$this->db->all('SELECT * FROM cp_audit WHERE '.$where.' ORDER BY id DESC LIMIT 200',$args);return['items'=>array_map(function($row){$row['metadata']=Support::decode($row['metadata']);return$row;},$rows),'total'=>count($rows)];
    }
}
