<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login(app_url('/profile.php'));
$page_title = "My Profile · A little about you";

$db = get_db_connection();
$user_id = (int)$user['id'];

$info_msg = null;
$pw_msg = null;
$error_msg = null;

// Handle profile update & password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $preferred_name = trim($_POST['preferred_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $faculty = trim($_POST['faculty'] ?? '');

        if (!empty($name)) {
            $stmt = $db->prepare("UPDATE users SET name = :n, preferred_name = :p, phone = :ph, faculty = :f WHERE id = :id");
            $stmt->execute([
                ':n' => $name,
                ':p' => $preferred_name ?: null,
                ':ph' => $phone ?: null,
                ':f' => $faculty ?: null,
                ':id' => $user_id
            ]);
            $info_msg = "Profile details updated successfully.";
            $user = current_user(true);
        } else {
            $error_msg = "Full name cannot be empty.";
        }
    } elseif ($action === 'change_password') {
        $current_pw = $_POST['current_password'] ?? '';
        $new_pw = $_POST['new_password'] ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current_pw, $row['password_hash'])) {
            $error_msg = "Current password does not match.";
        } elseif (strlen($new_pw) < 8 || !preg_match('/[0-9]/', $new_pw) || !preg_match('/[a-zA-Z]/', $new_pw)) {
            $error_msg = "New password must be at least 8 characters and include a letter and a number.";
        } elseif ($new_pw !== $confirm_pw) {
            $error_msg = "New passwords do not match.";
        } else {
            $new_hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmt->execute([':hash' => $new_hash, ':id' => $user_id]);
            $pw_msg = "Password updated successfully.";
        }
    }
}

// User sports activity stats
$stmt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = :id AND status IN ('confirmed', 'awaiting_payment', 'awaiting_approval')");
$stmt->execute([':id' => $user_id]);
$active_reg_count = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = :id AND status = 'confirmed'");
$stmt->execute([':id' => $user_id]);
$confirmed_count = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = :id");
$stmt->execute([':id' => $user_id]);
$total_sessions_count = (int)$stmt->fetchColumn();

