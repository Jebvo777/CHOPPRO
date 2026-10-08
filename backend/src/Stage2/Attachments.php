<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Attachments
{
    public function __construct(public Resources $r){}
    public function listing(string $kind,string $id):array{$this->r->access->find($kind,$id);return['items'=>$this->r->db()->all('SELECT id,name,mime,scan_status,created_at FROM cp_files WHERE tenant_id=? AND entity_type=? AND entity_id=? AND deleted_at IS NULL ORDER BY created_at DESC',[$this->r->access->tenant(),$kind,$id])];}
    public function upload(string $kind,string $id,array $file):array
    {
        $a=$this->r->access;$a->need($kind.'.update');$a->find($kind,$id,false);if(($file['error']??4)!==0||($file['size']??0)>10485760||!is_uploaded_file($file['tmp_name']??''))throw new Problem(422,'UPLOAD_INVALID','Выберите файл PDF, PNG или JPEG до 10 МБ');$tmp=$file['tmp_name'];$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);if(!in_array($mime,['application/pdf','image/png','image/jpeg'],true))throw new Problem(422,'FILE_TYPE','Разрешены PDF, PNG и JPEG');if($mime==='application/pdf'&&!str_starts_with(file_get_contents($tmp,false,null,0,8),'%PDF-'))throw new Problem(422,'FILE_INVALID','Некорректный PDF');if(str_starts_with($mime,'image/')){$info=@getimagesize($tmp);if(!$info||$info[0]*$info[1]>16000000)throw new Problem(422,'IMAGE_INVALID','Некорректное изображение');}$scan=(new Documents($this->r))->scan($tmp);if($scan==='INFECTED')throw new Problem(422,'FILE_INFECTED','Файл заблокирован');$name=bin2hex(random_bytes(24)).match($mime){'image/png'=>'.png','image/jpeg'=>'.jpg',default=>'.pdf'};$dir=$this->r->config['storage'].'/uploads/'.$a->tenant();if(!is_dir($dir))mkdir($dir,0700,true);$target=$dir.'/'.$name;if(!move_uploaded_file($tmp,$target))throw new Problem(500,'UPLOAD_FAILED','Не удалось сохранить файл');chmod($target,0600);$fileId=Support::uuid();try{return $this->r->db()->transaction(function()use($fileId,$a,$kind,$id,$file,$name,$mime,$target,$scan){$platform=new Platform($this->r->db(),$this->r->config);$platform->room('storage',$a->tenant(),(int)filesize($target));$this->r->db()->run('INSERT INTO cp_files(id,tenant_id,entity_type,entity_id,name,file_path,mime,sha256,scan_status,created_at)VALUES(?,?,?,?,?,?,?,?,?,?)',[$fileId,$a->tenant(),$kind,$id,mb_substr(basename($file['name']??'Документ'),0,200),$name,$mime,hash_file('sha256',$target),$scan,Support::now()]);$a->audit('attachment.uploaded',$kind,$id,['file_id'=>$fileId,'scan_status'=>$scan]);$platform->file($fileId,$a->tenant(),$kind,(int)filesize($target));return['id'=>$fileId,'scan_status'=>$scan];});}catch(\Throwable$e){@unlink($target);throw$e;}
    }
    private function authorized(string $id):array
    {
        $a=$this->r->access;$file=$this->r->db()->one('SELECT * FROM cp_files WHERE id=? AND tenant_id=? AND deleted_at IS NULL',[$id,$a->tenant()]);if(!$file)throw new Problem(404,'NOT_FOUND','Файл недоступен');$a->need($file['entity_type'].'.download');$a->find($file['entity_type'],$file['entity_id']);if($file['scan_status']!=='CLEAN')throw new Problem(409,'FILE_QUARANTINED','Файл находится в карантине');return$file;
    }
    private function signature(string $id,int $expires):string{return hash_hmac('sha256',$id.'|'.$expires.'|'.$this->r->access->user['id'].'|'.($this->r->access->user['session_id']??''),$this->r->config['key']);}
    public function link(string $id):array
    {
        $file=$this->authorized($id);$expires=time()+300;return['path'=>'/v1/files/'.$id.'/download','query'=>['expires'=>$expires,'ticket'=>$this->signature($id,$expires)],'expires_at'=>gmdate('c',$expires),'name'=>$file['name'],'mime'=>$file['mime']];
    }
    public function download(string $id,array $query=[]):never
    {
        if(isset($query['ticket'])){$expires=(int)($query['expires']??0);if($expires<time()||$expires>time()+300||!hash_equals($this->signature($id,$expires),(string)$query['ticket']))throw new Problem(403,'DOWNLOAD_EXPIRED','Ссылка истекла. Получите новую ссылку.');}
        $a=$this->r->access;$file=$this->r->db()->one('SELECT * FROM cp_files WHERE id=? AND tenant_id=? AND deleted_at IS NULL',[$id,$a->tenant()]);if(!$file)throw new Problem(404,'NOT_FOUND','Файл недоступен');$a->need($file['entity_type'].'.download');$a->find($file['entity_type'],$file['entity_id']);if($a->user['role']==='platform_admin'&&!$a->canDownloadDocuments())throw new Problem(403,'SUPPORT_ACCESS_REQUIRED','Откройте временный доступ поддержки');if($file['scan_status']!=='CLEAN')throw new Problem(409,'FILE_QUARANTINED','Файл находится в карантине');$path=$this->r->config['storage'].'/uploads/'.$file['tenant_id'].'/'.basename($file['file_path']);if(!is_file($path)||!hash_equals($file['sha256'],hash_file('sha256',$path)))throw new Problem(404,'FILE_NOT_FOUND','Файл недоступен');$a->audit('attachment.downloaded',$file['entity_type'],$file['entity_id'],['file_id'=>$id]);header('Content-Type: '.$file['mime']);header('Content-Disposition: attachment; filename="attachment-'.substr($id,0,8).'.'.pathinfo($path,PATHINFO_EXTENSION).'"');readfile($path);exit;
    }
}
