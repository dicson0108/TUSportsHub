<?php
// All stored session/deadline values are Malaysia local time, regardless of host config.
date_default_timezone_set('Asia/Kuala_Lumpur');
// Auto-detect URL prefix for this installation dynamically
if (!defined('APP_BASE_PATH')) {
    $configured_base = getenv('APP_BASE_PATH');
    $base = $configured_base === false ? '' : rtrim($configured_base, '/');
    if ($configured_base === false && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $resolved_root = realpath($_SERVER['DOCUMENT_ROOT']);
        $docRoot = $resolved_root === false ? false : rtrim(str_replace('\\', '/', $resolved_root), '/');
        $projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__)));
        if ($docRoot !== false && $projectRoot && ($projectRoot === $docRoot || str_starts_with($projectRoot, $docRoot . '/'))) {
            $base = substr($projectRoot, strlen($docRoot));
        }
    } elseif ($configured_base === false && PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $base = preg_replace('#/(admin|api|config|database|includes|tests)$#', '', $scriptDir);
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
