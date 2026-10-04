<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Operations
{
    public function __construct(public Resources $r) {}
    public function assign(array $input): array
    {
        $a=$this->r->access;$a->need('assignments.create');
        return $this->r->db()->transaction(function()use($input,$a){
            $data=$this->r->normalize('assignments',$input);
            $shift=$a->find('shifts',$data['shift_id'],false);$post=$a->reference('posts',$shift['post_id']);$facility=$a->reference('facilities',$post['facility_id']);
            $a->reference('employees',$data['employee_id']);$employee=$this->r->db()->one('SELECT * FROM cp_employees WHERE id=? FOR UPDATE',[$data['employee_id']]);$shift=$this->r->db()->one('SELECT * FROM cp_shifts WHERE id=? FOR UPDATE',[$shift['id']]);
            if($employee['status']!=='ACTIVE'||$post['status']!=='ACTIVE'||$facility['status']!=='ACTIVE'||$shift['status']==='CANCELLED')throw new Problem(422,'ASSIGNMENT_UNAVAILABLE','Сотрудник, пост и объект должны быть активны');
            $issues=[];
            if((int)$employee['qualification']<(int)$post['qualification'])$issues[]='Разряд сотрудника ниже требований поста';
            $overlap=$this->r->db()->scalar("SELECT COUNT(*) FROM cp_assignments a JOIN cp_shifts s ON s.id=a.shift_id WHERE a.tenant_id=? AND a.employee_id=? AND a.deleted_at IS NULL AND a.status IN ('ASSIGNED','CONFIRMED') AND s.status<>'CANCELLED' AND s.starts_at<? AND s.ends_at>?",[$a->tenant(),$employee['id'],$shift['ends_at'],$shift['starts_at']]);
            if($overlap)$issues[]='Назначение пересекается с другой сменой';
            $count=(int)$this->r->db()->scalar("SELECT COUNT(*) FROM cp_assignments WHERE shift_id=? AND deleted_at IS NULL AND status IN ('ASSIGNED','CONFIRMED')",[$shift['id']]);
            if($count>=(int)$post['headcount'])throw new Problem(409,'SHIFT_FILLED','Все места смены уже заняты');
            if($issues){$a->need('assignments.override');if(mb_strlen(trim($input['override_reason']??''))<5)throw new Problem(409,'ASSIGNMENT_CONFLICT',implode('. ',$issues),['issues'=>$issues]);}
            $data['status']='ASSIGNED';$data['confirmed_at']=null;$row=$this->r->insert('assignments',$data);
            if((int)$shift['published'])$this->r->write('shifts',$shift['id'],['status'=>$count+1<(int)$post['headcount']?'UNFILLED':'PUBLISHED']);
            $a->audit('assignment.created','assignments',$row['id'],['employee_id'=>$employee['id'],'shift_id'=>$shift['id'],'issues'=>$issues,'reason'=>$input['override_reason']??null]);
            $settings=Support::decode($this->r->db()->scalar('SELECT settings FROM cp_tenants WHERE id=?',[$a->tenant()]));
            $hours=(float)$this->r->db()->scalar("SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE,s.starts_at,s.ends_at))/60,0) FROM cp_assignments a JOIN cp_shifts s ON s.id=a.shift_id WHERE a.employee_id=? AND a.tenant_id=? AND a.status IN ('ASSIGNED','CONFIRMED') AND s.starts_at>=DATE_SUB(?,INTERVAL 7 DAY) AND s.starts_at<=?",[$employee['id'],$a->tenant(),$shift['starts_at'],$shift['starts_at']]);
            return [...$a->safe('assignments',$row),'warnings'=>$hours>(float)($settings['weekly_hours_warning']??48)?['Плановая нагрузка за 7 дней: '.round($hours,1).' ч.']:[]];
        });
    }
    public function publish(string $id): array
    {
        $a=$this->r->access;$a->need('shifts.publish');$s=$a->find('shifts',$id,false);$p=$a->reference('posts',$s['post_id']);$f=$a->reference('facilities',$p['facility_id']);
        if($p['status']!=='ACTIVE'||$f['status']!=='ACTIVE'||$s['status']==='CANCELLED')throw new Problem(422,'POST_INACTIVE','Пост и объект должны быть активны');
        $n=(int)$this->r->db()->scalar("SELECT COUNT(*) FROM cp_assignments WHERE shift_id=? AND status IN ('ASSIGNED','CONFIRMED') AND deleted_at IS NULL",[$id]);
        $this->r->write('shifts',$id,['published'=>1,'status'=>$n<(int)$p['headcount']?'UNFILLED':'PUBLISHED']);$a->audit('shift.published','shifts',$id);return $a->safe('shifts',$a->find('shifts',$id,false));
    }
    public function replace(string $id,array $input): array
    {
        $a=$this->r->access;$a->need('assignments.replace');if(mb_strlen(trim($input['reason']??''))<5)throw new Problem(422,'REASON_REQUIRED','Укажите причину замены');
        return $this->r->db()->transaction(function()use($a,$id,$input){$this->r->db()->run('SELECT id FROM cp_assignments WHERE id=? FOR UPDATE',[$id]);$old=$a->find('assignments',$id,false);if(isset($input['version'])&&(int)$input['version']!==(int)$old['version'])throw new Problem(409,'VERSION_CONFLICT','Назначение изменилось');if(!in_array($old['status'],['ASSIGNED','CONFIRMED'],true))throw new Problem(409,'ASSIGNMENT_INACTIVE','Назначение уже заменено или отменено');$this->r->write('assignments',$id,['status'=>'REPLACED']);$new=$this->assign(['name'=>$old['name'],'shift_id'=>$old['shift_id'],'employee_id'=>$input['employee_id']??'','override_reason'=>$input['override_reason']??'']);$this->r->insert('assignment_history',['name'=>'Замена сотрудника','assignment_id'=>$id,'old_employee_id'=>$old['employee_id'],'new_employee_id'=>$new['employee_id'],'reason'=>$input['reason'],'actor_id'=>$a->user['id']]);(new Presence($this->r->db()))->shift($old['shift_id']);$a->audit('assignment.replaced','assignments',$id,['old_employee_id'=>$old['employee_id'],'new_employee_id'=>$new['employee_id'],'reason'=>$input['reason']]);return $new;});
    }
    public function confirm(string $id,array $input): array { $a=$this->r->access;$a->need('assignments.confirm');$row=$a->find('assignments',$id,false);$shift=$a->reference('shifts',$row['shift_id']);if(!(int)$shift['published'])throw new Problem(404,'NOT_FOUND','Смена не опубликована');if(!in_array($row['status'],['ASSIGNED','CONFIRMED'],true))throw new Problem(409,'ASSIGNMENT_INACTIVE','Назначение недоступно');$status=($input['accept']??true)?'CONFIRMED':'DECLINED';$this->r->write('assignments',$id,['status'=>$status,'confirmed_at'=>Support::now()]);$a->audit('assignment.'.strtolower($status),'assignments',$id);return $a->safe('assignments',$a->find('assignments',$id,false)); }
    public function repeat(string $templateId,array $input): array
    {
        $a=$this->r->access;$a->need('shifts.create');$t=$a->find('shift_templates',$templateId,false);$p=$a->reference('posts',$t['post_id']);$f=$a->reference('facilities',$p['facility_id']);
        try{$from=new \DateTimeImmutable($input['from']??'today',new \DateTimeZone($f['timezone']));$to=new \DateTimeImmutable($input['to']??'+7 days',new \DateTimeZone($f['timezone']));}catch(\Throwable){throw new Problem(422,'DATE_INVALID','Укажите даты');}
        if($to<$from||$to->getTimestamp()-$from->getTimestamp()>86400*62)throw new Problem(422,'DATE_RANGE','Диапазон не более 62 дней');
        $days=Support::decode($t['weekdays']);$rows=[];
        return $this->r->db()->transaction(function()use($from,$to,$t,$days,$a,&$rows){for($d=$from;$d<=$to;$d=$d->modify('+1 day')){if($days&&!in_array((int)$d->format('N'),array_map('intval',$days),true))continue;$start=$d->setTime((int)substr($t['start_time'],0,2),(int)substr($t['start_time'],3,2));$end=$start->modify('+'.(int)((float)$t['duration_hours']*60).' minutes');$utc=$start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');if($this->r->db()->scalar('SELECT COUNT(*) FROM cp_shifts WHERE tenant_id=? AND post_id=? AND starts_at=? AND deleted_at IS NULL',[$a->tenant(),$t['post_id'],$utc]))continue;$rows[]=$this->r->create('shifts',['name'=>$t['name'].' · '.$d->format('d.m'),'post_id'=>$t['post_id'],'starts_at'=>$start->format('c'),'ends_at'=>$end->format('c')]);}return ['created'=>count($rows),'items'=>$rows];});
    }
    public function attendance(array $input): array
    {
        $a=$this->r->access;$a->need('attendance.create');$key=(string)($input['idempotency_key']??($_SERVER['HTTP_IDEMPOTENCY_KEY']??''));
        if(strlen($key)<8||strlen($key)>128)throw new Problem(422,'IDEMPOTENCY_REQUIRED','Укажите Idempotency-Key длиной 8–128 символов');
        return $this->r->db()->transaction(function()use($a,$input,$key){
            $this->r->db()->run('SELECT id FROM cp_assignments WHERE id=? FOR UPDATE',[$input['assignment_id']??'']);$assignment=$a->find('assignments',$input['assignment_id']??'',false);
            $s=$a->reference('shifts',$assignment['shift_id']);$post=$a->reference('posts',$s['post_id']);$f=$a->reference('facilities',$post['facility_id']);
            if(!in_array($assignment['status'],['ASSIGNED','CONFIRMED'],true)||!(int)$s['published'])throw new Problem(409,'NO_ACTIVE_ASSIGNMENT','Нет активного опубликованного назначения');
            if($a->user['role']==='guard'&&$a->user['employee_id']!==$assignment['employee_id'])throw new Problem(404,'NOT_FOUND','Назначение не найдено');
            if(!empty($input['offline'])&&empty($input['client_time']))throw new Problem(422,'CLIENT_TIME_REQUIRED','Для офлайн-события сохраните исходное время');
            $previous=$this->r->db()->one('SELECT * FROM cp_attendance WHERE tenant_id=? AND idempotency_key=?',[$a->tenant(),$key]);
            if($previous){if(isset($input['client_time'])&&strtotime($input['client_time'])!==strtotime($previous['client_time']))throw new Problem(409,'IDEMPOTENCY_CONFLICT','Исходное время отличается');foreach(['lat','lng','accuracy','device_id','qr_token','offline']as$field)if(isset($input[$field])&&(string)$previous[$field]!== (string)$input[$field]&&!(is_numeric($previous[$field])&&is_numeric($input[$field])&&(float)$previous[$field]===(float)$input[$field]))throw new Problem(409,'IDEMPOTENCY_CONFLICT','Содержимое повторного запроса отличается');if($previous['assignment_id']!==$assignment['id']||$previous['event_type']!==($input['event_type']??'CHECK_IN'))throw new Problem(409,'IDEMPOTENCY_CONFLICT','Ключ уже использован для другой операции');return $a->safe('attendance',$previous);}
            $event=$input['event_type']??'CHECK_IN';if(!in_array($event,['CHECK_IN','CHECK_OUT'],true))throw new Problem(422,'EVENT_INVALID','Неизвестный тип события');
            foreach(['lat','lng','accuracy']as$v)if(!isset($input[$v])||!is_numeric($input[$v]))throw new Problem(422,'COORDINATES_REQUIRED','Передайте координаты и точность');
            if(abs((float)$input['lat'])>90||abs((float)$input['lng'])>180||(float)$input['accuracy']<0)throw new Problem(422,'COORDINATES_INVALID','Некорректные координаты');
            if(empty($input['device_id'])||strlen((string)$input['device_id'])>100)throw new Problem(422,'DEVICE_REQUIRED','Укажите устройство');
            try{$client=(new \DateTimeImmutable($input['client_time']??Support::now()))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');}catch(\Throwable){throw new Problem(422,'TIME_INVALID','Некорректное время события');}
            $settings=Support::decode($this->r->db()->scalar('SELECT settings FROM cp_tenants WHERE id=?',[$a->tenant()]));$reasons=[];
            $time=!empty($input['offline'])?strtotime($client):time();$start=strtotime($s['starts_at']);$end=strtotime($s['ends_at']);
            if($time<$start-(int)($settings['checkin_early_minutes']??60)*60||$time>$end+(int)($settings['checkin_late_minutes']??120)*60)$reasons[]='Вне временного окна смены';
            if(strtotime($client)>time()+300)$reasons[]='Время устройства опережает сервер';if(empty($input['offline'])&&abs(strtotime($client)-time())>300)$reasons[]='Время устройства отличается от времени сервера';
            if((float)$input['accuracy']>(float)($settings['gps_max_accuracy']??100))$reasons[]='Недостаточная точность GPS';
            if(!self::inside((float)$input['lat'],(float)$input['lng'],$f))$reasons[]='За пределами геозоны';
            $qr=$this->r->db()->one("SELECT id FROM cp_qr_points WHERE tenant_id=? AND facility_id=? AND (post_id IS NULL OR post_id=?) AND token=? AND status='ACTIVE' AND deleted_at IS NULL",[$a->tenant(),$f['id'],$post['id'],$input['qr_token']??'']);if(!$qr)$reasons[]='QR-токен недействителен или отозван';
            $in=$this->r->db()->one("SELECT t.* FROM cp_attendance t WHERE t.assignment_id=? AND t.event_type='CHECK_IN' AND (t.status='VALID' OR EXISTS(SELECT 1 FROM cp_attendance m WHERE m.source_id=t.id AND m.status='MANUAL_OVERRIDE')) ORDER BY server_time DESC LIMIT 1",[$assignment['id']]);
            if($event==='CHECK_OUT'&&!$in)$reasons[]='Нет подтверждённого заступления';if($event==='CHECK_OUT'&&$in&&strtotime($client)<strtotime($in['client_time']))$reasons[]='Завершение раньше заступления';
            $duplicate=$this->r->db()->scalar("SELECT COUNT(*) FROM cp_attendance e WHERE e.assignment_id=? AND e.event_type=? AND (e.status='VALID' OR EXISTS(SELECT 1 FROM cp_attendance m WHERE m.source_id=e.id AND m.status='MANUAL_OVERRIDE'))",[$assignment['id'],$event]);if($duplicate)throw new Problem(409,'EVENT_EXISTS','Такое подтверждённое событие уже есть');
            $row=$this->r->insert('attendance',['name'=>$event==='CHECK_IN'?'Заступление на смену':'Завершение смены','assignment_id'=>$assignment['id'],'employee_id'=>$assignment['employee_id'],'event_type'=>$event,'client_time'=>$client,'server_time'=>Support::now(),'lat'=>(float)$input['lat'],'lng'=>(float)$input['lng'],'accuracy'=>(float)$input['accuracy'],'device_id'=>$input['device_id'],'qr_token'=>$input['qr_token']??'','offline'=>(int)!empty($input['offline']),'status'=>$reasons?'REQUIRES_REVIEW':'VALID','reasons'=>$reasons,'idempotency_key'=>$key,'duration_minutes'=>$event==='CHECK_OUT'&&$in?max(0,(int)round((strtotime($client)-strtotime($in['client_time']))/60)):null]);
            (new Presence($this->r->db()))->assignment($assignment['id']);$a->audit('attendance.'.strtolower($event),'attendance',$row['id'],['assignment_id'=>$assignment['id'],'status'=>$row['status'],'reasons'=>$reasons,'device'=>$input['device_id']]);return $a->safe('attendance',$row);
        });
    }
    public static function inside(float $lat,float $lng,array $facility):bool
    {
        $polygon=Support::decode($facility['polygon']??'[]');if(!$polygon)return Support::distance($lat,$lng,(float)$facility['lat'],(float)$facility['lng'])<=(float)$facility['radius'];
        $inside=false;$j=count($polygon)-1;for($i=0;$i<count($polygon);$i++){[$yi,$xi]=$polygon[$i];[$yj,$xj]=$polygon[$j];if(($yi>$lat)!==($yj>$lat)&&$lng<($xj-$xi)*($lat-$yi)/($yj-$yi)+$xi)$inside=!$inside;$j=$i;}return$inside;
    }
    public function override(string $id,array $input): array
    {
        $a=$this->r->access;$a->need('attendance.override');if(mb_strlen(trim($input['reason']??''))<5)throw new Problem(422,'REASON_REQUIRED','Укажите причину подтверждения');
        return $this->r->db()->transaction(function()use($a,$id,$input){
            $old=$a->find('attendance',$id,false);$this->r->db()->run('SELECT id FROM cp_assignments WHERE id=? FOR UPDATE',[$old['assignment_id']]);
            if($old['status']!=='REQUIRES_REVIEW'||$this->r->db()->scalar('SELECT COUNT(*) FROM cp_attendance WHERE source_id=?',[$id]))throw new Problem(409,'ALREADY_REVIEWED','Решение уже принято');
            if($this->r->db()->scalar("SELECT COUNT(*) FROM cp_attendance e WHERE e.assignment_id=? AND e.event_type=? AND (e.status='VALID' OR EXISTS(SELECT 1 FROM cp_attendance m WHERE m.source_id=e.id AND m.status='MANUAL_OVERRIDE'))",[$old['assignment_id'],$old['event_type']]))throw new Problem(409,'EVENT_EXISTS','Такое подтверждённое событие уже есть');
            if($old['event_type']==='CHECK_OUT'){$in=$this->r->db()->one("SELECT e.client_time FROM cp_attendance e WHERE e.assignment_id=? AND e.event_type='CHECK_IN' AND (e.status='VALID' OR EXISTS(SELECT 1 FROM cp_attendance m WHERE m.source_id=e.id AND m.status='MANUAL_OVERRIDE')) ORDER BY e.client_time LIMIT 1",[$old['assignment_id']]);if(!$in||$old['client_time']<$in['client_time'])throw new Problem(409,'EVENT_ORDER_INVALID','Сначала подтвердите корректное заступление');}
            $row=$this->r->insert('attendance',['name'=>'Ручное подтверждение','assignment_id'=>$old['assignment_id'],'employee_id'=>$old['employee_id'],'event_type'=>'MANUAL_OVERRIDE','client_time'=>$old['client_time'],'server_time'=>Support::now(),'status'=>'MANUAL_OVERRIDE','source_id'=>$id,'reason'=>$input['reason'],'reasons'=>[],'idempotency_key'=>'override-'.$id]);(new Presence($this->r->db()))->assignment($old['assignment_id']);$a->audit('attendance.override','attendance',$id,['decision_id'=>$row['id'],'reason'=>$input['reason']]);return $a->safe('attendance',$row);
        });
    }
}
