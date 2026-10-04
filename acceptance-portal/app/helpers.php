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
function stage_values(string $value): array
{
    preg_match_all('/(\d+)(?:\s*[-–]\s*(\d+))?/u', $value, $matches, PREG_SET_ORDER);
    $stages = [];
    foreach ($matches as $match) {
        $first = (int)$match[1];
        $last = isset($match[2]) ? (int)$match[2] : $first;
        if ($last >= $first && $last - $first <= 20) {
            foreach (range($first, $last) as $stage) $stages[] = (string)$stage;
        }
    }
    return array_values(array_unique($stages));
}

function portal_asset(string $name):string{return defined('CHOPPRO_RELEASE_SHA')?CHOPPRO_HOST_BASE.'/asset.php?path=acceptance-portal/assets/'.$name.'&release='.CHOPPRO_RELEASE_SHA:'assets/'.$name;}
function document_group(string $path):string
{
    if(str_contains($path,'Спецификация')||str_contains($path,'Актуальная')||str_contains($path,'Этап_2'))return 'Актуальная спецификация';
    if(preg_match('/Архитектура|Модель|модель|схема|интеграций|Структура/iu',$path))return 'Архитектура и данные';
    if(preg_match('/требований|сценариев|технического|ролей/iu',$path))return 'Требования и сценарии';
    return 'Результаты и решения по этапам';
}
function portal_icon(string $key):string
{
    $paths=['home'=>'<path d="M3 10 12 3l9 7v10h-6v-6H9v6H3z"/>','check'=>'<rect x="4" y="4" width="16" height="17" rx="3"/><path d="m8 12 3 3 5-6M9 2h6"/>','target'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>','book'=>'<path d="M12 5v16M3 4c4-1 7 0 9 2 2-2 5-3 9-2v15c-4-1-7 0-9 2-2-2-5-3-9-2z"/>','code'=>'<path d="m8 5-6 7 6 7m8-14 6 7-6 7m-3-17-2 20"/>','layers'=>'<path d="m2 8 10-5 10 5-10 5zm0 5 10 5 10-5M2 18l10 5 10-5"/>','route'=>'<circle cx="5" cy="5" r="3"/><circle cx="19" cy="19" r="3"/><path d="M8 5h8a4 4 0 0 1 0 8H8a3 3 0 0 0 0 6h8"/>','layout'=>'<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18M9 9v12"/>','git'=>'<circle cx="6" cy="5" r="3"/><circle cx="6" cy="19" r="3"/><circle cx="18" cy="6" r="3"/><path d="M6 8v8m0-5c9 0 12-1 12-2"/>','folder'=>'<path d="M3 7V4h6l3 3h9v13H3z"/>','refresh'=>'<path d="M20 10a8 8 0 0 0-14-5L3 8m0-5v5h5m-4 6a8 8 0 0 0 14 5l3-3m0 5v-5h-5"/>'];
    return '<svg class="portal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$key]??$paths['book']).'</svg>';
}
