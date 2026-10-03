<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Repository.php';
$repository = new Repository(require dirname(__DIR__).'/config.php');
$repository->synchronize();
$query = http_build_query(array_filter($_GET, 'is_scalar'));
header('Location: ../index.php?page=prototypes'.($query ? '&'.$query : ''), true, 302);
