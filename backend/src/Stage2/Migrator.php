<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Migrator
{
    public function __construct(public Db $db,public array $config) {}
    public function apply(bool $seed=true): array
    {
        $lock='choppro:migrations:'.$this->config['database']['name'];
        if((int)$this->db->scalar('SELECT GET_LOCK(?,10)',[$lock])!==1)throw new Problem(409,'MIGRATION_BUSY','База уже обновляется. Повторите позже.');
        $changes=[];
        try{
            $this->db->pdo->exec('CREATE TABLE IF NOT EXISTS cp_schema_migrations (version INT PRIMARY KEY,checksum CHAR(64) NOT NULL,applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $files=glob(dirname(__DIR__,2).'/database/stage2/[0-9][0-9][0-9]_*.sql');sort($files,SORT_STRING);
            foreach($files as$file){$number=(int)basename($file);$sql=file_get_contents($file);$checksum=hash('sha256',$sql);$old=$this->db->one('SELECT checksum FROM cp_schema_migrations WHERE version=?',[$number]);
                if($old){if(!hash_equals($old['checksum'],$checksum))throw new Problem(409,'MIGRATION_CHANGED','Применённая миграция '.$number.' изменилась. Требуется отдельная новая миграция.');continue;}
                foreach(self::statements($sql)as$statement){
                    if(preg_match('/^CREATE INDEX ([a-zA-Z0-9_]+) ON ([a-zA-Z0-9_]+)/i',$statement,$index)&&$this->db->scalar('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?',[$this->config['database']['name'],$index[2],$index[1]]))continue;
                    $this->db->pdo->exec($statement);
                }
                $this->db->run('INSERT INTO cp_schema_migrations(version,checksum,applied_at) VALUES(?,?,?)',[$number,$checksum,Support::now()]);$changes[]=basename($file);
            }
            $seeded=false;
            if($seed && !$this->db->one('SELECT id FROM cp_installation WHERE id=1')){
                if((int)$this->db->scalar('SELECT COUNT(*) FROM cp_tenants')>0)throw new Problem(409,'EXISTING_DATA','В системе уже есть данные. Автоматическое заполнение остановлено; миграции применены.');
                $fk=(int)$this->db->scalar('SELECT @@FOREIGN_KEY_CHECKS');$this->db->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                try{$this->db->transaction(function(){(new Seeder($this->db,$this->config))->fill();$this->db->run('INSERT INTO cp_installation(id,seed_version,seeded_at) VALUES(1,1,?)',[Support::now()]);});$seeded=true;}finally{$this->db->pdo->exec('SET FOREIGN_KEY_CHECKS='.$fk);}
            }
            (new RuntimeUpgrade($this->db,$this->config))->apply();
            (new DemoData($this->db,$this->config))->upgrade();
            (new MobileUpgrade($this->db,$this->config))->apply();
            $version=(int)$this->db->scalar('SELECT COALESCE(MAX(version),0) FROM cp_schema_migrations');
            Support::atomic($this->config['storage'].'/schema.json',Support::json(['version'=>$version,'updated_at'=>Support::now()]));
            return ['version'=>$version,'migrations'=>$changes,'seeded'=>$seeded,'tenants'=>(int)$this->db->scalar('SELECT COUNT(*) FROM cp_tenants'),'employees'=>(int)$this->db->scalar('SELECT COUNT(*) FROM cp_employees'),'shifts'=>(int)$this->db->scalar('SELECT COUNT(*) FROM cp_shifts')];
        }finally{$this->db->run('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
    public static function statements(string $sql): array
    {
        $out=[];$part='';$quote=null;$escaped=false;
        foreach(str_split($sql)as$c){if($escaped){$part.=$c;$escaped=false;continue;}if($quote!==null){$part.=$c;if($c==='\\')$escaped=true;elseif($c===$quote)$quote=null;continue;}if(in_array($c,["'",'"','`'],true)){$quote=$c;$part.=$c;}elseif($c===';'){if(trim($part)!=='')$out[]=trim($part);$part='';}else$part.=$c;}
        if(trim($part)!=='')$out[]=trim($part);return $out;
    }
}
