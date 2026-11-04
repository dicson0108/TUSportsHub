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

$page_title = $session['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto; padding: 3rem 1.5rem;">
    <!-- Breadcrumb -->
    <div style="font-size: 0.88rem; color: #86868b; margin-bottom: 1.5rem;">
        <a href="<?= app_url('/sessions.php') ?>" style="color: #6e6e73; text-decoration: none;">Explore Sessions</a>
        <span> · </span>
        <a href="<?= app_url('/club.php?id=' . $session['club_id']) ?>" style="color: #6e6e73; text-decoration: none;"><?= e($session['club_name']) ?></a>
        <span> · </span>
        <span><?= e($session['title']) ?></span>
    </div>

    <!-- Main Detail Card -->
    <div style="background: #fff; border-radius: 24px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 6px 24px rgba(0,0,0,0.04); padding: 2.5rem; margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #c8102e; background: #fdf2f4; padding: 0.35rem 0.75rem; border-radius: 980px;">
                    <?= e($session['sport_type']) ?>
                </span>
                <span style="font-size: 0.88rem; color: #86868b;">Hosted by <a href="<?= app_url('/club.php?id=' . $session['club_id']) ?>" style="color: #1d1d1f; font-weight: 600; text-decoration: none;"><?= e($session['club_name']) ?></a></span>
            </div>
            <div>
                <?= get_status_badge($session['status']) ?>
            </div>
        </div>

        <h1 style="font-size: 2.25rem; font-weight: 800; color: #1d1d1f; margin: 0 0 1.25rem; letter-spacing: -0.02em; line-height: 1.2;">
            <?= e($session['title']) ?>
        </h1>

        <!-- Key Details Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; background: #fafafa; border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem;">
            <div>
                <span style="display: block; font-size: 0.8rem; color: #86868b; text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem;">Date</span>
                <span style="font-size: 1rem; font-weight: 600; color: #1d1d1f;"><?= format_session_date($session['session_date']) ?></span>
            </div>
            <div>
                <span style="display: block; font-size: 0.8rem; color: #86868b; text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem;">Time</span>
                <span style="font-size: 1rem; font-weight: 600; color: #1d1d1f;"><?= format_session_time_range($session['start_time'], $session['end_time']) ?></span>
            </div>
            <div>
                <span style="display: block; font-size: 0.8rem; color: #86868b; text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem;">Price</span>
                <span style="font-size: 1.15rem; font-weight: 700; color: #c8102e;"><?= format_price($session['price']) ?></span>
            </div>
            <div>
                <span style="display: block; font-size: 0.8rem; color: #86868b; text-transform: uppercase; font-weight: 600; margin-bottom: 0.25rem;">Places Available</span>
                <span style="font-size: 1rem; font-weight: 600; color: <?= $seat_info['is_full'] ? '#d70015' : '#248a3d' ?>;">
                    <?= $seat_info['seats_left'] ?> of <?= $seat_info['capacity'] ?> places left
                </span>
            </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.5rem;">Venue</h3>
            <p style="font-size: 0.95rem; color: #424245; margin: 0;">📍 <?= e($session['venue']) ?></p>
        </div>

        <div style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.5rem;">Session Overview</h3>
            <p style="font-size: 1rem; color: #424245; line-height: 1.6; margin: 0; white-space: pre-line;"><?= e($session['description']) ?></p>
        </div>

        <?php if ($session['status'] === 'cancelled'): ?>
            <div style="background: #fff2f2; border: 1px solid #fecdd3; border-radius: 14px; padding: 1.25rem; margin-bottom: 2rem;">
                <h4 style="color: #c81e27; margin: 0 0 0.25rem; font-size: 1rem;">Session Cancelled by Organiser</h4>
                <p style="color: #6e6e73; font-size: 0.9rem; margin: 0;"><?= e($session['cancellation_reason'] ?: 'No reason provided.') ?></p>
            </div>
        <?php endif; ?>

        <!-- Action / Booking Area -->
        <div style="border-top: 1px solid #f0f0f2; padding-top: 2rem;">
            <?php if (!$current_user): ?>
                <div style="text-align: center; padding: 1.5rem; background: #fafafa; border-radius: 16px;">
                    <p style="font-size: 1rem; color: #424245; margin: 0 0 1rem;">Sign in with your Taylor's student email to join this session.</p>
                    <a href="<?= app_url('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/sessions.php')) ?>" class="btn-hero-primary" style="display: inline-block;">
                        Sign in to Register
                    </a>
                </div>
            <?php elseif ($existing_reg && in_array($existing_reg['status'], ['confirmed', 'awaiting_payment', 'awaiting_approval', 'payment_rejected', 'cancellation_pending'])): ?>
                <!-- User has an active registration -->
                <div style="background: #fafafa; border-radius: 16px; padding: 1.75rem; border: 1px solid #e5e5ea;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <span style="font-size: 0.85rem; color: #86868b;">Your Registration Status:</span>
                            <div style="margin-top: 0.35rem;"><?= get_status_badge($existing_reg['status']) ?></div>
                        </div>
                        <?php if ($existing_reg['status'] === 'awaiting_payment' || $existing_reg['status'] === 'payment_rejected'): ?>
                            <a href="<?= app_url('/payment.php?id=' . $existing_reg['id']) ?>" class="btn-hero-primary" style="display: inline-block; font-size: 0.92rem;">
                                Complete Payment & Upload Receipt →
                            </a>
                        <?php elseif ($existing_reg['status'] === 'confirmed'): ?>
                            <a href="<?= app_url('/cancel-registration.php?id=' . $existing_reg['id']) ?>" style="color: #c81e27; text-decoration: none; font-size: 0.88rem; font-weight: 500;">
                                Cancel Registration
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($existing_reg['status'] === 'awaiting_payment'): ?>
                        <p style="font-size: 0.88rem; color: #6e6e73; margin: 0;">
                            Place held! Please transfer <?= format_price($existing_reg['price_snapshot']) ?> and upload your receipt before <?= format_session_time($existing_reg['payment_deadline']) ?> on <?= format_session_date($existing_reg['payment_deadline']) ?>.
                        </p>
                    <?php elseif ($existing_reg['status'] === 'awaiting_approval'): ?>
                        <p style="font-size: 0.88rem; color: #6e6e73; margin: 0;">
                            Receipt uploaded. The club manager will review your submission soon. Your place is secured during review.
                        </p>
                    <?php elseif ($existing_reg['status'] === 'payment_rejected'): ?>
                        <p style="font-size: 0.88rem; color: #d70015; margin: 0;">
                            Receipt rejected: <?= e($existing_reg['rejection_reason']) ?>. You have a new 24h window to resubmit your transfer receipt.
                        </p>
                    <?php endif; ?>
                </div>
            <?php elseif ($session['status'] !== 'published' || $seat_info['is_started']): ?>
                <div style="background: #f5f5f7; border-radius: 14px; padding: 1.25rem; text-align: center; color: #6e6e73;">
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
                <div style="background: #fff2f2; border-radius: 14px; padding: 1.25rem; text-align: center; color: #c81e27; font-weight: 600;">
                    This session is currently full (<?= $seat_info['capacity'] ?> / <?= $seat_info['capacity'] ?>). Check back later if a spot opens up.
                </div>
            <?php else: ?>
                <!-- Can Register -->
                <form method="POST" action="<?= app_url('/reserve.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #1d1d1f;">
                                <?= (float)$session['price'] <= 0 ? 'Join Free Session' : 'Reserve Place for ' . format_price($session['price']) ?>
                            </div>
                            <div style="font-size: 0.85rem; color: #86868b;">
                                <?= (float)$session['price'] <= 0 ? 'Instant confirmation' : '24-hour hold to complete bank transfer & receipt upload' ?>
                            </div>
                        </div>

                        <button type="submit" class="btn-hero-primary" style="border: none; cursor: pointer;">
                            <?= (float)$session['price'] <= 0 ? 'Join Session Now' : 'Reserve Place →' ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Manager Controls Banner if User is Club Manager -->
            <?php if ($is_mgr): ?>
                <div style="margin-top: 2rem; padding: 1.25rem; background: #fdf2f4; border: 1px dashed #fecdd3; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <strong style="color: #c8102e; font-size: 0.95rem;">Manager Quick Actions</strong>
                        <div style="font-size: 0.85rem; color: #6e6e73;">You are an authorised manager for <?= e($session['club_name']) ?>.</div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <a href="<?= app_url('/manager/participants.php?session_id=' . $session['id']) ?>" style="padding: 0.5rem 1rem; background: #c8102e; color: #fff; text-decoration: none; border-radius: 980px; font-size: 0.85rem; font-weight: 600;">
                            View Participants (<?= $seat_info['occupied'] ?>)
                        </a>
                        <a href="<?= app_url('/manager/session-edit.php?id=' . $session['id']) ?>" style="padding: 0.5rem 1rem; background: #fff; color: #1d1d1f; text-decoration: none; border-radius: 980px; font-size: 0.85rem; font-weight: 500; border: 1px solid #d2d2d7;">
                            Session Settings
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
