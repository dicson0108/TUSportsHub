<?php
// All stored session/deadline values are Malaysia local time, regardless of host config.
date_default_timezone_set('Asia/Kuala_Lumpur');
// Auto-detect URL prefix for this installation dynamically
if (!defined('APP_BASE_PATH')) {
    $base = '';
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
        $projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__)));
        if ($docRoot && $projectRoot && str_starts_with($projectRoot, $docRoot)) {
            $base = substr($projectRoot, strlen($docRoot));
        }
    } elseif (!empty($_SERVER['SCRIPT_NAME'])) {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $base = preg_replace('#/(admin|api|config|database|includes|tests)$#', '', $scriptDir);
    }

    if ($base === '' && PHP_SAPI === 'cli') {
        $base = '/MyProject/27TUSportsHub';
    }

    define('APP_BASE_PATH', rtrim($base, '/'));
}

function app_url(string $path): string {
    if ($path === APP_BASE_PATH || (APP_BASE_PATH !== '' && str_starts_with($path, APP_BASE_PATH . '/'))) {
        return $path;
    }
    return APP_BASE_PATH . '/' . ltrim($path, '/');
}

require_once __DIR__ . '/../includes/error.php';
if (PHP_SAPI !== 'cli') {
    ob_start();
    set_exception_handler('handle_app_exception');
}
