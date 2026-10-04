<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Documents
{
    public function __construct(public Resources $r) {}
    public function scan(string $path): string
    {
        $bytes=file_get_contents($path);
        if(str_contains($bytes,'EICAR-STANDARD-ANTIVIRUS-TEST-FILE'))return 'INFECTED';
        $scanner=$this->r->config['scanner'];if(!$scanner['host'])return 'PENDING_SCAN';
        $s=@fsockopen($scanner['host'],(int)$scanner['port'],$errno,$errstr,5);
        if(!$s)return 'PENDING_SCAN';stream_set_timeout($s,15);fwrite($s,"zINSTREAM\0");
        foreach(str_split($bytes,65536)as$chunk){$packet=pack('N',strlen($chunk)).$chunk;$offset=0;while($offset<strlen($packet)){$n=fwrite($s,substr($packet,$offset));if(!$n){fclose($s);return 'PENDING_SCAN';}$offset+=$n;}}
        fwrite($s,pack('N',0));$answer='';while(!feof($s)&&strlen($answer)<4096){$v=fread($s,1024);if($v===false||$v==='')break;$answer.=$v;if(str_contains($answer,"\0"))break;}fclose($s);
        return str_contains($answer,'FOUND')?'INFECTED':(str_contains($answer,'OK')?'CLEAN':'PENDING_SCAN');
    }
    public function upload(array $input,array $file): array
    {
        $a=$this->r->access;$a->need('documents.create');
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>10*1024*1024)throw new Problem(422,'UPLOAD_INVALID','Выберите файл не больше 10 МБ');
        $path=$file['tmp_name'];if(!is_uploaded_file($path))throw new Problem(422,'UPLOAD_INVALID','Файл не получен');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,['image/png','image/jpeg','application/pdf'],true))throw new Problem(422,'FILE_TYPE','Разрешены PDF, JPEG и PNG');
        if($mime==='application/pdf'&&!str_starts_with(file_get_contents($path,false,null,0,8),'%PDF-'))throw new Problem(422,'FILE_INVALID','Некорректный PDF');
        if(str_starts_with($mime,'image/')){$info=@getimagesize($path);if(!$info||$info[0]*$info[1]>16000000)throw new Problem(422,'IMAGE_INVALID','Некорректное или слишком большое изображение');}
        $scan=$this->scan($path);if($scan==='INFECTED')throw new Problem(422,'FILE_INFECTED','Файл заблокирован проверкой безопасности');
        $data=$this->r->normalize('documents',$input);$name=bin2hex(random_bytes(24)).match($mime){'image/png'=>'.png','image/jpeg'=>'.jpg',default=>'.pdf'};
        $dir=$this->r->config['storage'].'/uploads/'.$a->tenant();if(!is_dir($dir))mkdir($dir,0700,true);$target=$dir.'/'.$name;
        if(!move_uploaded_file($path,$target))throw new Problem(500,'UPLOAD_FAILED','Не удалось сохранить файл');chmod($target,0600);
        try{$row=$this->r->insert('documents',[...$data,'status'=>'PENDING_REVIEW','scan_status'=>$scan,'file_path'=>$name,'mime'=>$mime,'sha256'=>hash_file('sha256',$target)]);$a->audit('document.uploaded','documents',$row['id'],['scan_status'=>$scan,'mime'=>$mime]);return $a->safe('documents',$row);}catch(\Throwable$e){@unlink($target);throw$e;}
    }
    public function review(string $id,array $input): array
    {
        $a=$this->r->access;$a->need('documents.review');$d=$a->find('documents',$id,false);
        $settings=Support::decode($this->r->db()->scalar('SELECT settings FROM cp_tenants WHERE id=?',[$a->tenant()]));$threshold=(int)($settings['expiring_days']??30);$decision=$input['decision']??'';if(!in_array($decision,['VALID','REJECTED'],true))throw new Problem(422,'DECISION_REQUIRED','Выберите решение');
        if($decision==='VALID'){
            if($d['scan_status']!=='CLEAN')throw new Problem(409,'SCAN_REQUIRED','Файл ожидает антивирусной проверки');
            $type=$a->reference('document_types',$d['type_id']);foreach(Support::decode($type['required_fields'])as$f)if(empty($d[$f]))throw new Problem(422,'DOCUMENT_INCOMPLETE','Не заполнено поле: '.(Schema::labels()[$f]??$f));
        }
        if($decision==='REJECTED'&&mb_strlen(trim($input['reason']??''))<5)throw new Problem(422,'REASON_REQUIRED','Укажите причину отклонения');
        return $this->r->db()->transaction(function()use($a,$d,$decision,$input,$threshold){$status=$decision;if($decision==='VALID'){$days=(strtotime($d['expires_at']??'+100 years')-strtotime(gmdate('Y-m-d')))/86400;$status=$days<0?'EXPIRED':($days<=$threshold?'EXPIRING':'VALID');}$history=$this->r->insert('document_reviews',['name'=>'Проверка документа','document_id'=>$d['id'],'decision'=>$decision,'reason'=>$input['reason']??'Проверено','actor_id'=>$a->user['id']]);$this->r->write('documents',$d['id'],['status'=>$status]);$a->audit('document.reviewed','documents',$d['id'],['decision'=>$decision,'review_id'=>$history['id']]);return $a->safe('documents',$a->find('documents',$d['id'],false));});
    }
    public function download(string $id): never
    {
        $a=$this->r->access;$d=$a->find('documents',$id);if(!$a->canDownloadDocuments())throw new Problem(403,'DOWNLOAD_FORBIDDEN','Нет отдельного права на скачивание');
        if($d['scan_status']!=='CLEAN'||$d['status']==='REJECTED')throw new Problem(409,'FILE_QUARANTINED','Файл ещё не разрешён к выдаче');
        $name=basename($d['file_path']??'');$path=$this->r->config['storage'].'/uploads/'.$d['tenant_id'].'/'.$name;
        if(!$name||!is_file($path)||!hash_equals($d['sha256'],hash_file('sha256',$path)))throw new Problem(404,'FILE_NOT_FOUND','Файл недоступен');
        $a->audit('document.downloaded','documents',$id);header('Content-Type: '.$d['mime']);header('Content-Disposition: attachment; filename="document-'.substr($id,0,8).'.'.pathinfo($name,PATHINFO_EXTENSION).'"');header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store');readfile($path);exit;
    }
}
