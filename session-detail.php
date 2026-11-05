<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$session_id = (int)($_GET['id'] ?? 0);
$db = get_db_connection();

expire_overdue_reservations($session_id);

$stmt = $db->prepare("SELECT s.*, c.name AS club_name, c.slug AS club_slug, c.bank_name, c.account_holder, c.account_number 
                      FROM sessions s 
                      INNER JOIN clubs c ON s.club_id = c.id 
                      WHERE s.id = :id LIMIT 1");
$stmt->execute([':id' => $session_id]);
$session = $stmt->fetch();

if (!$session) {
    render_error_page(404);
}

$seat_info = get_session_seat_info($session_id);
$current_user = current_user();
$existing_reg = $current_user ? get_user_session_registration($session_id, $current_user['id']) : null;
$is_mgr = $current_user ? is_club_manager($session['club_id'], $current_user['id']) : false;
if ($session['status'] === 'draft' && !$is_mgr) render_error_page(404);
$signup_status = $session['status'];
if ($signup_status === 'published' && $seat_info['is_started']) $signup_status = 'started';
elseif ($signup_status === 'published' && $seat_info['is_full']) $signup_status = 'full';

$page_title = $session['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="session-detail-page">
    <?php journey_feedback(); ?>

    <!-- Breadcrumb -->
    <div class="session-detail-breadcrumb">
        <span class="session-eyebrow">SPORTSHUB / SESSIONS</span>
    </div>

    <!-- Header Titles -->
    <h1 class="session-detail-title"><?= e($session['title']) ?></h1>
    <div class="session-detail-subtitle">
        <?= e($session['club_name']) ?> · Individual session
    </div>

    <!-- Two-Column UIUX Layout -->
    <div class="session-detail-layout">
        <!-- Left Main Column: Media & Explanations -->
        <div class="session-detail-main">
            <div class="session-media-frame">
                <img src="<?= e(get_session_cover_url($session)) ?>" alt="<?= e($session['title']) ?>">
            </div>

            <div class="session-info-card">
                <div class="session-info-section">
                    <h2>Your session, explained</h2>
                    <p><?= nl2br(e($session['description'])) ?></p>
                </div>

                <div class="session-info-section">
                    <h2>Before you join</h2>
                    <p>Taylor's student accounts only. No membership needed. Registration confirms a place, not attendance.</p>
                </div>

                <div class="session-info-section">
                    <h2>Cancellation terms</h2>
                    <p>Request cancellation before the session starts for a full refund. Refunds are transferred externally and recorded by the organiser.</p>
                </div>

                <?php if ($session['status'] === 'cancelled'): ?>
                    <div class="session-state-banner alert">
                        <strong>Session Cancelled by Organiser</strong>
                        <p style="margin: 4px 0 0;"><?= e($session['cancellation_reason'] ?: 'No reason provided.') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Sticky Summary & Reservation Card -->
        <aside class="session-detail-sidebar">
            <div class="session-summary-card">
                <div class="summary-card-header">
                    <h3 class="summary-card-title">Session details</h3>
                    <span class="session-status-badge <?= e($signup_status) ?>">
                        <?= ucfirst(e($signup_status)) ?>
                    </span>
                </div>

                <div class="session-facts-table">
                    <div class="fact-row">
                        <span class="fact-label">Date</span>
                        <span class="fact-value"><?= format_session_date($session['session_date']) ?></span>
                    </div>
                    <div class="fact-row">
                        <span class="fact-label">Time</span>
                        <span class="fact-value"><?= format_session_time_range($session['start_time'], $session['end_time']) ?> MYT</span>
                    </div>
                    <div class="fact-row">
                        <span class="fact-label">Venue</span>
                        <span class="fact-value"><?= e($session['venue']) ?></span>
                    </div>
                    <div class="fact-row">
                        <span class="fact-label">Price</span>
                        <span class="fact-value price"><?= format_price($session['price']) ?></span>
                    </div>
                    <div class="fact-row">
                        <span class="fact-label">Places</span>
                        <span class="fact-value"><?= $seat_info['occupied'] ?> / <?= $seat_info['capacity'] ?> occupied</span>
                    </div>
                    <div class="fact-row">
                        <span class="fact-label">Available</span>
                        <span class="fact-value"><?= $seat_info['seats_left'] ?> places</span>
                    </div>
                </div>

                <!-- Callout Box -->
                <div class="session-callout-box">
                    <?php if ((float)$session['price'] > 0): ?>
                        <h4>Payment required</h4>
                        <p>Reserve a place, pay externally and upload your receipt within 24 hours or before start, whichever is earlier.</p>
                    <?php else: ?>
                        <h4>Free session</h4>
                        <p>Instant confirmation upon reservation. No payment or receipt required.</p>
                    <?php endif; ?>
                </div>

                <!-- Booking / Action Area -->
                <div class="session-action-area">
                    <?php if (!$current_user && $session['status'] === 'published' && !$seat_info['is_started'] && !$seat_info['is_full']): ?>
                        <a href="<?= app_url('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/sessions.php')) ?>" class="btn-reserve">
                            Sign in to reserve
                        </a>
                    <?php elseif ($existing_reg && in_array($existing_reg['status'], ['confirmed', 'awaiting_payment', 'awaiting_approval', 'payment_rejected', 'cancellation_pending'])): ?>
                        <div class="session-registered-box">
                            <div class="session-registered-header">
                                <span style="font-size: 12.5px; color: var(--text-muted);">Your Registration:</span>
                                <div><?= get_status_badge($existing_reg['status']) ?></div>
                            </div>
                            <?php if ($existing_reg['status'] === 'awaiting_payment'): ?>
                                <p style="font-size: 12.5px; color: var(--text-secondary); margin: 6px 0 12px;">
                                    Place held! Submit receipt before <?= format_session_time($existing_reg['payment_deadline']) ?> on <?= format_session_date($existing_reg['payment_deadline']) ?>.
                                </p>
                            <?php elseif ($existing_reg['status'] === 'awaiting_approval'): ?>
                                <p style="font-size: 12.5px; color: var(--text-secondary); margin: 6px 0 12px;">
                                    Receipt uploaded. Your place is held during organiser review.
                                </p>
                            <?php elseif ($existing_reg['status'] === 'payment_rejected'): ?>
                                <p style="font-size: 12.5px; color: #d70015; margin: 6px 0 12px;">
                                    Receipt rejected: <?= e($existing_reg['rejection_reason']) ?>.
                                </p>
                            <?php endif; ?>
                            <a href="<?= app_url('/payment.php?id=' . $existing_reg['id']) ?>" class="btn-reserve">
                                View registration & next step
                            </a>
                        </div>
                    <?php elseif ($session['status'] !== 'published' || $seat_info['is_started']): ?>
                        <div class="session-state-banner">
                            <?php if ($session['status'] === 'closed'): ?>
                                Registration for this session has been closed by the organiser.
                            <?php elseif ($session['status'] === 'cancelled'): ?>
                                This session is cancelled.
                            <?php elseif ($seat_info['is_started']): ?>
                                This session has already commenced. Registration is closed.
                            <?php else: ?>
                                This session is currently in draft mode.
                            <?php endif; ?>
                        </div>
                    <?php elseif ($seat_info['is_full']): ?>
                        <div class="session-state-banner alert">
                            This session is currently full (<?= $seat_info['capacity'] ?> / <?= $seat_info['capacity'] ?>). Check back later if a spot opens up.
                        </div>
                    <?php else: ?>
                        <!-- Can Register -->
                        <form method="POST" action="<?= app_url('/reserve.php') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                            <button type="submit" class="btn-reserve">
                                <?= (float)$session['price'] <= 0 ? 'Join free session' : 'Reserve place · ' . format_price($session['price']) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Manager Controls Quick Panel -->
                <?php if ($is_mgr): ?>
                    <div class="manager-quick-box">
                        <span class="manager-quick-title">Manager Actions</span>
                        <div class="manager-quick-links">
                            <a href="<?= app_url('/manager/participants.php?session_id=' . $session['id']) ?>" class="manager-pill-btn primary">
                                Participants (<?= $seat_info['occupied'] ?>)
                            </a>
                            <a href="<?= app_url('/manager/session-edit.php?id=' . $session['id']) ?>" class="manager-pill-btn">
                                Edit Settings
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
