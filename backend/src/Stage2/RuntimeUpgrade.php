<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class RuntimeUpgrade
{
    public function __construct(public Db $db,public array $config){}
    public function apply():void
    {
        (new Platform($this->db,$this->config))->ensure();$file=$this->config['storage'].'/upgrade-4.json';if(is_file($file))return;
        $platform=new Platform($this->db,$this->config);foreach(['documents','files']as$kind){$rows=$this->db->all('SELECT id,tenant_id,file_path FROM cp_'.$kind.' WHERE deleted_at IS NULL');foreach(array_chunk($rows,400)as$batch)$this->db->transaction(function()use($batch,$platform,$kind){foreach($batch as$row){$path=$this->config['storage'].'/uploads/'.$row['tenant_id'].'/'.basename($row['file_path']??'');if(is_file($path))$platform->file($row['id'],$row['tenant_id'],$kind,filesize($path));}});}
        (new AuditIndex($this->db))->rebuild();(new Presence($this->db))->rebuild();$this->db->run('UPDATE cp_contracts SET legal_hold_until=DATE_ADD(ends_at,INTERVAL 3 YEAR) WHERE ends_at IS NOT NULL');Support::atomic($file,Support::json(['version'=>4,'at'=>Support::now()]));
    }
}
