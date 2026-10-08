<?php
declare(strict_types=1);
namespace Choppro\Stage2;

final class IncidentReport
{
    public function __construct(public Resources $r){}
    public function lines(string $id):array
    {
        $a=$this->r->access;$row=$a->safe('incidents',$a->find('incidents',$id));$facility=$a->find('facilities',$row['facility_id']);$lines=['Происшествие: '.$row['name'],'Идентификатор: '.$row['id'],'Версия: '.$row['version'],'Объект: '.$facility['name'],'Зафиксировано: '.$row['created_at'],'Важность: '.(['LOW'=>'Низкая','MEDIUM'=>'Средняя','HIGH'=>'Высокая','CRITICAL'=>'Критическая'][$row['severity']]??$row['severity']),'Состояние: '.(['OPEN'=>'Открыто','IN_PROGRESS'=>'В работе','RESOLVED'=>'Закрыто'][$row['status']]??$row['status']),'Описание: '.$row['description']];
        if($a->user['role']!=='customer'){
            $meta=$this->r->db()->one('SELECT client_time,measures FROM cp_mobile_incidents WHERE tenant_id=? AND incident_id=?',[$a->tenant(),$id]);if($meta){$lines[]='Время устройства: '.$meta['client_time'];$lines[]='Первичные меры: '.$meta['measures'];}
            foreach($this->r->db()->all('SELECT description,measures,client_time FROM cp_incident_updates WHERE tenant_id=? AND incident_id=? ORDER BY client_time',[$a->tenant(),$id])as$update){$lines[]='Дополнение от '.$update['client_time'];$lines[]=$update['description'];$lines[]='Меры: '.$update['measures'];}
            foreach($this->r->db()->all('SELECT x.result,x.measures,x.created_at,u.name FROM cp_incident_resolutions x LEFT JOIN cp_users u ON u.id=x.responsible_id WHERE x.tenant_id=? AND x.incident_id=? ORDER BY x.created_at',[$a->tenant(),$id])as$resolution){$lines[]='Закрытие от '.$resolution['created_at'];$lines[]='Ответственный: '.$resolution['name'];$lines[]='Итог: '.$resolution['result'];$lines[]='Принятые меры: '.$resolution['measures'];}
        }
        $files=$this->r->db()->all("SELECT id,name,sha256 FROM cp_files WHERE tenant_id=? AND entity_type='incidents' AND entity_id=? AND deleted_at IS NULL AND scan_status='CLEAN'".($a->user['role']==='customer'?' AND published=1':'').' ORDER BY created_at',[$a->tenant(),$id]);$lines[]='Проверенные материалы: '.count($files);foreach($files as$file){$lines[]=$file['name'].' · '.$file['id'];$lines[]='SHA-256: '.$file['sha256'];}$lines[]='Контрольная сумма отчёта SHA-256: '.hash('sha256',Support::json($lines));return$lines;
    }
    public function download(string $id):never
    {
        $lines=$this->lines($id);$pdf=Pdf::render($lines);$this->r->access->audit('incident.report_downloaded','incidents',$id,['sha256'=>hash('sha256',$pdf)]);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="incident-'.substr($id,0,8).'.pdf"');echo$pdf;exit;
    }
}
