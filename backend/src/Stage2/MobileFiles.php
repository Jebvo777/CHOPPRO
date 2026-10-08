<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class MobileFiles
{
    public function __construct(public Resources $r){}
    public function upload(string $incident,string $key,array $file):array
    {
        if(($file['error']??4)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||($file['size']??0)>104857600)throw new Problem(422,'UPLOAD_INVALID','Файл не загружен. Проверьте размер файла и ограничения хостинга');
        $tmp=$file['tmp_name'];$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);$extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','audio/mpeg'=>'mp3','audio/mp4'=>'m4a','video/mp4'=>'m4a','audio/x-m4a'=>'m4a','audio/x-wav'=>'wav','audio/wav'=>'wav','audio/ogg'=>'ogg','application/ogg'=>'ogg','audio/webm'=>'webm','video/webm'=>'webm'];if(!isset($extensions[$mime]))throw new Problem(422,'FILE_TYPE','Разрешены PDF, JPEG, PNG и аудиозаписи');
        $settings=(new OperationalSettings($this->r))->get();$audio=!str_starts_with($mime,'image/')&&$mime!=='application/pdf';$max=$audio?$settings['incident_audio_mb']:$settings['incident_image_mb'];if((int)$file['size']>$max*1048576)throw new Problem(422,'FILE_SIZE','Размер файла должен быть не больше '.$max.' МБ');
        $sha=hash_file('sha256',$tmp);$a=$this->r->access;$db=$this->r->db();$target=null;
        try{return$db->transaction(function()use($incident,$key,$file,$tmp,$mime,$extensions,$sha,$a,$db,$settings,$audio,&$target){
            $db->run('SELECT id FROM cp_users WHERE id=? FOR UPDATE',[$a->user['id']]);$old=$db->one('SELECT * FROM cp_mobile_uploads WHERE tenant_id=? AND user_id=? AND event_key=?',[$a->tenant(),$a->user['id'],$key]);if($old){if(!hash_equals($sha,$old['sha256'])||$old['entity_id']!==$incident)throw new Problem(409,'IDEMPOTENCY_CONFLICT','Вложение с этим ключом содержит другие данные');return$db->one('SELECT id,name,mime,scan_status FROM cp_files WHERE id=?',[$old['file_id']]);}
            $filter=$audio?"(mime LIKE 'audio/%' OR mime='application/ogg')":"(mime NOT LIKE 'audio/%' AND mime<>'application/ogg')";$count=(int)$db->scalar("SELECT COUNT(*) FROM cp_files WHERE tenant_id=? AND entity_type='incidents' AND entity_id=? AND deleted_at IS NULL AND ".$filter,[$a->tenant(),$incident]);if($count>=($audio?1:$settings['incident_images']))throw new Problem(422,'ATTACHMENT_LIMIT',$audio?'Можно приложить одну аудиозапись':'Достигнут лимит изображений и документов');
            $scan=(new Documents($this->r))->scan($tmp);if($scan==='INFECTED')throw new Problem(422,'FILE_INFECTED','Вложение заблокировано');
            $head=file_get_contents($tmp,false,null,0,4096);if($mime==='application/pdf'&&(!str_starts_with($head,'%PDF-')||$this->unsafePdf($tmp)))throw new Problem(422,'FILE_UNSAFE','PDF содержит активное содержимое');
            $dir=$this->r->config['storage'].'/uploads/'.$a->tenant();if(!is_dir($dir))mkdir($dir,0700,true);$name=bin2hex(random_bytes(24)).'.'.$extensions[$mime];$target=$dir.'/'.$name;
            if(str_starts_with($mime,'image/')){$info=@getimagesize($tmp);if(!$info||$info[0]*$info[1]>16000000)throw new Problem(422,'IMAGE_INVALID','Некорректное изображение');$image=@imagecreatefromstring(file_get_contents($tmp));if(!$image)throw new Problem(422,'IMAGE_INVALID','Изображение не удалось прочитать');imagedestroy($image);}
            if(!move_uploaded_file($tmp,$target))throw new Problem(500,'UPLOAD_FAILED','Не удалось сохранить вложение');
            chmod($target,0600);$platform=new Platform($db,$this->r->config);$platform->room('storage',$a->tenant(),filesize($target));$id=Support::uuid();$safeMime=in_array($mime,['video/mp4','audio/x-m4a'],true)?'audio/mp4':$mime;
            $db->run('INSERT INTO cp_files(id,tenant_id,entity_type,entity_id,name,file_path,mime,sha256,scan_status,created_at)VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$a->tenant(),'incidents',$incident,mb_substr(basename($file['name']??'Вложение'),0,200),$name,$safeMime,hash_file('sha256',$target),$scan,Support::now()]);$db->run('INSERT INTO cp_mobile_uploads(tenant_id,user_id,event_key,file_id,sha256,entity_id)VALUES(?,?,?,?,?,?)',[$a->tenant(),$a->user['id'],$key,$id,$sha,$incident]);$platform->file($id,$a->tenant(),'files',filesize($target));$a->audit('incident.attachment_added','incidents',$incident,['file_id'=>$id,'scan_status'=>$scan]);return['id'=>$id,'name'=>basename($file['name']??'Вложение'),'mime'=>$safeMime,'scan_status'=>$scan];
        });}catch(\Throwable$e){if($target)@unlink($target);throw$e;}
    }
    private function unsafePdf(string $path):bool
    {
        $f=fopen($path,'rb');$tail='';try{while(!feof($f)){$chunk=fread($f,65536);if($chunk===false)return true;if(preg_match('~/JavaScript|/JS\s|/Launch|/EmbeddedFile~i',$tail.$chunk))return true;$tail=substr($chunk,-64);}return false;}finally{fclose($f);}
    }
    public function review(string $id,array $input):array
    {
        $a=$this->r->access;$a->need('files.review');$db=$this->r->db();$file=$db->one('SELECT * FROM cp_files WHERE id=? AND tenant_id=? AND deleted_at IS NULL',[$id,$a->tenant()]);if(!$file)throw new Problem(404,'NOT_FOUND','Вложение не найдено');$a->find($file['entity_type'],$file['entity_id']);$reason=trim((string)($input['reason']??''));if(mb_strlen($reason)<10||!in_array($input['decision']??'',['CLEAN','INFECTED'],true))throw new Problem(422,'REVIEW_REQUIRED','Укажите решение и основание проверки');if($file['scan_status']==='INFECTED')throw new Problem(409,'FILE_INFECTED','Зараженное вложение не разблокируется');$db->run('UPDATE cp_files SET scan_status=? WHERE id=? AND tenant_id=?',[$input['decision'],$id,$a->tenant()]);$a->audit('attachment.reviewed',$file['entity_type'],$file['entity_id'],['file_id'=>$id,'decision'=>$input['decision'],'reason'=>$reason]);return['ok'=>true];
    }
}
