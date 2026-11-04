<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    header("Location: " . app_url('/sessions.php'));
    exit;
}

$page_title = "Create an account · Taylor's SportsHub";
$errors = [];
$name_val = trim($_POST['name'] ?? '');
$email_val = trim($_POST['email'] ?? '');
$phone_val = trim($_POST['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = "Session expired. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        $terms = !empty($_POST['terms']);

        if (empty($name_val)) {
            $errors['name'] = "Full name is required.";
        }

        if (empty($email_val)) {
            $errors['email'] = "Taylor's email is required.";
        } elseif (!is_valid_student_email($email_val)) {
            $errors['email'] = "Use @sd.taylors.edu.my or @taylors.edu.my.";
        }

        if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[a-zA-Z]/', $password)) {
            $errors['password'] = "Use at least 8 characters, a letter and a number.";
        }

        if ($password !== $confirm) {
            $errors['password_confirm'] = "Your passwords don't match.";
        }

        if (!$terms) {
            $errors['terms'] = "You must agree to the terms to proceed.";
        }

        if (empty($errors)) {
            $db = get_db_connection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => strtolower($email_val)]);
            if ($stmt->fetch()) {
                $errors['email'] = "An account with this email already exists. Please sign in.";
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
    <title><?= e($page_title) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= APP_BASE_PATH ?>/favicon.ico">
    <link rel="icon" type="image/webp" href="<?= APP_BASE_PATH ?>/assets/images/logo/logo.webp">
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= APP_BASE_PATH ?>/assets/css/auth.css">
    <style>
        .auth-poster-card {
            position: relative;
            border-radius: var(--radius-xl, 24px);
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.12);
            background: #141824;
            aspect-ratio: 16 / 10;
        }
        .auth-poster-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .auth-poster-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.78) 100%);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 24px;
            color: #ffffff;
        }
        .auth-poster-tag {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #a7f3d0;
            margin-bottom: 6px;
        }
        .auth-poster-title {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 4px;
        }
        .auth-poster-desc {
            font-size: 13.5px;
            color: #d1d5db;
        }
    </style>
</head>
<body style="background: var(--bg-surface-subtle, #fbfbfd);">

<header class="site-header" style="background: rgba(255, 255, 255, 0.88); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(0,0,0,0.08); padding: 0.75rem 1.5rem;">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between;">
        <a href="<?= APP_BASE_PATH ?>/index.php" class="brand-group" aria-label="Taylor's SportsHub Home" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
            <?= get_site_logo_html() ?>
            <div class="brand-text">
                <span class="brand-title">Taylor's</span>
                <span class="brand-subtitle">SPORTS HUB</span>
            </div>
        </a>

        <nav class="nav-links" style="display: flex; align-items: center; gap: 1.5rem;">
            <a href="<?= APP_BASE_PATH ?>/index.php" class="nav-link" style="color: #424245; text-decoration: none; font-size: 0.92rem; font-weight: 500;">Home</a>
            <a href="<?= APP_BASE_PATH ?>/sessions.php" class="nav-link" style="color: #424245; text-decoration: none; font-size: 0.92rem; font-weight: 500;">Explore sessions</a>
            <a href="<?= APP_BASE_PATH ?>/clubs.php" class="nav-link" style="color: #424245; text-decoration: none; font-size: 0.92rem; font-weight: 500;">Clubs</a>
        </nav>

        <div class="nav-actions" style="display: flex; align-items: center; gap: 0.75rem;">
            <a href="<?= APP_BASE_PATH ?>/login.php" class="btn-link" style="color: var(--text-main, #1d1d1f); font-weight: 500; text-decoration: none; font-size: 0.9rem; padding: 0.45rem 0.95rem;">Log in</a>
            <a href="<?= APP_BASE_PATH ?>/register.php" class="btn-primary" style="background: #c8102e; color: #fff; padding: 0.45rem 1rem; border-radius: 980px; text-decoration: none; font-size: 0.9rem; font-weight: 600;">Register</a>
        </div>
    </div>
</header>

