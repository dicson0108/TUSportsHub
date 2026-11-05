<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    $redirect = $_GET['redirect'] ?? app_url('/sessions.php');
    header("Location: " . $redirect);
    exit;
}

$page_title = "Log in";
$error = null;
$email_val = trim($_POST['email'] ?? '');
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? app_url('/sessions.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';

        if (empty($email_val) || empty($password)) {
            $error = "Email or password is incorrect. Check your details and try again.";
        } else {
            $db = get_db_connection();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email_val]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'suspended') {
                    $error = "Your account has been suspended. Please contact the administrator.";
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

require_once __DIR__ . '/includes/header.php';
?>

<main class="auth-page-container">
    <div class="auth-split-layout">
        <!-- Left Hero -->
        <div class="auth-hero-col">
            <span class="auth-hero-eyebrow">WELCOME BACK TO CAMPUS LIFE</span>
            <h1 class="auth-hero-title">
                Your next great<br>moment is waiting.
            </h1>
            <p class="auth-hero-desc">
                Pick up where you left off. Your sports sessions, your community, all in one place.
            </p>

            <div class="auth-poster-card">
                <img src="<?= APP_BASE_PATH ?>/assets/images/sports/badminton-open-play.webp?v=<?= filemtime(__DIR__ . '/assets/images/sports/badminton-open-play.webp') ?>" alt="Taylor's Badminton Club Open Play">
                <div class="auth-poster-overlay">
                    <span class="auth-poster-tag">TAYLOR'S BADMINTON CLUB</span>
                    <div class="auth-poster-title">OPEN PLAY</div>
                    <div class="auth-poster-desc">Sports Complex · Court 1-3 · Weekly sparring</div>
                </div>
            </div>
        </div>

        <!-- Right Card -->
        <div class="auth-form-card">
            <span class="auth-card-eyebrow">TAYLOR'S SPORTS HUB</span>
            <h2 class="auth-card-title">Good to see you again.</h2>
            <p class="auth-card-sub">Log in with your Taylor's email to save your seat.</p>

            <?php if ($error): ?>
                <div class="auth-alert" role="alert">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                    <strong><?= e($error) ?></strong>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= APP_BASE_PATH ?>/login.php">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                <div class="form-field">
                    <label class="auth-label">Taylor's email</label>
                    <input type="email" name="email" value="<?= e($email_val ?: 'alex@sd.taylors.edu.my') ?>" 
                           class="auth-input <?= $error ? 'input-error' : '' ?>" required placeholder="name@sd.taylors.edu.my">
                </div>

                <div class="form-field">
                    <label class="auth-label">Password</label>
                    <input type="password" name="password" class="auth-input <?= $error ? 'input-error' : '' ?>" 
                           required value="Password123!" placeholder="••••••••••••">
                </div>

                <div class="auth-checkbox-row">
                    <label class="auth-checkbox-label">
                        <input type="checkbox" name="remember" checked class="auth-checkbox">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="auth-forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="auth-submit-btn">
                    <span>Log in</span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>

                <div class="auth-switch-prompt">
                    New to Sports Hub? <a href="<?= APP_BASE_PATH ?>/register.php" class="auth-switch-link">Create an account</a>
                </div>

                <div class="auth-disclaimer">
                    Students and club managers use the same login. Manager workspace access is assigned by the university.
                </div>
            </form>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
