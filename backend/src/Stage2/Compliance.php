<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Compliance
{
    public function __construct(public Resources $r) {}
    public function checks(string $id): array
    {
        $a=$this->r->access;$c=$a->find('contracts',$id);$l=$a->reference('licenses',$c['license_id']);$issues=[];
        foreach(['number','starts_at','ends_at','signed_at','amount','compensation','ownership_proof']as$f)if(empty($c[$f]))$issues[]='Не заполнено: '.(Schema::labels()[$f]??$f);
        if($l['status']!=='ACTIVE'||$l['expires_at']<$c['starts_at'])$issues[]='Лицензия не действует на начало услуг';
        $services=Support::decode($c['services']);$allowed=Support::decode($l['services']);foreach($services as$s)if(!in_array($s,$allowed,true))$issues[]='Услуга отсутствует в лицензии: '.$s;
        if(in_array('armed',$services,true)&&empty($c['weapon_details']))$issues[]='Нет сведений об оружии';
        if($c['ends_at']<$c['starts_at'])$issues[]='Окончание раньше начала договора';
        return ['issues'=>$issues,'can_activate'=>!$issues,'license'=>$a->safe('licenses',$l),'notice'=>'Подготовка и контроль сроков. Не юридическое заключение и не автоматическая отправка.'];
    }
    public function activate(string $id,array $input): array
    {
        $a=$this->r->access;$a->need('contracts.update');
        return $this->r->db()->transaction(function()use($a,$id,$input){$this->r->db()->run('SELECT id FROM cp_contracts WHERE id=? FOR UPDATE',[$id]);$c=$a->find('contracts',$id,false);if($c['status']==='ACTIVE')return $a->safe('contracts',$c);$checks=$this->checks($id);if($checks['issues']){$a->need('contracts.override');if(mb_strlen(trim($input['reason']??''))<5)throw new Problem(409,'CONTRACT_INCOMPLETE','Устраните замечания или укажите основание исключения',$checks);}
            $hold=(new \DateTimeImmutable($c['ends_at']))->modify('+5 years')->format('Y-m-d');$this->r->insert('contract_versions',['name'=>$c['name'].' / v'.$c['version'],'contract_id'=>$id,'snapshot'=>$this->r->auditSafe($c),'actor_id'=>$a->user['id']]);$this->r->write('contracts',$id,['status'=>'ACTIVE','legal_hold_until'=>$hold,'override_reason'=>$input['reason']??null]);
            $rules=$this->r->db()->all('SELECT * FROM cp_compliance_rules WHERE tenant_id=? AND deleted_at IS NULL AND valid_from<=? ORDER BY rule_version DESC',[$a->tenant(),$c['signed_at']??gmdate('Y-m-d')]);$seen=[];$tasks=[];
            foreach($rules as$rule){if(isset($seen[$rule['rule_code']]))continue;$seen[$rule['rule_code']]=true;$services=Support::decode($c['services']);if($rule['service_code']&&!in_array($rule['service_code'],$services,true))continue;$base=str_contains($rule['rule_code'],'end')?$c['ends_at']:($rule['rule_code']==='contract_copy'?$c['signed_at']:$c['starts_at']);$deadline=$this->deadline($base,(int)$rule['days'],(bool)$rule['working_days'],(int)$rule['offset_hours']);$tasks[]=$this->r->insert('compliance_tasks',['name'=>$rule['name'].' · '.$c['number'],'contract_id'=>$id,'rule_id'=>$rule['id'],'rule_version'=>$rule['rule_version'],'deadline'=>$deadline,'status'=>strtotime($deadline)<time()?'OVERDUE':'DUE']);}
            $a->audit('contract.activated','contracts',$id,['issues'=>$checks['issues'],'override_reason'=>$input['reason']??null,'tasks'=>count($tasks)]);return ['contract'=>$a->safe('contracts',$a->find('contracts',$id,false)),'tasks'=>count($tasks)];
        });
    }
    public function deadline(string $base,int $days,bool $working,int $hours): string
    {
        $tenant=$this->r->access->tenant();$settings=Support::decode($this->r->db()->scalar('SELECT settings FROM cp_tenants WHERE id=?',[$tenant]));$date=new \DateTimeImmutable($base.' 12:00:00',new \DateTimeZone($settings['timezone']??'Europe/Moscow'));
        if($working){for($i=0;$i<abs($days);){$date=$date->modify($days>=0?'+1 day':'-1 day');$custom=$this->r->db()->one('SELECT is_working FROM cp_holidays WHERE tenant_id=? AND date=? AND deleted_at IS NULL',[$tenant,$date->format('Y-m-d')]);$ok=$custom?(bool)$custom['is_working']:(int)$date->format('N')<6;if($ok)$i++;}}
        else $date=$date->modify(($days>=0?'+':'').$days.' days');
        if($hours)$date=$date->modify(($hours>=0?'+':'').$hours.' hours');return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    public function validateTask(array $row): void
    {
        if($row['status']==='MARKED_SENT' && (empty($row['channel'])||empty($row['sent_at'])||(empty($row['attachment'])&&empty($row['receipt']))))throw new Problem(422,'PROOF_REQUIRED','Укажите канал, время и вложение или исходящий номер');
        if($row['status']==='CONFIRMED'&&empty($row['receipt']))throw new Problem(422,'RECEIPT_REQUIRED','Укажите квитанцию или входящий номер');
    }
    public function draft(string $id): array { $task=$this->r->access->find('compliance_tasks',$id);$c=!empty($task['contract_id'])?$this->r->access->reference('contracts',$task['contract_id']):['number'=>'Не относится к договору'];$tenant=$this->r->db()->one('SELECT name,inn FROM cp_tenants WHERE id=?',[$this->r->access->tenant()]);return ['name'=>$task['name'],'text'=>"Черновик уведомления\nОрганизация: ".$tenant['name']."\nИНН: ".$tenant['inn']."\nДоговор: ".$c['number']."\nСрок исполнения: ".$task['deadline']." UTC\nВерсия правила: ".$task['rule_version']."\n\nПодготовка/контроль, не отправка. Подтверждение подачи вносится ответственным пользователем."]; }
}
