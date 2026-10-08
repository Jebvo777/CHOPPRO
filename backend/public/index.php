<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Core/Autoloader.php';
use Choppro\Stage2\{Auth,Config,Db,Kernel,Problem,Support};
$GLOBALS['correlation_id']=Support::uuid();
header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');header('X-Correlation-Id: '.$GLOBALS['correlation_id']);
try{
    $config=Config::load();if(is_file($config['storage'].'/restore.json'))(new \Choppro\Stage2\Backup(new Db($config),$config))->recover();
    $GLOBALS['maintenance_handle']=fopen($config['storage'].'/maintenance.lock','c');if(!flock($GLOBALS['maintenance_handle'],LOCK_SH|LOCK_NB))throw new Problem(503,'MAINTENANCE','Обслуживание системы. Повторите позже.');
    register_shutdown_function(static function(){if(isset($GLOBALS['maintenance_handle'])&&is_resource($GLOBALS['maintenance_handle'])){flock($GLOBALS['maintenance_handle'],LOCK_UN);fclose($GLOBALS['maintenance_handle']);}});Auth::start($config);
    $path=$_GET['path']??parse_url($_SERVER['REQUEST_URI']??'/v1/health',PHP_URL_PATH);$path=preg_replace('~^.*?/api(?=/v1)~','',$path);
    if($path==='/v1/csrf')$output=['csrf'=>$_SESSION['csrf']];
    else{
        $input=$_POST;
        if(str_contains($_SERVER['CONTENT_TYPE']??'','application/json')){
            $raw=file_get_contents('php://input',false,null,0,2097153);if(strlen($raw)>2097152)throw new Problem(413,'BODY_TOO_LARGE','Слишком большой запрос');
            try{$input=Support::decode($raw?:'{}');}catch(Throwable){throw new Problem(422,'JSON_INVALID','Некорректный JSON');}
        }
        $output=(new Kernel(new Db($config),$config))->dispatch($_SERVER['REQUEST_METHOD']??'GET',$path,$input,$_GET,$_FILES);
    }
    echo Support::json($output);
    register_shutdown_function(static function()use($config){if(session_status()===PHP_SESSION_ACTIVE)session_write_close();if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();try{(new \Choppro\Stage2\Background($config))->run();}catch(Throwable $e){error_log('CHOPPRO background processing: '.$e->getMessage());}});
}catch(Problem $e){http_response_code($e->status);echo Support::json(['code'=>$e->codeName,'message'=>$e->getMessage(),'details'=>$e->details,'correlation_id'=>$GLOBALS['correlation_id']]);}
catch(PDOException $e){$number=(int)($e->errorInfo[1]??0);$setup=in_array($number,[1045,1049,1146,2002],true);http_response_code($setup?503:($number===1062?409:500));error_log('CHOPPRO '.$GLOBALS['correlation_id'].' '.$e->getMessage());echo Support::json(['code'=>$setup?'DATABASE_SETUP_REQUIRED':($number===1062?'DUPLICATE':'DATABASE_ERROR'),'message'=>$setup?'Актуализируйте БД на портале':($number===1062?'Такая запись уже существует':'Ошибка операции. Код обращения: '.$GLOBALS['correlation_id'])]);}
catch(Throwable $e){http_response_code(500);error_log('CHOPPRO '.$GLOBALS['correlation_id'].' '.$e->getMessage());echo Support::json(['code'=>'INTERNAL_ERROR','message'=>'Ошибка операции. Код обращения: '.$GLOBALS['correlation_id']]);}
