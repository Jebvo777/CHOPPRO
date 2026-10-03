<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
portal_headers();
$status = $repository->viewStatus();
$sha = $status['sha'];
$page = input('page', 'overview');
if ($page === 'figma') {
    $page = 'design'; // Compatibility with old bookmarks.
}
$section = $catalog->section($page);
if ($page !== 'doc' && !$section) {
    http_response_code(404);
}
$type = $page === 'doc' ? 'doc' : ($section['type'] ?? 'missing');
$title = $page === 'doc' ? 'Просмотр документа' : ($section['title'] ?? 'Раздел не найден');
$project = $catalog->project;
$requirementsSection = null;
$storiesSection = null;
foreach ($catalog->sections as $s) {
    if ($s['type'] === 'requirements') $requirementsSection = $s;
    if ($s['type'] === 'stories') $storiesSection = $s;
}
$requirements = $requirementsSection ? $catalog->data($requirementsSection) : [];
$stories = $storiesSection ? $catalog->data($storiesSection) : [];
$diagramFiles = array_filter($catalog->files(), static fn($entry, $path) => str_ends_with($path, '.puml'), ARRAY_FILTER_USE_BOTH);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?=e($title)?> — <?=e($project['name'] ?? 'ЧОППРО')?></title>
<link rel="stylesheet" href="assets/style.css?v=5"><script src="assets/app.js?v=5" defer></script>
</head>
<body data-sha="<?=e($sha)?>" data-repository="<?=e($repository->config['owner'].'/'.$repository->config['repository'])?>">
<div class="shell">
<header class="header">
<div><div class="logo"><?=e($project['name'] ?? 'ЧОППРО')?> / ПОРТАЛ ПРОЕКТА</div><div class="subtitle"><?=e($project['subtitle'] ?? 'Документация и приемка')?></div></div>
<div class="sync-badge"><span class="badge"><span class="status-dot <?=$status['status'] === 'current' ? 'live' : ''?>"></span><?=$status['status'] === 'current' ? 'GitHub · синхронизировано' : 'Сохраненная версия'?></span><div class="small">Проверка: <?=e(display_date($status['checked_at']))?></div></div>
</header>
<div class="sync-notice" data-sync-notice hidden>В GitHub появилась новая версия. <a href="<?=e($_SERVER['REQUEST_URI'] ?? './')?>" data-load-version>Открыть актуальную</a></div>
<?php if ($status['error']): ?><div class="warning"><?=e($status['error'])?> Данные на <?=e(display_date($status['synced_at']))?>. Следующая проверка: <?=e(display_date($status['next_check_at']))?>.</div><?php endif; ?>
<?php if (!$repository->config['branch'] && $status['branch'] === ($repository->config['transition_branch'] ?? '')): ?><div class="sync-notice">Источник — ветка <?=e($status['branch'])?>. После слияния изменений портал автоматически перейдет на основную ветку GitHub.</div><?php endif; ?>
<div class="layout">
<aside class="sidebar" aria-label="Разделы портала">
<?php foreach ($catalog->sections as $s): ?><a class="nav <?=$page === $s['id'] ? 'active' : ''?>" href="<?=e(nav_url($s['id']))?>"><span><?=e($s['icon'] ?? '·')?></span><?=e($s['title'])?></a><?php endforeach; ?>
<div class="sidebar-note">Содержимое из GitHub<br><a href="<?=e($repository->repoUrl())?>" target="_blank" rel="noopener">CHOPPRO ↗</a></div>
</aside>
<main class="content">
<?php if ($type !== 'doc'): ?>
<div class="page-title"><div><h1><?=e($section['heading'] ?? $title)?></h1><?php if (!empty($section['description'])): ?><p><?=e($section['description'])?></p><?php endif; ?></div></div>
<?php endif; ?>

