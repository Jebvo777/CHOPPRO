<?php
declare(strict_types=1);
require_once __DIR__.'/Repository.php';
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/Markdown.php';
require_once __DIR__.'/Catalog.php';

$repository = new Repository(require dirname(__DIR__).'/config.php');
$repository->synchronize();
$catalog = new Catalog($repository);

function portal_headers(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'self'; form-action 'self'");
}
