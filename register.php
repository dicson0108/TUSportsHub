<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    header("Location: " . app_url('/sessions.php'));
    exit;
}

$error = null;
$name_val = trim($_POST['name'] ?? '');
$email_val = trim($_POST['email'] ?? '');
$phone_val = trim($_POST['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        $terms = !empty($_POST['terms']);

        if (empty($name_val) || empty($email_val) || empty($password)) {
            $error = "Please fill in all required fields.";
        } elseif (!is_valid_student_email($email_val)) {
            $error = "Registration requires an active Taylor's student email address ending in @sd.taylors.edu.my.";
        } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = "Password must be at least 8 characters long and contain both letters and numbers.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (!$terms) {
            $error = "You must agree to the Terms of Service to create an account.";
        } else {
            $db = get_db_connection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => strtolower($email_val)]);
            if ($stmt->fetch()) {
                $error = "An account with this student email already exists. Please sign in.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role, status, phone) 
                                      VALUES (:name, :email, :hash, 'student', 'active', :phone)");
                $stmt->execute([
                    ':name' => $name_val,
                    ':email' => strtolower($email_val),
                    ':hash' => $hash,
                    ':phone' => $phone_val ?: null,
                ]);
                $user_id = (int)$db->lastInsertId();

                $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $user_id]);
                $new_user = $stmt->fetch();

                login_user($new_user);
                header("Location: " . app_url('/sessions.php'));
                exit;
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
    <title>Create account · Taylor's SportsHub</title>
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/css/style.css">
    <style>
        .auth-container { min-height: 85vh; display: flex; align-items: center; justify-content: center; padding: 2.5rem 1rem; }
        .auth-card { width: 100%; max-width: 480px; background: #fff; border-radius: 20px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.06); }
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
        .checkbox-label { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.85rem; color: #424245; cursor: pointer; line-height: 1.4; }
        .checkbox-label input { margin-top: 0.15rem; }
    </style>
</head>
<body style="background: #fafafa;">

<header style="padding: 1.25rem 2rem; background: #fff; border-bottom: 1px solid #e5e5ea; display: flex; align-items: center; justify-content: space-between;">
    <a href="<?= APP_BASE_PATH ?>/index.php" style="text-decoration: none; color: #c8102e; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 0.35rem;">
        <strong>Taylor's</strong> <span style="color: #1d1d1f;">SportsHub</span>
    </a>
    <a href="<?= APP_BASE_PATH ?>/login.php" style="text-decoration: none; color: #6e6e73; font-size: 0.9rem; font-weight: 500;">Already registered? Sign in →</a>
</header>

<main class="auth-container">
    <div class="auth-card">
        <span class="auth-badge">Student Sign Up</span>
        <h1 class="auth-title">Create account</h1>
        <p class="auth-subtitle">Join Taylor's sports sessions with your student email</p>

        <?php if ($error): ?>
            <div class="auth-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= app_url('/register.php') ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="name">Full name</label>
                <input class="form-control" type="text" id="name" name="name" value="<?= e($name_val) ?>" placeholder="e.g. Liam Tan" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Student email</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= e($email_val) ?>" placeholder="yourname@sd.taylors.edu.my" required>
                <span class="form-help">Must end with @sd.taylors.edu.my</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Phone number (optional)</label>
                <input class="form-control" type="tel" id="phone" name="phone" value="<?= e($phone_val) ?>" placeholder="+60123456789">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" placeholder="At least 8 characters with letters & numbers" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirm">Confirm password</label>
                <input class="form-control" type="password" id="password_confirm" name="password_confirm" placeholder="Re-enter password" required>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="terms" value="1" required>
                    <span>I confirm I am a registered Taylor's University student and agree to the SportsHub community rules.</span>
                </label>
            </div>

            <button type="submit" class="btn-submit">Create account</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="<?= app_url('/login.php') ?>">Sign in</a>
        </div>
    </div>
</main>

</body>
</html>
