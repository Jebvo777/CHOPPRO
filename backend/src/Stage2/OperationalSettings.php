<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class OperationalSettings
{
    public const CATEGORIES=[['code'=>'SECURITY','name'=>'Безопасность','required_fields'=>['description']],['code'=>'ACCESS','name'=>'Пропускной режим','required_fields'=>['description']],['code'=>'FIRE','name'=>'Пожар','required_fields'=>['description','measures']],['code'=>'MEDICAL','name'=>'Помощь','required_fields'=>['description']],['code'=>'TECHNICAL','name'=>'Техническое событие','required_fields'=>['description']],['code'=>'OTHER','name'=>'Другое','required_fields'=>['description']]];
    public const ACCOUNTING=['badge'=>'Табельный номер','employee'=>'Сотрудник','date'=>'Дата смены','facility'=>'Объект','post'=>'Пост','planned'=>'План, ч','actual'=>'Факт, ч','status'=>'Статус','overrides'=>'Ручных решений'];
    public function __construct(public Resources $r){}
    public function get():array
    {
        $settings=Support::decode($this->r->db()->scalar('SELECT settings FROM cp_tenants WHERE id=?',[$this->r->access->tenant()])?:'{}');
        return ['incident_categories'=>$settings['incident_categories']??self::CATEGORIES,'incident_images'=>(int)($settings['incident_images']??10),'incident_image_mb'=>(int)($settings['incident_image_mb']??20),'incident_audio_mb'=>(int)($settings['incident_audio_mb']??100),'monthly_auto'=>(bool)($settings['monthly_auto']??true),'accounting_columns'=>$settings['accounting_columns']??array_map(fn($k,$v)=>['key'=>$k,'name'=>$v],array_keys(self::ACCOUNTING),array_values(self::ACCOUNTING))];
    }
    public function save(array $input):array
    {
        $a=$this->r->access;$a->need('settings.update');$old=$this->get();$new=array_replace($old,array_intersect_key($input,$old));
        foreach(['incident_images'=>[1,10],'incident_image_mb'=>[1,20],'incident_audio_mb'=>[1,100]]as$key=>[$min,$max])if(!is_numeric($new[$key])||(int)$new[$key]!=(float)$new[$key]||$new[$key]<$min||$new[$key]>$max)throw new Problem(422,'SETTING_INVALID','Проверьте ограничения файлов');
        if(!is_array($new['incident_categories'])||!count($new['incident_categories'])||count($new['incident_categories'])>20)throw new Problem(422,'CATEGORY_INVALID','Укажите от одной до двадцати категорий');$codes=[];
        foreach($new['incident_categories']as&$c){if(!is_array($c)||!preg_match('/^[A-Z][A-Z0-9_]{1,31}$/',$c['code']??'')||isset($codes[$c['code']])||mb_strlen(trim($c['name']??''))<2||mb_strlen($c['name'])>100||!is_array($c['required_fields']??null)||array_diff($c['required_fields'],['description','measures']))throw new Problem(422,'CATEGORY_INVALID','Проверьте названия, коды и обязательные поля категорий');$codes[$c['code']]=true;$c=['code'=>$c['code'],'name'=>trim($c['name']),'required_fields'=>array_values(array_unique($c['required_fields']))];}unset($c);
        if(!is_array($new['accounting_columns'])||!count($new['accounting_columns'])||count($new['accounting_columns'])>9)throw new Problem(422,'TEMPLATE_INVALID','Настройте колонки табельной выгрузки');$keys=[];foreach($new['accounting_columns']as&$c){if(!is_array($c)||!isset(self::ACCOUNTING[$c['key']??''])||isset($keys[$c['key']])||mb_strlen(trim($c['name']??''))<1||mb_strlen($c['name'])>100)throw new Problem(422,'TEMPLATE_INVALID','Проверьте колонки табельной выгрузки');$keys[$c['key']]=true;$c=['key'=>$c['key'],'name'=>trim($c['name'])];}unset($c);
        $settings=Support::decode($this->r->db()->scalar('SELECT settings FROM cp_tenants WHERE id=?',[$a->tenant()]));$new['monthly_auto']=(bool)$new['monthly_auto'];$this->r->write('tenants',$a->tenant(),['settings'=>array_replace($settings,$new)],(int)($input['version']??0));$a->audit('operational.settings_changed','tenants',$a->tenant(),['fields'=>array_keys($new)]);return$this->get();
    }
}
