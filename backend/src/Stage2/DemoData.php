<?php
declare(strict_types=1);
namespace Choppro\Stage2;

final class DemoData
{
    public function __construct(public Db $db,public array $config) {}

    public static function location(int $tenant,int $index):array
    {
        static $catalog;
        $catalog??=Support::decode(file_get_contents(dirname(__DIR__,2).'/database/stage2/demo-locations.json'));
        $city=$catalog['cities'][$tenant];
        return [...$city['locations'][$index%count($city['locations'])],'city'=>$city['city'],'timezone'=>$city['timezone']];
    }

    public static function template(string $tenant):string
    {
        return Pdf::render(['Личное дело сотрудника',$tenant,'Удостоверение, медицинское заключение и сведения о квалификации','Демонстрационный образец — не действителен.','При открытии документа реквизиты заполняются из карточки сотрудника.']);
    }

    public static function employeeDocument(array $row,array $employee,string $type,string $tenant):string
    {
        return Pdf::render([$type,$tenant,'Демонстрационный образец — не действителен.',
            'Номер документа: '.($row['number']??'Не указан'),
            'Сотрудник: '.$employee['name'],
            'Табельный номер: '.($employee['badge']??'Не указан'),
            'Квалификационный разряд: '.($employee['qualification']??'Не указан'),
            'Дата выдачи: '.($row['issued_at']??'Не указана'),
            'Действителен до: '.($row['expires_at']??'Без ограничения'),
            match(true){str_contains($type,'Медицин')=>'Заключение о допуске к работе. Данные предназначены для проверки кадрового процесса.',str_contains($type,'квалификац')=>'Сведения о прохождении обучения и присвоении квалификации. Программа: организация охраны, пропускной режим, действия при происшествиях.',default=>'Сведения об удостоверении частного охранника и допуске к охранной деятельности.'},
            'Документ используется в демонстрационном пространстве ЧОППРО. Все персональные сведения вымышлены.']);
    }