<?php if ($type === 'overview'): ?>
<section class="section"><div class="grid">
<div class="card"><div class="metric"><?=count($catalog->documents)?></div><div>документов в GitHub</div></div>
<div class="card"><div class="metric"><?=count($diagramFiles)?></div><div>PUML-диаграмм</div></div>
<div class="card"><div class="metric"><?=count($requirements)?></div><div>требований и AT</div></div>
<div class="card"><div class="metric"><?=count($stories)?></div><div>сценариев</div></div>
</div></section>
<?php if (!empty($project['status'])): ?><div class="card section"><h2>Состояние проекта</h2><p><?=e($project['status'])?></p></div><?php endif; ?>
<?php if (!empty($project['stack'])): ?><section class="section"><h2>Актуальный стек</h2><div class="stack-tags"><?php foreach ($project['stack'] as $item): ?><span class="badge"><?=e($item)?></span><?php endforeach; ?></div></section><?php endif; ?>
<?php foreach ($project['decisions'] ?? [] as $item): ?><div class="card section"><h3><?=e($item['title'])?></h3><p><?=e($item['text'])?></p></div><?php endforeach; ?>
<section class="section"><h2>Обзор из README</h2><article class="viewer"><?=$catalog->render($section['path'] ?? 'README.md')?></article></section>

<?php elseif ($type === 'docs'): ?>
<div class="filters"><input data-doc-search placeholder="Найти документ…" aria-label="Найти документ"></div>
<div class="doc-list"><?php foreach ($catalog->documents as $doc): if (!str_starts_with($doc['path'], rtrim($section['directory'] ?? 'docs', '/').'/')) continue; ?>
<article class="doc" data-doc><h3><?=e($doc['title'])?></h3><p class="small muted"><?=e($doc['path'])?></p>
<div class="toolbar"><a class="btn primary" href="<?=e(nav_url('doc', ['path' => $doc['path']]))?>">Смотреть онлайн</a><a class="btn" href="<?=e(asset_url($doc['path'], $sha, true))?>">MD</a>
<?php if ($doc['docx']): ?><a class="btn" href="<?=e(asset_url($doc['docx'], $sha, true))?>">DOCX</a><?php endif; ?>
<a class="btn" href="<?=e($repository->repoUrl($doc['path']))?>" target="_blank" rel="noopener">GitHub ↗</a></div></article>
<?php endforeach; ?></div>

<?php elseif ($type === 'doc' || $type === 'file'): ?>
<?php
$path = $type === 'file' ? ($section['path'] ?? '') : input('path');
if ($path === '' && input('doc') !== '') {
    foreach ($catalog->documents as $doc) {
        if (str_starts_with(basename($doc['path']), input('doc'))) { $path = $doc['path']; break; }
    }
}
$entry = $catalog->files()[$path] ?? null;
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
?>
<?php if (!$entry): http_response_code(404); ?><div class="warning">Файл не найден в текущей версии GitHub.</div>
<?php else: ?>
<?php if ($type === 'doc'): ?><div class="page-title"><div><h1><?=e(file_title($path))?></h1><p class="small"><?=e($path)?></p></div></div><?php endif; ?>
<div class="toolbar"><a class="btn primary" href="<?=e(asset_url($path, $sha, true))?>">Скачать исходник</a><a class="btn" href="<?=e($repository->repoUrl($path))?>" target="_blank" rel="noopener">Открыть в GitHub ↗</a></div>
<?php if (in_array($extension, ['md', 'markdown', 'txt', 'yaml', 'yml', 'json', 'sql', 'csv', 'tsv', 'puml', 'html', 'css', 'js', 'mjs'], true)): ?>
<article class="viewer"><?=$catalog->render($path)?></article>
<?php elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)): ?><article class="viewer"><img src="<?=e(asset_url($path, $sha))?>" alt="<?=e(file_title($path))?>"></article>
<?php else: ?><p>Файл доступен для скачивания.</p><?php endif; ?>
<?php endif; ?>

