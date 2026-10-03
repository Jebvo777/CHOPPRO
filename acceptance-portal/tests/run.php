<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Repository.php';
require_once dirname(__DIR__).'/app/helpers.php';
require_once dirname(__DIR__).'/app/Markdown.php';
require_once dirname(__DIR__).'/app/Catalog.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
    echo "OK ".$message.PHP_EOL;
}
function entry(string $path, string $content): array
{
    return ['path' => $path, 'sha' => sha1('blob '.strlen($content)."\0".$content), 'type' => 'blob', 'mode' => '100644', 'size' => strlen($content)];
}
function removeTree(string $path): void
{
    if (!is_dir($path)) return;
    foreach (new DirectoryIterator($path) as $item) {
        if ($item->isDot()) continue;
        if ($item->isDir()) removeTree($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($path);
}
$temp = sys_get_temp_dir().'/choppro-portal-'.bin2hex(random_bytes(6));
$config = require dirname(__DIR__).'/config.php';
$config['cache_dir'] = $temp.'/cache';
$config['bootstrap_dir'] = $temp.'/empty-bootstrap';
$config['token'] = '';
$manifest = ['version' => 1, 'autodiscover_sections' => true,
    'project' => ['name' => 'ЧОППРО', 'stack' => ['PHP 8.3+ / PDO']],
    'sections' => [
        ['id' => 'overview', 'title' => 'Обзор', 'type' => 'overview', 'path' => 'README.md'],
        ['id' => 'docs', 'title' => 'Документы', 'type' => 'docs', 'directory' => 'docs'],
        ['id' => 'stories', 'title' => 'Сценарии', 'type' => 'stories', 'path' => 'docs/stories.md'],
        ['id' => 'acceptance', 'title' => 'Приемка', 'type' => 'acceptance', 'path' => 'docs/acceptance.md'],
        ['id' => 'requirements', 'title' => 'Матрица', 'type' => 'requirements', 'path' => 'docs/matrix.csv'],
    ]];
$files = [
    'README.md' => "# ЧОППРО\n\nPHP 8.3+ / PDO.\n",
    'acceptance-portal/portal.json' => json_encode($manifest, JSON_UNESCAPED_UNICODE),
    'docs/старый.md' => "# Старый документ\n\nНачальная версия.",
    'docs/stories.md' => "## UC-01. Подключение\n\n1. Создать tenant.\n2. Назначить роли.\n",
    'docs/acceptance.md' => "## 2. Документы\n\n[ ] Проверить API.\n[ ] Проверить RBAC.\n",
    'docs/matrix.csv' => "id,category,priority,requirement,acceptance,implementation_stage\nFR-1,API,M,\"Текст, с запятой\",Ответ 200,2\n",
    'prototypes/index.html' => '<html><script src="ui.js"></script></html>',
    'prototypes/ui.js' => 'console.log("prototype");',
    'backend/run.php' => '<?php exit("NEVER EXECUTE");',
];
$commitSha = str_repeat('a', 40);
$treeSha = str_repeat('b', 40);
$mode = 'ok';
$requests = [];
$transport = function (string $url, array $headers) use (&$files, &$commitSha, &$treeSha, &$mode, &$requests): array {
    $requests[] = [$url, $headers];
    if ($mode === 'rate') {
        return ['status' => 429, 'headers' => ['x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => (string)(time() + 7200)], 'body' => '{}'];
    }
    if ($mode === 'offline') return ['status' => 0, 'headers' => [], 'body' => ''];
    if (str_contains($url, '/commits?')) {
        if ($mode === '304') return ['status' => 304, 'headers' => [], 'body' => ''];
        $body = [['sha' => $commitSha, 'commit' => ['tree' => ['sha' => $treeSha], 'message' => 'Обновление', 'committer' => ['date' => '2026-10-03T12:00:00Z']]]];
    } elseif (str_contains($url, '/git/trees/')) {
        $body = ['tree' => array_map('entry', array_keys($files), array_values($files)), 'truncated' => false];
    } elseif (str_starts_with($url, 'https://raw.githubusercontent.com/')) {
        $parts = explode('/', parse_url($url, PHP_URL_PATH));
        $path = rawurldecode(implode('/', array_slice($parts, 4)));
        return ['status' => isset($files[$path]) ? 200 : 404, 'headers' => [],
            'body' => $mode === 'corrupt' && $path === 'docs/новый.md' ? 'corrupt data' : ($files[$path] ?? '')];
    } else {
        $body = ['default_branch' => 'main', 'private' => false, 'description' => 'CHOPPRO'];
    }
    return ['status' => 200, 'headers' => ['etag' => '"head-'.$commitSha.'"'], 'body' => json_encode($body, JSON_UNESCAPED_UNICODE)];
};
try {
    $repo = new Repository($config, $transport);
    $repo->synchronize(true);
    check(($repo->snapshot['sha'] ?? '') === $commitSha, 'Initial snapshot uses the GitHub commit');
    check($repo->content('docs/старый.md') === $files['docs/старый.md'], 'UTF-8 document content matches the blob');
    check(!isset($repo->snapshot['files']['backend/run.php']), 'Repository PHP code is never materialized as executable content');
    check($repo->viewStatus()['status'] === 'current', 'Successful verification is shown as current');
    $catalog = new Catalog($repo);
    check(count($catalog->data($catalog->section('stories'))) === 1, 'Scenarios come directly from Markdown');
    check(count($catalog->data($catalog->section('acceptance'))['Документы']) === 2, 'Acceptance checklist comes directly from Markdown');
    check($catalog->data($catalog->section('requirements'))[0]['requirement'] === 'Текст, с запятой', 'CSV preserves quoted commas');
    $oldSnapshot = $repo->snapshot;
    $oldSha = $commitSha;
    $commitSha = str_repeat('c', 40);
    unset($files['docs/старый.md']);
    $files['docs/новый.md'] = "# Новый документ\n\nОбновление.";
    $files['docs/Этап_2/README.md'] = "# Core MVP\n\nНовый раздел.";
    $files['prototypes/ui.js'] = 'console.log("updated prototype");';
    $repo->synchronize(true);
    $catalog = new Catalog($repo);
    check($repo->snapshot['sha'] === $commitSha, 'A new commit updates the entire snapshot');
    check(!isset($repo->snapshot['files']['docs/старый.md']), 'Deleted documents disappear from navigation');
    check($repo->content('docs/новый.md') === $files['docs/новый.md'], 'New documents appear without editing the portal');
    check($repo->content('prototypes/ui.js') === $files['prototypes/ui.js'], 'Prototype assets update with the same commit');
    check(count(array_filter($catalog->sections, fn($s) => ($s['directory'] ?? '') === 'docs/Этап_2')) === 1, 'New documentation folders become sections automatically');
    check($repo->content('docs/старый.md', $repo->at($oldSha)) !== null, 'Previously opened immutable links keep working');
    $publishedSha = $commitSha;
    $mode = 'offline';
    $repo->synchronize(true);
    check($repo->snapshot['sha'] === $publishedSha && $repo->viewStatus()['status'] === 'saved', 'Network outage keeps the complete saved snapshot and marks it saved');
    $mode = 'rate';
    $repo->synchronize(true);
    check($repo->state['next_check_at'] >= time() + 7100, 'GitHub rate limit reset controls retry time');
    $count = count($requests);
    $repo->synchronize();
    check(count($requests) === $count, 'Visitors cannot bypass rate limit backoff');
    $mode = 'corrupt';
    $commitSha = str_repeat('d', 40);
    $files['docs/новый.md'] .= "\nUpdated again.";
    $repo->synchronize(true);
    check($repo->snapshot['sha'] === $publishedSha, 'Corrupt downloads never publish a partial snapshot');
    $mode = '304';
    $repo->synchronize(true);
    check($repo->snapshot['sha'] === $publishedSha && empty($repo->state['error']), 'Conditional 304 recovers without losing saved content');
    check(!Repository::validPath('../config.php') && !Repository::validPath("docs/\0bad") && $repo->content('../config.php') === null, 'Traversal and control characters are rejected');
    $html = Markdown::render("# Документ\n\n<script>alert(1)</script>\n\n[bad](javascript:alert)\n\n| A | B |\n| --- | --- |\n| **Да** | Нет |\n");
    check(!str_contains($html, '<script>') && !str_contains($html, 'href="javascript:') && str_contains($html, '<table'), 'Markdown escapes scripts and supports tables');
    check(!Markdown::safeUrl('data:text/html,bad') && !Markdown::safeUrl('//evil.test') && Markdown::safeUrl('https://github.com'), 'Unsafe document links are blocked');
    $invalid = $manifest;
    $invalid['sections'][0]['path'] = '../secret';
    $rejected = false;
    try { $repo->validateManifest($invalid); } catch (GitHubFailure $e) { $rejected = true; }
    check($rejected, 'Unsafe manifest paths are rejected');
    $authenticatedConfig = $config;
    $authenticatedConfig['token'] = 'test-token-not-a-real-secret';
    $authCalls = [];
    $client = new GitHubClient($authenticatedConfig, function ($url, $headers) use (&$authCalls): array {
        $authCalls[] = [$url, $headers];
        return ['status' => 200, 'headers' => [], 'body' => json_encode(['encoding' => 'base64', 'content' => base64_encode('asset')])];
    });
    $blob = entry('prototypes/asset.js', 'asset');
    check($client->blobs([$blob], str_repeat('e', 40))[$blob['sha']] === 'asset', 'Authenticated blob downloads are verified');
    check(str_starts_with($authCalls[0][0], 'https://api.github.com/') && in_array('Authorization: Bearer test-token-not-a-real-secret', $authCalls[0][1]), 'Tokens are sent only to the GitHub API');
    echo "PASS ".$checks." checks".PHP_EOL;
} finally {
    removeTree($temp);
}
