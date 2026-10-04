<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Presence
{
    public function __construct(public Db $db){}
    public function assignment(string $id):void
    {
        $assignment=$this->db->one('SELECT * FROM cp_assignments WHERE id=?',[$id]);if(!$assignment)return;
        $events=$this->db->all("SELECT e.* FROM cp_attendance e WHERE e.assignment_id=? AND (e.status='VALID' OR (e.status='REQUIRES_REVIEW' AND EXISTS(SELECT 1 FROM cp_attendance m WHERE m.source_id=e.id AND m.status='MANUAL_OVERRIDE'))) ORDER BY e.client_time,CASE e.event_type WHEN 'CHECK_IN' THEN 0 WHEN 'CHECK_OUT' THEN 1 ELSE 2 END,e.created_at,e.id",[$id]);$in=null;$out=null;foreach($events as$e){if($e['event_type']==='CHECK_IN'&&!$in)$in=$e['client_time'];if($e['event_type']==='CHECK_OUT'&&$in&&$e['client_time']>=$in)$out=$e['client_time'];}
        if($in){$minutes=$out?max(0,(int)round((strtotime($out)-strtotime($in))/60)):null;$this->db->run('INSERT INTO cp_presence(assignment_id,tenant_id,started_at,finished_at,worked_minutes,status,updated_at)VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE started_at=VALUES(started_at),finished_at=VALUES(finished_at),worked_minutes=VALUES(worked_minutes),status=VALUES(status),updated_at=VALUES(updated_at)',[$id,$assignment['tenant_id'],$in,$out,$minutes,$out?'COMPLETED':'IN_PROGRESS',Support::now()]);$this->db->run("UPDATE cp_no_shows SET status='RESOLVED',updated_at=UTC_TIMESTAMP() WHERE assignment_id=? AND status='ACTIVE'",[$id]);}
        $this->shift($assignment['shift_id']);
    }
    public function shift(string $id):void
    {
        $s=$this->db->one('SELECT s.*,p.headcount FROM cp_shifts s JOIN cp_posts p ON p.id=s.post_id WHERE s.id=?',[$id]);if(!$s||!(int)$s['published']||$s['status']==='CANCELLED')return;
        $n=(int)$this->db->scalar("SELECT COUNT(*) FROM cp_assignments WHERE shift_id=? AND deleted_at IS NULL AND status IN ('ASSIGNED','CONFIRMED')",[$id]);
        $active=(int)$this->db->scalar("SELECT COUNT(*) FROM cp_presence x JOIN cp_assignments a ON a.id=x.assignment_id WHERE a.shift_id=? AND a.status IN ('ASSIGNED','CONFIRMED') AND x.status='IN_PROGRESS'",[$id]);$done=(int)$this->db->scalar("SELECT COUNT(*) FROM cp_presence x JOIN cp_assignments a ON a.id=x.assignment_id WHERE a.shift_id=? AND a.status IN ('ASSIGNED','CONFIRMED') AND x.status='COMPLETED'",[$id]);
        $status=$active?'IN_PROGRESS':(($done>0&&($done>=$n||strtotime($s['ends_at'])<=time()))?'COMPLETED':($n<(int)$s['headcount']?'UNFILLED':'PUBLISHED'));
        if($s['status']!==$status){$this->db->run('UPDATE cp_shifts SET status=?,updated_at=UTC_TIMESTAMP(),version=version+1 WHERE id=?',[$status,$id]);(new Access($this->db,['id'=>null,'tenant_id'=>$s['tenant_id'],'role'=>'tenant_admin','scopes'=>'[]']))->audit('shift.presence_changed','shifts',$id,['old'=>$s['status'],'new'=>$status]);}
    }
    public function rebuild():void
    {
        $rows=$this->db->all("SELECT DISTINCT assignment_id FROM cp_attendance WHERE event_type IN ('CHECK_IN','CHECK_OUT') AND server_time>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 14 DAY)");
        foreach(array_chunk($rows,400)as$batch)$this->db->transaction(function()use($batch){foreach($batch as$row)$this->assignment($row['assignment_id']);});
    }
}
