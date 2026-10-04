<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Telemetry
{
    public function __construct(public Resources $r){}
    public function presence():array
    {
        [$where,$args]=$this->r->access->where('assignments');$db=$this->r->db();
        $summary=$db->one("SELECT COUNT(*) total,COALESCE(SUM(p.status='COMPLETED'),0) completed,COALESCE(SUM(CASE WHEN p.status='IN_PROGRESS' AND t.status IN ('ASSIGNED','CONFIRMED') AND s.starts_at<=UTC_TIMESTAMP() AND s.ends_at>UTC_TIMESTAMP() AND s.status<>'CANCELLED' THEN 1 ELSE 0 END),0) present,COALESCE(SUM(p.worked_minutes),0) worked_minutes FROM cp_assignments t JOIN cp_presence p ON p.assignment_id=t.id JOIN cp_shifts s ON s.id=t.shift_id WHERE ".$where,$args);foreach($summary as&$value)$value=(int)$value;unset($value);
        return['summary'=>$summary,'items'=>$db->all('SELECT p.*,t.shift_id,t.employee_id,t.name FROM cp_assignments t JOIN cp_presence p ON p.assignment_id=t.id WHERE '.$where.' ORDER BY p.updated_at DESC LIMIT 500',$args)];
    }
    public function get():array
    {
        $a=$this->r->access;$db=$this->r->db();$out=['coverage'=>['required'=>0,'active_required'=>0,'assigned'=>0,'present'=>0,'percent'=>0,'actual_percent'=>0],'documents'=>[],'qualifications'=>[],'incidents'=>[],'daily'=>[],'facilities'=>[],'worked_hours'=>0,'today_shifts'=>0];
        if($a->allows('shifts.read')){
            [$where,$args]=$a->where('shifts');$rows=$db->all("SELECT t.id,t.status,p.facility_id,p.headcount,f.name facility_name,t.starts_at,t.ends_at,(SELECT COUNT(*) FROM cp_assignments ax WHERE ax.shift_id=t.id AND ax.deleted_at IS NULL AND ax.status IN ('ASSIGNED','CONFIRMED')) assigned,(SELECT COUNT(*) FROM cp_presence x JOIN cp_assignments ax ON ax.id=x.assignment_id WHERE ax.shift_id=t.id AND ax.status IN ('ASSIGNED','CONFIRMED') AND x.status='IN_PROGRESS') present FROM cp_shifts t JOIN cp_posts p ON p.id=t.post_id JOIN cp_facilities f ON f.id=p.facility_id WHERE ".$where." AND t.published=1 AND t.status<>'CANCELLED' AND t.starts_at<DATE_ADD(UTC_DATE(),INTERVAL 1 DAY) AND t.ends_at>UTC_DATE()",$args);$fac=[];
            foreach($rows as$row){$out['today_shifts']++;$out['coverage']['required']+=(int)$row['headcount'];$out['coverage']['assigned']+=min((int)$row['headcount'],(int)$row['assigned']);if(strtotime($row['starts_at'])<=time()&&strtotime($row['ends_at'])>time()){$out['coverage']['active_required']+=(int)$row['headcount'];$out['coverage']['present']+=min((int)$row['headcount'],(int)$row['present']);}$key=$row['facility_id'];$fac[$key]??=['id'=>$key,'name'=>$row['facility_name'],'required'=>0,'assigned'=>0,'present'=>0];$fac[$key]['required']+=(int)$row['headcount'];$fac[$key]['assigned']+=min((int)$row['headcount'],(int)$row['assigned']);$fac[$key]['present']+=strtotime($row['starts_at'])<=time()&&strtotime($row['ends_at'])>time()?(int)$row['present']:0;}
            $out['facilities']=array_values($fac);$out['coverage']['percent']=$out['coverage']['required']?round(100*$out['coverage']['assigned']/$out['coverage']['required']):100;$out['coverage']['actual_percent']=$out['coverage']['active_required']?round(100*$out['coverage']['present']/$out['coverage']['active_required']):0;
            $out['daily']=$db->all("SELECT DATE(t.starts_at) day,COUNT(*) shifts,SUM(CASE WHEN t.status='UNFILLED' THEN 1 ELSE 0 END) unfilled FROM cp_shifts t WHERE ".$where." AND t.published=1 AND t.status<>'CANCELLED' AND t.starts_at>=DATE_SUB(UTC_DATE(),INTERVAL 6 DAY) AND t.starts_at<DATE_ADD(UTC_DATE(),INTERVAL 1 DAY) GROUP BY DATE(t.starts_at) ORDER BY day",$args);
            $out['worked_hours']=round((float)$db->scalar("SELECT COALESCE(SUM(x.worked_minutes),0)/60 FROM cp_shifts t JOIN cp_assignments ax ON ax.shift_id=t.id JOIN cp_presence x ON x.assignment_id=ax.id WHERE ".$where." AND x.finished_at>=DATE_SUB(UTC_DATE(),INTERVAL 7 DAY)",$args),1);
        }
        foreach(['documents'=>'status','employees'=>'qualification','incidents'=>'severity']as$kind=>$field)if($a->allows($kind.'.read')){[$where,$args]=$a->where($kind);$key=match($kind){'employees'=>'qualifications',default=>$kind};$out[$key]=$db->all('SELECT t.'.$field.' label,COUNT(*) count FROM cp_'.$kind.' t WHERE '.$where.' GROUP BY t.'.$field.' ORDER BY t.'.$field,$args);}
        return$out;
    }
}
