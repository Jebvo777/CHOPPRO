<?php
declare(strict_types=1);
require_once __DIR__.'/app/Repository.php';
$repository = new Repository(require __DIR__.'/config.php');
$info = rawurldecode($_SERVER['PATH_INFO'] ?? '');
if (!preg_match('#^/([a-f0-9]{40})/(.+)$#', $info, $match)) {
    http_response_code(404);
    exit('Прототип не найден.');
}
[$all, $sha, $path] = $match;
$snapshot = $repository->at($sha);
$body = $snapshot ? $repository->content($path, $snapshot) : null;
$mime = [
    'html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8',
    'js' => 'text/javascript; charset=utf-8', 'mjs' => 'text/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8', 'svg' => 'image/svg+xml',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
    'gif' => 'image/gif', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'ico' => 'image/x-icon',
];
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$permitted = str_starts_with($path, 'prototypes/');
foreach ($snapshot['manifest']['sections'] ?? [] as $section) {
    $permitted = $permitted || (($section['type'] ?? '') === 'prototypes'
        && str_starts_with($path, rtrim($section['directory'] ?? 'prototypes', '/').'/'));
}
if ($body === null || !$permitted || !isset($mime[$extension])) {
    http_response_code(404);
    exit('Ресурс прототипа не найден.');
}
header('Content-Type: '.$mime[$extension]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=31536000, immutable');
header('Referrer-Policy: no-referrer');

header("Content-Security-Policy: sandbox allow-scripts allow-forms allow-downloads; default-src 'self' data: blob:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'self'; form-action 'none'");
echo $body;
