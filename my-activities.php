<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();
$db = get_db_connection();

expire_overdue_reservations();

// Fetch student's registrations
$stmt = $db->prepare("SELECT r.*, s.title, s.sport_type, s.venue, s.session_date, s.start_time, s.end_time, s.status AS session_status, s.image_path,
                             c.name AS club_name, c.slug AS club_slug 
                      FROM registrations r 
                      INNER JOIN sessions s ON r.session_id = s.id 
                      INNER JOIN clubs c ON s.club_id = c.id 
                      WHERE r.user_id = :uid 
                      ORDER BY s.session_date DESC, s.start_time DESC");
$stmt->execute([':uid' => $user['id']]);
$all_registrations = $stmt->fetchAll();

$confirmed_list = [];
$awaiting_payment_list = [];
$awaiting_approval_list = [];
$cancelled_list = [];

foreach ($all_registrations as $r) {
    if ($r['status'] === 'confirmed') {
        $confirmed_list[] = $r;
    } elseif (in_array($r['status'], ['awaiting_payment', 'payment_rejected'])) {
        $awaiting_payment_list[] = $r;
    } elseif ($r['status'] === 'awaiting_approval') {
        $awaiting_approval_list[] = $r;
    } else {
        $cancelled_list[] = $r;
    }
}

// Active tab filter from query
$active_tab = $_GET['tab'] ?? 'all';

if ($active_tab === 'confirmed') {
    $display_list = $confirmed_list;
} elseif ($active_tab === 'payment') {
    $display_list = $awaiting_payment_list;
} elseif ($active_tab === 'review') {
    $display_list = $awaiting_approval_list;
} elseif ($active_tab === 'cancelled') {
    $display_list = $cancelled_list;
} else {
    $display_list = $all_registrations;
}

$page_title = "My Activities · Taylor's SportsHub";
require_once __DIR__ . '/includes/header.php';
?>

<div class="app-container" style="max-width: 1100px; margin: 0 auto; padding: 32px 1.5rem 64px;">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div class="section-label" style="font-size: 11.5px; font-weight: 700; color: #c8102e; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 6px;">
                STUDENT SPORTS CALENDAR
            </div>
            <h1 style="font-size: 38px; font-weight: 800; color: #1d1d1f; margin: 0 0 8px; letter-spacing: -0.03em; line-height: 1.1;">
                My Sports Activities
            </h1>
            <p style="color: var(--text-secondary, #424245); font-size: 15px; margin: 0; max-width: 540px;">
                Track your active session places, payment review decisions, and play history across Taylor's.
            </p>
        </div>
        <a href="<?= APP_BASE_PATH ?>/sessions.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 980px; text-decoration: none; font-size: 14px; font-weight: 600; background: #c8102e; color: #fff;">
            <span>Explore sessions</span>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
    </div>

    <!-- Stats Summary Cards (Matching 26TUEventManagement) -->
    <div class="stats-summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 32px;">
        <div class="stat-metric-card" style="background: #fff; border-radius: 18px; padding: 22px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
            <div class="stat-metric-label" style="font-size: 12.5px; font-weight: 600; color: #86868b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px;">Confirmed places</div>
            <div class="stat-metric-value" style="font-size: 34px; font-weight: 800; color: #1d1d1f; line-height: 1; margin-bottom: 6px;"><?= count($confirmed_list) ?></div>
            <div class="stat-metric-sub" style="font-size: 13px; color: #248a3d; font-weight: 500;">Ready on court</div>
        </div>

        <div class="stat-metric-card" style="background: #fff; border-radius: 18px; padding: 22px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
            <div class="stat-metric-label" style="font-size: 12.5px; font-weight: 600; color: #86868b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px;">Awaiting payment</div>
            <div class="stat-metric-value" style="font-size: 34px; font-weight: 800; color: #1d1d1f; line-height: 1; margin-bottom: 6px;"><?= count($awaiting_payment_list) ?></div>
            <div class="stat-metric-sub" style="font-size: 13px; color: #b25000; font-weight: 500;">Needs receipt upload</div>
        </div>

        <div class="stat-metric-card" style="background: #fff; border-radius: 18px; padding: 22px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
            <div class="stat-metric-label" style="font-size: 12.5px; font-weight: 600; color: #86868b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px;">Under review</div>
            <div class="stat-metric-value" style="font-size: 34px; font-weight: 800; color: #1d1d1f; line-height: 1; margin-bottom: 6px;"><?= count($awaiting_approval_list) ?></div>
            <div class="stat-metric-sub" style="font-size: 13px; color: #0071e3; font-weight: 500;">Organiser checking receipt</div>
        </div>
    </div>

    <!-- Tabs Nav -->
    <div class="tab-nav-row" style="display: flex; gap: 8px; border-bottom: 1px solid rgba(0,0,0,0.08); margin-bottom: 28px; overflow-x: auto; padding-bottom: 2px;">
        <a href="?tab=all" class="tab-item <?= $active_tab === 'all' ? 'active' : '' ?>" style="padding: 10px 16px; font-size: 14px; font-weight: 600; text-decoration: none; border-bottom: 2px solid <?= $active_tab === 'all' ? '#c8102e' : 'transparent' ?>; color: <?= $active_tab === 'all' ? '#c8102e' : '#6e6e73' ?>; white-space: nowrap;">
            All (<?= count($all_registrations) ?>)
        </a>
        <a href="?tab=confirmed" class="tab-item <?= $active_tab === 'confirmed' ? 'active' : '' ?>" style="padding: 10px 16px; font-size: 14px; font-weight: 600; text-decoration: none; border-bottom: 2px solid <?= $active_tab === 'confirmed' ? '#c8102e' : 'transparent' ?>; color: <?= $active_tab === 'confirmed' ? '#c8102e' : '#6e6e73' ?>; white-space: nowrap;">
            Confirmed (<?= count($confirmed_list) ?>)
        </a>
        <a href="?tab=payment" class="tab-item <?= $active_tab === 'payment' ? 'active' : '' ?>" style="padding: 10px 16px; font-size: 14px; font-weight: 600; text-decoration: none; border-bottom: 2px solid <?= $active_tab === 'payment' ? '#c8102e' : 'transparent' ?>; color: <?= $active_tab === 'payment' ? '#c8102e' : '#6e6e73' ?>; white-space: nowrap;">
            Awaiting Payment (<?= count($awaiting_payment_list) ?>)
        </a>
        <a href="?tab=review" class="tab-item <?= $active_tab === 'review' ? 'active' : '' ?>" style="padding: 10px 16px; font-size: 14px; font-weight: 600; text-decoration: none; border-bottom: 2px solid <?= $active_tab === 'review' ? '#c8102e' : 'transparent' ?>; color: <?= $active_tab === 'review' ? '#c8102e' : '#6e6e73' ?>; white-space: nowrap;">
            Under Review (<?= count($awaiting_approval_list) ?>)
        </a>
        <a href="?tab=cancelled" class="tab-item <?= $active_tab === 'cancelled' ? 'active' : '' ?>" style="padding: 10px 16px; font-size: 14px; font-weight: 600; text-decoration: none; border-bottom: 2px solid <?= $active_tab === 'cancelled' ? '#c8102e' : 'transparent' ?>; color: <?= $active_tab === 'cancelled' ? '#c8102e' : '#6e6e73' ?>; white-space: nowrap;">
            Cancelled / Past (<?= count($cancelled_list) ?>)
        </a>
    </div>

    <!-- Activity Items List -->
    <?php if (empty($display_list)): ?>
        <div style="background: #fff; border-radius: 20px; padding: 4rem 2rem; text-align: center; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 4px 18px rgba(0,0,0,0.03);">
            <div style="font-size: 2.75rem; margin-bottom: 1rem;">🏸</div>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.5rem; letter-spacing: -0.02em;">
                No activities in this category
            </h3>
            <p style="color: #6e6e73; font-size: 0.95rem; margin: 0 0 1.5rem; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                You don't have any sessions matching the selected filter right now. Discover open games across campus and reserve your place.
            </p>
            <a href="<?= APP_BASE_PATH ?>/sessions.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; border-radius: 980px; background: #c8102e; color: #fff; text-decoration: none; font-weight: 600; font-size: 14px;">
                <span>Browse upcoming sessions</span>
                <span>→</span>
            </a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <?php foreach ($display_list as $r): 
                $cover_url = get_session_cover_url($r);
            ?>
                <article style="background: #fff; border-radius: 18px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 10px rgba(0,0,0,0.03); overflow: hidden; display: flex; align-items: stretch; transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <!-- Thumbnail WebP (From UIUX Designs specification) -->
                    <div style="width: 200px; min-width: 200px; background: #141824; position: relative; overflow: hidden;">
                        <img src="<?= e($cover_url) ?>" alt="<?= e($r['title']) ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;" loading="lazy">
                        <div style="position: absolute; top: 10px; left: 10px;">
                            <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #fff; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); padding: 3px 8px; border-radius: 980px;">
                                <?= e($r['sport_type']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Content Body -->
                    <div style="padding: 20px 24px; flex: 1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        <div style="flex: 1; min-width: 260px;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <?= get_status_badge($r['status']) ?>
                                <span style="font-size: 12.5px; font-weight: 500; color: #86868b;"><?= e($r['club_name']) ?></span>
                            </div>

                            <h3 style="font-size: 18px; font-weight: 700; color: #1d1d1f; margin: 0 0 6px;">
                                <a href="<?= APP_BASE_PATH ?>/session-detail.php?id=<?= $r['session_id'] ?>" style="color: inherit; text-decoration: none;">
                                    <?= e($r['title']) ?>
                                </a>
                            </h3>

                            <div style="font-size: 13.5px; color: #424245; display: flex; flex-direction: column; gap: 3px;">
                                <div>📅 <?= format_session_date($r['session_date']) ?> · <?= format_session_time_range($r['start_time'], $r['end_time']) ?></div>
                                <div>📍 <?= e($r['venue']) ?> · 💰 <?= format_price($r['price_snapshot']) ?></div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                            <?php if ($r['status'] === 'awaiting_payment' || $r['status'] === 'payment_rejected'): ?>
                                <a href="<?= APP_BASE_PATH ?>/payment.php?id=<?= $r['id'] ?>" class="btn-primary" style="padding: 8px 18px; background: #c8102e; color: #fff; text-decoration: none; border-radius: 980px; font-weight: 600; font-size: 13.5px; display: inline-flex; align-items: center; gap: 4px;">
                                    <span>Upload Receipt</span>
                                    <span>→</span>
                                </a>
                                <?php if (!empty($r['payment_deadline'])): ?>
                                    <span style="font-size: 12px; color: #d70015; font-weight: 500;">
                                        Due <?= date('j M, g:i A', strtotime($r['payment_deadline'])) ?>
                                    </span>
                                <?php endif; ?>
                            <?php elseif ($r['status'] === 'awaiting_approval'): ?>
                                <a href="<?= APP_BASE_PATH ?>/payment.php?id=<?= $r['id'] ?>" style="padding: 8px 18px; background: #f0f7ff; color: #0071e3; text-decoration: none; border-radius: 980px; font-weight: 600; font-size: 13.5px; border: 1px solid #cce5ff;">
                                    Receipt Under Review
                                </a>
                            <?php elseif ($r['status'] === 'confirmed'): ?>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <a href="<?= APP_BASE_PATH ?>/cancel-registration.php?id=<?= $r['id'] ?>" style="padding: 6px 14px; font-size: 12.5px; color: #6e6e73; text-decoration: none; border-radius: 980px; border: 1px solid #d2d2d7; background: #fff;">
                                        Cancel reservation
                                    </a>
                                    <a href="<?= APP_BASE_PATH ?>/session-detail.php?id=<?= $r['session_id'] ?>" style="padding: 6px 14px; font-size: 12.5px; color: #1d1d1f; text-decoration: none; border-radius: 980px; background: #f5f5f7; font-weight: 600;">
                                        Session details
                                    </a>
                                </div>
                            <?php else: ?>
                                <a href="<?= APP_BASE_PATH ?>/session-detail.php?id=<?= $r['session_id'] ?>" style="padding: 6px 14px; font-size: 12.5px; color: #6e6e73; text-decoration: none; border-radius: 980px; background: #f5f5f7;">
                                    View Session
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