$faculty_list = [
    'The Design School',
    'School of Computer Science',
    'Taylor\'s Business School',
    'School of Media & Communication',
    'School of Bioscience',
    'School of Engineering',
    'School of Hospitality, Tourism & Events',
    'Taylor\'s Law School',
    'School of Medicine'
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="profile-page-container">
    <div class="profile-header">
        <div class="profile-eyebrow">YOUR ACCOUNT</div>
        <h1 class="profile-title">A little about you.</h1>
        <p class="profile-subtitle">
            Keep your details up to date and your campus account secure.
        </p>
    </div>

    <?php if ($info_msg): ?>
        <div class="status-alert-box success">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
            <div><?= e($info_msg) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="status-alert-box danger">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
            <div><?= e($error_msg) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($pw_msg): ?>
        <div class="status-alert-box success">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
            <div><?= e($pw_msg) ?></div>
        </div>
    <?php endif; ?>

    <div class="profile-grid-layout">
        <!-- Left: Forms -->
        <div>
            <!-- Personal Information Card -->
            <div class="profile-card">
                <h2 class="profile-card-title">Personal information</h2>

                <div class="avatar-upload-row">
                    <div class="avatar-large">
                        <?= get_initials($user['name']) ?>
                    </div>
                    <div>
                        <div style="font-size: 14px; font-weight: 700; color: var(--text-main); margin-bottom: 2px;">
                            <?= e($user['name']) ?>
                        </div>
                        <div style="font-size: 12.5px; color: var(--text-muted);">
                            Verified Taylor's Student Account
                        </div>
                    </div>
                </div>

                <form method="POST" action="<?= APP_BASE_PATH ?>/profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="update_profile">

                    <div class="profile-form-grid-2">
                        <div class="profile-form-group">
                            <label class="form-label" for="full_name">Full name</label>
                            <input type="text" id="full_name" name="name" class="form-input" value="<?= e($user['name']) ?>" required>
                        </div>

                        <div class="profile-form-group">
                            <label class="form-label" for="preferred_name">Preferred name</label>
                            <input type="text" id="preferred_name" name="preferred_name" class="form-input" value="<?= e($user['preferred_name'] ?? '') ?>" placeholder="e.g. Alex">
                        </div>
                    </div>

                    <div class="profile-form-group">
                        <label class="form-label" for="user_email">Taylor's email</label>
                        <input type="email" id="user_email" class="form-input" value="<?= e($user['email']) ?>" disabled>
                        <div class="profile-form-help">
                            Your university student email is verified. Contact campus administration to modify.
                        </div>
                    </div>

                    <div class="profile-form-grid-2">
                        <div class="profile-form-group">
                            <label class="form-label" for="phone_number">Mobile number (optional)</label>
                            <input type="text" id="phone_number" name="phone" class="form-input" value="<?= e($user['phone'] ?? '') ?>" placeholder="+60 12 345 6789">
                        </div>

                        <div class="profile-form-group">
                            <label class="form-label" for="faculty_select">School / faculty</label>
                            <select id="faculty_select" name="faculty" class="form-select">
                                <option value="">Select faculty</option>
                                <?php foreach ($faculty_list as $fac): ?>
                                    <option value="<?= e($fac) ?>" <?= ($user['faculty'] ?? '') === $fac ? 'selected' : '' ?>>
                                        <?= e($fac) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="profile-form-actions">
                        <span style="font-size: 12.5px; color: var(--text-muted);">Your contact details are kept secure.</span>
                        <button type="submit" class="hero-search-btn" style="min-height: 42px; padding: 8px 22px;">Save changes</button>
                    </div>
                </form>
            </div>

            <!-- Change Password Card -->
            <div class="profile-card">
                <h2 class="profile-card-title">Change password</h2>
                <p class="profile-card-desc">
                    Choose a strong password you don't reuse on other websites.
                </p>

                <form method="POST" action="<?= APP_BASE_PATH ?>/profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="change_password">

                    <div class="profile-form-group">
                        <label class="form-label" for="curr_pw">Current password</label>
                        <input type="password" id="curr_pw" name="current_password" class="form-input" required placeholder="••••••••••••">
                    </div>

                    <div class="profile-form-grid-2">
                        <div class="profile-form-group">
                            <label class="form-label" for="new_pw">New password</label>
                            <input type="password" id="new_pw" name="new_password" class="form-input" required placeholder="Enter new password">
                        </div>

                        <div class="profile-form-group">
                            <label class="form-label" for="conf_pw">Confirm new password</label>
                            <input type="password" id="conf_pw" name="confirm_password" class="form-input" required placeholder="Re-enter password">
                        </div>
                    </div>

                    <div class="profile-form-help" style="margin-bottom: 20px;">
                        Must be at least 8 characters, with a letter and a number.
                    </div>

                    <button type="submit" class="hero-search-btn" style="min-height: 42px; padding: 8px 22px;">Update password</button>
                </form>
            </div>
        </div>

        <!-- Right: Sidebar -->
        <aside>
            <div class="profile-card">
                <div class="profile-sidebar-user">
                    <div class="avatar-large" style="margin-bottom: 12px;">
                        <?= get_initials($user['name']) ?>
                    </div>
                    <h3 style="font-size: 20px; font-weight: 800; margin: 0; color: var(--text-main);"><?= e($user['name']) ?></h3>
                    <span class="profile-role-badge">
                        <?= $user['role'] === 'admin' ? 'Verified Administrator' : 'Verified Student' ?>
                    </span>
                    <div class="profile-user-faculty"><?= e($user['faculty'] ?? "Taylor's University") ?></div>
                    <div class="profile-user-member-since">
                        Member since <?= date('F Y', strtotime($user['created_at'])) ?>
                    </div>
                </div>

                <hr class="profile-divider">

                <div class="profile-stats-title">Your campus story</div>

                <div class="profile-stats-list">
                    <div class="profile-stat-row">
                        <span class="stat-label">Upcoming registrations</span>
                        <strong class="stat-val"><?= $active_reg_count ?></strong>
                    </div>
                    <div class="profile-stat-row">
                        <span class="stat-label">Confirmed places</span>
                        <strong class="stat-val"><?= $confirmed_count ?></strong>
                    </div>
                    <div class="profile-stat-row">
                        <span class="stat-label">Sessions booked</span>
                        <strong class="stat-val"><?= $total_sessions_count ?></strong>
                    </div>
                </div>

                <a href="<?= APP_BASE_PATH ?>/my-activities.php" class="btn-secondary" style="width: 100%; justify-content: center; padding: 10px; font-size: 13.5px; border-radius: 980px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <span>View My Activities</span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>

            <!-- Privacy Card -->
            <div class="profile-card">
                <div class="profile-privacy-box">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Private by default</span>
                </div>
                <p class="profile-privacy-desc">
                    Only your name appears on attendance lists. Your student ID, phone number and email remain strictly private.
                </p>
                <a href="<?= APP_BASE_PATH ?>/privacy.php" class="profile-privacy-link">Read our privacy notice (PDPA) ↗</a>
            </div>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
