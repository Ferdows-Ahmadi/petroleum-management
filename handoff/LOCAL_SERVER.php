<?php

// Local launcher router: use only this installation's configuration.
$root = dirname(__DIR__);
foreach (file($root.'/.env.example') as $line) {
    if (preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $match)) {
        putenv($match[1]);
        unset($_ENV[$match[1]], $_SERVER[$match[1]]);
    }
}
putenv('DB_URL');
unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
$public = $root.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = realpath($public.$uri);
if ($uri !== '/' && $file && is_file($file) &&
    str_starts_with(strtolower(str_replace('\\', '/', $file)), strtolower(str_replace('\\', '/', realpath($public))).'/')) {
    return false;
}
require $public.'/index.php';
