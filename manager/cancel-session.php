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

<div style="max-width: 640px; margin: 0 auto; padding: 4rem 1.5rem;">
    <div style="background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 6px 24px rgba(0,0,0,0.04); padding: 2.5rem;">
        <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #c8102e; background: #fdf2f4; padding: 0.35rem 0.75rem; border-radius: 980px; display: inline-block; margin-bottom: 0.75rem;">
            Emergency Action
        </span>
        <h1 style="font-size: 1.85rem; font-weight: 800; color: #1d1d1f; margin: 0 0 0.75rem; letter-spacing: -0.02em;">
            Cancel Session?
        </h1>
        <p style="color: #6e6e73; font-size: 0.95rem; line-height: 1.5; margin: 0 0 1.5rem;">
            Cancelling <strong><?= e($session['title']) ?></strong> will close registrations permanently. Any paid participants will have their registrations queued for a full external refund.
        </p>

        <?php if ($error): ?>
            <div style="background: #fff2f2; border: 1px solid #fecdd3; color: #c81e27; padding: 0.85rem 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>">
            <?= csrf_field() ?>

            <div style="margin-bottom: 1.75rem;">
                <label for="cancellation_reason" style="display: block; font-size: 0.88rem; font-weight: 600; color: #1d1d1f; margin-bottom: 0.4rem;">
                    Reason for Cancellation
                </label>
                <textarea id="cancellation_reason" name="cancellation_reason" rows="3" required placeholder="e.g. Venue maintenance, adverse weather, or emergency coach unavailability..." style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box; font-family: inherit;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="<?= app_url('/manager/sessions.php') ?>" class="btn-hero-secondary" style="display: inline-block;">
                    Keep Session
                </a>
                <button type="submit" style="padding: 0.85rem 1.5rem; background: #c8102e; color: #fff; border: none; border-radius: 980px; font-weight: 600; font-size: 0.95rem; cursor: pointer;">
                    Confirm & Cancel Session
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
