<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class PatrolRoutes
{
    public function __construct(public Resources $r){}
    public function save(array $input):array
    {
        $a=$this->r->access;$a->need('patrol_routes.'.(empty($input['id'])?'create':'update'));$a->need('patrol_route_points.create');$points=$input['points']??[];if(!is_array($points)||count($points)<1||count($points)>100)throw new Problem(422,'ROUTE_POINTS_INVALID','Добавьте от 1 до 100 точек');
        return$this->r->db()->transaction(function()use($input,$points,$a){$new=empty($input['id']);if($new)$route=$this->r->create('patrol_routes',[...$input,'status'=>'DRAFT']);else{$route=$a->find('patrol_routes',$input['id']);if((int)($input['version']??0)!==(int)$route['version'])throw new Problem(409,'VERSION_CONFLICT','Маршрут уже изменен');$a->need('patrol_route_points.delete');$this->r->db()->run('SELECT id FROM cp_patrol_routes WHERE id=? FOR UPDATE',[$route['id']]);}
            $facility=$a->find('facilities',(string)($input['facility_id']??$route['facility_id']));$seen=[];$old=$this->r->db()->all('SELECT id FROM cp_patrol_route_points WHERE route_id=? AND deleted_at IS NULL',[$route['id']]);foreach($points as$i=>$p){$qr=$a->find('qr_points',(string)($p['qr_point_id']??''));if($qr['facility_id']!==$facility['id']||$qr['status']!=='ACTIVE'||isset($seen[$qr['id']]))throw new Problem(422,'POINT_INVALID','Выберите различные действующие QR-точки выбранного объекта');$seen[$qr['id']]=true;$this->r->create('patrol_route_points',['name'=>$p['name']??$qr['name'],'route_id'=>$route['id'],'qr_point_id'=>$qr['id'],'position'=>$i+1,'lat'=>$p['lat']??$facility['lat'],'lng'=>$p['lng']??$facility['lng'],'radius'=>$p['radius']??$facility['radius'],'offset_minutes'=>$p['offset_minutes']??0]);}
            foreach($old as$p)$this->r->write('patrol_route_points',$p['id'],['deleted_at'=>Support::now()]);$updated=$this->r->update('patrol_routes',$route['id'],[...$input,'facility_id'=>$facility['id'],'status'=>$input['status']??'ACTIVE','version'=>$route['version']]);$a->audit('patrol.route_saved','patrol_routes',$route['id'],['points'=>count($points),'facility_id'=>$facility['id']]);return$updated;
        });
    }
}