<?php elseif ($type === 'diagrams'): ?>
<div class="diagram-grid">
<?php
$renderIndex = $repository->json(rtrim($section['directory'], '/').'/render-index.json');
foreach ($catalog->under($section['directory']) as $path => $entry):
if (!str_ends_with($path, '.puml')) continue;
$base = substr($path, 0, -5);
$render = $renderIndex['renders'][$path] ?? [];
$renderCurrent = ($render['source_sha'] ?? '') === $entry['sha'];
$svg = $renderCurrent && isset($catalog->files()[$base.'.svg']) ? $base.'.svg' : null;
?>
<article class="diagram"><h3><?=e(file_title($path))?></h3>
<?php if ($svg): ?><img loading="lazy" src="<?=e(asset_url($svg, $sha))?>" alt="<?=e(file_title($path))?>">
<?php else: ?><p class="muted">Актуальный PUML доступен ниже. Рендер появится после автоматической сборки в GitHub.</p><?php endif; ?>
<div class="toolbar"><a class="btn primary" href="<?=e(nav_url('doc', ['path' => $path]))?>">PUML онлайн</a><a class="btn" href="<?=e(asset_url($path, $sha, true))?>">PUML</a>
<?php foreach (['svg', 'png'] as $ext): if ($renderCurrent && isset($catalog->files()[$base.'.'.$ext])): ?><a class="btn" href="<?=e(asset_url($base.'.'.$ext, $sha, true))?>"><?=e(strtoupper($ext))?></a><?php endif; endforeach; ?></div></article>
<?php endforeach; ?></div>

<?php elseif ($type === 'prototypes'): ?>
<?php foreach ($section['launches'] ?? [] as $launch): if (!isset($catalog->files()[$launch['path']])) continue; ?>
<div class="card section"><h3><?=e($launch['title'])?></h3><p><?=e($launch['description'] ?? '')?></p><a class="btn primary" href="<?=e(nav_url($page, ['path' => $launch['path']] + ($launch['query'] ?? [])))?>">Открыть</a></div>
<?php endforeach; ?>
<?php
$prototypePath = input('path', $section['entry'] ?? rtrim($section['directory'] ?? 'prototypes', '/').'/index.html');
$query = [];
foreach (['app', 'screen', 'story', 'step'] as $param) {
    if (input($param) !== '') $query[$param] = input($param);
}
$guidedStory = null;
foreach ($stories as $story) {
    if (($story['id'] ?? '') === input('story')) { $guidedStory = $story; break; }
}
if ($guidedStory && !empty($guidedStory['steps'])):
$stepNumber = max(1, min(count($guidedStory['steps']), (int)input('step', '1')));
$mapped = $repository->snapshot['manifest']['scenario_screens'][$guidedStory['id']][$stepNumber - 1] ?? '';
if (str_contains($mapped, '-')) {
    [$query['app'], $query['screen']] = explode('-', $mapped, 2);
}
?>
<article class="story-card"><h3><?=e($guidedStory['id'].' — '.$guidedStory['title'])?></h3><p>Шаг <?=$stepNumber?> из <?=count($guidedStory['steps'])?>: <?=e($guidedStory['steps'][$stepNumber - 1])?></p><div class="toolbar">
<?php if ($stepNumber > 1): ?><a class="btn" href="<?=e(nav_url($page, ['story' => $guidedStory['id'], 'step' => $stepNumber - 1]))?>">← Назад</a><?php endif; ?>
<?php if ($stepNumber < count($guidedStory['steps'])): ?><a class="btn primary" href="<?=e(nav_url($page, ['story' => $guidedStory['id'], 'step' => $stepNumber + 1]))?>">Следующий шаг →</a><?php else: ?><a class="btn primary" href="<?=e(nav_url('stories'))?>">Все сценарии</a><?php endif; ?>
</div></article>
<?php endif;
if (isset($catalog->files()[$prototypePath]) && str_starts_with($prototypePath, rtrim($section['directory'] ?? 'prototypes', '/').'/') && str_ends_with($prototypePath, '.html')):
?>
<div class="prototype-wrap"><iframe title="Интерактивный прототип" src="<?=e(prototype_url($prototypePath, $sha, $query))?>" sandbox="allow-scripts allow-forms allow-downloads" referrerpolicy="no-referrer"></iframe></div>
<div class="toolbar"><a class="btn" href="<?=e($repository->repoUrl($prototypePath))?>" target="_blank" rel="noopener">Исходник прототипа ↗</a></div>
<?php else: ?><div class="warning">Прототип не найден в текущей версии.</div><?php endif; ?>
<details class="section"><summary>Все HTML-прототипы из GitHub</summary><div class="toolbar"><?php foreach ($catalog->under($section['directory'] ?? 'prototypes') as $path => $entry): if (str_ends_with($path, '.html')): ?><a class="btn" href="<?=e(nav_url($page, ['path' => $path]))?>"><?=e(file_title($path))?></a><?php endif; endforeach; ?></div></details>

