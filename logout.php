<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        logout_user();
    }
} else {
    logout_user();
}

header("Location: " . app_url("/index.php"));
exit;
