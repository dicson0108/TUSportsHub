<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    header("Location: " . app_url('/sessions.php'));
    exit;
}

$page_title = "Create an account";
$errors = [];
$name_val = trim($_POST['name'] ?? '');
$email_val = trim($_POST['email'] ?? '');
$phone_val = trim($_POST['phone'] ?? '');
$duplicate_email = false;
$success_user = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = "Session expired. Please try again.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? $_POST['password_confirm'] ?? '';
        $terms = !empty($_POST['terms']);

        if (empty($name_val)) {
            $errors['name'] = "Full name is required.";
        }

        if (empty($email_val)) {
            $errors['email'] = "Taylor's email is required.";
        } elseif (!is_valid_student_email($email_val) && !is_valid_taylors_email($email_val)) {
            $errors['email'] = "Use @sd.taylors.edu.my or @taylors.edu.my.";
        }

        if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[a-zA-Z]/', $password)) {
            $errors['password'] = "Use at least 8 characters, including a letter and a number.";
        }

        if ($password !== $confirm_password) {
            $errors['confirm_password'] = "Your passwords don't match.";
        }

        if (!$terms) {
            $errors['terms'] = "You must agree to the sports terms and cancellation policy.";
        }

        if (empty($errors)) {
            $db = get_db_connection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email_val]);
            if ($stmt->fetch()) {
                $duplicate_email = true;
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

                $success_user = [
                    'name' => $name_val,
                    'email' => $email_val
                ];
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="app-container">
    <div class="auth-split-layout">
        <!-- Left Hero -->
        <div class="auth-hero-col">
            <span class="section-label">YOUR CAMPUS EXPERIENCE STARTS HERE</span>
            <h1 class="auth-hero-title">
                A new connection.<br>A new rally.
            </h1>
            <p class="auth-hero-desc">
                One account for the moments that matter. Join university sports clubs, book your court places, and track your active journey at Taylor's.
            </p>

            <div class="auth-hero-poster">
                <img src="<?= APP_BASE_PATH ?>/assets/images/sports/badminton-footwork.webp?v=<?= filemtime(__DIR__ . '/assets/images/sports/badminton-footwork.webp') ?>" alt="Taylor's Badminton Training" class="poster-uploaded-img">
            </div>
            <div class="auth-hero-tagline">
                Made for the Taylor's sports community. Made for you.
            </div>
        </div>

        <!-- Right Card -->
        <div class="auth-form-card">
            <?php if ($success_user): ?>
                <!-- Success State (Matches Account validation states) -->
                <div>
                    <div class="auth-success-circle">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>

                    <h2 class="auth-card-title">You're part of it.</h2>

                    <div class="status-alert-box success" role="alert">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                        <div>
                            <div class="auth-status-title">Your account is ready.</div>
                            <div>Welcome to Taylor's SportsHub, <?= e($success_user['name']) ?>.</div>
                        </div>
                    </div>

                    <p class="auth-success-desc">
                        Log in with <strong><?= e($success_user['email']) ?></strong> and start exploring club sparring and training sessions.
                    </p>

                    <a href="<?= APP_BASE_PATH ?>/login.php" class="btn-primary btn-auth-submit">
                        <span>Continue to login</span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                </div>

            <?php elseif ($duplicate_email): ?>
                <!-- Duplicate Email State (Matches Account validation states) -->
                <div>
                    <h2 class="auth-card-title">Already one of us?</h2>

                    <div class="status-alert-box danger" role="alert">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                        <div>
                            <div class="auth-status-title">This email is already registered.</div>
                            <div>Log in or reset your password to access your account.</div>
                        </div>
                    </div>

                    <div class="form-field">
                        <label class="form-label">Taylor's email</label>
                        <input type="email" value="<?= e($email_val) ?>" class="form-input input-error" readonly>
                    </div>

                    <div class="auth-action-col">
                        <a href="<?= APP_BASE_PATH ?>/login.php" class="btn-primary btn-auth-submit">
                            <span>Go to login</span>
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                        <a href="<?= APP_BASE_PATH ?>/login.php" class="auth-forgot-link">
                            Return to sign in
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Standard Register Form -->
                <span class="auth-card-eyebrow">TAYLOR'S SPORTS HUB</span>
                <h2 class="auth-card-title">Create your account.</h2>
                <p class="auth-card-sub">
                    Already part of the community? <a href="<?= APP_BASE_PATH ?>/login.php" class="auth-switch-link">Log in</a>
                </p>

                <?php if (!empty($errors['general'])): ?>
                    <div class="status-alert-box danger" role="alert">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                        <strong><?= e($errors['general']) ?></strong>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= APP_BASE_PATH ?>/register.php">
                    <?= csrf_field() ?>

                    <div class="form-field">
                        <label class="form-label">Full name</label>
                        <input type="text" name="name" value="<?= e($name_val) ?>" 
                               class="form-input <?= !empty($errors['name']) ? 'input-error' : '' ?>" required placeholder="Aisha Tan">
                        <?php if (!empty($errors['name'])): ?>
                            <div class="field-error-msg"><?= e($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label class="form-label">Taylor's email</label>
                        <input type="email" name="email" value="<?= e($email_val) ?>" 
                               class="form-input <?= !empty($errors['email']) ? 'input-error' : '' ?>" required placeholder="name@sd.taylors.edu.my">
                        <?php if (!empty($errors['email'])): ?>
                            <div class="field-error-msg"><?= e($errors['email']) ?></div>
                        <?php else: ?>
                            <div class="field-help-msg">Use your @taylors.edu.my or @sd.taylors.edu.my email</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label class="form-label">Phone number <span class="auth-label-opt">(optional)</span></label>
                        <input type="tel" name="phone" value="<?= e($phone_val) ?>" 
                               class="form-input" placeholder="+6012-345 6789">
                    </div>

                    <div class="form-field">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input <?= !empty($errors['password']) ? 'input-error' : '' ?>" 
                               required placeholder="••••••••••••">
                        <?php if (!empty($errors['password'])): ?>
                            <div class="field-error-msg"><?= e($errors['password']) ?></div>
                        <?php else: ?>
                            <div class="field-help-msg">At least 8 characters, including a letter and a number</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label class="form-label">Confirm password</label>
                        <input type="password" name="confirm_password" class="form-input <?= !empty($errors['confirm_password']) ? 'input-error' : '' ?>" 
                               required placeholder="••••••••••••">
                        <?php if (!empty($errors['confirm_password'])): ?>
                            <div class="field-error-msg"><?= e($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label class="auth-checkbox-label">
                            <input type="checkbox" name="terms" value="1" checked required class="auth-checkbox">
                            <span>I agree to the Taylor's SportsHub Rules, Fair Play Guidelines and cancellation terms.</span>
                        </label>
                        <?php if (!empty($errors['terms'])): ?>
                            <div class="field-error-msg"><?= e($errors['terms']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-primary btn-auth-submit">
                        <span>Create account</span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>

                    <div class="auth-switch-prompt">
                        Already have an account? <a href="<?= APP_BASE_PATH ?>/login.php" class="auth-switch-link">Log in</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