<?php elseif ($type === 'stories'): $items = $catalog->data($section); ?>
<?php foreach ($items as $story): ?><article class="story-card"><h3><?=e($story['id'].' — '.$story['title'])?></h3><p class="muted"><?=e(!empty($story['official']) ? 'Сценарий исходного ТЗ' : 'Демонстрационный сценарий')?></p><ol><?php foreach ($story['steps'] ?? [] as $step): ?><li><?=e($step)?></li><?php endforeach; ?></ol><a class="btn primary" href="<?=e(nav_url('prototypes', ['story' => $story['id'], 'step' => '1']))?>">Пройти сценарий →</a></article><?php endforeach; ?>
<?php if (!$items): ?><p class="muted">В GitHub пока нет сценариев для этого раздела.</p><?php endif; ?>

<?php elseif ($type === 'requirements'): $items = $catalog->data($section); $categories = []; $stages = []; foreach ($items as $item) { $categories[$item['category'] ?? 'Без категории'] = true; foreach (stage_values($item['implementation_stage'] ?? '') as $stage) $stages[$stage] = true; } ksort($stages, SORT_NUMERIC); ?>
<div class="filters"><input data-req-search placeholder="ID, требование, критерий…" aria-label="Поиск требований"><select data-req-cat aria-label="Категория"><option value="">Все категории</option><?php foreach (array_keys($categories) as $category): ?><option><?=e($category)?></option><?php endforeach; ?></select><select data-req-stage aria-label="Этап"><option value="">Все этапы</option><?php foreach (array_keys($stages) as $stage): ?><option value="<?=e($stage)?>">Этап <?=e($stage)?></option><?php endforeach; ?></select></div>
<p class="muted" data-req-count><?=count($items)?> требований</p><div class="table-wrap"><table class="data-table"><thead><tr><th>ID</th><th>Категория</th><th>P</th><th>Требование</th><th>Критерий</th><th>Этап</th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr data-req-row data-cat="<?=e($item['category'] ?? '')?>" data-stage="<?=e($item['implementation_stage'] ?? '')?>"><td><strong><?=e($item['id'] ?? '')?></strong></td><td><?=e($item['category'] ?? '')?></td><td><?=e($item['priority'] ?? '')?></td><td><?=e($item['requirement'] ?? '')?></td><td><?=e($item['acceptance'] ?? '')?></td><td><?=e($item['implementation_stage'] ?? '')?></td></tr><?php endforeach; ?>
</tbody></table></div><div class="toolbar"><a class="btn" href="<?=e(asset_url($section['path'], $sha, true))?>">Скачать матрицу</a></div>

