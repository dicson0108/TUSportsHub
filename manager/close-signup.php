<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();
$session_id = (int)($_GET['id'] ?? 0);
$db = get_db_connection();

$stmt = $db->prepare("SELECT s.*, c.name AS club_name FROM sessions s INNER JOIN clubs c ON s.club_id = c.id WHERE s.id = :id LIMIT 1");
$stmt->execute([':id' => $session_id]);
$session = $stmt->fetch();

if (!$session) {
    render_error_page(404);
}

if (!is_club_manager($session['club_id'], $user['id'])) {
    render_error_page(403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $stmt = $db->prepare("UPDATE sessions SET status = 'closed', updated_at = NOW() WHERE id = :id AND status = 'published'");
        $stmt->execute([':id' => $session_id]);
        header("Location: " . app_url('/manager/participants.php?session_id=' . $session_id . '&msg=' . ($stmt->rowCount() ? 'closed' : 'invalid_status')));
        exit;
    }
}

$page_title = "Close Signup · " . $session['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="manager-confirm-box">
    <div class="manager-confirm-card">
        <div class="manager-confirm-icon">
            <svg width="44" height="44" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <h1 class="manager-confirm-title">
            Close Session Signup?
        </h1>
        <p class="manager-confirm-desc">
            Closing signup for <strong><?= e($session['title']) ?></strong> will prevent new students from joining or reserving spots. Existing reserved participants will keep their active payment window until their deadline expires.
        </p>

        <form method="POST" class="manager-confirm-actions">
            <?= csrf_field() ?>
            <a href="<?= app_url('/manager/sessions.php') ?>" class="manager-btn manager-btn-secondary">
                Nevermind
            </a>
            <button type="submit" class="manager-btn manager-btn-danger">
                Confirm Close Signup
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
