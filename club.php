<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$club_id = (int)($_GET['id'] ?? 0);
$db = get_db_connection();

$stmt = $db->prepare("SELECT * FROM clubs WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $club_id]);
$club = $stmt->fetch();

if (!$club) {
    render_error_page(404);
}

// Fetch manager(s)
$stmt = $db->prepare("SELECT u.name, u.email FROM club_managers cm INNER JOIN users u ON cm.user_id = u.id WHERE cm.club_id = :id");
$stmt->execute([':id' => $club_id]);
$managers = $stmt->fetchAll();

// Fetch sessions
$stmt = $db->prepare("SELECT * FROM sessions WHERE club_id = :id AND status = 'published' ORDER BY session_date ASC, start_time ASC");
$stmt->execute([':id' => $club_id]);
$sessions = $stmt->fetchAll();

$page_title = $club['name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="club-page-container">
    <!-- Breadcrumb -->
    <div class="club-breadcrumb">
        <span class="club-eyebrow"><a href="<?= app_url('/clubs.php') ?>" style="color: inherit; text-decoration: none;">SPORTS CLUBS</a> / <?= strtoupper(e($club['sport_type'])) ?></span>
    </div>

    <!-- Title and Subtitle matching UIUX Designs -->
    <h1 class="club-profile-title"><?= e($club['name']) ?></h1>
    <p class="club-profile-subtitle">Official Student Sports Club · Lakeside Campus</p>

    <!-- Wide Photography Banner -->
    <div class="club-banner-frame">
        <img src="<?= e(get_club_banner_url($club)) ?>" alt="<?= e($club['name']) ?>">
    </div>

    <!-- Intro Card matching Figma (03-club-profile.png) -->
    <div class="club-intro-card">
        <h2 class="club-intro-title">Find your people on court</h2>
        <p class="club-intro-desc">
            <?= e($club['description']) ?>
        </p>

        <div class="club-facts-grid">
            <div class="club-fact-col">
                <span class="label">Who can join</span>
                <span class="value">Active Taylor's student accounts</span>
            </div>
            <div class="club-fact-col">
                <span class="label">Where</span>
                <span class="value">Taylor's Lakeside Sports Centre</span>
            </div>
            <div class="club-fact-col">
                <span class="label">Registration</span>
                <span class="value">Individual · free and paid sessions</span>
            </div>
        </div>

        <div class="club-meta-strip">
            <div class="club-meta-item">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span><strong>Appointed Managers:</strong> 
                    <?php if (empty($managers)): ?>
                        Seeded management pending
                    <?php else: ?>
                        <?= implode(', ', array_map(fn($m) => e($m['name']), $managers)) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="club-meta-item">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                <span><strong>Payment Details:</strong> <?= e($club['bank_name']) ?> (<?= e($club['account_number']) ?>)</span>
            </div>
        </div>
    </div>

    <!-- Sessions Hosted by this Club -->
    <section class="club-sessions-section">
        <h2 class="club-section-title">Sessions from this club</h2>

        <?php if (empty($sessions)): ?>
            <div class="empty-state-box">
                <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 14.14 14.14"/></svg>
                <h3 class="empty-state-title">No upcoming sessions published</h3>
                <p class="empty-state-desc">
                    This club doesn't have active sessions scheduled right now. Explore all upcoming sessions across the campus!
                </p>
                <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                    <a href="<?= app_url('/sessions.php') ?>" class="btn-primary" style="padding: 0.65rem 1.5rem; border-radius: 980px;">Browse All Sessions →</a>
                    <a href="<?= app_url('/clubs.php') ?>" class="btn-secondary" style="padding: 0.65rem 1.5rem; border-radius: 980px;">All Clubs</a>
                </div>
            </div>
        <?php else: ?>
            <div class="sessions-grid">
                <?php foreach ($sessions as $sess): 
                    $seat_info = get_session_seat_info($sess['id']); 
                    $cover_url = get_session_cover_url($sess);
                ?>
                    <a href="<?= app_url('/session-detail.php?id=' . $sess['id']) ?>" class="session-card-link">
                        <div class="session-card-media">
                            <img src="<?= e($cover_url) ?>" alt="<?= e($sess['title']) ?>" loading="lazy">
                            <div class="session-badge-overlay">
                                <span class="badge-sport">
                                    <?= e($sess['sport_type']) ?>
                                </span>
                                <span class="badge-price">
                                    <?= format_price($sess['price']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="session-card-body">
                            <div>
                                <h3 class="session-card-title">
                                    <?= e($sess['title']) ?>
                                </h3>
                                <div class="session-card-meta-list">
                                    <div class="session-meta-item">
                                        <svg class="session-meta-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                                        <span><?= format_session_date($sess['session_date']) ?></span>
                                    </div>
                                    <div class="session-meta-item">
                                        <svg class="session-meta-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        <span><?= format_session_time_range($sess['start_time'], $sess['end_time']) ?></span>
                                    </div>
                                    <div class="session-meta-item">
                                        <svg class="session-meta-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                        <span><?= e($sess['venue']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="session-card-footer">
                                <span class="seats-status <?= $seat_info['is_full'] ? 'full' : 'open' ?>">
                                    <?= $seat_info['is_full'] ? 'Full' : $seat_info['seats_left'] . ' places left' ?>
                                </span>
                                <span class="card-action-indicator">
                                    <span>View session</span>
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
