<?php
declare(strict_types=1);
namespace Choppro\Stage2;

final class Support
{
    public static function uuid(): string { $v = random_bytes(16); $v[6] = chr((ord($v[6]) & 15) | 64); $v[8] = chr((ord($v[8]) & 63) | 128); $h = bin2hex($v); return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20); }
    public static function now(): string { return gmdate('Y-m-d H:i:s'); }
    public static function json(mixed $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
    public static function decode(?string $v, mixed $default = []): mixed { return $v ? json_decode($v, true, 512, JSON_THROW_ON_ERROR) : $default; }
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float { $a = sin(deg2rad($lat2-$lat1)/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin(deg2rad($lng2-$lng1)/2)**2; return 6371000*2*asin(min(1,sqrt($a))); }
    public static function encrypt(string $value, string $key): string { $iv = random_bytes(12); $cipher = openssl_encrypt($value,'aes-256-gcm',hash('sha256',$key,true),OPENSSL_RAW_DATA,$iv,$tag); return base64_encode($iv.$tag.$cipher); }
    public static function decrypt(string $value, string $key): string { $raw=base64_decode($value,true); if ($raw===false || strlen($raw)<28) throw new \RuntimeException('Некорректный ключ'); $v=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',$key,true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16)); if($v===false) throw new \RuntimeException('Не удалось прочитать ключ'); return $v; }
    public static function atomic(string $path, string $data): void { $tmp=$path.'.'.bin2hex(random_bytes(6)).'.tmp'; if(file_put_contents($tmp,$data,LOCK_EX)===false || !rename($tmp,$path)) { @unlink($tmp); throw new \RuntimeException('Не удалось сохранить состояние'); } }
    public static function phone(string $v): string { $v=preg_replace('/\D/','',$v); if(strlen($v)===11 && $v[0]==='8') $v='7'.substr($v,1); return '+'.$v; }
}