<?php elseif ($type === 'acceptance'): $groups = $catalog->data($section); ?>
<p class="muted">Отметки сохраняются в этом браузере. При изменении текста пункта его нужно подтвердить заново.</p>
<div class="toolbar"><span class="badge" data-progress-text>0 / 0</span><button class="btn" data-reset-accept>Сбросить отметки</button></div><div class="progress"><div data-progress-bar></div></div>
<?php foreach ($groups as $group => $items): ?><section class="accept-group"><h2><?=e($group)?></h2><?php foreach ($items as $item): ?><label class="accept-row"><input type="checkbox" data-accept="<?=e($item[0])?>" data-revision="<?=e(hash('sha256', $item[1]))?>"><span><?=e($item[1])?></span></label><?php endforeach; ?></section><?php endforeach; ?>

<?php elseif ($type === 'github'): ?>
<div class="github-box"><div class="card"><h3>Репозиторий</h3><a href="<?=e($repository->repoUrl())?>" target="_blank" rel="noopener">Jebvo777/CHOPPRO ↗</a></div><div class="card"><h3>Ветка</h3><?=e($status['branch'])?></div><div class="card"><h3>Коммит комплекта</h3><span class="small"><?=e($sha)?></span></div><div class="card"><h3>Обновление содержимого</h3><?=e(display_date($status['synced_at']))?></div></div>
<section class="section"><h2>Последние коммиты комплекта</h2><?php foreach ($repository->snapshot['commits'] ?? [] as $commit): ?><article class="card section"><strong><?=e(substr($commit['sha'], 0, 12))?></strong> · <?=e(display_date($commit['date']))?><p><?=nl2br(e($commit['message']))?></p></article><?php endforeach; ?></section>
<section class="section"><h2>Текущая структура репозитория</h2><div class="repo-tree"><?php foreach ($repository->snapshot['tree'] ?? [] as $path => $entry): ?><a href="<?=e($repository->repoUrl($path, $entry['type'] === 'tree'))?>" target="_blank" rel="noopener"><?=e($path)?><?=$entry['type'] === 'tree' ? '/' : ''?></a><?php endforeach; ?></div></section>
<div class="toolbar"><a class="btn" href="<?=e($repository->repoUrl().'/actions')?>" target="_blank" rel="noopener">Проверки GitHub Actions ↗</a><a class="btn" href="<?=e($repository->repoUrl().'/branches')?>" target="_blank" rel="noopener">Ветки ↗</a></div>

<?php elseif ($type === 'delivery'): ?>
<div class="toolbar"><a class="btn primary" href="<?=e($repository->repoUrl().'/archive/'.$sha.'.zip')?>">Скачать репозиторий этого комплекта</a><a class="btn" href="<?=e($repository->repoUrl().'/releases')?>" target="_blank" rel="noopener">Релизы ↗</a></div>
<p class="muted">Документы и вложения ниже принадлежат коммиту <?=e(substr($sha, 0, 12))?>.</p>
<div class="file-list"><?php foreach ($catalog->files() as $path => $entry): ?><div class="file-row"><a href="<?=e(nav_url('doc', ['path' => $path]))?>"><?=e($path)?></a><a class="btn" href="<?=e(asset_url($path, $sha, true))?>">Скачать</a></div><?php endforeach; ?></div>

<?php elseif ($type === 'directory'): ?>
<div class="file-list"><?php foreach ($catalog->under($section['directory']) as $path => $entry): ?><div class="file-row"><a href="<?=e(nav_url('doc', ['path' => $path]))?>"><?=e(file_title($path))?></a><span class="small muted"><?=e($path)?></span><a class="btn" href="<?=e(asset_url($path, $sha, true))?>">Скачать</a></div><?php endforeach; ?></div>
<?php if (!empty($section['path'])): ?><article class="viewer section"><?=$catalog->render($section['path'])?></article><?php endif; ?>

<?php else: ?><div class="warning">Раздел отсутствует в настройках GitHub.</div>
<?php endif; ?>
</main></div>
<footer class="footer"><?=e($project['name'] ?? 'ЧОППРО')?> · GitHub <?=e(substr($sha, 0, 12))?> · <?=e(display_date($status['synced_at']))?></footer>
</div></body></html>
