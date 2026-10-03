<?php
declare(strict_types=1);
require_once __DIR__.'/GitHubClient.php';

final class Repository
{
    public array $snapshot;
    public array $state;
    private GitHubClient $client;

    public function __construct(public array $config, ?Closure $transport = null)
    {
        $this->client = new GitHubClient($config, $transport);
        $this->snapshot = $this->readJson($config['cache_dir'].'/current.json')
            ?: $this->readJson($config['bootstrap_dir'].'/index.json');
        $this->state = $this->readJson($config['cache_dir'].'/state.json');
    }

    public static function validPath(string $path): bool
    {
        return $path !== '' && strlen($path) < 1024 && !str_starts_with($path, '/')
            && !str_contains($path, '\\') && !preg_match('/[\x00-\x1f\x7f]/', $path)
            && !in_array('..', explode('/', $path), true) && !in_array('.', explode('/', $path), true);
    }

    public function synchronize(bool $force = false): void
    {
        // Shared PHP hosts often allow 30 seconds; typical updates fetch only changed blobs.
        if (function_exists('set_time_limit')) {
            @set_time_limit(90);
        }
        $now = time();
        if (!$force && ($this->state['next_check_at'] ?? 0) > $now) {
            return;
        }
        $dir = $this->config['cache_dir'];
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->state['error'] = 'Нет доступа для записи кэша. Показан комплект из архива.';
            return;
        }
        $lock = @fopen($dir.'/sync.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }
            return;
        }
        try {
            // Re-read after the lock, so concurrent visitors never repeat a sync.
            $this->state = $this->readJson($dir.'/state.json');
            if (!$force && ($this->state['next_check_at'] ?? 0) > $now) {
                return;
            }
            $saved = $this->readJson($dir.'/current.json');
            if ($saved) {
                $this->snapshot = $saved;
            }
            $meta = $this->state['meta'] ?? [];
            if (!$meta || ($this->state['meta_checked_at'] ?? 0) < $now - 3600) {
                $meta = $this->client->api('')['data'];
                $this->state['meta'] = array_intersect_key($meta, array_flip(['default_branch', 'description', 'html_url', 'private']));
                $this->state['meta_checked_at'] = $now;
            }
            $branch = $this->config['branch'] ?: ($meta['default_branch'] ?? 'main');
            $head = $this->client->api('/commits?per_page=5&sha='.rawurlencode($branch), $this->state['etag'] ?? '');
            if ($head['not_modified']) {
                if (!$this->snapshot) {
                    throw new GitHubFailure('GitHub не вернул исходную версию.');
                }
            } else {
                $commits = $head['data'];
                $commit = $commits[0] ?? null;
                if (!is_array($commit) || !preg_match('/^[a-f0-9]{40}$/', $commit['sha'] ?? '')) {
                    throw new GitHubFailure('GitHub не вернул коммит.');
                }
                if (($this->snapshot['sha'] ?? '') !== $commit['sha']) {
                    $tree = $this->tree($commit['commit']['tree']['sha']);
                    $manifestEntry = $tree[$this->config['manifest_path']] ?? null;
                    $manifest = [];
                    if ($manifestEntry) {
                        $blobs = $this->download([$manifestEntry], $commit['sha']);
                        $manifest = json_decode($blobs[$manifestEntry['sha']], true, 512, JSON_THROW_ON_ERROR);
                        $this->validateManifest($manifest);
                    }
                    $selected = $this->select($tree, $manifest);
                    $this->download(array_values($selected), $commit['sha']);
                    $new = [
                        'sha' => $commit['sha'], 'branch' => $branch, 'tree' => $tree,
                        'files' => $selected, 'manifest' => $manifest,
                        'message' => $commit['commit']['message'] ?? '',
                        'commit_date' => $commit['commit']['committer']['date'] ?? '',
                        'synced_at' => $now,
                        'commits' => array_map(static fn(array $c): array => [
                            'sha' => $c['sha'], 'message' => $c['commit']['message'] ?? '',
                            'date' => $c['commit']['committer']['date'] ?? '',
                        ], $commits),
                    ];
                    $this->writeJson($dir.'/snapshots/'.$new['sha'].'.json', $new);
                    $this->writeJson($dir.'/current.json', $new); // Atomic publish, after all blobs.
                    $this->snapshot = $new;
                }
                $this->state['etag'] = $head['etag'];
            }
            $this->state['checked_at'] = $now;
            $this->state['error'] = null;
            $this->state['failures'] = 0;
            $this->state['next_check_at'] = $now + $this->config['interval'];
            $this->prune();
        } catch (Throwable $e) {
            $failures = min(6, (int)($this->state['failures'] ?? 0) + 1);
            $this->state['error'] = $e instanceof GitHubFailure
                ? $e->getMessage() : 'Обновление не завершено. Сохранена предыдущая целостная версия.';
            $this->state['failures'] = $failures;
            $this->state['next_check_at'] = max(
                $now + min(3600, $this->config['interval'] * (2 ** ($failures - 1))),
                $e instanceof GitHubFailure ? $e->retryAt : 0
            );
        } finally {
            try {
                $this->writeJson($dir.'/state.json', $this->state);
            } catch (Throwable $e) {
                $this->state['error'] = 'Кэш недоступен для записи. Показана сохраненная версия.';
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function viewStatus(): array
    {
        $current = empty($this->state['error']) && !empty($this->state['checked_at'])
            && $this->state['checked_at'] >= time() - $this->config['interval'] * 2;
        return [
            'sha' => $this->snapshot['sha'] ?? '',
            'branch' => $this->snapshot['branch'] ?? ($this->config['branch'] ?: 'main'),
            'status' => $current ? 'current' : 'saved',
            'checked_at' => $this->state['checked_at'] ?? null,
            'synced_at' => $this->snapshot['synced_at'] ?? null,
            'next_check_at' => $this->state['next_check_at'] ?? null,
            'error' => $this->state['error'] ?? null,
            'interval' => $this->config['interval'],
        ];
    }

    public function at(string $sha): array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $sha)) {
            return [];
        }
        if (($this->snapshot['sha'] ?? '') === $sha) {
            return $this->snapshot;
        }
        $old = $this->readJson($this->config['cache_dir'].'/snapshots/'.$sha.'.json');
        if ($old) {
            return $old;
        }
        $bootstrap = $this->readJson($this->config['bootstrap_dir'].'/index.json');
        return ($bootstrap['sha'] ?? '') === $sha ? $bootstrap : [];
    }

    public function content(string $path, ?array $snapshot = null): ?string
    {
        if (!self::validPath($path)) {
            return null;
        }
        $snapshot ??= $this->snapshot;
        $sha = $snapshot['files'][$path]['sha'] ?? '';
        if (!preg_match('/^[a-f0-9]{40}$/', $sha)) {
            return null;
        }
        foreach (['cache_dir', 'bootstrap_dir'] as $key) {
            $file = $this->config[$key].'/blobs/'.$sha;
            if (is_file($file)) {
                $content = file_get_contents($file);
                if (is_string($content) && sha1('blob '.strlen($content)."\0".$content) === $sha) {
                    return $content;
                }
            }
        }
        return null;
    }

    public function json(string $path): array
    {
        $text = $this->content($path);
        $data = $text !== null ? json_decode($text, true) : null;
        return is_array($data) ? $data : [];
    }

    public function repoUrl(string $path = '', bool $tree = false): string
    {
        $base = 'https://github.com/'.rawurlencode($this->config['owner']).'/'.rawurlencode($this->config['repository']);
        return $path === '' ? $base : $base.'/'.($tree ? 'tree' : 'blob').'/'.($this->snapshot['sha'] ?? 'main')
            .'/'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    public function validateManifest(array $manifest): void
    {
        if (($manifest['version'] ?? 0) !== 1 || !is_array($manifest['sections'] ?? null)) {
            throw new GitHubFailure('Неподдерживаемая версия настроек портала в GitHub.');
        }
        $ids = [];
        foreach ($manifest['sections'] as $section) {
            if (!is_array($section) || !preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $section['id'] ?? '')
                || isset($ids[$section['id']])
                || !in_array($section['type'] ?? '', ['overview', 'docs', 'diagrams', 'prototypes', 'stories', 'requirements', 'file', 'github', 'acceptance', 'delivery', 'directory'], true)) {
                throw new GitHubFailure('Некорректное описание раздела портала.');
            }
            $ids[$section['id']] = true;
            if (!is_string($section['title'] ?? null) || !is_array($manifest['project'] ?? [])) {
                throw new GitHubFailure('Некорректные подписи разделов портала.');
            }
            foreach (['path', 'directory'] as $key) {
                if (isset($section[$key]) && !self::validPath(rtrim($section[$key], '/'))) {
                    throw new GitHubFailure('Некорректный путь в настройках портала.');
                }
            }
        }
    }

    private function tree(string $sha): array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $sha)) {
            throw new GitHubFailure('Некорректный идентификатор дерева.');
        }
        $response = $this->client->api('/git/trees/'.$sha.'?recursive=1')['data'];
        $entries = $response['tree'] ?? [];
        if ($response['truncated'] ?? false) {
            // GitHub's recursive tree can be truncated; never publish a partial catalog.
            $entries = [];
            $queue = [['sha' => $sha, 'prefix' => '']];
            for ($i = 0; $i < count($queue); $i++) {
                if ($i > 2000) {
                    throw new GitHubFailure('Дерево репозитория превышает допустимый размер.');
                }
                $subtree = $this->client->api('/git/trees/'.$queue[$i]['sha'])['data'];
                if ($subtree['truncated'] ?? false) {
                    throw new GitHubFailure('GitHub вернул неполное дерево репозитория.');
                }
                foreach ($subtree['tree'] ?? [] as $entry) {
                    $entry['path'] = $queue[$i]['prefix'].$entry['path'];
                    $entries[] = $entry;
                    if ($entry['type'] === 'tree') {
                        $queue[] = ['sha' => $entry['sha'], 'prefix' => $entry['path'].'/'];
                    }
                }
            }
        }
        $tree = [];
        foreach ($entries as $entry) {
            if (self::validPath($entry['path'] ?? '') && preg_match('/^[a-f0-9]{40}$/', $entry['sha'] ?? '')) {
                $tree[$entry['path']] = array_intersect_key($entry, array_flip(['path', 'sha', 'type', 'mode', 'size']));
            }
        }
        ksort($tree, SORT_NATURAL);
        return $tree;
    }

    public function select(array $tree, array $manifest): array
    {
        $extensions = ['md', 'markdown', 'txt', 'json', 'yaml', 'yml', 'csv', 'tsv', 'puml', 'svg', 'png',
            'jpg', 'jpeg', 'webp', 'gif', 'pdf', 'docx', 'xlsx', 'sql', 'html', 'css', 'js', 'mjs',
            'woff', 'woff2', 'ttf', 'ico'];
        $files = [];
        $total = 0;
        foreach ($tree as $path => $entry) {
            if ($entry['type'] !== 'blob' || ($entry['mode'] ?? '100644') !== '100644'
                || !self::validPath($path) || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)) {
                continue;
            }
            $selected = $path === 'README.md' || $path === $this->config['manifest_path']
                || str_starts_with($path, 'docs/') || str_starts_with($path, 'prototypes/');
            foreach ($manifest['sections'] ?? [] as $section) {
                $selected = $selected || $path === ($section['path'] ?? '')
                    || (isset($section['directory']) && str_starts_with($path, rtrim($section['directory'], '/').'/'));
            }
            if (!$selected) {
                continue;
            }
            $total += $entry['size'] ?? 0;
            if (($entry['size'] ?? 0) > $this->config['max_file_bytes']
                || $total > $this->config['max_snapshot_bytes'] || count($files) >= $this->config['max_files']) {
                throw new GitHubFailure('Материалы превышают лимит кэша. Сохранена предыдущая версия.');
            }
            $files[$path] = $entry;
        }
        return $files;
    }

    private function download(array $entries, string $commit): array
    {
        $missing = [];
        $out = [];
        foreach ($entries as $entry) {
            $existing = $this->content($entry['path'], ['files' => [$entry['path'] => $entry]]);
            if ($existing === null) {
                $missing[] = $entry;
            } else {
                $out[$entry['sha']] = $existing;
            }
        }
        foreach ($this->client->blobs($missing, $commit) as $sha => $content) {
            $this->write($this->config['cache_dir'].'/blobs/'.$sha, $content);
            $out[$sha] = $content;
        }
        return $out;
    }

    private function readJson(string $path): array
    {
        $value = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
        return is_array($value) ? $value : [];
    }

    private function writeJson(string $path, array $value): void
    {
        $this->write($path, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function prune(): void
    {
        // Keep ten previous releases for open immutable links; remove unreferenced cached blobs.
        $dir = $this->config['cache_dir'];
        $snapshots = glob($dir.'/snapshots/*.json') ?: [];
        usort($snapshots, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        $used = [];
        foreach ($this->snapshot['files'] ?? [] as $entry) $used[$entry['sha']] = true;
        foreach ($snapshots as $i => $path) {
            if ($i < 10 || basename($path, '.json') === ($this->snapshot['sha'] ?? '')) {
                foreach ($this->readJson($path)['files'] ?? [] as $entry) $used[$entry['sha']] = true;
            } else {
                @unlink($path);
            }
        }
        foreach (glob($dir.'/blobs/*') ?: [] as $blob) {
            if (preg_match('/^[a-f0-9]{40}$/', basename($blob)) && !isset($used[basename($blob)])) @unlink($blob);
        }
    }

    private function write(string $path, string $value): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cache directory unavailable');
        }
        $temp = tempnam($dir, 'write-');
        if ($temp === false) {
            throw new RuntimeException('Cache file unavailable');
        }
        try {
            if (file_put_contents($temp, $value, LOCK_EX) !== strlen($value) || !@rename($temp, $path)) {
                throw new RuntimeException('Cache write failed');
            }
            @chmod($path, 0640);
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }
}
