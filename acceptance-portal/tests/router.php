<?php
declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#^/(app|bootstrap|storage|tests|bin|deploy)(/|$)#', $path)
    || preg_match('#^/(config\.php|portal\.json|\.env.*|.*\.md)$#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
return false;