    public function upgrade():void
    {
        if(!$this->config['demo']||$this->db->scalar("SELECT COUNT(*) FROM cp_data_migrations WHERE name='workspace-6'"))return;
        $this->db->transaction(function(){
            $platform=new Platform($this->db,$this->config);$seeder=new Seeder($this->db,$this->config);
            $centers=[[53.1959,45.0183],[55.7558,37.6173],[53.1955,50.1018],[59.9343,30.3351],[56.2965,43.9361]];
            foreach($centers as$ti=>[$lat,$lng]){
                $tenant=Seeder::id('tenant-'.$ti);$company=$this->db->one('SELECT name,slug FROM cp_tenants WHERE id=?',[$tenant]);
                if(!$company||!$this->db->scalar('SELECT COUNT(*) FROM cp_users WHERE tenant_id=? AND is_demo=1',[$tenant]))continue;
                $dir=$this->config['storage'].'/uploads/'.$tenant;if(!is_dir($dir))mkdir($dir,0700,true);
                $pdf=self::template($company['name']);Support::atomic($dir.'/demo-document.pdf',$pdf);$sha=hash('sha256',$pdf);
                for($fi=0;$fi<24;$fi++){
                    $id=Seeder::id('facility-'.$ti.'-'.$fi);$row=$this->db->one('SELECT * FROM cp_facilities WHERE id=? AND tenant_id=? AND deleted_at IS NULL',[$id,$tenant]);
                    $location=self::location($ti,$fi);
                    $oldAddress=$location['city'].', '.['ул. Центральная','просп. Победы','ул. Заводская','ул. Мира','Логистический проезд'][$fi%5].', '.($fi*3+10);
                    if($row&&$row['address']===$oldAddress&&abs((float)$row['lat']-($lat+($fi%6-3)*0.014))<0.000001&&abs((float)$row['lng']-($lng+((int)floor($fi/6)-2)*0.019))<0.000001&&!Support::decode($row['polygon']??'[]'))
                        $this->db->run('UPDATE cp_facilities SET address=?,lat=?,lng=?,timezone=?,updated_at=UTC_TIMESTAMP(),version=version+1 WHERE id=?',[$location['address'],$location['lat'],$location['lng'],$location['timezone'],$id]);
                }
                $documents=array_column($this->db->all('SELECT * FROM cp_documents WHERE tenant_id=?',[$tenant]),null,'id');
                $employees=array_column($this->db->all('SELECT * FROM cp_employees WHERE tenant_id=?',[$tenant]),null,'id');
                $types=array_column($this->db->all('SELECT * FROM cp_document_types WHERE tenant_id=?',[$tenant]),null,'id');
                for($ei=0;$ei<200;$ei++)for($di=0;$di<3;$di++){
                    $id=Seeder::id('doc-'.$ti.'-'.$ei.'-'.$di);$row=$documents[$id]??null;
                    if(!$row||!in_array($row['file_path'],['demo-document.png','demo-document.pdf'],true)||!in_array($row['mime'],['image/png','application/pdf'],true))continue;
                    $employee=$employees[$row['employee_id']]??null;$type=$types[$row['type_id']]??null;if(!$employee||!$type)continue;
                    $bytes=self::employeeDocument($row,$employee,$type['name'],$company['name']);$filename='demo-'.$id.'.pdf';Support::atomic($dir.'/'.$filename,$bytes);chmod($dir.'/'.$filename,0600);
                    $changed=$this->db->run('UPDATE cp_documents SET file_path=?,mime=?,sha256=?,updated_at=UTC_TIMESTAMP(),version=version+1 WHERE id=? AND version=?',[$filename,'application/pdf',hash('sha256',$bytes),$id,$row['version']])->rowCount();
                    if(!$changed)throw new Problem(409,'VERSION_CONFLICT','Документ изменился во время актуализации. Повторите обновление.');
                    $platform->file($id,$tenant,'documents',strlen($bytes));
                }
                $license=Seeder::id('license-'.$ti);$row=$this->db->one('SELECT * FROM cp_licenses WHERE id=? AND tenant_id=?',[$license,$tenant]);
                if($row){$pdf=Pdf::render([$row['name'],'Демонстрационный образец — не действителен.','Организация: '.$company['name'],'Номер: '.$row['number'],'Дата выдачи: '.$row['issued_at'],'Действительна до: '.$row['expires_at'],'Виды услуг: физическая охрана, пропускной режим, видеонаблюдение.']);Support::atomic($dir.'/demo-license.pdf',$pdf);$id=Seeder::id('file-license-'.$ti);
                    $this->db->run("UPDATE cp_files SET name='Лицензия.pdf',file_path='demo-license.pdf',mime='application/pdf',sha256=? WHERE id=? AND tenant_id=? AND file_path IN ('demo-document.png','demo-document.pdf','demo-license.pdf')",[hash('sha256',$pdf),$id,$tenant]);
                    if($this->db->scalar("SELECT COUNT(*) FROM cp_files WHERE id=? AND file_path='demo-license.pdf'",[$id]))$platform->file($id,$tenant,'files',strlen($pdf));
                }
                for($vi=0;$vi<16;$vi++)for($ci=0;$ci<4;$ci++){
                    $vacancy=Seeder::id('vacancy-'.$ti.'-'.$vi);$key='candidate-'.$ti.'-'.$vi.'-'.$ci;$id=Seeder::id($key);
                    if(!$this->db->scalar('SELECT COUNT(*) FROM cp_vacancies WHERE id=? AND tenant_id=?',[$vacancy,$tenant])||$this->db->scalar('SELECT COUNT(*) FROM cp_applications WHERE id=?',[$id]))continue;
                    $n=$vi*4+$ci;$person=['Алексей Никитин','Марина Белова','Виктор Соловьёв','Денис Фомин','Андрей Громов','Ольга Коваль'][$n%6];
                    $seeder->add('applications',$key,['tenant_id'=>$tenant,'name'=>$person,'vacancy_id'=>$vacancy,'phone'=>'+7990'.str_pad((string)($ti*1000+$n),7,'0',STR_PAD_LEFT),'email'=>'candidate'.$n.'@'.$company['slug'].'.example','message'=>['Опыт охраны объектов — 5 лет. Разряд 6. Готов обсудить график.','Есть действующее удостоверение, интересует дневная смена.','Опыт работы на КПП и в складских комплексах.','Готов приступить после собеседования.'][$ci],'status'=>['NEW','IN_REVIEW','INTERVIEW','HIRED','REJECTED'][$n%5],'created_at'=>gmdate('Y-m-d H:i:s',time()-$n*7200)]);
                }
                foreach(['demo-document.png','demo-document.pdf']as$oldFile)if(!$this->db->scalar('SELECT (SELECT COUNT(*) FROM cp_documents WHERE tenant_id=? AND file_path=?)+(SELECT COUNT(*) FROM cp_files WHERE tenant_id=? AND file_path=?)',[$tenant,$oldFile,$tenant,$oldFile]))@unlink($dir.'/'.$oldFile);
            }
            $this->db->run("INSERT INTO cp_data_migrations(name,applied_at) VALUES('workspace-6',UTC_TIMESTAMP())");
        });
    }
}
