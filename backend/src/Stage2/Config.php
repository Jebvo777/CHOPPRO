<?php
declare(strict_types=1);
namespace Choppro\Stage2;

final class Config
{
    public static function load(): array
    {
        $storage = defined('CHOPPRO_STORAGE_ROOT') ? CHOPPRO_STORAGE_ROOT : (getenv('CHOPPRO_STORAGE_ROOT') ?: dirname(__DIR__, 3).'/var');
        if (!is_dir($storage)) mkdir($storage, 0700, true);
        $private = defined('CHOPPRO_HOST_ROOT') ? CHOPPRO_HOST_ROOT.'/.private/site.php' : (getenv('CHOPPRO_PRIVATE_CONFIG') ?: '');
        $site = $private && is_file($private) ? require $private : [];
        $keyPath = $storage.'/key';
        if (empty($site['key']) && !is_file($keyPath)) {
            $f = @fopen($keyPath, 'x');
            if ($f) { fwrite($f, bin2hex(random_bytes(32))); fclose($f); chmod($keyPath, 0600); }
        }
        return array_replace_recursive([
            'storage' => $storage,
            'key' => $site['key'] ?? trim((string)@file_get_contents($keyPath)),
            'demo' => getenv('CHOPPRO_DEMO') !== '0',
            'database' => ['host' => getenv('DB_HOST') ?: 'localhost', 'port' => (int)(getenv('DB_PORT') ?: 3306), 'name' => getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'system404_chopro', 'user' => getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'system404_chopro', 'password' => getenv('DB_PASSWORD') ?: ''],
            'scanner' => ['host' => getenv('CLAMAV_HOST') ?: '', 'port' => (int)(getenv('CLAMAV_PORT') ?: 3310)],
            'otp' => ['url' => getenv('OTP_PROVIDER_URL') ?: '', 'token' => getenv('OTP_PROVIDER_TOKEN') ?: ''],
            'base' => defined('CHOPPRO_HOST_BASE') ? CHOPPRO_HOST_BASE : '',
        ], $site);
    }
}
