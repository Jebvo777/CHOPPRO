<?php
declare(strict_types=1);
function e($value): string
{
    return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function input(string $key, string $default = ''): string
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? $_GET[$key] : $default;
}
function nav_url(string $page, array $extra = []): string
{
    return '?'.http_build_query(array_merge(['page' => $page], $extra));
}
function asset_url(string $path, string $sha, bool $download = false): string
{
    return 'file.php?'.http_build_query(['path' => $path, 'ref' => $sha] + ($download ? ['download' => '1'] : []));
}
function prototype_url(string $path, string $sha, array $query = []): string
{
    return 'prototype.php/'.$sha.'/'.implode('/', array_map('rawurlencode', explode('/', $path)))
        .($query ? '?'.http_build_query($query) : '');
}
function display_date($timestamp): string
{
    if (!$timestamp) {
        return 'еще не проверено';
    }
    $date = is_numeric($timestamp) ? (new DateTimeImmutable('@'.(int)$timestamp)) : new DateTimeImmutable($timestamp);
    return $date->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m.Y H:i').' МСК';
}
function file_title(string $path): string
{
    return str_replace('_', ' ', preg_replace('/\.[^.]+$/', '', basename($path)));
}
