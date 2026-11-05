<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();
$session_id = (int)($_GET['session_id'] ?? 0);
$db = get_db_connection();

expire_overdue_reservations($session_id);

$stmt = $db->prepare("SELECT s.*, c.name AS club_name FROM sessions s INNER JOIN clubs c ON s.club_id = c.id WHERE s.id = :id LIMIT 1");
$stmt->execute([':id' => $session_id]);
$session = $stmt->fetch();

if (!$session) {
    render_error_page(404);
}

if (!is_club_manager($session['club_id'], $user['id'])) {
    render_error_page(403);
}

// Fetch all participants
$stmt = $db->prepare("SELECT r.*, u.name, u.email, u.phone 
                      FROM registrations r 
                      INNER JOIN users u ON r.user_id = u.id 
                      WHERE r.session_id = :sid 
                      ORDER BY r.created_at ASC");
$stmt->execute([':sid' => $session_id]);
$participants = $stmt->fetchAll();

$seat_info = get_session_seat_info($session_id);

$page_title = "Participants · " . $session['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="participants-container">
    <?php journey_feedback(); ?>

    <!-- Breadcrumb & Header -->
    <a href="<?= app_url('/manager/sessions.php') ?>" class="participants-back-link">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span>Back to Manager Workspace</span>
    </a>

    <div class="participants-header-row">
        <div>
            <span class="participants-badge">
                <?= e($session['sport_type']) ?> · <?= e($session['club_name']) ?>
            </span>
            <h1 class="participants-title">
                <?= e($session['title']) ?>
            </h1>
            <div class="participants-meta">
                <?= format_session_date($session['session_date']) ?> · <?= format_session_time_range($session['start_time'], $session['end_time']) ?> · <?= e($session['venue']) ?>
            </div>
        </div>

        <div class="participants-actions">
            <?php if ($session['status'] === 'published'): ?>
                <a href="<?= app_url('/manager/close-signup.php?id=' . $session['id']) ?>" class="btn-participants-control secondary">
                    Close Signup
                </a>
                <a href="<?= app_url('/manager/cancel-session.php?id=' . $session['id']) ?>" class="btn-participants-control danger">
                    Cancel Session
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Metrics Grid -->
    <div class="participants-metrics-grid">
        <div class="participants-metric-card">
            <span class="metric-card-label">Capacity</span>
            <div class="metric-card-val"><?= $seat_info['capacity'] ?></div>
        </div>
        <div class="participants-metric-card">
            <span class="metric-card-label">Places Remaining</span>
            <div class="metric-card-val <?= $seat_info['seats_left'] === 0 ? 'color-red' : 'color-green' ?>">
                <?= $seat_info['seats_left'] ?>
            </div>
        </div>
        <div class="participants-metric-card">
            <span class="metric-card-label">Active Holds</span>
            <div class="metric-card-val"><?= $seat_info['occupied'] ?></div>
        </div>
        <div class="participants-metric-card">
            <span class="metric-card-label">Price</span>
            <div class="metric-card-val color-primary"><?= format_price($session['price']) ?></div>
        </div>
    </div>

    <!-- Participants Table Card -->
    <div class="participants-card">
        <div class="participants-card-header">
            <h2 class="participants-card-title">
                Registered Students (<?= count($participants) ?>)
            </h2>
        </div>

        <?php if (empty($participants)): ?>
            <div class="participants-empty">
                <svg class="participants-empty-icon" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <h3 class="participants-empty-title">No registrations yet</h3>
                <p class="participants-empty-desc">
                    Students haven't registered for this session yet. Share your session link with students or promote it during club practice!
                </p>
                <div class="participants-empty-actions">
                    <a href="<?= app_url('/session-detail.php?id=' . $session['id']) ?>" target="_blank" class="btn-table-action primary">
                        <span>View Public Session</span>
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 17L17 7M7 7h10v10"/></svg>
                    </a>
                    <button type="button" data-copy-link="<?= app_url('/session-detail.php?id=' . $session['id']) ?>" class="btn-table-action secondary">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                        <span>Copy Link</span>
                    </button>
                    <a href="<?= app_url('/manager/sessions.php') ?>" class="btn-table-action secondary">
                        Back to Workspace
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="participants-table-wrap" tabindex="0" role="region" aria-label="Participant records; scroll horizontally for actions">
                <table class="participants-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Registered At</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $p): ?>
                            <tr>
                                <td>
                                    <strong class="student-name"><?= e($p['name']) ?></strong>
                                    <span class="student-email"><?= e($p['email']) ?></span>
                                    <?php if (!empty($p['phone'])): ?>
                                        <span class="student-phone"> · <?= e($p['phone']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= get_status_badge($p['status']) ?>
                                    <?php if (in_array($p['status'], ['awaiting_payment','payment_rejected'])): ?>
                                        <div class="deadline-note">
                                            Due: <?= format_deadline($p['payment_deadline']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($p['cancellation_requested_at'] && $p['status'] === 'awaiting_approval'): ?>
                                        <div class="cancellation-note">Cancellation requested; check settlement</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="price-text"><?= format_price($p['price_snapshot']) ?></span>
                                </td>
                                <td>
                                    <span class="date-text"><?= date('j M Y, g:i A', strtotime($p['created_at'])) ?></span>
                                </td>
                                <td class="col-action-cell">
                                    <?php if ($p['status'] === 'awaiting_approval'): ?>
                                        <a href="<?= app_url('/manager/review-receipt.php?id=' . $p['id']) ?>" class="btn-table-action primary">
                                            <span>Review Receipt</span>
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                        </a>
                                    <?php elseif ($p['status'] === 'cancellation_pending'): ?>
                                        <a href="<?= app_url('/manager/record-refund.php?id=' . $p['id']) ?>" class="btn-table-action dark">
                                            <span>Record Refund</span>
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                        </a>
                                    <?php elseif (!empty($p['receipt_path'])): ?>
                                        <?php if (str_ends_with(strtolower($p['receipt_path']), '.pdf')): ?>
                                            <a href="<?= app_url('/evidence.php?registration_id=' . $p['id']) ?>" target="_blank" rel="noopener" class="btn-table-action link">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                <span>View PDF</span>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn-table-action secondary" data-receipt-zoom="<?= app_url('/evidence.php?registration_id=' . $p['id']) ?>" data-receipt-title="Receipt · <?= e($p['name']) ?> (<?= format_price($p['price_snapshot']) ?>)">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                                <span>View Receipt</span>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="student-phone">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
