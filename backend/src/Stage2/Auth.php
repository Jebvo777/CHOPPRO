<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Auth
{
    public function __construct(public Db $db,public array $config) {}
    public static function start(array $config): void
    {
        if(session_status()===PHP_SESSION_ACTIVE)return;
        $dir=$config['storage'].'/sessions';if(!is_dir($dir))mkdir($dir,0700,true);
        session_save_path($dir);$space=$_GET['space']??'admin';if(!in_array($space,['admin','client','platform','jobs','mobile'],true))$space='admin';session_name('choppro_app_'.$space);
        session_set_cookie_params(['lifetime'=>0,'path'=>($config['base']?:'').'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
        if(($_SERVER['HTTP_X_CHOPPRO_CLIENT']??'')==='native'&&empty($_SERVER['HTTP_ORIGIN']))ini_set('session.use_cookies','0');
        ini_set('session.use_strict_mode','1');session_start();
        $_SESSION['csrf']??=bin2hex(random_bytes(32));
    }
    public function limit(string $key,int $maximum=10,int $seconds=300): void
    {
        $key=hash('sha256',$key.'|'.($_SERVER['REMOTE_ADDR']??'cli'));
        $this->db->run('INSERT INTO cp_rate_limits(rate_key,attempts,resets_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(resets_at<UTC_TIMESTAMP(),1,attempts+1),resets_at=IF(resets_at<UTC_TIMESTAMP(),VALUES(resets_at),resets_at)',[$key,gmdate('Y-m-d H:i:s',time()+$seconds)]);
        if((int)$this->db->scalar('SELECT attempts FROM cp_rate_limits WHERE rate_key=?',[$key])>$maximum)throw new Problem(429,'RATE_LIMIT','Слишком много попыток. Повторите позже.');
    }
    public static function bearerToken(): ?string
    {
        $header=trim((string)($_SERVER['HTTP_AUTHORIZATION']??''));if($header==='')$header=trim((string)($_SERVER['REDIRECT_HTTP_AUTHORIZATION']??''));
        if($header===''&&($_SERVER['HTTP_X_CHOPPRO_CLIENT']??'')==='native'&&empty($_SERVER['HTTP_ORIGIN']))$header=trim((string)($_SERVER['HTTP_X_CHOPPRO_AUTHORIZATION']??''));
        return preg_match('~^Bearer[ \t]+([^\s]+)$~iD',$header,$match)?$match[1]:null;
    }
    public function user(): array
    {
        $token=self::bearerToken()??($_SESSION['access_token']??'');
        if(!$token)throw new Problem(401,'AUTH_REQUIRED','Войдите в систему');
        $row=$this->db->one("SELECT u.*,s.id session_id FROM cp_sessions s JOIN cp_users u ON u.id=s.user_id LEFT JOIN cp_tenants t ON t.id=u.tenant_id WHERE s.access_hash=? AND s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP() AND u.status='ACTIVE' AND u.deleted_at IS NULL AND (u.tenant_id IS NULL OR (t.status='ACTIVE' AND t.deleted_at IS NULL))",[hash('sha256',$token)]);
        if(!$row)throw new Problem(401,'SESSION_EXPIRED','Сессия истекла или доступ отозван'); return $row;
    }
    public function csrf(): void
    {
        if(self::bearerToken()!==null)return;
        $path=$_GET['path']??'';
        if(($_SERVER['HTTP_X_CHOPPRO_CLIENT']??'')==='native'&&empty($_SERVER['HTTP_COOKIE'])&&empty($_SERVER['HTTP_ORIGIN'])&&str_contains($_SERVER['CONTENT_TYPE']??'','application/json')&&in_array($path,['/v1/auth/otp/request','/v1/auth/otp/verify','/v1/auth/refresh'],true))return;
        if(!hash_equals($_SESSION['csrf']??'',$_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['csrf']??'')))throw new Problem(403,'CSRF','Обновите страницу и повторите действие');
    }
    public function login(array $input): array
    {
        $login=trim((string)($input['login']??''));$this->limit('login:'.mb_strtolower($login),12);
        $u=$this->db->one("SELECT * FROM cp_users WHERE (email=? OR phone=?) AND status='ACTIVE' AND deleted_at IS NULL",[$login,Support::phone($login)]);
        if(!$u||!password_verify((string)($input['password']??''),$u['password_hash']??''))throw new Problem(401,'INVALID_LOGIN','Неверный логин или пароль');
        if($u['tenant_id'] && !$this->db->one("SELECT id FROM cp_tenants WHERE id=? AND status='ACTIVE' AND deleted_at IS NULL",[$u['tenant_id']]))throw new Problem(403,'TENANT_SUSPENDED','Доступ организации приостановлен');
        $access=new Access($this->db,$u);
        if($u['mfa_enabled'] || $access->allows('users.update') || $access->allows('roles.update') || in_array($u['role'],['tenant_admin','platform_admin'],true)) {
            $secret=$u['mfa_secret']?Support::decrypt($u['mfa_secret'],$this->config['key']):Totp::secret();
            $id=Support::uuid();$setup=!$u['mfa_enabled'];
            $this->db->run('INSERT INTO cp_challenges(id,user_id,channel,expires_at,payload) VALUES(?,?,?,?,?)',[$id,$u['id'],'MFA',gmdate('Y-m-d H:i:s',time()+300),Support::json(['secret'=>Support::encrypt($secret,$this->config['key']),'setup'=>$setup])]);
            $out=['mfa_required'=>true,'challenge_id'=>$id,'setup_required'=>$setup];
            if($setup){$out['secret']=$secret;$out['uri']='otpauth://totp/CHOPPRO:'.rawurlencode($u['email']??$u['phone']).'?secret='.$secret.'&issuer=CHOPPRO';}
            if($this->config['demo']&&(int)$u['is_demo'])$out['demo_code']=Totp::code($secret);
            return $out;
        }
        return $this->issue($u);
    }
    public function verifyMfa(array $input): array
    {
        return $this->db->transaction(function()use($input){
            $c=$this->db->one("SELECT * FROM cp_challenges WHERE id=? AND channel='MFA' AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE",[$input['challenge_id']??'']);
            if(!$c||(int)$c['attempts']>=5)throw new Problem(401,'CHALLENGE_EXPIRED','Код недоступен. Войдите заново.');
            $this->db->run('UPDATE cp_challenges SET attempts=attempts+1 WHERE id=?',[$c['id']]);
            $p=Support::decode($c['payload']);$secret=Support::decrypt($p['secret'],$this->config['key']);
            $step=Totp::matchedStep($secret,(string)($input['code']??''));if($step===null)return ['_error'=>new Problem(401,'INVALID_MFA','Неверный код подтверждения')];
            $u=$this->db->one("SELECT * FROM cp_users WHERE id=? AND status='ACTIVE' AND deleted_at IS NULL FOR UPDATE",[$c['user_id']]);
            if(!$u)throw new Problem(401,'INVALID_LOGIN','Доступ отозван');
            if(!$p['setup'] && (int)($u['mfa_last_step']??0)>=$step)throw new Problem(401,'MFA_REPLAY','Этот код уже использован');
            $this->db->run('UPDATE cp_users SET mfa_secret=?,mfa_enabled=1,mfa_last_step=? WHERE id=?',[$p['secret'],$step,$u['id']]);
            $this->db->run('UPDATE cp_challenges SET used_at=UTC_TIMESTAMP() WHERE id=?',[$c['id']]);return $this->issue($u);
        });
    }
    public function requestOtp(array $input): array
    {
        $phone=Support::phone((string)($input['phone']??''));$this->limit('otp:'.$phone,5,600);
        $u=$this->db->one("SELECT * FROM cp_users WHERE phone=? AND role='guard' AND status='ACTIVE' AND deleted_at IS NULL",[$phone]);
        $id=Support::uuid();$code=(string)random_int(100000,999999);
        if($u){$this->db->run('INSERT INTO cp_challenges(id,user_id,channel,code_hash,expires_at,payload) VALUES(?,?,?,?,?,?)',[$id,$u['id'],'OTP',hash_hmac('sha256',$code,$this->config['key']),gmdate('Y-m-d H:i:s',time()+300),'{}']);
            if(!$this->config['demo']||!(int)$u['is_demo']) {
                if(!$this->config['otp']['url'])throw new Problem(503,'OTP_PROVIDER_UNAVAILABLE','Отправка кода временно недоступна');
                if(!str_starts_with($this->config['otp']['url'],'https://'))throw new Problem(503,'OTP_PROVIDER_UNAVAILABLE','Провайдер не настроен');
                $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nAuthorization: Bearer ".$this->config['otp']['token'],'content'=>Support::json(['phone'=>$phone,'code'=>$code]),'timeout'=>8]]);
                if(@file_get_contents($this->config['otp']['url'],false,$ctx)===false)throw new Problem(503,'OTP_DELIVERY_FAILED','Не удалось отправить код');
            }
        }
        return ['challenge_id'=>$id,'expires_in'=>300]+($u&&$this->config['demo']&&(int)$u['is_demo']?['demo_code'=>$code]:[]);
    }
    public function verifyOtp(array $input): array
    {
        return $this->db->transaction(function()use($input){
            $c=$this->db->one("SELECT * FROM cp_challenges WHERE id=? AND channel='OTP' AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE",[$input['challenge_id']??'']);
            if(!$c||(int)$c['attempts']>=5)throw new Problem(401,'CHALLENGE_EXPIRED','Код недоступен');
            $this->db->run('UPDATE cp_challenges SET attempts=attempts+1 WHERE id=?',[$c['id']]);
            if(!hash_equals($c['code_hash'],hash_hmac('sha256',(string)($input['code']??''),$this->config['key'])))return ['_error'=>new Problem(401,'INVALID_OTP','Неверный код')];
            $u=$this->db->one("SELECT * FROM cp_users WHERE id=? AND status='ACTIVE' AND deleted_at IS NULL",[$c['user_id']]);if(!$u)throw new Problem(401,'INVALID_LOGIN','Доступ отозван');
            $this->db->run('UPDATE cp_challenges SET used_at=UTC_TIMESTAMP() WHERE id=?',[$c['id']]);return $this->issue($u);
        });
    }
    public function issue(array $u): array
    {
        $access=bin2hex(random_bytes(32));$refresh=bin2hex(random_bytes(40));$id=Support::uuid();
        $this->db->run('INSERT INTO cp_sessions(id,user_id,access_hash,refresh_hash,expires_at,refresh_expires_at,created_at,ip) VALUES(?,?,?,?,?,?,?,?)',[$id,$u['id'],hash('sha256',$access),hash('sha256',$refresh),gmdate('Y-m-d H:i:s',time()+900),gmdate('Y-m-d H:i:s',time()+2592000),Support::now(),$_SERVER['REMOTE_ADDR']??'cli']);
        if(session_status()===PHP_SESSION_ACTIVE&&($_SERVER['HTTP_X_CHOPPRO_CLIENT']??'')!=='native'){session_regenerate_id(true);$_SESSION['access_token']=$access;$_SESSION['refresh_token']=$refresh;}
        $a=new Access($this->db,$u);$a->audit('auth.login','users',$u['id']);
        return ['access_token'=>$access,'refresh_token'=>$refresh,'expires_in'=>900,'user'=>$a->safe('users',$u),'csrf'=>$_SESSION['csrf']??''];
    }
    public function refresh(array $input): array
    {
        return $this->db->transaction(function()use($input){$token=$input['refresh_token']??($_SESSION['refresh_token']??'');$s=$this->db->one('SELECT * FROM cp_sessions WHERE refresh_hash=? AND revoked_at IS NULL AND refresh_expires_at>UTC_TIMESTAMP() FOR UPDATE',[hash('sha256',$token)]);if(!$s)throw new Problem(401,'REFRESH_EXPIRED','Войдите заново');$u=$this->db->one("SELECT * FROM cp_users WHERE id=? AND status='ACTIVE' AND deleted_at IS NULL",[$s['user_id']]);if(!$u)throw new Problem(401,'INVALID_LOGIN','Доступ отозван');$this->db->run('UPDATE cp_sessions SET revoked_at=UTC_TIMESTAMP() WHERE id=?',[$s['id']]);return $this->issue($u);});
    }
    public function logout(array $u): array { $this->db->run('UPDATE cp_sessions SET revoked_at=UTC_TIMESTAMP() WHERE id=?',[$u['session_id']]);unset($_SESSION['access_token'],$_SESSION['refresh_token']);return ['ok'=>true]; }
}
