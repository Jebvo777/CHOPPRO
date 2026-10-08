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
        if($path==='/v1/health'||$path==='/v1/ready')return ['status'=>'ready','version'=>'3.0.0','database'=>$this->db->scalar('SELECT 1')==1?'ok':'error'];
        if($path==='/v1/auth/login'&&$method==='POST')return $this->auth->login($input);
        if($path==='/v1/auth/mfa/verify'&&$method==='POST'){ $out=$this->auth->verifyMfa($input);if(isset($out['_error']))throw$out['_error'];return$out; }
        if($path==='/v1/auth/otp/request'&&$method==='POST')return $this->auth->requestOtp($input);
        if($path==='/v1/auth/otp/verify'&&$method==='POST'){ $out=$this->auth->verifyOtp($input);if(isset($out['_error']))throw$out['_error'];return$out; }
        if($path==='/v1/auth/refresh'&&$method==='POST')return $this->auth->refresh($input);
        if($path==='/v1/auth/invite'&&$method==='POST'){ $this->auth->limit('invite',10);$dummy=['id'=>null,'tenant_id'=>null,'role'=>'guard','scopes'=>'[]'];return(new Administration(new Resources(new Access($this->db,$dummy),$this->config)))->activateInvite($input); }
        if($path==='/v1/public/jobs'&&$method==='GET')return $this->jobs($query);
        if(preg_match('~^/v1/public/jobs/([a-f0-9-]+)/apply$~',$path,$m)&&$method==='POST')return $this->applyJob($m[1],$input);
        $u=$this->auth->user();$a=new Access($this->db,$u,$u['role']==='platform_admin'?($query['tenant']??null):null);$r=new Resources($a,$this->config);$admin=new Administration($r);$ops=new Operations($r);
        if(!preg_match('~^/v1/(auth|me|schema|settings|tenants|support|audit|health|retention|worker|search|planner)(?:/|$)~',$path))$r->feature($path==='/v1/compliance-events'?'compliance_tasks':explode('/',trim($path,'/'))[1]);
        if(preg_match('~^/v1/reporting/(timesheet|coverage|patrols|deadlines|incidents|accounting)(/export)?$~',$path,$m)&&$method==='GET'){if(!empty($m[2]))(new Reporting($r))->export($m[1],$query);return(new Reporting($r))->data($m[1],$query);}
        if($path==='/v1/reports/generate'&&$method==='POST')return(new Reporting($r))->monthly($input);
        if($path==='/v1/report-jobs'&&$method==='GET'){$a->need('reports.read');[$w,$args]=$a->where('facilities');return['items'=>$this->db->all('SELECT j.* FROM cp_report_jobs j JOIN cp_facilities t ON t.id=j.facility_id WHERE '.$w.' AND j.tenant_id=? ORDER BY j.created_at DESC LIMIT 100',[...$args,$a->tenant()])];}
        if(preg_match('~^/v1/backups(?:/([a-f0-9-]+)/(verify|restore|download))?$~',$path,$m)){$admin->platform();$backup=new Backup($this->db,$this->config);$id=$m[1]??null;$action=$m[2]??null;if($method==='GET'&&!$id)return$backup->listing();if($method==='POST'&&(!$id||$action==='restore')){if(mb_strlen(trim($input['reason']??''))<10)throw new Problem(422,'REASON_REQUIRED','Укажите основание операции');if($id&&($input['confirmation']??'')!=='ВОССТАНОВИТЬ')throw new Problem(422,'CONFIRMATION_REQUIRED','Подтвердите восстановление словом ВОССТАНОВИТЬ');$a->audit($id?'backup.restore_requested':'backup.requested','system',$id,['reason'=>$input['reason']]);if(isset($GLOBALS['maintenance_handle']))flock($GLOBALS['maintenance_handle'],LOCK_UN);return$id?$backup->restore($id):$backup->create();}if($id&&$action==='verify'&&$method==='POST')return$backup->verify($id);if($id&&$action==='download'&&$method==='GET')$backup->download($id);}
        if($path==='/v1/patrol_routes/save'&&$method==='POST')return(new PatrolRoutes($r))->save($input);
        if(preg_match('~^/v1/files/([a-f0-9-]+)/link$~',$path,$m)&&$method==='GET')return(new Attachments($r))->link($m[1]);
        if(preg_match('~^/v1/incidents/([a-f0-9-]+)/summary$~',$path,$m)&&$method==='GET'){$a->find('incidents',$m[1]);return['item'=>$this->db->one('SELECT client_time,measures,escalation_status FROM cp_mobile_incidents WHERE tenant_id=? AND incident_id=?',[$a->tenant(),$m[1]])];}
        if(preg_match('~^/v1/patrols/([a-f0-9-]+)/scans$~',$path,$m)&&$method==='GET'){$a->find('patrols',$m[1]);return['items'=>$this->db->all('SELECT s.id,s.point_id,p.name point_name,s.status,s.client_time,s.server_time,s.reasons,s.explanation FROM cp_patrol_scans s LEFT JOIN cp_patrol_route_points p ON p.id=s.point_id WHERE s.tenant_id=? AND s.patrol_id=? ORDER BY s.client_time',[$a->tenant(),$m[1]])];}
        if($path==='/v1/mobile/snapshot'&&$method==='GET')return(new Mobile($r))->snapshot();
        if($path==='/v1/mobile/events'&&$method==='POST')return(new Mobile($r))->event($input);
        if($path==='/v1/mobile/upload'&&$method==='POST')return(new Mobile($r))->upload($input,$files['file']??[]);
        if(preg_match('~^/v1/files/([a-f0-9-]+)/review$~',$path,$m)&&$method==='POST')return(new MobileFiles($r))->review($m[1],$input);
        if(preg_match('~^/v1/attendance/([a-f0-9-]+)/explanations$~',$path,$m)&&$method==='GET'){$a->find('attendance',$m[1]);return['items'=>$this->db->all('SELECT id,reason,client_time,created_at FROM cp_attendance_explanations WHERE tenant_id=? AND attendance_id=? ORDER BY created_at',[$a->tenant(),$m[1]])];}
        if($path==='/v1/auth/logout'&&$method==='POST')return $this->auth->logout($u);
        if($path==='/v1/me'){$tenant=$u['tenant_id']?$this->db->one('SELECT id,name,slug,settings,features FROM cp_tenants WHERE id=?',[$u['tenant_id']]):null;if($tenant){$tenant['settings']=Support::decode($tenant['settings']);$tenant['features']=Support::decode($tenant['features']);}return ['user'=>$a->safe('users',$u),'tenant'=>$tenant,'permissions'=>$a->permissions(),'csrf'=>$_SESSION['csrf']??'','version'=>'3.0.0'];}
        if($path==='/v1/schema'){ $fields=Schema::all();$fields['users']=['name'=>'varchar(200)','email'=>'varchar(200)','phone'=>'varchar(32)','role'=>'varchar(64)','status'=>'varchar(32)','customer_id'=>'char(36)','employee_id'=>'char(36)','scopes'=>'json'];return ['resources'=>array_filter($fields,fn($v,$k)=>$a->allows($k.'.read'),ARRAY_FILTER_USE_BOTH),'names'=>Schema::names(),'labels'=>Schema::labels(),'references'=>Schema::references(),'statuses'=>array_combine(array_keys($fields),array_map([Schema::class,'statuses'],array_keys($fields))),'defaults'=>array_combine(array_keys($fields),array_map([Schema::class,'defaults'],array_keys($fields))),'required'=>array_combine(array_keys($fields),array_map([Schema::class,'required'],array_keys($fields))),'roles'=>array_values(array_unique([...array_keys(Access::ROLES),...array_column($this->db->all('SELECT code FROM cp_roles WHERE tenant_id=? AND deleted_at IS NULL',[$a->tenant()]),'code')]))]; }
        if(preg_match('~^/v1/(licenses|contracts|compliance_tasks|incidents)/([a-f0-9-]+)/files$~',$path,$m)){if($method==='GET')return(new Attachments($r))->listing($m[1],$m[2]);if($method==='POST')return(new Attachments($r))->upload($m[1],$m[2],$files['file']??[]);}
        if(preg_match('~^/v1/files/([a-f0-9-]+)/download$~',$path,$m)&&$method==='GET')(new Attachments($r))->download($m[1],$query);
        if($path==='/v1/worker'&&$method==='POST'){$admin->platform();return(new Worker($this->db,$this->config))->tick();}
        if(preg_match('~^/v1/contracts/([a-f0-9-]+)/signage$~',$path,$m)&&$method==='GET'){$contract=$a->find('contracts',$m[1]);$tenant=$this->db->one('SELECT name FROM cp_tenants WHERE id=?',[$a->tenant()]);return['checklist'=>Support::decode($contract['signage']??'{}'),'layout'=>'ОБЪЕКТ ОХРАНЯЕТСЯ'."\n".$tenant['name']."\n".'На объекте действует пропускной и внутриобъектовый режим. При наличии видеонаблюдения требуется соответствующее уведомление.'."\n".'Договор '.$contract['number'],'notice'=>'Макет необходимо проверить и разместить физически. Размещение выполняет ЧОП/заказчик.'];}
        if($path==='/v1/compliance-events'&&$method==='POST')return(new Extensions($r))->event($input);
        if(preg_match('~^/v1/([a-z_]+)/export$~',$path,$m)&&$method==='GET')(new Extensions($r))->export($m[1],$query);
        if(preg_match('~^/v1/(service_types|compliance_rules|contract_templates)/([a-f0-9-]+)/new-version$~',$path,$m)&&$method==='POST')return(new Extensions($r))->newVersion($m[1],$m[2],$input);
        if(preg_match('~^/v1/users/([a-f0-9-]+)/invite$~',$path,$m)&&$method==='POST'){$a->need('users.update');$a->find('users',$m[1],false);return['invitation_token'=>$admin->invitation($m[1])];}
        if(preg_match('~^/v1/reports/([a-f0-9-]+)/pdf$~',$path,$m)&&$method==='GET'){$a->need('reports.download');$report=$a->safe('reports',$a->find('reports',$m[1]));if(!(int)$report['published'])throw new Problem(409,'NOT_PUBLISHED','Отчёт ещё не опубликован');$tenant=$this->db->scalar('SELECT name FROM cp_tenants WHERE id=?',[$report['tenant_id']]);$pdf=Pdf::render([$report['name'],$tenant,'Период: '.$report['period'],'Версия: '.$report['version'],$report['content']??'']);$a->audit('report.downloaded','reports',$report['id'],['version'=>$report['version']]);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="report-'.substr($report['id'],0,8).'.pdf"');echo$pdf;exit;}
        if(preg_match('~^/v1/reports/([a-f0-9-]+)/history$~',$path,$m)&&$method==='GET'){$a->find('reports',$m[1]);return['items'=>$this->db->all('SELECT revision,snapshot,created_at FROM cp_report_versions WHERE report_id=? AND tenant_id=? ORDER BY revision DESC',[$m[1],$a->tenant()])];}
        if($path==='/v1/search'&&$method==='GET')return(new Workspace($r))->search($query);
        if($path==='/v1/planner'&&$method==='GET')return(new Workspace($r))->planner($query);
        if(preg_match('~^/v1/shifts/([a-f0-9-]+)/move$~',$path,$m)&&$method==='POST')return$ops->move($m[1],$input);
        if($path==='/v1/dashboard')return $this->dashboard($a,$r);
        if($path==='/v1/settings')return $admin->settings($method==='GET'?[]:$input);
        if($path==='/v1/tenants'){if($method==='GET')return $admin->tenants();if($method==='POST')return $admin->tenants($input);}
        if(preg_match('~^/v1/tenants/([a-f0-9-]+)$~',$path,$m)&&$method==='PUT')return $admin->tenants($input,$m[1]);
        if($path==='/v1/users'&&$method==='POST')return $admin->saveUser($input);
        if(preg_match('~^/v1/users/([a-f0-9-]+)$~',$path,$m)&&$method==='PUT')return $admin->saveUser($input,$m[1]);
        if($path==='/v1/audit/options')return $this->auditOptions($a);
        if($path==='/v1/audit')return $this->audit($a,$query);
        if($path==='/v1/health/platform'){$admin->platform();return(new Platform($this->db,$this->config))->status();}
        if($path==='/v1/support/access'&&$method==='POST')return(new Platform($this->db,$this->config))->support($a,$input);
        if($path==='/v1/support/close'&&$method==='POST')return(new Platform($this->db,$this->config))->endSupport($a);
        if($path==='/v1/support/status'){ $admin->platform();$until=$a->tenant()?$this->db->scalar('SELECT MAX(expires_at) FROM cp_break_glass WHERE tenant_id=? AND user_id=? AND deleted_at IS NULL AND expires_at>UTC_TIMESTAMP()',[$a->tenant(),$u['id']]):null;return['active'=>(bool)$until,'expires_at'=>$until?:null];}
        if(preg_match('~^/v1/tenants/([a-f0-9-]+)/limits$~',$path,$m)){$admin->platform();$p=new Platform($this->db,$this->config);return$method==='GET'?$p->limits($m[1]):$p->save($a,$m[1],$input);}
        if($path==='/v1/retention'){if($method!=='GET'){$a->need('settings.update');return(new Retention($this->db,$this->config))->run(true,$a->tenant());} $a->need('settings.read');return(new Retention($this->db,$this->config))->summary($a->tenant());}
        if($path==='/v1/analytics')return(new Telemetry($r))->get();
        if($path==='/v1/presence'){ $a->need('attendance.read');return(new Telemetry($r))->presence();}
        if(preg_match('~^/v1/imports/(employees|facilities|customers)/(preview|apply)$~',$path,$m)&&$method==='POST')return $admin->import($m[1],$input,$m[2]==='apply');
        if(preg_match('~^/v1/imports/([a-f0-9-]+)/rollback$~',$path,$m)&&$method==='POST')return $admin->rollbackImport($m[1],$input);
        if($path==='/v1/documents/upload'&&$method==='POST')return (new Documents($r))->upload($input,$files['file']??[]);
        if(preg_match('~^/v1/documents/([a-f0-9-]+)/(review|download|preview|text|history)$~',$path,$m)){
            if($m[2]==='text'&&$method==='GET')return(new Documents($r))->text($m[1]);
            if(in_array($m[2],['download','preview'],true)&&$method==='GET')(new Documents($r))->download($m[1],$m[2]==='preview');
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
        if($path==='/v1/attendance/options'){$a->need('attendance.create');[$w,$args]=$a->where('assignments');return['demo'=>$this->config['demo'],'items'=>$this->db->all("SELECT t.id,t.name,t.employee_id,e.name employee_name,s.id shift_id,s.starts_at,s.ends_at,p.name post_name,f.name facility_name,f.lat,f.lng,(SELECT q.token FROM cp_qr_points q WHERE q.tenant_id=t.tenant_id AND q.facility_id=f.id AND (q.post_id IS NULL OR q.post_id=p.id) AND q.status='ACTIVE' AND q.deleted_at IS NULL ORDER BY q.created_at DESC LIMIT 1) qr_token FROM cp_assignments t JOIN cp_employees e ON e.id=t.employee_id JOIN cp_shifts s ON s.id=t.shift_id JOIN cp_posts p ON p.id=s.post_id JOIN cp_facilities f ON f.id=p.facility_id WHERE ".$w." AND t.status IN ('ASSIGNED','CONFIRMED') AND s.published=1 AND s.status<>'CANCELLED' AND s.starts_at<=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) AND s.ends_at>=UTC_TIMESTAMP() AND NOT EXISTS(SELECT 1 FROM cp_presence completed WHERE completed.assignment_id=t.id AND completed.status='COMPLETED') ORDER BY s.starts_at DESC LIMIT 300",$args)];}
        if($path==='/v1/attendance'&&$method==='POST')return$ops->attendance($input);
        if(preg_match('~^/v1/attendance/([a-f0-9-]+)/override$~',$path,$m)&&$method==='POST')return$ops->override($m[1],$input);
        if(preg_match('~^/v1/qr_points/([a-f0-9-]+)/rotate$~',$path,$m)&&$method==='POST'){$a->need('qr_points.update');$old=$a->find('qr_points',$m[1],false);$r->write('qr_points',$old['id'],['status'=>'INACTIVE']);$new=$r->create('qr_points',['name'=>$old['name'],'facility_id'=>$old['facility_id'],'post_id'=>$old['post_id']]);$a->audit('qr.rotated','qr_points',$old['id'],['new_id'=>$new['id']]);return$new;}
        if(preg_match('~^/v1/instructions/([a-f0-9-]+)/acknowledge$~',$path,$m)&&$method==='POST'){$a->need('instructions.acknowledge');$i=$a->find('instructions',$m[1],false);$latest=(int)$this->db->scalar('SELECT MAX(revision) FROM cp_instructions WHERE tenant_id=? AND post_id=? AND deleted_at IS NULL',[$a->tenant(),$i['post_id']]);if((int)$i['revision']!==$latest)throw new Problem(409,'INSTRUCTION_SUPERSEDED','Ознакомьтесь с новой версией');$employee=$u['employee_id']??($input['employee_id']??'');$a->reference('employees',$employee);$exists=$this->db->one('SELECT id FROM cp_instruction_receipts WHERE instruction_id=? AND employee_id=?',[$i['id'],$employee]);if(!$exists)$r->insert('instruction_receipts',['name'=>'Ознакомление с инструкцией','instruction_id'=>$i['id'],'employee_id'=>$employee,'acknowledged_at'=>Support::now()]);$a->audit('instruction.acknowledged','instructions',$i['id'],['revision'=>$latest]);return['ok'=>true];}
        if(preg_match('~^/v1/reports/([a-f0-9-]+)/acknowledge$~',$path,$m)&&$method==='POST'){$a->need('reports.acknowledge');$report=$a->find('reports',$m[1],false);$r->insert('report_receipts',['name'=>'Ознакомление с отчётом','report_id'=>$report['id'],'user_id'=>$u['id'],'report_version'=>$report['version'],'ip'=>$_SERVER['REMOTE_ADDR']??'cli']);$a->audit('report.acknowledged','reports',$report['id'],['version'=>$report['version']]);return['ok'=>true];}
        if(preg_match('~^/v1/([a-z_]+)(?:/([a-f0-9-]+))?$~',$path,$m)){
            $kind=$m[1];$id=$m[2]??null;
            if($u['role']==='guard'&&$method!=='GET'&&in_array($kind,['incidents','patrols'],true))throw new Problem(405,'ACTION_REQUIRED','Используйте рабочее действие мобильного приложения');if($method==='GET')return$id?['item'=>$a->safe($kind,$a->find($kind,$id))]:$r->list($kind,$query);
            if($method==='POST'&&!$id)return$r->create($kind,$input);if($method==='PUT'&&$id)return$r->update($kind,$id,$input);if($method==='DELETE'&&$id)return$r->remove($kind,$id);
        }
        throw new Problem(404,'NOT_FOUND','Метод не найден');
    }
    public function jobs(array $query): array
    {
        $base="v.status='PUBLISHED' AND v.deleted_at IS NULL AND t.status='ACTIVE' AND t.deleted_at IS NULL AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.features,'$.jobs')),'true')<>'false'";$where=$base;$args=[];
        foreach(['city','schedule','qualification']as$key)if(!empty($query[$key])){$where.=' AND v.'.$key.'=?';$args[]=$query[$key];}
        if(!empty($query['q'])){$where.=' AND (v.name LIKE ? OR v.description LIKE ? OR t.name LIKE ? OR f.address LIKE ?)';$q='%'.mb_substr((string)$query['q'],0,100).'%';array_push($args,$q,$q,$q,$q);}
        if(!empty($query['salary'])){$where.=' AND v.salary_to>=?';$args[]=max(0,(float)$query['salary']);}
        if(!empty($query['ids'])){$ids=array_values(array_filter(explode(',',(string)$query['ids']),fn($id)=>preg_match('/^[a-f0-9-]{36}$/',$id)));$ids=array_slice($ids,0,100);if(!$ids)$where.=' AND 1=0';else{$where.=' AND v.id IN ('.implode(',',array_fill(0,count($ids),'?')).')';array_push($args,...$ids);}}
        $join=' FROM cp_vacancies v JOIN cp_tenants t ON t.id=v.tenant_id LEFT JOIN cp_facilities f ON f.id=v.facility_id AND f.deleted_at IS NULL AND f.tenant_id=v.tenant_id';
        $total=(int)$this->db->scalar('SELECT COUNT(*)'.$join.' WHERE '.$where,$args);$limit=min(200,max(1,(int)($query['limit']??200)));$page=max(1,(int)($query['page']??1));
        $sort=match($query['sort']??'date'){'salary'=>'v.salary_to DESC','salary_asc'=>'v.salary_from ASC',default=>'v.created_at DESC'};
        $rows=$this->db->all('SELECT v.id,v.name,v.city,v.salary_from,v.salary_to,v.schedule,v.qualification,v.description,v.pinned_rank,t.name tenant_name,f.name facility_name,f.address,f.lat,f.lng'.$join.' WHERE '.$where.' ORDER BY v.pinned_rank IS NULL,v.pinned_rank,'.$sort.',v.id LIMIT '.$limit.' OFFSET '.(($page-1)*$limit),$args);
        $facets=$this->db->all('SELECT DISTINCT v.city,v.schedule'.$join.' WHERE '.$base);
        $cities=array_values(array_unique(array_column($facets,'city')));sort($cities);$schedules=array_values(array_unique(array_column($facets,'schedule')));sort($schedules);
        return ['items'=>$rows,'total'=>$total,'page'=>$page,'pages'=>(int)ceil($total/$limit),'cities'=>$cities,'schedules'=>$schedules,'employers'=>(int)$this->db->scalar('SELECT COUNT(DISTINCT v.tenant_id)'.$join.' WHERE '.$where,$args)];
    }
    public function applyJob(string $id,array $input): array
    {
        $this->auth->limit('job-application',8,600);$v=$this->db->one("SELECT v.id,v.tenant_id FROM cp_vacancies v JOIN cp_tenants t ON t.id=v.tenant_id WHERE v.id=? AND v.status='PUBLISHED' AND v.deleted_at IS NULL AND t.status='ACTIVE' AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.features,'$.jobs')),'true')<>'false'",[$id]);if(!$v)throw new Problem(404,'NOT_FOUND','Вакансия недоступна');if(mb_strlen(trim($input['name']??''))<2||!preg_match('/^\+\d{10,15}$/',Support::phone($input['phone']??'')))throw new Problem(422,'APPLICATION_INVALID','Укажите имя и телефон');
        $r=new Resources(new Access($this->db,['id'=>null,'role'=>'tenant_admin','tenant_id'=>$v['tenant_id'],'scopes'=>'[]']),$this->config);$data=$r->normalize('applications',['name'=>$input['name'],'phone'=>$input['phone'],'email'=>$input['email']??null,'message'=>$input['message']??'','vacancy_id'=>$id,'status'=>'NEW']);$row=$r->insert('applications',$data);return ['ok'=>true,'id'=>$row['id']];
    }
    public function dashboard(Access $a,Resources $r): array
    {
        $a->need('dashboard.read');$metrics=[];
        foreach(['employees','facilities','posts','shifts','documents','compliance_tasks','vacancies','patrols','incidents','reports']as$kind)if($a->allows($kind.'.read')){[$w,$args]=$a->where($kind);$metrics[$kind]=(int)$this->db->scalar('SELECT COUNT(*) FROM cp_'.$kind.' t WHERE '.$w,$args);}
        $today=gmdate('Y-m-d');$series=[];
        if($a->allows('attendance.read')){[$w,$args]=$a->where('attendance');$series=$this->db->all("SELECT DATE(t.client_time) date,COUNT(*) count FROM cp_attendance t WHERE ".$w." AND t.event_type='CHECK_IN' AND t.client_time>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 14 DAY) GROUP BY DATE(t.client_time) ORDER BY date",$args);}
        $upcoming=$a->allows('shifts.read')?$r->list('shifts',['from'=>Support::now(),'sort'=>'starts_at','direction'=>'asc','limit'=>8]):['items'=>[]];
        $notices=$a->allows('notifications.read')?$r->list('notifications',['limit'=>6]):['items'=>[]];
        $facilities=$a->allows('facilities.read')?$r->list('facilities',['limit'=>8,'sort'=>'name','direction'=>'asc']):['items'=>[]];
        $tasks=$a->allows('compliance_tasks.read')?$r->list('compliance_tasks',['status'=>'OVERDUE','limit'=>5]):['items'=>[]];
        $open=[];if($a->allows('shifts.read'))$open=$r->list('shifts',['status'=>'UNFILLED','limit'=>5,'sort'=>'starts_at','direction'=>'asc']);
        return ['analytics'=>(new Telemetry($r))->get(),'metrics'=>$metrics,'series'=>$series,'upcoming'=>$upcoming['items'],'notices'=>$notices['items'],'facilities'=>$facilities['items'],'tasks'=>$tasks['items'],'unfilled'=>$open['items']??[],'time'=>Support::now(),'tenant'=>$a->tenant()?$this->db->one('SELECT name,slug,settings,features FROM cp_tenants WHERE id=?',[$a->tenant()]):null];
    }
    public function auditOptions(Access $a):array
    {
        $a->need('audit.read');$where='deleted_at IS NULL';$args=[];if($a->tenant()){$where.=' AND tenant_id=?';$args[]=$a->tenant();}
        $facilities=$this->db->all('SELECT id,name FROM cp_facilities WHERE '.$where.' ORDER BY name',$args);$scope=$a->facilities();if($scope!==null)$facilities=array_values(array_filter($facilities,fn($f)=>in_array($f['id'],$scope,true)));
        $users=$a->user['role']==='customer'?[]:$this->db->all('SELECT id,name FROM cp_users WHERE '.$where.' ORDER BY name',$args);return['facilities'=>$facilities,'users'=>$users];
    }
    public function audit(Access $a,array $query):array
    {
        $a->need('audit.read');$where='1=1';$args=[];if($a->tenant()){$where.=' AND t.tenant_id=?';$args[]=$a->tenant();}if($a->user['role']==='customer')$where.=" AND t.action IN ('support.access','support.closed')";
        $facilities=$a->facilities();if($facilities!==null&&$a->user['role']!=='customer'){if(!$facilities)$where.=' AND 1=0';else{$where.=' AND EXISTS(SELECT 1 FROM cp_audit_facilities af WHERE af.audit_id=t.id AND af.facility_id IN ('.implode(',',array_fill(0,count($facilities),'?')).'))';array_push($args,...$facilities);}}
        foreach(['action','entity_type','actor_id','entity_id']as$f)if(!empty($query[$f])){$where.=' AND t.'.$f.'=?';$args[]=$query[$f];}
        foreach(['from','to']as$f)if(!empty($query[$f])){$v=$query[$f];if(!preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/',$v))throw new Problem(422,'DATE_INVALID','Некорректная дата');if(strlen($v)===10)$v.=$f==='from'?' 00:00:00':' 23:59:59';$where.=' AND t.created_at'.($f==='from'?'>=':'<=').'?';$args[]=$v;}
        if(!empty($query['facility_id'])){$where.=' AND EXISTS(SELECT 1 FROM cp_audit_facilities f WHERE f.audit_id=t.id AND f.facility_id=?)';$args[]=$query['facility_id'];}
        if(!empty($query['q'])){$where.=' AND (t.action LIKE ? OR t.entity_type LIKE ? OR u.name LIKE ?)';for($i=0;$i<3;$i++)$args[]='%'.mb_substr($query['q'],0,100).'%';}
        $total=(int)$this->db->scalar('SELECT COUNT(*) FROM cp_audit t LEFT JOIN cp_users u ON u.id=t.actor_id WHERE '.$where,$args);$limit=min(500,max(1,(int)($query['limit']??50)));$page=max(1,(int)($query['page']??1));$offset=($page-1)*$limit;
        $rows=$this->db->all("SELECT t.*,COALESCE(u.name,'Система') actor_name FROM cp_audit t LEFT JOIN cp_users u ON u.id=t.actor_id WHERE ".$where.' ORDER BY t.id DESC LIMIT '.$limit.' OFFSET '.$offset,$args);foreach($rows as&$row)$row['metadata']=$a->auditMetadata(Support::decode($row['metadata']),$row['tenant_id']);unset($row);
        $actions=$this->db->all('SELECT DISTINCT t.action FROM cp_audit t LEFT JOIN cp_users u ON u.id=t.actor_id WHERE '.$where.' ORDER BY t.action LIMIT 200',$args);return['items'=>$rows,'total'=>$total,'page'=>$page,'pages'=>(int)ceil($total/$limit),'actions'=>array_column($actions,'action')];
    }
}
