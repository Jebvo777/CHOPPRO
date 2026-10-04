<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Totp
{
    public static function secret(): string { $bits=''; foreach(str_split(random_bytes(20)) as $b) $bits.=str_pad(decbin(ord($b)),8,'0',STR_PAD_LEFT); $out=''; foreach(str_split($bits,5) as $b) $out.='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'[bindec($b)]; return $out; }
    public static function code(string $secret, ?int $time=null, int $digits=6): string { $bits=''; foreach(str_split(strtoupper($secret)) as $c) { $n=strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',$c); if($n===false) continue; $bits.=str_pad(decbin($n),5,'0',STR_PAD_LEFT); } $raw=''; foreach(str_split($bits,8) as $b) if(strlen($b)===8) $raw.=chr(bindec($b)); $step=(int)floor(($time??time())/30); $hash=hash_hmac('sha1',pack('N2',0,$step),$raw,true); $offset=ord($hash[19])&15; $n=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff; return str_pad((string)($n%(10**$digits)),$digits,'0',STR_PAD_LEFT); }
    public static function matchedStep(string $secret,string $code): ?int { if(!preg_match('/^\d{6}$/',$code)) return null; for($i=-1;$i<=1;$i++) if(hash_equals(self::code($secret,time()+$i*30),$code)) return (int)floor((time()+$i*30)/30); return null; }
    public static function verify(string $secret,string $code): bool { return self::matchedStep($secret,$code)!==null; }
}
