<?php
declare(strict_types=1);

final class HostingStorage
{
    private function __construct(public readonly string $directory, public readonly string $mode)
    {
    }

    public static function locate(string $siteRoot): self
    {
        $default = $siteRoot.'/.private';
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $webRoot = ($documentRoot !== '' ? @realpath($documentRoot) : false) ?: $siteRoot;
        $configured = getenv('CHOPPRO_STATE_DIR');
        if ($configured !== false && $configured !== '') {
            $parent = @realpath(dirname($configured));
            $name = basename($configured);
            if (!str_starts_with($configured, '/') || $parent === false || in_array($name, ['', '.', '..'], true)) {
                return new self($default, 'unavailable');
            }
            $directory = rtrim($parent, '/').'/'.$name;
            if ((self::inside($directory, $webRoot) || self::inside($directory, $siteRoot)) && !self::inside($directory, $default)) {
                return new self($default, 'unavailable');
            }
            return new self($directory, self::prepare($directory, false) ? 'configured' : 'unavailable');
        }

        $locations = [$default => 'site'];
        $sessionPath = explode(';', session_save_path());
        $parents = [
            [$documentRoot !== '' ? dirname($webRoot) : '', 'external'],
            [end($sessionPath), 'temporary'],
            [sys_get_temp_dir(), 'temporary'],
            [(string)ini_get('upload_tmp_dir'), 'temporary'],
        ];
        $name = '.choppro-'.hash('sha256', $siteRoot);
        foreach ($parents as [$parent, $mode]) {
            $path = self::outsideDirectory($parent, $webRoot, $siteRoot);
            if ($path !== null) $locations[$path.'/'.$name] ??= $mode;
        }

        // Keep using initialized storage if permissions elsewhere change later.
        foreach ($locations as $directory => $mode) {
            if (@is_file($directory.'/runtime/schema.json')) {
                return new self($directory, self::prepare($directory, $mode !== 'site') ? $mode : 'unavailable');
            }
        }
        foreach ($locations as $directory => $mode) {
            if (self::prepare($directory, $mode !== 'site')) return new self($directory, $mode);
        }
        return new self($default, 'unavailable');
    }

    private static function outsideDirectory(string $parent, string $webRoot, string $siteRoot): ?string
    {
        if ($parent === '' || !str_starts_with($parent, '/')) return null;
        $path = @realpath($parent);
        if ($path === false || $path === '/' || !@is_dir($path) || self::inside($path, $webRoot) || self::inside($path, $siteRoot)) {
            return null;
        }
        $permissions = @fileperms($path);
        if ($permissions === false || (($permissions & 0022) !== 0 && ($permissions & 01000) === 0)) {
            return null;
        }
        return rtrim($path, '/');
    }

    private static function inside(string $path, string $directory): bool
    {
        return $path === $directory || str_starts_with($path, rtrim($directory, '/').'/');
    }

    private static function prepare(string $directory, bool $isolated): bool
    {
        if (@is_link($directory)) return false;
        if (!@is_dir($directory) && !@mkdir($directory, 0700) && !@is_dir($directory)) return false;
        if (!@is_writable($directory)) return false;
        if ($isolated) {
            $permissions = @fileperms($directory);
            if ($permissions === false || ($permissions & 0077) !== 0) return false;
            if (function_exists('posix_geteuid') && @fileowner($directory) !== posix_geteuid()) return false;
        }

        $runtime = $directory.'/runtime';
        if (@is_link($runtime)) return false;
        if (!@is_dir($runtime) && !@mkdir($runtime, 0700) && !@is_dir($runtime)) return false;
        return @is_writable($runtime);
    }
}
