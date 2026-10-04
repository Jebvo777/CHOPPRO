<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class AuditIndex
{
    public function __construct(public Db $db){}
    public function record(int $auditId,?string $tenant,string $kind,?string $id,array $metadata=[]):void
    {
        if(!$tenant||!$id)return;$ids=[];
        if($kind==='facilities')$ids=[$id];
        elseif(in_array($kind,['employees','posts','qr_points','vacancies','incidents','patrols','reports'],true))$ids=array_column($this->db->all('SELECT facility_id FROM cp_'.$kind.' WHERE tenant_id=? AND id=?',[$tenant,$id]),'facility_id');
        elseif($kind==='contracts')$ids=array_column($this->db->all('SELECT id facility_id FROM cp_facilities WHERE tenant_id=? AND contract_id=?',[$tenant,$id]),'facility_id');
        elseif(in_array($kind,['shifts','shift_templates','instructions'],true))$ids=array_column($this->db->all('SELECT p.facility_id FROM cp_'.$kind.' t JOIN cp_posts p ON p.id=t.post_id WHERE t.tenant_id=? AND t.id=?',[$tenant,$id]),'facility_id');
        elseif($kind==='assignments')$ids=array_column($this->db->all('SELECT p.facility_id FROM cp_assignments a JOIN cp_shifts s ON s.id=a.shift_id JOIN cp_posts p ON p.id=s.post_id WHERE a.tenant_id=? AND a.id=?',[$tenant,$id]),'facility_id');
        elseif($kind==='attendance')$ids=array_column($this->db->all('SELECT p.facility_id FROM cp_attendance e JOIN cp_assignments a ON a.id=e.assignment_id JOIN cp_shifts s ON s.id=a.shift_id JOIN cp_posts p ON p.id=s.post_id WHERE e.tenant_id=? AND e.id=?',[$tenant,$id]),'facility_id');
        elseif($kind==='documents')$ids=array_column($this->db->all('SELECT e.facility_id FROM cp_documents d JOIN cp_employees e ON e.id=d.employee_id WHERE d.tenant_id=? AND d.id=?',[$tenant,$id]),'facility_id');
        foreach(array_unique(array_filter($ids))as$facility)$this->db->run('INSERT IGNORE INTO cp_audit_facilities(audit_id,facility_id) VALUES(?,?)',[$auditId,$facility]);
    }
    public function rebuild():int
    {
        $total=0;$after=0;do{$rows=$this->db->all('SELECT id,tenant_id,entity_type,entity_id,metadata FROM cp_audit WHERE id>? ORDER BY id LIMIT 500',[$after]);foreach($rows as$row){$this->record((int)$row['id'],$row['tenant_id'],$row['entity_type'],$row['entity_id'],Support::decode($row['metadata']));$after=(int)$row['id'];$total++;}}while(count($rows)===500);return$total;
    }
}
