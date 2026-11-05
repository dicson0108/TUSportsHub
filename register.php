<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    header("Location: " . app_url('/sessions.php'));
    exit;
}

$page_title = "Sign up";
$errors = [];
$name_val = trim($_POST['name'] ?? '');
$email_val = trim($_POST['email'] ?? '');
$phone_val = trim($_POST['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = "Session expired. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $terms = !empty($_POST['terms']);

        if (empty($name_val)) {
            $errors['name'] = "Name is required.";
        }

        if (empty($email_val)) {
            $errors['email'] = "Email is required.";
        } elseif (!is_valid_student_email($email_val) && !is_valid_taylors_email($email_val)) {
            $errors['email'] = "Only official Taylor's email addresses (@sd.taylors.edu.my or @taylors.edu.my) are accepted.";
        }

        if (strlen($password) < 8) {
            $errors['password'] = "Password must be at least 8 characters.";
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors['password'] = "Password must contain both letters and numbers.";
        }

        if ($password !== $password_confirm) {
            $errors['password_confirm'] = "Passwords do not match.";
        }

        if (!$terms) {
            $errors['terms'] = "You must agree to the sports terms and cancellation policy.";
        }

        if (empty($errors)) {
            $db = get_db_connection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email_val]);
            if ($stmt->fetch()) {
                $errors['email'] = "An account with this email already exists. Please log in.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role, status, phone) 
                                      VALUES (:name, :email, :password_hash, 'student', 'active', :phone)");
                $stmt->execute([
                    ':name' => $name_val,
                    ':email' => $email_val,
                    ':password_hash' => $password_hash,
                    ':phone' => $phone_val ?: null,
                ]);

                $new_id = (int)$db->lastInsertId();
                $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $new_id]);
                $user = $stmt->fetch();

                login_user($user);
                header("Location: " . app_url('/sessions.php'));
                exit;
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
            <span class="auth-hero-eyebrow">YOUR CAMPUS EXPERIENCE STARTS HERE</span>
            <h1 class="auth-hero-title">
                A new connection.<br>A new rally.
            </h1>
            <p class="auth-hero-desc">
                Join university sports clubs, book your court places, and track your active journey at Taylor's.
            </p>

            <div class="auth-poster-card">
                <img src="<?= APP_BASE_PATH ?>/assets/images/sports/badminton-footwork.webp?v=<?= filemtime(__DIR__ . '/assets/images/sports/badminton-footwork.webp') ?>" alt="Taylor's Badminton Training">
                <div class="auth-poster-overlay">
                    <span class="auth-poster-tag signup-tag">START STRONG</span>
                    <div class="auth-poster-title">FOOTWORK CLINIC</div>
                    <div class="auth-poster-desc">Multi-Purpose Hall Court A · Beginner to intermediate</div>
                </div>
            </div>
        </div>

        <!-- Right Card -->
        <div class="auth-form-card">
            <span class="auth-card-eyebrow">TAYLOR'S SPORTS HUB</span>
            <h2 class="auth-card-title">Create your account.</h2>
            <p class="auth-card-sub">Join sessions with your Taylor's email.</p>

            <?php if (!empty($errors['general'])): ?>
                <div class="auth-alert" role="alert">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                    <strong><?= e($errors['general']) ?></strong>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= APP_BASE_PATH ?>/register.php">
                <?= csrf_field() ?>

                <div class="form-field">
                    <label class="auth-label">Full name</label>
                    <input type="text" name="name" value="<?= e($name_val) ?>" 
                           class="auth-input <?= !empty($errors['name']) ? 'input-error' : '' ?>" required placeholder="e.g. Aisha Tan">
                    <?php if (!empty($errors['name'])): ?>
                        <div class="field-error-msg"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="auth-label">Taylor's email</label>
                    <input type="email" name="email" value="<?= e($email_val) ?>" 
                           class="auth-input <?= !empty($errors['email']) ? 'input-error' : '' ?>" required placeholder="name@sd.taylors.edu.my">
                    <?php if (!empty($errors['email'])): ?>
                        <div class="field-error-msg"><?= e($errors['email']) ?></div>
                    <?php else: ?>
                        <div class="field-help-msg">Use @sd.taylors.edu.my or @taylors.edu.my</div>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="auth-label">Phone number <span class="auth-label-opt">(optional)</span></label>
                    <input type="tel" name="phone" value="<?= e($phone_val) ?>" 
                           class="auth-input" placeholder="+6012-345 6789">
                </div>

                <div class="form-field">
                    <label class="auth-label">Password</label>
                    <input type="password" name="password" class="auth-input <?= !empty($errors['password']) ? 'input-error' : '' ?>" 
                           required placeholder="••••••••••••">
                    <?php if (!empty($errors['password'])): ?>
                        <div class="field-error-msg"><?= e($errors['password']) ?></div>
                    <?php else: ?>
                        <div class="field-help-msg">At least 8 characters, a letter and a number</div>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="auth-label">Confirm password</label>
                    <input type="password" name="password_confirm" class="auth-input <?= !empty($errors['password_confirm']) ? 'input-error' : '' ?>" 
                           required placeholder="••••••••••••">
                    <?php if (!empty($errors['password_confirm'])): ?>
                        <div class="field-error-msg"><?= e($errors['password_confirm']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-field">
                    <label class="auth-checkbox-label">
                        <input type="checkbox" name="terms" value="1" checked class="auth-checkbox">
                        <span>I agree to the Taylor's SportsHub Rules, Fair Play Guidelines and cancellation terms.</span>
                    </label>
                    <?php if (!empty($errors['terms'])): ?>
                        <div class="field-error-msg"><?= e($errors['terms']) ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="auth-submit-btn">
                    <span>Create account</span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>

                <div class="auth-switch-prompt">
                    Already have an account? <a href="<?= APP_BASE_PATH ?>/login.php" class="auth-switch-link">Log in</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
