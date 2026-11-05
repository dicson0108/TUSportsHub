<?php
require_once __DIR__ . '/branding.php';
// Intentionally independent of authentication and database-backed site chrome.
function render_error_page(int $status): void {
    $pages = [
        403 => ['Access denied', 'You do not have permission to open this page or complete this action. Return to SportsHub home to continue.'],
        404 => ['Page not found', 'This page may have moved or is no longer available. Find your next session in Explore sessions.'],
        500 => ['Something went wrong', 'Your request could not be completed. Please try again in a moment, or return to the home page.'],
    ];
    [$title, $message] = $pages[$status] ?? ['Error', 'An unexpected error occurred.'];
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $base = htmlspecialchars(defined('APP_BASE_PATH') ? APP_BASE_PATH : '', ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#c8102e">
    <link rel="icon" type="image/x-icon" href="<?= $base ?>/favicon.ico">
    <title><?= $status ?> · <?= htmlspecialchars($title) ?> · Taylor's SportsHub</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/errors.css?v=<?= filemtime(__DIR__ . '/../assets/css/errors.css') ?>">
</head>
<body class="error-page" data-error-code="<?= $status ?>">
    <header class="error-header">
        <a class="brand-group" href="<?= $base ?>/index.php" aria-label="Taylor's SportsHub home">
            <?= get_site_logo_html() ?>
            <div class="brand-text">
                <span class="brand-title">Taylor's</span>
                <span class="brand-subtitle">SPORTS HUB</span>
            </div>
        </a>
    </header>
    <main class="error-main">
        <div class="error-content">
            <p class="error-code"><?= $status ?></p>
            <h1><?= htmlspecialchars($title) ?></h1>
            <p class="error-description"><?= htmlspecialchars($message) ?></p>
            <nav class="error-actions" aria-label="Error recovery">
                <a class="btn-primary" href="<?= $base ?>/sessions.php">Explore sessions</a>
                <a class="btn-secondary" href="<?= $base ?>/index.php">Back to home</a>
            </nav>
        </div>
    </main>
    <footer class="error-footer">Taylor’s SportsHub · Student sports and club activities.</footer>
</body>
</html>
    <?php
    exit;
}

function handle_app_exception(Throwable $error): void {
    error_log((string) $error);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    render_error_page(500);
}
