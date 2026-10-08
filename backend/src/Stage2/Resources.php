<?php
declare(strict_types=1);
namespace Choppro\Stage2;

final class Resources
{
    public const IMMUTABLE=['attendance','assignment_history','document_reviews','contract_versions','instruction_receipts','report_receipts','no_shows','break_glass','outbox','notifications'];
    public function __construct(public Access $access,public array $config) {}
    public function db(): Db { return $this->access->db; }
    public function list(string $kind,array $query): array
    {
        if($kind==='users')return $this->users($query);
        $fields=Schema::fields($kind);$this->access->need($kind.'.read');
        $this->feature($kind);[$where,$args]=$this->access->where($kind);
        foreach(array_keys($fields) as $f) if($f!=='status'&&(str_ends_with($f,'_id')||in_array($f,['qualification','severity','role','category','mime','published'],true))&&!empty($query[$f])){$where.=' AND t.`'.$f.'`=?';$args[]=$query[$f];}
        if(!empty($query['q'])){$search=['name'];foreach(['phone','email','address','number','city']as$f)if(isset($fields[$f]))$search[]=$f;$where.=' AND ('.implode(' OR ',array_map(fn($f)=>'t.`'.$f.'` LIKE ?',$search)).')';foreach($search as$f)$args[]='%'.mb_substr((string)$query['q'],0,100).'%';$where=substr($where,0,-1);foreach(['employee_id'=>'employees','facility_id'=>'facilities','contract_id'=>'contracts','vacancy_id'=>'vacancies','post_id'=>'posts']as$key=>$resource)if(isset($fields[$key])){$where.=' OR EXISTS(SELECT 1 FROM cp_'.$resource.' ref WHERE ref.id=t.`'.$key.'` AND ref.tenant_id=t.tenant_id AND ref.deleted_at IS NULL AND ref.name LIKE ?)';$args[]='%'.mb_substr((string)$query['q'],0,100).'%';}$where.=')';}
        if($kind==='employees'&&!empty($query['expires_before'])){$where.=' AND EXISTS(SELECT 1 FROM cp_documents d WHERE d.employee_id=t.id AND d.tenant_id=t.tenant_id AND d.expires_at<=? AND d.deleted_at IS NULL)';$args[]=$query['expires_before'];}
        if($kind==='documents'&&!empty($query['expires_before'])){$where.=' AND t.expires_at<=?';$args[]=$query['expires_before'];}
        if(!empty($query['facility_id'])&&!isset($fields['facility_id'])){
            $scope=match($kind){'documents'=>"(SELECT e.facility_id FROM cp_employees e WHERE e.id=t.employee_id)",'shifts','shift_templates','instructions'=>"(SELECT p.facility_id FROM cp_posts p WHERE p.id=t.post_id)",'assignments'=>"(SELECT p.facility_id FROM cp_shifts s JOIN cp_posts p ON p.id=s.post_id WHERE s.id=t.shift_id)",default=>null};
            if($scope){$where.=' AND '.$scope.'=?';$args[]=$query['facility_id'];}
        }
        if($kind==='documents'){
            if(($query['expiry']??'')==='expired')$where.=' AND t.expires_at<CURDATE()';
            if(($query['expiry']??'')==='undated')$where.=' AND t.expires_at IS NULL';
            if(in_array((string)($query['expiry']??''),['7','30'],true))$where.=' AND t.expires_at>=CURDATE() AND t.expires_at<=DATE_ADD(CURDATE(),INTERVAL '.(int)$query['expiry'].' DAY)';
        }
        if($kind==='shifts')foreach(['from'=>'starts_at','to'=>'ends_at']as$key=>$field)if(!empty($query[$key])){$where.=' AND t.'.$field.($key==='from'?'>=?':'<=?');$args[]=$query[$key];}
        if($kind==='shifts'&&!empty($query['starts_before'])){$where.=' AND t.starts_at<?';$args[]=$query['starts_before'];}
        $summaryWhere=$where;$summaryArgs=$args;if(isset($fields['status'])&&!empty($query['status'])){$where.=' AND t.status=?';$args[]=$query['status'];}
        $limit=min(500,max(1,(int)($query['limit']??50)));$page=max(1,(int)($query['page']??1));$offset=($page-1)*$limit;
        $sort=$query['sort']??'created_at';if(!in_array($sort,[...array_keys($fields),'created_at','updated_at'],true))$sort='created_at';$direction=($query['direction']??'desc')==='asc'?'ASC':'DESC';
        $count=(int)$this->db()->scalar('SELECT COUNT(*) FROM cp_'.$kind.' t WHERE '.$where,$args);
        $rows=$this->db()->all('SELECT t.* FROM cp_'.$kind.' t WHERE '.$where.' ORDER BY t.`'.$sort.'` '.$direction.',t.id LIMIT '.$limit.' OFFSET '.$offset,$args);
        $summary=isset($fields['status'])?$this->db()->all('SELECT t.status label,COUNT(*) count FROM cp_'.$kind.' t WHERE '.$summaryWhere.' GROUP BY t.status',$summaryArgs):[];return ['summary'=>$summary,'items'=>array_map(fn($r)=>$this->access->safe($kind,$r),$rows),'total'=>$count,'page'=>$page,'pages'=>(int)ceil($count/$limit)];
    }
    public function feature(string $kind): void
    {
        $tenant=$this->access->tenant();if(!$tenant)return;$features=Support::decode($this->db()->scalar('SELECT features FROM cp_tenants WHERE id=?',[$tenant])?:'{}');
        $module=match($kind){'vacancies','applications'=>'jobs','compliance_tasks','compliance_rules','contracts','licenses','service_types','contract_templates','personal_cards','holidays'=>'compliance',default=>'core'};
        if(array_key_exists($module,$features)&&!$features[$module])throw new Problem(403,'MODULE_DISABLED','Модуль отключён для организации');
    }
    public function normalize(string $kind,array $input,?array $old=null): array
    {
        $fields=Schema::fields($kind);$data=[];
        foreach($fields as$f=>$type){
            if(!array_key_exists($f,$input))continue;$v=$input[$f];
            if($v===''&&$type!=='text')$v=null;
            if($v!==null){
                if($type==='json'){if(is_string($v)){try{$v=Support::decode($v);}catch(\Throwable){$v=array_values(array_filter(array_map('trim',explode(',',$v))));}}if(!is_array($v))throw new Problem(422,'VALIDATION','Ожидается список или объект',['field'=>$f]);$v=Support::json($v);}
                elseif(str_starts_with($type,'int')||$type==='tinyint'){if(!is_numeric($v)||floor((float)$v)!=(float)$v)throw new Problem(422,'VALIDATION','Ожидается целое число',['field'=>$f]);$v=(int)$v;}
                elseif(str_starts_with($type,'decimal')){if(!is_numeric($v)||!is_finite((float)$v))throw new Problem(422,'VALIDATION','Некорректное число',['field'=>$f]);$v=(float)$v;}
                elseif($type==='date'){if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$v)||!strtotime($v))throw new Problem(422,'VALIDATION','Некорректная дата',['field'=>$f]);}
                elseif($type==='datetime'){$timezone='UTC';if($kind==='shifts'&&!preg_match('/(?:Z|[+-]\d{2}:\d{2})$/',(string)$v)){ $post=$this->access->reference('posts',$input['post_id']??($old['post_id']??''));$facility=$this->access->reference('facilities',$post['facility_id']);$timezone=$facility['timezone']; }try{$v=(new \DateTimeImmutable((string)$v,new \DateTimeZone($timezone)))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');}catch(\Throwable){throw new Problem(422,'VALIDATION','Некорректные дата и время',['field'=>$f]);}}
                else {if(!is_scalar($v))throw new Problem(422,'VALIDATION','Некорректное значение',['field'=>$f]);$v=trim((string)$v);if(preg_match('/varchar\((\d+)\)/',$type,$m)&&mb_strlen($v)>(int)$m[1])throw new Problem(422,'VALIDATION','Слишком длинное значение',['field'=>$f]);if(strlen($v)>60000)throw new Problem(422,'VALIDATION','Слишком большой текст',['field'=>$f]);}
            }
            $data[$f]=$v;
        }
        if(!$old)$data=array_replace(array_intersect_key(Schema::defaults($kind),$fields),$data);
        foreach($data as$f=>$v)if(is_array($v))$data[$f]=Support::json($v);
        $combined=array_replace($old??[],$data);
        foreach(Schema::required($kind)as$f)if(!isset($combined[$f])||$combined[$f]==='')throw new Problem(422,'VALIDATION','Заполните обязательное поле',['field'=>$f]);
        foreach(Schema::references()as$f=>$resource)if(isset($data[$f])&&$data[$f])$this->access->reference($resource,$data[$f]);
        if(isset($data['status'])&&!in_array($data['status'],Schema::statuses($kind),true))throw new Problem(422,'VALIDATION','Недопустимый статус');
        if($kind==='patrol_routes'){
            if((int)$combined['window_minutes']<1||(int)$combined['window_minutes']>1440||(int)$combined['tolerance_minutes']<0||(int)$combined['tolerance_minutes']>120)throw new Problem(422,'ROUTE_TIME_INVALID','Время обхода: 1–1440 минут, допуск: 0–120 минут');
            if(($combined['status']??'')==='ACTIVE'&&(!$old||!$this->db()->scalar('SELECT id FROM cp_patrol_route_points WHERE route_id=? AND tenant_id=? AND deleted_at IS NULL',[$old['id'],$this->access->tenant()])))throw new Problem(422,'ROUTE_EMPTY','Добавьте точки перед активацией маршрута');
        }
        if($kind==='patrol_route_points'){
            if((int)$combined['position']<1||(int)$combined['position']>100||(int)$combined['radius']<10||(int)$combined['radius']>1000||abs((float)$combined['lat'])>90||abs((float)$combined['lng'])>180||(int)$combined['offset_minutes']<0)throw new Problem(422,'POINT_INVALID','Проверьте порядок, координаты и допуск точки');
            $route=$this->access->reference('patrol_routes',$combined['route_id']);$qr=$this->access->reference('qr_points',$combined['qr_point_id']);if($route['facility_id']!==$qr['facility_id'])throw new Problem(422,'POINT_FACILITY_INVALID','QR-точка должна относиться к объекту маршрута');
        }
        if($kind==='mobile_policies'&&((int)$combined['revision']<1||mb_strlen($combined['text'])<20))throw new Problem(422,'POLICY_INVALID','Укажите версию и полный текст правил');
        if($kind==='facilities'){
            $polygon=Support::decode($combined['polygon']??'[]');if($polygon&&(count($polygon)<3||count($polygon)>100))throw new Problem(422,'POLYGON_INVALID','Полигон содержит от 3 до 100 точек');foreach($polygon as$point)if(!is_array($point)||count($point)!==2||!is_numeric($point[0])||!is_numeric($point[1])||abs((float)$point[0])>90||abs((float)$point[1])>180)throw new Problem(422,'POLYGON_INVALID','Некорректные точки полигона');
            if((float)$combined['lat']<-90||(float)$combined['lat']>90||(float)$combined['lng']<-180||(float)$combined['lng']>180||(int)$combined['radius']<10||(int)$combined['radius']>10000)throw new Problem(422,'GEOFENCE_INVALID','Проверьте координаты и радиус геозоны');
            if(!in_array($combined['timezone'],\DateTimeZone::listIdentifiers(),true))throw new Problem(422,'TIMEZONE_INVALID','Неизвестный часовой пояс');
        }
        if($kind==='shift_templates'){if(!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/',(string)($combined['start_time']??''))||(float)($combined['duration_hours']??0)<1||(float)($combined['duration_hours']??0)>48)throw new Problem(422,'TEMPLATE_TIME_INVALID','Укажите время начала и длительность от 1 до 48 часов');$weekdays=Support::decode($combined['weekdays']??'[]');if(!$weekdays||count($weekdays)>7)throw new Problem(422,'TEMPLATE_DAYS_INVALID','Выберите рабочие дни');foreach($weekdays as$day)if(!is_numeric($day)||(int)$day!=(float)$day||(int)$day<1||(int)$day>7)throw new Problem(422,'TEMPLATE_DAYS_INVALID','Выберите дни с понедельника по воскресенье');}
        if($kind==='employees'&&((int)$combined['qualification']<1||(int)$combined['qualification']>6))throw new Problem(422,'QUALIFICATION_INVALID','Разряд должен быть от 1 до 6');
        if($kind==='shifts' && ($combined['ends_at']<=$combined['starts_at']||strtotime($combined['ends_at'])-strtotime($combined['starts_at'])>172800))throw new Problem(422,'SHIFT_TIME_INVALID','Смена должна длиться от 1 минуты до 48 часов');
        if($kind==='posts'&&((int)$combined['headcount']<1||(int)$combined['headcount']>100))throw new Problem(422,'HEADCOUNT_INVALID','Укажите численность от 1 до 100');
        if($kind==='vacancies'&&((float)$combined['salary_from']<0||(float)$combined['salary_to']<(float)$combined['salary_from']))throw new Problem(422,'SALARY_INVALID','Проверьте диапазон зарплаты');
        if(isset($data['email'])&&$data['email']&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new Problem(422,'EMAIL_INVALID','Некорректный email');
        if(isset($data['phone'])&&$data['phone'])$data['phone']=Support::phone($data['phone']);
        if($kind==='roles'){if(($combined['code']??'')==='platform_admin')throw new Problem(403,'FORBIDDEN','Системная роль недоступна');foreach(Support::decode($combined['permissions']??'[]')as$p)if(!is_string($p)||!preg_match('/^(\*|[a-z_]+\.(\*|[a-z_]+))$/',$p))throw new Problem(422,'PERMISSION_INVALID','Некорректное разрешение');}
        return $data;
    }
    public function create(string $kind,array $input): array
    {
        $this->access->need($kind.'.create');$this->feature($kind);
        if(in_array($kind,self::IMMUTABLE,true))throw new Problem(405,'ACTION_REQUIRED','Используйте действие над записью');
        if($kind==='assignments')return (new Operations($this))->assign($input);
        $data=$this->normalize($kind,$input);
        if($kind==='documents'){$data['status']='PENDING_REVIEW';$data['scan_status']='PENDING_SCAN';unset($data['file_path'],$data['sha256'],$data['mime']);}
        if($kind==='vacancies'){unset($data['pinned_rank'],$data['pinned_at']);}
        if($kind==='contracts'&&($data['status']??'')==='ACTIVE'){$data['status']='DRAFT';}
        if($kind==='shifts'){ $data['published']=0;$data['status']='DRAFT'; }
        return $this->db()->transaction(function()use($kind,$data){(new Platform($this->db(),$this->config))->room($kind,$this->access->tenant());$row=$this->insert($kind,$data);$this->access->audit($kind.'.created',$kind,$row['id'],['new'=>$this->auditSafe($row)]);if($kind==='personal_cards')(new Extensions($this))->card($row);if($kind==='posts')$this->insert('instructions',['name'=>'Инструкция: '.$row['name'],'post_id'=>$row['id'],'text'=>$row['instruction']??'','revision'=>1,'author_id'=>$this->access->user['id']]);return $this->access->safe($kind,$row);});
    }
    public function insert(string $kind,array $data,?string $tenant=null): array
    {
        $data=['id'=>Support::uuid(),'tenant_id'=>$tenant??$this->access->tenant(),...$data,'created_at'=>Support::now(),'updated_at'=>Support::now(),'version'=>1];
        foreach($data as$k=>$v)if(is_array($v))$data[$k]=Support::json($v);
        if(!$data['tenant_id'])throw new Problem(422,'SELECT_TENANT','Выберите организацию');
        $keys=array_keys($data);$this->db()->run('INSERT INTO cp_'.$kind.' (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_fill(0,count($keys),'?')).')',array_values($data));return $data;
    }
    public function update(string $kind,string $id,array $input): array
    {
        $this->access->need($kind.'.update');$this->feature($kind);if(in_array($kind,self::IMMUTABLE,true)||$kind==='assignments')throw new Problem(405,'ACTION_REQUIRED','Используйте действие над записью');
        return $this->db()->transaction(function()use($kind,$id,$input){
            $old=$this->access->find($kind,$id,false);$version=(int)($input['version']??0);if($version!==(int)$old['version'])throw new Problem(409,'VERSION_CONFLICT','Запись изменилась. Обновите страницу.');
            if(in_array($kind,['service_types','compliance_rules'],true)||($kind==='contract_templates'&&(int)$old['published']))throw new Problem(409,'IMMUTABLE_VERSION','Создайте новую версию справочника');
            if($kind==='mobile_policies'&&$old['status']==='PUBLISHED')throw new Problem(409,'IMMUTABLE_VERSION','Создайте новую версию правил');
            $data=$this->normalize($kind,$input,$old);
            if($kind==='reports'&&(int)$old['published'])$this->db()->run('INSERT INTO cp_report_versions(id,tenant_id,report_id,revision,snapshot,created_at)VALUES(?,?,?,?,?,?)',[Support::uuid(),$old['tenant_id'],$id,$old['version'],Support::json($this->access->safe('reports',$old)),Support::now()]);
            if($kind==='documents'){foreach(['status','scan_status','file_path','sha256','mime']as$f)unset($data[$f]);}
            if($kind==='vacancies')unset($data['pinned_rank'],$data['pinned_at']);
            if($kind==='contracts'&&isset($data['status'])&&$data['status']!==$old['status'])throw new Problem(405,'ACTION_REQUIRED','Для активации используйте проверку договора');
            if($kind==='shifts'){unset($data['published']);if(isset($data['status'])&&$data['status']!==$old['status'])throw new Problem(405,'ACTION_REQUIRED','Используйте публикацию или отмену смены');}
            if($kind==='compliance_tasks'){if(in_array($data['status']??'', ['MARKED_SENT','CONFIRMED'],true))$data['author_id']=$this->access->user['id'];(new Compliance($this))->validateTask(array_replace($old,$data));}
            if($kind==='contract_templates'&&!empty($data['published']))$data['published_by']=$this->access->user['id'];
            if($kind==='qr_points'&&isset($data['token']))unset($data['token']);
            $this->write($kind,$id,$data,$version);
            if($kind==='reports')$this->db()->run('UPDATE cp_report_jobs SET status=?,updated_at=UTC_TIMESTAMP() WHERE report_id=? AND tenant_id=?',[!empty($data['published'])?'PUBLISHED':'READY',$id,$this->access->tenant()]);
            if($kind==='contracts')$this->insert('contract_versions',['name'=>$old['name'].' / v'.$old['version'],'contract_id'=>$id,'snapshot'=>$this->auditSafe($old),'actor_id'=>$this->access->user['id']]);
            if($kind==='posts'&&isset($data['instruction'])&&$data['instruction']!==$old['instruction']){ $revision=(int)$old['instruction_version']+1;$this->write('posts',$id,['instruction_version'=>$revision]);$this->insert('instructions',['name'=>'Инструкция: '.$old['name'],'post_id'=>$id,'text'=>$data['instruction'],'revision'=>$revision,'author_id'=>$this->access->user['id']]); }
            if($kind==='personal_cards'&&($data['status']??'')!==$old['status'])(new Extensions($this))->card(array_replace($old,$data));
            if($kind==='employees'&&($data['status']??'')==='DISMISSED'){ $this->db()->run("UPDATE cp_users SET status='DISMISSED' WHERE employee_id=? AND tenant_id=?",[$id,$this->access->tenant()]);$this->db()->run('UPDATE cp_sessions s JOIN cp_users u ON s.user_id=u.id SET s.revoked_at=UTC_TIMESTAMP() WHERE u.employee_id=? AND u.tenant_id=?',[$id,$this->access->tenant()]); }
            if($kind==='roles')$this->db()->run('UPDATE cp_sessions s JOIN cp_users u ON u.id=s.user_id SET s.revoked_at=UTC_TIMESTAMP() WHERE u.tenant_id=? AND u.role=?',[$this->access->tenant(),$old['code']]);
            $this->access->audit($kind.'.updated',$kind,$id,['old'=>$this->auditSafe($old),'new'=>$this->auditSafe($data)]);
            return $this->access->safe($kind,$this->access->find($kind,$id,false));
        });
    }
    public function write(string $kind,string $id,array $data,?int $version=null): void
    {
        if(!$data)return;$data['updated_at']=Support::now();foreach($data as$k=>$v)if(is_array($v))$data[$k]=Support::json($v);
        $sql='UPDATE cp_'.$kind.' SET '.implode(',',array_map(fn($f)=>'`'.$f.'`=?',array_keys($data))).',version=version+1 WHERE id=?';$args=[...array_values($data),$id];
        if($version!==null){$sql.=' AND version=?';$args[]=$version;}
        if($this->db()->run($sql,$args)->rowCount()===0)throw new Problem(409,'VERSION_CONFLICT','Запись изменена другим пользователем');
    }
    public function remove(string $kind,string $id): array
    {
        $this->access->need($kind.'.delete');if(in_array($kind,self::IMMUTABLE,true))throw new Problem(405,'IMMUTABLE','Исторические события не удаляются');$old=$this->access->find($kind,$id,false);
        if($kind==='contracts'&&($old['legal_hold_until']??'')>=gmdate('Y-m-d'))throw new Problem(409,'LEGAL_HOLD','Договор находится на обязательном хранении до '.$old['legal_hold_until']);
        if($kind==='facilities'&&$this->db()->scalar("SELECT COUNT(*) FROM cp_shifts s JOIN cp_posts p ON p.id=s.post_id WHERE p.facility_id=? AND s.deleted_at IS NULL AND s.ends_at>UTC_TIMESTAMP() AND s.status<>'CANCELLED'",[$id]))throw new Problem(409,'ACTIVE_SHIFTS','Сначала отмените будущие смены объекта');
        $this->write($kind,$id,['deleted_at'=>Support::now()]);$this->access->audit($kind.'.deleted',$kind,$id,['old'=>$this->auditSafe($old)]);return ['ok'=>true];
    }
    public function users(array $query): array { $this->access->need('users.read');[$where,$args]=$this->access->where('users');$rows=$this->db()->all('SELECT t.* FROM cp_users t WHERE '.$where.' ORDER BY created_at DESC LIMIT 200',$args);return ['items'=>array_map(fn($r)=>$this->access->safe('users',$r),$rows),'total'=>count($rows),'page'=>1,'pages'=>1]; }
    public function auditSafe(array $data): array { foreach(['password_hash','passport','mfa_secret','token','qr_token','file_path','phone','email']as$f)if(isset($data[$f]))$data[$f]='[protected]';return $data; }
}
