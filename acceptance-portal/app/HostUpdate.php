<?php
declare(strict_types=1);
final class HostUpdate
{
    public static function ensure(bool $force=false):array
    {
        if (!defined('CHOPPRO_HOST_ROOT')) return [];
        $storageError = 'PHP не может записать состояние в служебное хранилище.';
        if (defined('CHOPPRO_STORAGE_MODE') && CHOPPRO_STORAGE_MODE === 'unavailable') {
            return ['status'=>'retry', 'message'=>$storageError];
        }
        global $loader;
        if (!$force) $loader->sync();
        $sha = $loader->state()['portal'] ?? '';
        $statusPath = CHOPPRO_STORAGE_ROOT.'/update.json';
        $old = $loader->json($statusPath);
        if (!$force && ($old['release'] ?? '') === $sha && ($old['status'] ?? '') === 'ready') return $old;
        if (!$force && ($old['retry_after'] ?? 0) > time()) return $old;

        $lock = @fopen($loader->private.'/database-update.lock', 'c');
        if (!$lock) {
            return ['status'=>'retry', 'message'=>$storageError];
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return ['status'=>'updating'];
        }

        try {
            @set_time_limit(240);
            $release = $loader->current('portal');
            require_once $release['path'].'/backend/src/Core/Autoloader.php';
            $config = \Choppro\Stage2\Config::load();
            $db = new \Choppro\Stage2\Db($config);
            $result = (new \Choppro\Stage2\Migrator($db, $config))->apply();
            $loader->promote();
            $out = ['status'=>'ready', 'release'=>$release['sha'], 'at'=>gmdate('c'), 'database'=>$result];
            $loader->atomic($statusPath, json_encode($out, JSON_UNESCAPED_UNICODE));
            return $out;
        } catch (Throwable $e) {
            error_log('CHOPPRO automatic database update: '.$e->getMessage());
            $message = $e instanceof PDOException
                ? 'Не удалось открыть или обновить базу. Проверьте подключение к MySQL, реквизиты и журнал PHP.'
                : 'Обновление базы пока не завершено. Проверьте журнал PHP и повторите обновление.';
            if ($e instanceof \Choppro\Stage2\Problem && in_array($e->codeName, ['STORAGE_UNAVAILABLE', 'DATABASE_CONFIG_UNREADABLE'], true)) {
                $message = $e->getMessage();
            }
            $out = ['status'=>'retry', 'release'=>$sha, 'retry_after'=>time()+60, 'message'=>$message];
            try {
                $loader->atomic($statusPath, json_encode($out, JSON_UNESCAPED_UNICODE));
            } catch (Throwable $statusError) {
                error_log('CHOPPRO database update state: '.$statusError->getMessage());
            }
            return $out;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
