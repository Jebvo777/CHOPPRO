<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/Core/Autoloader.php';
use Choppro\Stage2\{Config,Db,Migrator,Seeder,Access,Resources,Auth,Totp,Support,Operations,Compliance,Administration,Documents,Worker,Problem};
$config=Config::load();$db=new Db($config);$checks=0;
function check(bool $value,string $message):void{global $checks;if(!$value)throw new RuntimeException($message);$checks++;}
function problem(callable $fn,string $code):void{try{$fn();}catch(Problem $e){check($e->codeName===$code,'Expected '.$code.', got '.$e->codeName);return;}throw new RuntimeException('Expected '.$code);}
$first=(new Migrator($db,$config))->apply();check($first['seeded'],'Initial seed');
foreach(['tenants'=>5,'employees'=>1000,'documents'=>3000,'shifts'=>10080]as$table=>$count)check((int)$db->scalar('SELECT COUNT(*) FROM cp_'.$table)===$count,'Seed '.$table);
$again=(new Migrator($db,$config))->apply();check(!$again['seeded']&&$again['migrations']===[],'Idempotent migrations');check((int)$db->scalar('SELECT @@FOREIGN_KEY_CHECKS')===1,'Foreign keys restored');
check(Totp::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',59,8)==='94287082','RFC TOTP vector');
$id=fn(string $key)=>Seeder::id($key);$admin=$db->one("SELECT * FROM cp_users WHERE email='admin@obereg.example'");$a=new Access($db,$admin);$r=new Resources($a,$config);$ops=new Operations($r);$management=new Administration($r);
$db->pdo->beginTransaction();
try{
    problem(fn()=>$a->find('facilities',$id('facility-1-0')),'NOT_FOUND');
    $manager=$db->one("SELECT * FROM cp_users WHERE email='manager@obereg.example'");$mr=new Resources(new Access($db,$manager),$config);check($mr->list('facilities',[])['total']===3,'Object scopes');
    $customer=$db->one("SELECT * FROM cp_users WHERE email='customer@obereg.example'");$cr=new Resources(new Access($db,$customer),$config);check($cr->list('facilities',[])['total']===2,'Customer scope');problem(fn()=>$cr->list('employees',[]),'FORBIDDEN');
    foreach($cr->list('reports',[])['items']as$report)check((int)$report['published']===1&&!isset($report['internal_note']),'Published client data');
    $auth=new Auth($db,$config);$login=$auth->login(['login'=>$admin['email'],'password'=>'Demo2026!']);check(isset($login['challenge_id'],$login['demo_code']),'MFA challenge');$bad=$auth->verifyMfa(['challenge_id'=>$login['challenge_id'],'code'=>'000000']);check(isset($bad['_error']),'MFA rejects bad code');$ok=$auth->verifyMfa(['challenge_id'=>$login['challenge_id'],'code'=>$login['demo_code']]);check(isset($ok['access_token'],$ok['refresh_token']),'MFA issues tokens');$_SERVER['HTTP_AUTHORIZATION']='Bearer '.$ok['access_token'];check($auth->user()['id']===$admin['id'],'Bearer auth');$refresh=$auth->refresh(['refresh_token'=>$ok['refresh_token']]);check($refresh['refresh_token']!==$ok['refresh_token'],'Rotation');problem(fn()=>$auth->refresh(['refresh_token'=>$ok['refresh_token']]),'REFRESH_EXPIRED');unset($_SERVER['HTTP_AUTHORIZATION']);
    $employee=$r->create('employees',['name'=>'Проверочный сотрудник','qualification'=>6,'status'=>'ACTIVE','facility_id'=>$id('facility-0-0')]);
    $shift=$r->create('shifts',['name'=>'Проверочная смена','post_id'=>$id('post-0-0'),'starts_at'=>gmdate('c',time()-300),'ends_at'=>gmdate('c',time()+3600)]);
    $assignment=$ops->assign(['name'=>'Проверочное назначение','shift_id'=>$shift['id'],'employee_id'=>$employee['id']]);$ops->publish($shift['id']);check($a->find('shifts',$shift['id'])['published']==1,'Publish shift');
    $other=$r->create('shifts',['name'=>'Пересечение','post_id'=>$id('post-0-1'),'starts_at'=>gmdate('c',time()-300),'ends_at'=>gmdate('c',time()+3600)]);problem(fn()=>$ops->assign(['name'=>'Конфликт','shift_id'=>$other['id'],'employee_id'=>$employee['id']]),'ASSIGNMENT_CONFLICT');
    $facility=$a->find('facilities',$id('facility-0-0'));$point=['token'=>'demo-qr-0-0'];$event=['assignment_id'=>$assignment['id'],'event_type'=>'CHECK_IN','client_time'=>gmdate('c'),'lat'=>$facility['lat'],'lng'=>$facility['lng'],'accuracy'=>5,'device_id'=>'ci-device','qr_token'=>$point['token'],'idempotency_key'=>'ci-checkin-0001'];
    $attendance=$ops->attendance($event);check($attendance['status']==='VALID','Geofence and QR: '.Support::json($attendance['reasons']));$retry=$ops->attendance($event);check($retry['id']===$attendance['id'],'Attendance retry');
    $event['event_type']='CHECK_OUT';$event['idempotency_key']='ci-checkout-0001';$checkout=$ops->attendance($event);check($checkout['duration_minutes']!==null,'Factual duration');
    $replacement=$r->create('employees',['name'=>'Замещающий сотрудник','qualification'=>6,'status'=>'ACTIVE']);$ops->replace($assignment['id'],['employee_id'=>$replacement['id'],'reason'=>'Плановая замена сотрудника']);check($a->find('assignments',$assignment['id'])['status']==='REPLACED','Replacement history');
    $contract=$a->find('contracts',$id('contract-0-0'));(new Compliance($r))->activate($contract['id'],[]);check($a->find('contracts',$contract['id'])['status']==='ACTIVE','Contract activation');problem(fn()=>$r->remove('contracts',$contract['id']),'LEGAL_HOLD');
    $csv="name,qualification,status\nCSV сотрудник,6,ACTIVE\nНекорректный,99,ACTIVE\n";$preview=$management->import('employees',['csv'=>$csv,'template_version'=>1],false);check($preview['valid']===1&&$preview['errors']===1,'CSV row preview');
    $pin=$management->pin($id('vacancy-0-5'),['rank'=>1]);check((int)$a->find('vacancies',$id('vacancy-0-5'))['pinned_rank']===1,'Manual pin');$management->pin($id('vacancy-0-5'),['rank'=>null]);check($a->find('vacancies',$id('vacancy-0-5'))['pinned_rank']===null,'Unpin');
    $r->update('employees',$employee['id'],['version'=>1,'name'=>'Изменённое имя']);problem(fn()=>$r->update('employees',$employee['id'],['version'=>1,'name'=>'Старая версия']),'VERSION_CONFLICT');
    (new Worker($db,$config))->tick();$count=$db->scalar('SELECT COUNT(*) FROM cp_notifications');(new Worker($db,$config))->tick();check($db->scalar('SELECT COUNT(*) FROM cp_notifications')==$count,'Worker deduplication');
    check((int)$db->scalar('SELECT COUNT(*) FROM cp_audit')>500,'Immutable audit');echo 'PASS '.$checks." integration checks\n";
}finally{if($db->pdo->inTransaction())$db->pdo->rollBack();}