<main class="app-container" style="max-width: 1200px; margin: 0 auto; padding: 0 1.5rem;">
    <div class="auth-split-layout">
        <!-- Left Hero -->
        <div>
            <div class="section-label" style="font-size: 11.5px; font-weight: 700; color: #c8102e; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 16px;">
                YOUR CAMPUS EXPERIENCE STARTS HERE
            </div>
            <h1 style="font-size: 46px; font-weight: 800; line-height: 1.1; margin-bottom: 16px; color: #1d1d1f; letter-spacing: -0.03em;">
                A new connection.<br>A new rally.
            </h1>
            <p style="font-size: 16px; color: var(--text-secondary, #424245); margin-bottom: 32px; max-width: 440px; line-height: 1.5;">
                Join university sports clubs, book your court places, and track your active journey at Taylor's.
            </p>

            <div class="auth-poster-card" style="max-width: 460px;">
                <img src="<?= APP_BASE_PATH ?>/assets/images/sports/badminton-footwork.webp" alt="Taylor's Badminton Training">
                <div class="auth-poster-overlay">
                    <span class="auth-poster-tag">START STRONG</span>
                    <div class="auth-poster-title">FOOTWORK CLINIC</div>
                    <div class="auth-poster-desc">Multi-Purpose Hall Court A · Beginner to intermediate</div>
                </div>
            </div>
        </div>

        <!-- Right Card -->
        <div class="auth-form-card">
            <div style="font-size: 11px; font-weight: 700; color: #c8102e; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 8px;">
                TAYLOR'S SPORTS HUB
            </div>
            <h2 class="auth-card-title">Create your account.</h2>
            <p class="auth-card-sub">Join sessions with your Taylor's email.</p>

            <?php if (!empty($errors['general'])): ?>
                <div class="status-alert-box danger" style="display: flex; align-items: center; gap: 12px; background: #fff2f2; border: 1px solid #fecdd3; border-radius: 12px; padding: 12px 16px; margin-bottom: 24px; color: #991b1b; font-size: 13.5px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                    <strong><?= e($errors['general']) ?></strong>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= APP_BASE_PATH ?>/register.php">
                <?= csrf_field() ?>

                <div class="form-field">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 6px; display: block; color: #1d1d1f;">Full name</label>
                    <input type="text" name="name" value="<?= e($name_val) ?>" 
                           class="form-input <?= !empty($errors['name']) ? 'input-error' : '' ?>" required placeholder="e.g. Aisha Tan"
                           style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 15px; box-sizing: border-box;">
                    <?php if (!empty($errors['name'])): ?>
                        <div class="field-error-msg"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 6px; display: block; color: #1d1d1f;">Taylor's email</label>
                    <input type="email" name="email" value="<?= e($email_val) ?>" 
                           class="form-input <?= !empty($errors['email']) ? 'input-error' : '' ?>" required placeholder="name@sd.taylors.edu.my"
                           style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 15px; box-sizing: border-box;">
                    <?php if (!empty($errors['email'])): ?>
                        <div class="field-error-msg"><?= e($errors['email']) ?></div>
                    <?php else: ?>
                        <div style="font-size: 11.5px; color: var(--text-muted, #6e6e73); margin-top: 5px;">Use @sd.taylors.edu.my or @taylors.edu.my</div>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 6px; display: block; color: #1d1d1f;">Phone number <span style="font-size: 12px; color: #86868b; font-weight: 400;">(optional)</span></label>
                    <input type="tel" name="phone" value="<?= e($phone_val) ?>" 
                           class="form-input" placeholder="+6012-345 6789"
                           style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 15px; box-sizing: border-box;">
                </div>

                <div class="form-field">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 6px; display: block; color: #1d1d1f;">Password</label>
                    <input type="password" name="password" class="form-input <?= !empty($errors['password']) ? 'input-error' : '' ?>" 
                           required placeholder="••••••••••••"
                           style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 15px; box-sizing: border-box;">
                    <?php if (!empty($errors['password'])): ?>
                        <div class="field-error-msg"><?= e($errors['password']) ?></div>
                    <?php else: ?>
                        <div style="font-size: 11.5px; color: var(--text-muted, #6e6e73); margin-top: 5px;">At least 8 characters, a letter and a number</div>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 6px; display: block; color: #1d1d1f;">Confirm password</label>
                    <input type="password" name="password_confirm" class="form-input <?= !empty($errors['password_confirm']) ? 'input-error' : '' ?>" 
                           required placeholder="••••••••••••"
                           style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 15px; box-sizing: border-box;">
                    <?php if (!empty($errors['password_confirm'])): ?>
                        <div class="field-error-msg"><?= e($errors['password_confirm']) ?></div>
                    <?php endif; ?>
                </div>

                <div style="margin-bottom: 24px; font-size: 13px;">
                    <label style="display:flex; align-items:flex-start; gap:8px; cursor:pointer; color: #424245; line-height: 1.4;">
                        <input type="checkbox" name="terms" value="1" checked style="accent-color:#c8102e; margin-top: 2px;">
                        <span>I agree to the Taylor's SportsHub Rules, Fair Play Guidelines and cancellation terms.</span>
                    </label>
                    <?php if (!empty($errors['terms'])): ?>
                        <div class="field-error-msg"><?= e($errors['terms']) ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; padding:13px; font-size:15px; font-weight:600; margin-bottom: 24px; background:#c8102e; color:#fff; border:none; border-radius:980px; display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer;">
                    Create account
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>

                <div style="text-align:center; font-size: 13.5px; color: var(--text-secondary, #424245); padding-top: 16px; border-top: 1px solid rgba(0,0,0,0.08);">
                    Already have an account? <a href="<?= APP_BASE_PATH ?>/login.php" style="color:#c8102e; font-weight:600; text-decoration: none;">Log in</a>
                </div>
            </form>
        </div>
    </div>
</main>

<footer class="site-footer" style="border-top: 1px solid rgba(0,0,0,0.08); padding: 2rem 1.5rem; margin-top: 3rem; background: #fff;">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; font-size: 13px; color: #86868b;">
        <span>© <?= date('Y') ?> Taylor's University SportsHub. All rights reserved.</span>
        <div style="display:flex; gap:20px; align-items:center;">
            <span>Lakeside Campus, Malaysia</span>
            <span>·</span>
            <span>Malaysia Time (MYT)</span>
        </div>
    </div>
</footer>

</body>
</html>
