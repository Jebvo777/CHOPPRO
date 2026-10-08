<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class MobileUpgrade
{
    public function __construct(public Db $db,public array $config){}
    public function apply():void
    {
        $old=['dashboard.read','employees.read','documents.read','facilities.read','posts.read','instructions.read','instructions.acknowledge','shifts.read','assignments.read','assignments.confirm','attendance.read','attendance.create','notifications.read'];
        foreach($this->db->all("SELECT id,permissions FROM cp_roles WHERE code='guard' AND deleted_at IS NULL")as$role)if(Support::decode($role['permissions'])===$old)$this->db->run('UPDATE cp_roles SET permissions=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?',[Support::json(Access::ROLES['guard']),$role['id']]);
        $oldOps=['dashboard.read','employees.read','facilities.*','posts.*','instructions.*','qr_points.*','shift_templates.*','shifts.*','assignments.*','attendance.*','vacancies.*','applications.*','patrols.read','incidents.read','reports.read','notifications.read','audit.read'];
        foreach($this->db->all("SELECT id,permissions FROM cp_roles WHERE code='operations' AND deleted_at IS NULL")as$role)if(Support::decode($role['permissions'])===$oldOps)$this->db->run('UPDATE cp_roles SET permissions=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?',[Support::json(Access::ROLES['operations']),$role['id']]);
        if(!$this->config['demo'])return;
        $seed=new Seeder($this->db,$this->config);
        foreach($this->db->all("SELECT t.id FROM cp_tenants t WHERE t.deleted_at IS NULL AND EXISTS(SELECT 1 FROM cp_users u WHERE u.tenant_id=t.id AND u.is_demo=1)")as$t){
            if(!$this->db->scalar('SELECT id FROM cp_mobile_policies WHERE tenant_id=?',[$t['id']]))$seed->add('mobile_policies','mobile-policy-'.$t['id'],['tenant_id'=>$t['id'],'name'=>'Порядок работы сотрудника','basis'=>'Демонстрационный регламент организации','text'=>'Координаты фиксируются только при отметке начала и завершения смены, а также контрольной точки. Приложение не отслеживает перемещения непрерывно. Рабочие действия сохраняются на устройстве до подтверждения сервером. Отметки с отклонением проверяет руководитель; сотрудник вправе приложить объяснение. Фото не используются для распознавания лиц. Уведомления и внешние сервисы не подключены. Серверные данные хранятся три года по правилам категории. Этот демонстрационный текст заменяется утвержденным регламентом организации перед рабочим использованием.','revision'=>1,'status'=>'PUBLISHED']);
            foreach($this->db->all('SELECT * FROM cp_facilities WHERE tenant_id=? AND deleted_at IS NULL',[$t['id']])as$f){$rid=Seeder::id('mobile-route-'.$f['id']);if($this->db->scalar('SELECT id FROM cp_patrol_routes WHERE id=?',[$rid]))continue;$qr=$this->db->all("SELECT * FROM cp_qr_points WHERE tenant_id=? AND facility_id=? AND status='ACTIVE' AND deleted_at IS NULL ORDER BY created_at,id LIMIT 3",[$t['id'],$f['id']]);if(!$qr)continue;$seed->add('patrol_routes','mobile-route-'.$f['id'],['tenant_id'=>$t['id'],'name'=>'Проверка постов · '.$f['name'],'facility_id'=>$f['id'],'status'=>'ACTIVE','ordered'=>1,'window_minutes'=>120,'tolerance_minutes'=>15]);foreach($qr as$index=>$point)$seed->add('patrol_route_points','mobile-route-point-'.$point['id'],['tenant_id'=>$t['id'],'name'=>$point['name'],'route_id'=>$rid,'qr_point_id'=>$point['id'],'position'=>$index+1,'lat'=>$f['lat'],'lng'=>$f['lng'],'radius'=>$f['radius'],'offset_minutes'=>0]);}
        }
    }
}
