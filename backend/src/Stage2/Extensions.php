<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Extensions
{
    public function __construct(public Resources $r){}
    public function export(string $kind,array $query):never
    {
        $a=$this->r->access;$a->need($kind.'.export');
        if($kind==='audit'){$rows=[];for($page=1;$page<=100;$page++){$out=(new Kernel($this->r->db(),$this->r->config))->audit($a,[...$query,'limit'=>500,'page'=>$page]);array_push($rows,...$out['items']);if($page>=($out['pages']??1))break;}}else{$rows=[];$query['limit']=500;for($page=1;$page<=100;$page++){$out=$this->r->list($kind,[...$query,'page'=>$page]);array_push($rows,...$out['items']);if($page>=($out['pages']??1))break;}}
        $a->audit($kind.'.exported',$kind,null,['rows'=>count($rows)]);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$kind.'-'.gmdate('Ymd').'.csv"');header('Cache-Control: no-store');$f=fopen('php://output','w');fwrite($f,"\xEF\xBB\xBF");
        if($rows){$keys=array_keys($rows[0]);fputcsv($f,array_map(fn($key)=>Schema::labels()[$key]??'Данные',$keys),',','"','');foreach($rows as$row){$values=[];foreach($keys as$key){$v=$row[$key]??'';if(is_array($v))$v=Support::json($v);$v=(string)$v;if(preg_match('/^[\s\x00-\x1f]*[=+@-]/u',$v))$v="'".$v;$values[]=$v;}fputcsv($f,$values,',','"','');}}fclose($f);exit;
    }
    public function newVersion(string $kind,string $id,array $input):array
    {
        if(!in_array($kind,['service_types','compliance_rules','contract_templates'],true))throw new Problem(404,'NOT_FOUND','Запись не найдена');$a=$this->r->access;$a->need($kind.'.create');$old=$a->find($kind,$id,false);$field=$kind==='contract_templates'?'revision':'rule_version';$data=array_intersect_key($old,Schema::fields($kind));$data[$field]=(int)$old[$field]+1;if(isset($data['valid_from']))$data['valid_from']=gmdate('Y-m-d');if($kind==='contract_templates'){$data['published']=0;$data['published_by']=null;}
        $data=array_replace($data,array_intersect_key($input,Schema::fields($kind)));$data[$field]=(int)$old[$field]+1;$row=$this->r->create($kind,$data);$a->audit('reference.versioned',$kind,$row['id'],['previous'=>$id,'revision'=>$data[$field]]);return$row;
    }
    public function card(array $row):void
    {
        $c=new Compliance($this->r);$base=$row['surrendered_at']??$row['issued_at']??gmdate('Y-m-d');$deadline=$c->deadline(substr($base,0,10),1,true,0);$this->r->insert('compliance_tasks',['name'=>'Сообщение о личной карточке: '.$row['number'],'rule_version'=>1,'deadline'=>$deadline,'status'=>'DUE']);$this->r->access->audit('personal_card.changed','personal_cards',$row['id'],['deadline'=>$deadline,'status'=>$row['status']]);
    }
    public function event(array $input):array
    {
        $a=$this->r->access;$a->need('compliance_tasks.create');$event=$input['event']??'';if(!in_array($event,['participants_changed','leader_changed','service_ended'],true))throw new Problem(422,'EVENT_INVALID','Выберите событие изменения участников, руководителя или окончания услуги');$contract=$a->find('contracts',$input['contract_id']??'',false);$base=$input['date']??gmdate('Y-m-d');$days=$event==='service_ended'?5:15;$deadline=(new Compliance($this->r))->deadline($base,$days,false,0);$row=$this->r->insert('compliance_tasks',['name'=>($event==='service_ended'?'Окончание услуги':'Изменение сведений').' · '.$contract['number'],'contract_id'=>$contract['id'],'rule_version'=>1,'deadline'=>$deadline,'status'=>'DUE']);$a->audit('compliance.event','contracts',$contract['id'],['event'=>$event,'deadline'=>$deadline]);return$row;
    }
}
