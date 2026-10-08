<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class FilePreview
{
    public static function work(Db $db,array $config):int
    {
        $count=0;foreach($db->all("SELECT id,tenant_id,file_path,sha256 FROM cp_files WHERE mime IN ('image/png','image/jpeg') AND deleted_at IS NULL AND scan_status<>'INFECTED' ORDER BY created_at DESC LIMIT 200")as$file){$original=$config['storage'].'/uploads/'.$file['tenant_id'].'/'.basename($file['file_path']);$preview=$original.'.preview.jpg';if(is_file($preview)||!is_file($original)||!hash_equals($file['sha256'],hash_file('sha256',$original)))continue;$info=@getimagesize($original);if(!$info||$info[0]*$info[1]>16000000)continue;$image=@imagecreatefromstring(file_get_contents($original));if(!$image)continue;$ratio=min(1,1280/max($info[0],$info[1]));$scaled=imagescale($image,max(1,(int)($info[0]*$ratio)),max(1,(int)($info[1]*$ratio)));if($scaled&&imagejpeg($scaled,$preview.'.tmp',85)){chmod($preview.'.tmp',0600);rename($preview.'.tmp',$preview);(new Platform($db,$config))->file($file['id'],$file['tenant_id'],'files',filesize($original)+filesize($preview));$count++;}if($scaled)imagedestroy($scaled);imagedestroy($image);if($count>=10)break;}return$count;
    }
    public static function serve(Resources $r,string $id):never
    {
        $a=$r->access;$file=$r->db()->one("SELECT * FROM cp_files WHERE id=? AND tenant_id=? AND deleted_at IS NULL AND mime IN ('image/png','image/jpeg') AND scan_status<>'INFECTED'",[$id,$a->tenant()]);if(!$file)throw new Problem(404,'NOT_FOUND','Предпросмотр недоступен');$a->need($file['entity_type'].'.read');$a->find($file['entity_type'],$file['entity_id']);if($a->user['role']==='customer'&&(!(int)$file['published']||$file['scan_status']!=='CLEAN'))throw new Problem(404,'NOT_FOUND','Предпросмотр недоступен');$path=$r->config['storage'].'/uploads/'.$a->tenant().'/'.basename($file['file_path']).'.preview.jpg';if(!is_file($path))throw new Problem(409,'PREVIEW_PENDING','Предпросмотр готовится. Повторите позже');header('Content-Type: image/jpeg');header('Content-Disposition: inline; filename="preview.jpg"');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($path);exit;
    }
}
