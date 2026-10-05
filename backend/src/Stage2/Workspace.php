<?php
declare(strict_types=1);
namespace Choppro\Stage2;

final class Workspace
{
    public function __construct(public Resources $r) {}

    public function search(array $query): array
    {
        $q=trim(mb_substr((string)($query['q']??''),0,100));
        if(mb_strlen($q)<2)return ['groups'=>[],'total'=>0];
        $a=$this->r->access;
        if($a->user['role']==='platform_admin'&&!$a->tenant()){
            $rows=(new Administration($this->r))->tenants()['items'];
            $items=array_values(array_filter($rows,fn($row)=>mb_stripos($row['name'].' '.$row['inn'].' '.$row['slug'],$q)!==false));
            return ['groups'=>[['kind'=>'tenants','total'=>count($items),'items'=>array_slice($items,0,8)]],'total'=>count($items)];
        }
        $groups=[];$total=0;
        foreach(['employees','facilities','documents','contracts','customers','posts','shifts','vacancies','applications','reports','incidents','instructions','roles']as$kind){
            if(!$a->allows($kind.'.read'))continue;
            try{$out=$this->r->list($kind,['q'=>$q,'limit'=>5]);}
            catch(Problem $e){if($e->codeName==='MODULE_DISABLED')continue;throw$e;}
            if(!$out['total'])continue;
            $items=[];
            foreach($out['items']as$row)$items[]=['id'=>$row['id'],'name'=>$row['name'],'status'=>$row['status']??null,'subtitle'=>$row['address']??$row['number']??$row['city']??$row['phone']??''];
            $groups[]=['kind'=>$kind,'total'=>$out['total'],'items'=>$items];$total+=$out['total'];
        }
        return ['groups'=>$groups,'total'=>$total];
    }

    public function planner(array $query): array
    {
        $out=$this->r->list('shifts',$query);$ids=array_column($out['items'],'id');
        if(!$ids)return$out;
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $counts=$this->r->db()->all("SELECT shift_id,COUNT(*) count FROM cp_assignments WHERE shift_id IN ($marks) AND deleted_at IS NULL AND status IN ('ASSIGNED','CONFIRMED') GROUP BY shift_id",$ids);
        $counts=array_column($counts,'count','shift_id');
        $posts=$this->r->db()->all("SELECT p.id,p.name,p.headcount,p.facility_id,f.name facility_name,f.timezone FROM cp_posts p JOIN cp_facilities f ON f.id=p.facility_id WHERE p.id IN (SELECT post_id FROM cp_shifts WHERE id IN ($marks))",$ids);
        $posts=array_column($posts,null,'id');
        foreach($out['items']as&$row){$post=$posts[$row['post_id']]??[];$row['assigned_count']=(int)($counts[$row['id']]??0);$row['required_count']=(int)($post['headcount']??1);$row['post_name']=$post['name']??'';$row['facility_name']=$post['facility_name']??'';$row['facility_id']=$post['facility_id']??null;$row['timezone']=$post['timezone']??'Europe/Moscow';}
        unset($row);return$out;
    }
}
