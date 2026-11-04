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
        $stmt = $db->prepare("UPDATE sessions SET status = 'closed', updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $session_id]);
        header("Location: " . app_url('/manager/sessions.php?msg=closed'));
        exit;
    }
}

$page_title = "Close Signup · " . $session['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto; padding: 4rem 1.5rem;">
    <div style="background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 6px 24px rgba(0,0,0,0.04); padding: 2.5rem; text-align: center;">
        <div style="font-size: 2.5rem; margin-bottom: 1rem;">🔒</div>
        <h1 style="font-size: 1.75rem; font-weight: 800; color: #1d1d1f; margin: 0 0 0.75rem;">
            Close Session Signup?
        </h1>
        <p style="color: #6e6e73; font-size: 0.95rem; line-height: 1.5; margin: 0 0 2rem;">
            Closing signup for <strong><?= e($session['title']) ?></strong> will prevent new students from joining or reserving spots. Existing reserved participants will keep their active payment window until their deadline expires.
        </p>

        <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" style="display: flex; justify-content: center; gap: 1rem;">
            <?= csrf_field() ?>
            <a href="<?= app_url('/manager/sessions.php') ?>" class="btn-hero-secondary" style="display: inline-block;">
                Never mind
            </a>
            <button type="submit" class="btn-hero-primary" style="border: none; cursor: pointer;">
                Confirm Close Signup
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
