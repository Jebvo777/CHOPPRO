<?php
declare(strict_types=1);
namespace Choppro\Stage2;

final class ReportVersions
{
    public function __construct(public Resources $r){}
    public function history(string $id):array
    {
        $a=$this->r->access;$current=$a->find('reports',$id);$rows=$this->r->db()->all('SELECT revision,snapshot,created_at FROM cp_report_versions WHERE report_id=? AND tenant_id=? ORDER BY revision DESC',[$id,$a->tenant()]);
        if((int)$current['published']||$a->user['role']!=='customer')array_unshift($rows,['revision'=>(int)$current['version'],'snapshot'=>$current,'created_at'=>$current['updated_at']]);
        $items=[];foreach($rows as$row){$snapshot=is_array($row['snapshot'])?$row['snapshot']:Support::decode($row['snapshot']);if($a->user['role']==='customer'&&!(int)($snapshot['published']??0))continue;$row['snapshot']=$a->safe('reports',$snapshot);$row['revision']=(int)$row['revision'];$items[]=$row;}return['items'=>$items];
    }
    public function snapshot(string $id,?int $revision=null):array
    {
        $a=$this->r->access;$a->need('reports.download');$current=$a->find('reports',$id);
        if($revision===null)return$a->safe('reports',$current);
        if((int)$current['version']===$revision&&($a->user['role']!=='customer'||(int)$current['published']))return$a->safe('reports',$current);
        $stored=$this->r->db()->scalar('SELECT snapshot FROM cp_report_versions WHERE report_id=? AND tenant_id=? AND revision=?',[$id,$a->tenant(),$revision]);if(!$stored)throw new Problem(404,'NOT_FOUND','Версия отчёта недоступна');$snapshot=Support::decode($stored);if($a->user['role']==='customer'&&(!(int)($snapshot['published']??0)||$snapshot['facility_id']!==$current['facility_id']))throw new Problem(404,'NOT_FOUND','Версия отчёта недоступна');return$a->safe('reports',$snapshot);
    }
    public static function hash(array $report):string{return hash('sha256',Support::json(array_intersect_key($report,array_flip(['id','name','period','version','content']))));}
    public function download(string $id,?int $revision=null):never
    {
        $report=$this->snapshot($id,$revision);$tenant=$this->r->db()->scalar('SELECT name FROM cp_tenants WHERE id=?',[$report['tenant_id']]);$hash=self::hash($report);$pdf=Pdf::render([$report['name'],$tenant,'Идентификатор: '.$report['id'],'Период: '.$report['period'],'Версия: '.$report['version'],'Контрольная сумма SHA-256: '.$hash,$report['content']??'']);$this->r->access->audit('report.downloaded','reports',$report['id'],['version'=>$report['version'],'sha256'=>$hash]);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="report-'.substr($id,0,8).'-v'.$report['version'].'.pdf"');echo$pdf;exit;
    }
}
