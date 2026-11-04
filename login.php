<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    $redirect = $_GET['redirect'] ?? app_url('/sessions.php');
    header("Location: " . $redirect);
    exit;
}

$error = null;
$email_val = trim($_POST['email'] ?? '');
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? app_url('/sessions.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';

        if (empty($email_val) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            $db = get_db_connection();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email_val]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'suspended') {
                    $error = "Your account has been suspended. Please contact Taylor's SportsHub administration.";
                } else {
                    login_user($user);
                    header("Location: " . $redirect);
                    exit;
                }
            } else {
                $error = "Email or password is incorrect. Check your details and try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in · Taylor's SportsHub</title>
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/css/style.css">
    <style>
        .auth-container { min-height: 85vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .auth-card { width: 100%; max-width: 440px; background: #fff; border-radius: 20px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.06); }
        .auth-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.75rem; background: #fdf2f4; color: #c8102e; border-radius: 980px; font-size: 0.8rem; font-weight: 600; margin-bottom: 1rem; }
        .auth-title { font-size: 1.75rem; font-weight: 700; margin: 0 0 0.5rem; color: #1d1d1f; letter-spacing: -0.02em; }
        .auth-subtitle { color: #6e6e73; font-size: 0.95rem; margin: 0 0 1.75rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f; }
        .form-control { width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box; transition: border-color 0.15s, box-shadow 0.15s; }
        .form-control:focus { outline: none; border-color: #c8102e; box-shadow: 0 0 0 3px rgba(200,16,46,0.12); }
        .form-help { font-size: 0.8rem; color: #86868b; margin-top: 0.35rem; }
        .btn-submit { width: 100%; padding: 0.9rem; background: #c8102e; color: #fff; border: none; border-radius: 980px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.15s; margin-top: 0.75rem; }
        .btn-submit:hover { background: #a60d26; }
        .auth-error { background: #fff2f2; border: 1px solid #fecdd3; color: #c81e27; padding: 0.85rem 1rem; border-radius: 12px; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .auth-footer { margin-top: 2rem; text-align: center; font-size: 0.9rem; color: #6e6e73; border-top: 1px solid #f0f0f2; padding-top: 1.25rem; }
        .auth-footer a { color: #c8102e; text-decoration: none; font-weight: 600; }
        .auth-footer a:hover { text-decoration: underline; }
        .demo-accounts { margin-top: 1.5rem; padding: 1rem; background: #f5f5f7; border-radius: 12px; font-size: 0.8rem; color: #424245; }
        .demo-accounts strong { display: block; margin-bottom: 0.35rem; color: #1d1d1f; }
    </style>
</head>
<body style="background: #fafafa;">

<header style="padding: 1.25rem 2rem; background: #fff; border-bottom: 1px solid #e5e5ea; display: flex; align-items: center; justify-content: space-between;">
    <a href="<?= APP_BASE_PATH ?>/index.php" style="text-decoration: none; color: #c8102e; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.35rem;">
        <strong>Taylor's</strong> <span style="color: #1d1d1f;">SportsHub</span>
    </a>
    <a href="<?= APP_BASE_PATH ?>/sessions.php" style="text-decoration: none; color: #6e6e73; font-size: 0.9rem; font-weight: 500;">Explore sessions →</a>
</header>

<main class="auth-container">
    <div class="auth-card">
        <span class="auth-badge">Taylor's University</span>
        <h1 class="auth-title">Log in</h1>
        <p class="auth-subtitle">Sign in with your Taylor's student or staff account</p>

        <?php if ($error): ?>
            <div class="auth-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= app_url('/login.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

            <div class="form-group">
                <label class="form-label" for="email">Email address</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= e($email_val) ?>" placeholder="e.g. alex@sd.taylors.edu.my" required autofocus>
                <span class="form-help">Students: @sd.taylors.edu.my · Staff: @taylors.edu.my</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-submit">Sign in</button>
        </form>

        <div class="demo-accounts">
            <strong>Synthetic Demo Accounts (Password: Password123!)</strong>
            <div>• Manager: alex@sd.taylors.edu.my</div>
            <div>• Student: liam@sd.taylors.edu.my</div>
            <div>• Admin: admin@taylors.edu.my</div>
        </div>

        <div class="auth-footer">
            Don't have an account? <a href="<?= app_url('/register.php') ?>">Create account</a>
        </div>
    </div>
</main>

</body>
</html>
