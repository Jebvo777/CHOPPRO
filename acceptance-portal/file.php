<?php
declare(strict_types=1);
require_once __DIR__.'/app/Repository.php';
require_once __DIR__.'/app/helpers.php';
$repository = new Repository(require __DIR__.'/config.php');
$snapshot = $repository->at(input('ref'));
$path = input('path');
$body = $snapshot ? $repository->content($path, $snapshot) : null;
if ($body === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Файл не найден в этой версии.');
}
$mime = [
    'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'woff' => 'font/woff', 'woff2' => 'font/woff2',
];
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
header('Content-Type: '.($mime[$extension] ?? 'text/plain; charset=utf-8'));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: "'.$snapshot['files'][$path]['sha'].'"');
if (input('download') === '1' || !isset($mime[$extension])) {
    header("Content-Disposition: attachment; filename=\"download.".$extension."\"; filename*=UTF-8''".rawurlencode(basename($path)));
}
echo $body;
