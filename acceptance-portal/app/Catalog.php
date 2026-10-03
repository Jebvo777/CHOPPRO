<?php
declare(strict_types=1);

final class Catalog
{
    public array $project;
    public array $sections;
    public array $documents;

    public function __construct(public Repository $repository)
    {
        $manifest = $repository->snapshot['manifest'] ?? [];
        $this->project = $manifest['project'] ?? ['name' => 'ЧОППРО', 'subtitle' => 'Материалы проекта из GitHub'];
        $this->sections = $manifest['sections'] ?? [
            ['id' => 'overview', 'type' => 'overview', 'title' => 'Обзор'],
            ['id' => 'docs', 'type' => 'docs', 'title' => 'Документация', 'directory' => 'docs'],
            ['id' => 'prototypes', 'type' => 'prototypes', 'title' => 'Прототипы', 'directory' => 'prototypes'],
            ['id' => 'github', 'type' => 'github', 'title' => 'GitHub'],
            ['id' => 'delivery', 'type' => 'delivery', 'title' => 'Поставка'],
        ];
        $this->documents = [];
        $groups = [];
        foreach ($this->files() as $path => $entry) {
            if (str_starts_with($path, 'docs/') && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['md', 'markdown'], true)) {
                $text = $repository->content($path) ?? '';
                $title = preg_match('/^#\s+(.+)$/m', $text, $m) ? trim($m[1]) : file_title($path);
                $base = preg_replace('/\.(md|markdown)$/i', '', $path);
                $this->documents[] = ['path' => $path, 'title' => $title, 'sha' => $entry['sha'],
                    'docx' => isset($this->files()[$base.'.docx']) ? $base.'.docx' : null];
            }
            if (str_starts_with($path, 'docs/')) {
                $parts = explode('/', $path);
                if (count($parts) > 2 && !in_array($parts[1], ['диаграммы', 'diagrams', 'md', 'html', 'docx'], true)) {
                    $groups['docs/'.$parts[1]] = str_replace('_', ' ', $parts[1]);
                }
            }
        }
        if ($manifest['autodiscover_sections'] ?? true) {
            foreach ($groups as $directory => $title) {
                $exists = false;
                foreach ($this->sections as $section) {
                    $exists = $exists || rtrim($section['directory'] ?? '', '/') === $directory;
                }
                if (!$exists) {
                    $this->sections[] = ['id' => 'folder-'.substr(sha1($directory), 0, 10),
                        'type' => 'directory', 'title' => $title, 'directory' => $directory];
                }
            }
        }
    }

    public function files(): array
    {
        return $this->repository->snapshot['files'] ?? [];
    }

    public function section(string $id): ?array
    {
        foreach ($this->sections as $section) {
            if ($section['id'] === $id) {
                return $section;
            }
        }
        return null;
    }

    public function data(array $section): array
    {
        $path = $section['path'] ?? '';
        $source = $this->repository->content($path);
        if ($source === null) {
            return [];
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'json') {
            return $this->repository->json($path);
        }
        if (in_array($extension, ['csv', 'tsv'], true) && $section['type'] === 'requirements') {
            $handle = fopen('php://memory', 'r+');
            fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $source));
            rewind($handle);
            $delimiter = $extension === 'tsv' ? "\t" : ',';
            $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
            $rows = [];
            while (($values = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if ($headers && count($values) === count($headers)) {
                    $rows[] = array_combine($headers, $values);
                }
            }
            fclose($handle);
            return $rows;
        }
        if (in_array($extension, ['md', 'markdown'], true) && $section['type'] === 'stories') {
            $items = [];
            $current = null;
            foreach (explode("\n", $source) as $line) {
                if (preg_match('/^##\s+([A-Za-z]+-\d+)[.:]?\s+(.+)$/', $line, $m)) {
                    if ($current) $items[] = $current;
                    $current = ['id' => $m[1], 'title' => trim($m[2]), 'official' => str_starts_with($m[1], 'UC-'), 'steps' => []];
                } elseif ($current && preg_match('/^\d+[.)]\s+(.+)$/', $line, $m)) {
                    $current['steps'][] = trim($m[1]);
                }
            }
            if ($current) $items[] = $current;
            return $items;
        }
        if (in_array($extension, ['md', 'markdown'], true) && $section['type'] === 'acceptance') {
            $groups = [];
            $group = 'Приемка';
            foreach (explode("\n", $source) as $line) {
                if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                    $group = preg_replace('/^\d+\.\s*/', '', trim($m[1]));
                } elseif (preg_match('/^\s*(?:[-*]\s*)?\[[ xX]\]\s+(.+)$/', $line, $m)) {
                    $text = trim($m[1]);
                    $groups[$group][] = ['item-'.substr(sha1($group."\0".$text), 0, 16), $text];
                }
            }
            return $groups;
        }
        return [];
    }

    public function under(string $directory): array
    {
        return array_filter($this->files(), static fn(array $entry, string $path): bool =>
            str_starts_with($path, rtrim($directory, '/').'/'), ARRAY_FILTER_USE_BOTH);
    }

    public function render(string $path): string
    {
        $text = $this->repository->content($path);
        if ($text === null) {
            return '<div class="warning">Файл недоступен в этой версии.</div>';
        }
        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['md', 'markdown'], true)) {
            return '<pre class="codebox">'.e($text).'</pre>';
        }
        return Markdown::render($text, function (string $url, bool $image) use ($path): string {
            if (parse_url($url, PHP_URL_SCHEME) || str_starts_with($url, '#') || str_starts_with($url, '//')) {
                return $url;
            }
            $fragment = parse_url($url, PHP_URL_FRAGMENT);
            $relative = rawurldecode(parse_url($url, PHP_URL_PATH) ?? '');
            $parts = explode('/', str_starts_with($relative, '/') ? ltrim($relative, '/') : dirname($path).'/'.$relative);
            $normalized = [];
            foreach ($parts as $part) {
                if ($part === '..') {
                    array_pop($normalized);
                } elseif ($part !== '.' && $part !== '') {
                    $normalized[] = $part;
                }
            }
            $target = implode('/', $normalized);
            if (isset($this->files()[$target])) {
                return ($image ? asset_url($target, $this->repository->snapshot['sha'])
                    : nav_url('doc', ['path' => $target])).($fragment ? '#'.rawurlencode($fragment) : '');
            }
            return $this->repository->repoUrl($target);
        });
    }
}
