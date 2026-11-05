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

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please try again.";
    } else {
        $reason = trim($_POST['cancellation_reason'] ?? '');
        if (empty($reason)) {
            $error = "Please provide an explicit reason for cancelling this session.";
        } else {
            $db->beginTransaction();
            try {
                $lock = $db->prepare('SELECT status FROM sessions WHERE id=? FOR UPDATE');
                $lock->execute([$session_id]);
                if ($lock->fetchColumn() === 'cancelled') {
                    $db->rollBack();
                    header('Location: ' . app_url('/manager/sessions.php?msg=session_cancelled'));
                    exit;
                }
                // Cancel session
                $stmt = $db->prepare("UPDATE sessions SET status = 'cancelled', cancellation_reason = :reason, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':reason' => $reason, ':id' => $session_id]);

                // Update paid confirmed registrations -> cancellation_pending for refund
                $stmt = $db->prepare("UPDATE registrations 
                                      SET status = 'cancellation_pending', cancellation_requested_at = NOW(), 
                                          cancellation_reason = :reason, updated_at = NOW() 
                                      WHERE session_id = :sid AND status = 'confirmed' AND price_snapshot > 0");
                $stmt->execute([':reason' => 'Session cancelled by organiser: ' . $reason, ':sid' => $session_id]);

                // Uploaded evidence needs an external settlement decision even after cancellation.
                $stmt = $db->prepare("UPDATE registrations SET cancellation_requested_at=COALESCE(cancellation_requested_at,NOW()), cancellation_reason=:reason WHERE session_id=:sid AND status='awaiting_approval' AND price_snapshot>0");
                $stmt->execute([':reason'=>'Session cancelled by organiser: '.$reason, ':sid'=>$session_id]);
                $stmt = $db->prepare("UPDATE registrations SET status='cancelled', cancellation_reason=:reason, updated_at=NOW() WHERE session_id=:sid AND status IN ('awaiting_payment','payment_rejected')");
                $stmt->execute([':reason'=>'Session cancelled by organiser: '.$reason, ':sid'=>$session_id]);

                // Update free or awaiting registrations -> cancelled
                $stmt = $db->prepare("UPDATE registrations 
                                      SET status = 'cancelled', cancellation_reason = :reason, updated_at = NOW() 
                                      WHERE session_id = :sid AND status IN ('confirmed', 'awaiting_payment', 'awaiting_approval', 'payment_rejected') AND price_snapshot <= 0");
                $stmt->execute([':reason' => 'Session cancelled by organiser: ' . $reason, ':sid' => $session_id]);

                $db->commit();
                header("Location: " . app_url('/manager/sessions.php?msg=session_cancelled'));
                exit;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        }
    }
}

$page_title = "Cancel Session · " . $session['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="manager-confirm-box">
    <div class="manager-confirm-card">
        <span class="manager-eyebrow">
            Emergency Action
        </span>
        <h1 class="manager-confirm-title">
            Cancel Session?
        </h1>
        <p class="manager-confirm-desc">
            Cancelling <strong><?= e($session['title']) ?></strong> will close registrations permanently. Any paid participants will have their registrations queued for a full external refund.
        </p>

        <?php if ($error): ?>
            <div class="manager-alert-error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" class="manager-cancellation-form">
            <?= csrf_field() ?>

            <div class="form-field">
                <label for="cancellation_reason" class="auth-label">
                    Reason for Cancellation
                </label>
                <textarea id="cancellation_reason" name="cancellation_reason" rows="3" required placeholder="e.g. Venue maintenance, adverse weather, or emergency coach unavailability..." class="form-input"></textarea>
            </div>

            <div class="manager-confirm-actions">
                <a href="<?= app_url('/manager/sessions.php') ?>" class="manager-btn manager-btn-secondary">
                    Keep Session
                </a>
                <button type="submit" class="manager-btn manager-btn-danger">
                    Confirm & Cancel Session
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
