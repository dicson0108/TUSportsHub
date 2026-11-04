<?php
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
    <title><?= $status ?> · <?= htmlspecialchars($title) ?> · Taylor's SportsHub</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #fafafa; color: #1d1d1f; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .error-header { padding: 1.25rem 2rem; border-bottom: 1px solid #e5e5ea; background: #fff; }
        .error-brand { text-decoration: none; color: #c8102e; font-weight: 700; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
        .error-brand span { color: #1d1d1f; }
        .error-main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .error-content { max-width: 480px; text-align: center; background: #fff; border-radius: 18px; padding: 3rem 2rem; box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1px solid #ebebed; }
        .error-code { font-size: 3.5rem; font-weight: 800; color: #c8102e; margin: 0 0 0.5rem; line-height: 1; letter-spacing: -0.03em; }
        h1 { font-size: 1.5rem; margin: 0 0 0.75rem; font-weight: 600; }
        .error-description { color: #6e6e73; font-size: 1rem; line-height: 1.5; margin: 0 0 2rem; }
        .error-actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .btn-primary { background: #c8102e; color: #fff; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 980px; font-weight: 500; font-size: 0.95rem; transition: background 0.15s ease; }
        .btn-primary:hover { background: #a60d26; }
        .btn-secondary { background: #f5f5f7; color: #1d1d1f; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 980px; font-weight: 500; font-size: 0.95rem; border: 1px solid #d2d2d7; }
        .btn-secondary:hover { background: #e8e8ed; }
        .error-footer { text-align: center; padding: 1.5rem; font-size: 0.85rem; color: #86868b; border-top: 1px solid #e5e5ea; }
    </style>
</head>
<body class="error-page" data-error-code="<?= $status ?>">
    <header class="error-header">
        <a class="error-brand" href="<?= $base ?>/index.php" aria-label="Taylor's SportsHub home">
            <strong>Taylor's</strong> <span>SportsHub</span>
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
    if (str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', defined('APP_BASE_PATH') ? APP_BASE_PATH . '/api/' : '/api/')) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Server error occurred']);
        exit;
    }
    render_error_page(500);
}
