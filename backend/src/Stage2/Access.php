<?php
declare(strict_types=1);
namespace Choppro\Stage2;

final class Access
{
    public const ROLES = [
        'tenant_admin'=>['*'], 'platform_admin'=>['*'],
        'hr'=>['dashboard.read','employees.*','documents.*','document_types.*','document_reviews.read','users.read','facilities.read','posts.read','personal_cards.*','notifications.read','audit.read'],
        'operations'=>['dashboard.read','employees.read','facilities.*','posts.*','instructions.*','qr_points.*','shift_templates.*','shifts.*','assignments.*','attendance.*','vacancies.*','applications.*','patrols.*','patrol_routes.*','patrol_route_points.*','incidents.*','reports.*','reporting.read','reporting.export','files.review','notifications.read','audit.read'],
        'object_manager'=>['dashboard.read','employees.read','facilities.read','posts.read','instructions.read','qr_points.read','shifts.read','assignments.*','attendance.*','notifications.read'],
        'customer'=>['dashboard.read','facilities.read','posts.read','shifts.read','patrols.read','incidents.read','reports.read','reports.acknowledge','reports.download','notifications.read','audit.read'],
        'guard'=>['dashboard.read','employees.read','documents.read','facilities.read','posts.read','instructions.read','instructions.acknowledge','shifts.read','assignments.read','assignments.confirm','attendance.read','attendance.create','patrols.read','patrols.create','patrols.update','patrol_routes.read','patrol_route_points.read','incidents.read','incidents.create','incidents.attach','incidents.download','notifications.read'],
    ];
    public function __construct(public Db $db, public array $user, public ?string $selectedTenant=null) {}
    public function tenant(): ?string
    {
        if($this->user['role']!=='platform_admin') return $this->user['tenant_id'];
        if($this->selectedTenant && !$this->db->one("SELECT id FROM cp_tenants WHERE id=? AND deleted_at IS NULL",[$this->selectedTenant])) throw new Problem(404,'NOT_FOUND','Организация не найдена');
        return $this->selectedTenant;
    }
    public function permissions(): array { $tenant=$this->user['tenant_id']; $custom=$tenant?$this->db->one('SELECT permissions FROM cp_roles WHERE tenant_id=? AND code=? AND deleted_at IS NULL',[$tenant,$this->user['role']]):null; return $custom?Support::decode($custom['permissions']):(self::ROLES[$this->user['role']]??[]); }
    public function allows(string $permission): bool { foreach($this->permissions() as $p) if($p==='*'||$p===$permission||(str_ends_with($p,'.*')&&str_starts_with($permission,substr($p,0,-1)))) return true; return false; }
    public function need(string $permission): void { if($this->user['role']==='platform_admin'&&!in_array(explode('.',$permission)[0],['audit','settings','dashboard'],true))$this->supportAccess();if(!$this->allows($permission)) { $this->audit('security.denied','permission',null,['permission'=>$permission]); throw new Problem(403,'FORBIDDEN','Недостаточно прав для этого действия'); } }
    public function supportAccess():void
    {
        if($this->user['role']!=='platform_admin')return;$tenant=$this->tenant();if(!$tenant)throw new Problem(422,'SELECT_TENANT','Выберите организацию');
        if(!$this->db->one('SELECT id FROM cp_break_glass WHERE tenant_id=? AND user_id=? AND expires_at>UTC_TIMESTAMP() AND deleted_at IS NULL',[$tenant,$this->user['id']]))throw new Problem(403,'SUPPORT_ACCESS_REQUIRED','Откройте временный доступ поддержки с причиной');
    }
    public function audit(string $action,string $type,?string $id,array $metadata=[]):void
    {
        $tenant=$this->selectedTenant??$this->user['tenant_id'];$this->db->run('INSERT INTO cp_audit (tenant_id,actor_id,action,entity_type,entity_id,metadata,ip,device,correlation_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',[$tenant,$this->user['id'],$action,$type,$id,Support::json($metadata),$_SERVER['REMOTE_ADDR']??'cli',substr($_SERVER['HTTP_X_DEVICE_ID']??'',0,100),$GLOBALS['correlation_id']??Support::uuid(),Support::now()]);
        (new AuditIndex($this->db))->record((int)$this->db->pdo->lastInsertId(),$tenant,$type,$id,$metadata);
    }
    public function facilities(): ?array
    {
        $scopes=Support::decode($this->user['scopes']);
        if($this->user['role']==='customer') {
            $ids=array_column($this->db->all('SELECT id FROM cp_facilities WHERE tenant_id=? AND customer_id=? AND deleted_at IS NULL',[$this->user['tenant_id'],$this->user['customer_id']]),'id');
            return $scopes?array_values(array_intersect($ids,$scopes)):$ids;
        }
        return $scopes?:null;
    }
    public function where(string $kind): array
    {
        $this->supportAccess();$parts=['t.deleted_at IS NULL']; $args=[]; $tenant=$this->tenant();
        if($tenant){$parts[]='t.tenant_id=?';$args[]=$tenant;} elseif($this->user['role']!=='platform_admin') throw new Problem(403,'NO_TENANT','Организация не определена');
        if($kind==='notifications'&&in_array($this->user['role'],['customer','guard'],true)){$parts[]="(t.user_id=? OR (t.user_id IS NULL AND t.entity_type='break_glass'))";$args[]=$this->user['id'];}
        $facilities=$this->facilities();
        if($facilities!==null) {
            if(!$facilities){$parts[]='1=0';}
            else {
                $in=implode(',',array_fill(0,count($facilities),'?'));
                $scope=match($kind) { 'facilities'=>'t.id', 'posts','qr_points','employees','vacancies','incidents','patrols','reports','patrol_routes'=>'t.facility_id', 'patrol_route_points'=>"(SELECT x.facility_id FROM cp_patrol_routes x WHERE x.id=t.route_id)", 'instructions','shifts','shift_templates'=>"(SELECT p.facility_id FROM cp_posts p WHERE p.id=t.post_id)", 'assignments','attendance'=>"(SELECT p.facility_id FROM cp_posts p JOIN cp_shifts s ON s.post_id=p.id WHERE s.id=".($kind==='assignments'?'t.shift_id':'(SELECT a.shift_id FROM cp_assignments a WHERE a.id=t.assignment_id)').")", 'applications'=>"(SELECT v.facility_id FROM cp_vacancies v WHERE v.id=t.vacancy_id)", 'documents'=>"(SELECT e.facility_id FROM cp_employees e WHERE e.id=t.employee_id)", default=>null };
                if($scope){$parts[]=$scope.' IN ('.$in.')';array_push($args,...$facilities);}
            }
        }
        if($this->user['role']==='customer') {
            if($kind==='customers'){$parts[]='t.id=?';$args[]=$this->user['customer_id'];}
            if($kind==='incidents')$parts[]='t.published=1';
            if($kind==='reports')$parts[]="(t.published=1 OR EXISTS(SELECT 1 FROM cp_report_versions rv WHERE rv.report_id=t.id AND rv.tenant_id=t.tenant_id AND JSON_EXTRACT(rv.snapshot,'$.published')=1 AND JSON_UNQUOTE(JSON_EXTRACT(rv.snapshot,'$.facility_id'))=t.facility_id))";
            if($kind==='shifts')$parts[]='t.published=1';
        }
        if($this->user['role']==='guard') {
            if(in_array($kind,['employees','documents','assignments','attendance'],true)){$parts[]=($kind==='employees'?'t.id':'t.employee_id').'=?';$args[]=$this->user['employee_id'];}
            if($kind==='shifts'){$parts[]="t.published=1 AND EXISTS (SELECT 1 FROM cp_assignments a WHERE a.shift_id=t.id AND a.employee_id=? AND a.status IN ('ASSIGNED','CONFIRMED'))";$args[]=$this->user['employee_id'];}
        }
        if($this->user['role']==='guard') {
            if($kind==='incidents'){$parts[]='EXISTS(SELECT 1 FROM cp_mobile_incidents x WHERE x.incident_id=t.id AND x.employee_id=? AND x.tenant_id=t.tenant_id)';$args[]=$this->user['employee_id'];}
            if($kind==='patrols'){$parts[]='EXISTS(SELECT 1 FROM cp_mobile_patrol_runs x WHERE x.patrol_id=t.id AND x.employee_id=? AND x.tenant_id=t.tenant_id)';$args[]=$this->user['employee_id'];}
            $facilityColumn=match($kind){'facilities'=>'t.id','posts','patrol_routes'=>'t.facility_id','instructions'=>"(SELECT p.facility_id FROM cp_posts p WHERE p.id=t.post_id)",'patrol_route_points'=>"(SELECT p.facility_id FROM cp_patrol_routes p WHERE p.id=t.route_id)",default=>null};
            if($facilityColumn){$parts[]=$facilityColumn." IN (SELECT p.facility_id FROM cp_assignments ax JOIN cp_shifts sx ON sx.id=ax.shift_id JOIN cp_posts p ON p.id=sx.post_id WHERE ax.tenant_id=? AND ax.employee_id=? AND ax.deleted_at IS NULL AND ax.status IN ('ASSIGNED','CONFIRMED') AND sx.published=1 AND sx.deleted_at IS NULL AND sx.status<>'CANCELLED')";$args[]=$this->tenant();$args[]=$this->user['employee_id'];}
        }
        return [implode(' AND ',$parts),$args];
    }
    public function find(string $kind,string $id,bool $checkPermission=true): array
    {
        if(!preg_match('/^[a-z_]+$/',$kind) || (!isset(Schema::all()[$kind]) && $kind!=='users')) throw new Problem(404,'NOT_FOUND','Запись не найдена');
        if($checkPermission)$this->need($kind.'.read');
        [$where,$args]=$this->where($kind); $row=$this->db->one('SELECT t.* FROM cp_'.$kind.' t WHERE '.$where.' AND t.id=?',[...$args,$id]);
        if(!$row){$this->audit('security.not_found',$kind,$id);throw new Problem(404,'NOT_FOUND','Запись не найдена');}
        return $row;
    }
    public function reference(string $kind,string $id): array { $this->supportAccess();$tenant=$this->tenant(); if(!$tenant) throw new Problem(422,'SELECT_TENANT','Выберите организацию'); $row=$this->db->one('SELECT * FROM cp_'.$kind.' WHERE id=? AND tenant_id=? AND deleted_at IS NULL',[$id,$tenant]); if(!$row) throw new Problem(422,'INVALID_REFERENCE','Связанная запись недоступна',['resource'=>$kind]); return $row; }
    public function canDownloadDocuments(): bool
    {
        if(!$this->allows('documents.download')) return false;
        if($this->user['role']!=='platform_admin') return true;
        return (bool)$this->db->one('SELECT id FROM cp_break_glass WHERE tenant_id=? AND user_id=? AND expires_at>UTC_TIMESTAMP() AND deleted_at IS NULL',[$this->tenant(),$this->user['id']]);
    }
    public function auditMetadata(array $metadata,?string $tenant):array
    {
        $support=$this->user['role']!=='platform_admin'||($tenant&&(bool)$this->db->one('SELECT id FROM cp_break_glass WHERE tenant_id=? AND user_id=? AND expires_at>UTC_TIMESTAMP() AND deleted_at IS NULL',[$tenant,$this->user['id']]));
        $filter=function(array $items)use(&$filter,$support):array{$out=[];foreach($items as$key=>$value){if(in_array($key,['password','password_hash','mfa_secret','access_token','refresh_token','invitation_token'],true))$value='••••';elseif((!$support&&in_array($key,['phone','email','passport','personal_card','name','number','internal_note'],true))||($key==='passport'&&!$this->allows('employees.sensitive')))$value='••••';elseif(is_array($value))$value=$filter($value);$out[$key]=$value;}return$out;};return$filter($metadata);
    }
    public function safe(string $kind,array $row): array
    {
        if($kind==='reports'&&$this->user['role']==='customer'&&!(int)($row['published']??0)){
            $snapshot=$this->db->scalar("SELECT snapshot FROM cp_report_versions WHERE tenant_id=? AND report_id=? AND JSON_EXTRACT(snapshot,'$.published')=1 AND JSON_UNQUOTE(JSON_EXTRACT(snapshot,'$.facility_id'))=? ORDER BY revision DESC LIMIT 1",[$row['tenant_id'],$row['id'],$row['facility_id']]);
            if(!$snapshot)throw new Problem(404,'NOT_FOUND','Опубликованная версия недоступна');
            $row=array_replace($row,Support::decode($snapshot));
        }
        unset($row['password_hash'],$row['mfa_secret'],$row['mfa_last_step'],$row['file_path']);
        if($kind==='employees'&&(!$this->allows('employees.sensitive')||($this->user['role']==='platform_admin'&&!$this->canDownloadDocuments())))$row['passport']='•••• ••••••';
        if($kind==='employees'&&$this->user['role']==='platform_admin'&&!$this->canDownloadDocuments()){foreach(['phone','email','personal_card']as$field)if(isset($row[$field]))$row[$field]='••••';}
        if($kind==='documents'&&!$this->canDownloadDocuments())$row['number']='••••';
        if(in_array($this->user['role'],['customer','guard'],true))unset($row['internal_note'],$row['passport'],$row['override_reason']);
        foreach($row as $k=>$v) if(is_string($v)&&(str_starts_with($v,'[')||str_starts_with($v,'{'))) {try{$row[$k]=Support::decode($v);}catch(\Throwable){}}
        return $row;
    }
}
