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

// Fetch sessions
$stmt = $db->prepare("SELECT * FROM sessions WHERE club_id = :id AND status = 'published' ORDER BY session_date ASC, start_time ASC");
$stmt->execute([':id' => $club_id]);
$sessions = $stmt->fetchAll();

// Club metadata dictionary for Figma exact match
$club_metadata = [
    1 => [
        'initials' => 'BC',
        'badge_bg' => 'linear-gradient(135deg, #1b4931 0%, #0d281a 100%)',
        'members_count' => '186 members',
        'est_year' => 'Est. 2009',
        'membership_price' => 30,
        'schedule' => 'Training every Tuesday, 8–10 PM',
        'email' => 'badminton.club@taylors.edu.my',
        'social' => '@taylorsbadminton',
        'committee' => [
            ['initials' => 'NH', 'name' => 'Nurul Huda', 'role' => 'President', 'color' => '#fecdd3', 'text' => '#9f1239'],
            ['initials' => 'KO', 'name' => 'Kevin Ong', 'role' => 'Vice president', 'color' => '#dbeafe', 'text' => '#1e40af'],
            ['initials' => 'TM', 'name' => 'Tan Mei Xin', 'role' => 'Treasurer', 'color' => '#f3e8ff', 'text' => '#6b21a8'],
            ['initials' => 'FA', 'name' => 'Farhan Aziz', 'role' => 'Committee member', 'color' => '#ccfbf1', 'text' => '#115e59']
        ]
    ],
    2 => [
        'initials' => 'BB',
        'badge_bg' => 'linear-gradient(135deg, #7c2d12 0%, #431407 100%)',
        'members_count' => '142 members',
        'est_year' => 'Est. 2014',
        'membership_price' => 25,
        'schedule' => 'Training every Friday, 7–10 PM',
        'email' => 'basketball.club@taylors.edu.my',
        'social' => '@taylorsbasketball',
        'committee' => [
            ['initials' => 'JL', 'name' => 'Jason Lim', 'role' => 'President', 'color' => '#fecdd3', 'text' => '#9f1239'],
            ['initials' => 'MS', 'name' => 'Marcus See', 'role' => 'Vice president', 'color' => '#dbeafe', 'text' => '#1e40af'],
            ['initials' => 'WT', 'name' => 'Wong Ting', 'role' => 'Treasurer', 'color' => '#f3e8ff', 'text' => '#6b21a8'],
            ['initials' => 'AL', 'name' => 'Aaron Lee', 'role' => 'Committee member', 'color' => '#ccfbf1', 'text' => '#115e59']
        ]
    ],
    3 => [
        'initials' => 'PB',
        'badge_bg' => 'linear-gradient(135deg, #0369a1 0%, #0c4a6e 100%)',
        'members_count' => '94 members',
        'est_year' => 'Est. 2024',
        'membership_price' => 35,
        'schedule' => 'Training every Wednesday, 7–9 PM',
        'email' => 'pickleball.club@taylors.edu.my',
        'social' => '@taylorspickleball',
        'committee' => [
            ['initials' => 'RK', 'name' => 'Ryan Khoo', 'role' => 'President', 'color' => '#fecdd3', 'text' => '#9f1239'],
            ['initials' => 'SL', 'name' => 'Sarah Lim', 'role' => 'Vice president', 'color' => '#dbeafe', 'text' => '#1e40af'],
            ['initials' => 'DZ', 'name' => 'Daniel Zhang', 'role' => 'Treasurer', 'color' => '#f3e8ff', 'text' => '#6b21a8'],
            ['initials' => 'CW', 'name' => 'Chloe Wong', 'role' => 'Committee member', 'color' => '#ccfbf1', 'text' => '#115e59']
        ]
    ]
];

$meta = $club_metadata[$club_id] ?? [
    'initials' => strtoupper(substr($club['name'], 0, 2)),
    'badge_bg' => 'linear-gradient(135deg, #374151 0%, #111827 100%)',
    'members_count' => '75 members',
    'est_year' => 'Est. 2020',
    'membership_price' => 30,
    'schedule' => 'Regular weekly training',
    'email' => 'sports@taylors.edu.my',
    'social' => '@taylorsports',
    'committee' => [
        ['initials' => 'TC', 'name' => 'Taylor Captain', 'role' => 'President', 'color' => '#dbeafe', 'text' => '#1e40af'],
        ['initials' => 'SC', 'name' => 'Secretary Club', 'role' => 'Secretary', 'color' => '#f3e8ff', 'text' => '#6b21a8']
    ]
];

$page_title = $club['name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="club-page-container">
    <!-- Breadcrumb -->
    <nav class="club-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= app_url('/sessions.php') ?>">Explore sessions</a> / 
        <a href="<?= app_url('/clubs.php') ?>">Sports clubs</a> / 
        <span class="breadcrumb-current"><?= e($club['name']) ?></span>
    </nav>

    <!-- Top Banner Frame -->
    <div class="club-banner-frame">
        <img src="<?= e(get_club_banner_url($club)) ?>" alt="<?= e($club['name']) ?> Banner" loading="eager">
    </div>

    <!-- Identity Bar / Club Header Card (Matching Figma) -->
    <div class="club-identity-card">
        <div class="club-identity-left">
            <div class="club-squircle-badge" style="background: <?= $meta['badge_bg'] ?>;">
                <span><?= e($meta['initials']) ?></span>
            </div>
            <div class="club-identity-details">
                <h1 class="club-profile-title"><?= e($club['name']) ?></h1>
                <div class="club-profile-meta-row">
                    <span class="badge-registered-club">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>Registered sports club</span>
                    </span>
                    <span class="club-meta-separator">·</span>
                    <span class="club-meta-text"><?= e($club['sport_type']) ?> · <?= e($meta['members_count']) ?> · <?= e($meta['est_year']) ?></span>
                </div>
            </div>
        </div>

        <div class="club-identity-actions">
            <button type="button" class="btn-club-apply" onclick="showClubApplyModal()">
                <span>Apply for membership</span>
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
            <button type="button" class="btn-club-share" onclick="shareClubLink()">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                <span>Share</span>
            </button>
        </div>
    </div>

    <!-- Two-Column Profile Layout (Matching 03-club-profile.png) -->
    <div class="club-profile-grid">
        <!-- Left Main Column -->
        <div class="club-grid-main">
            <!-- Card 1: About the club -->
            <section class="club-content-card">
                <h2 class="club-card-heading">About the club</h2>
                <p class="club-about-text">
                    <?= nl2br(e($club['description'])) ?>
                </p>
            </section>

            <!-- Card 2: Upcoming sessions & tournaments -->
            <section class="club-content-card">
                <div class="club-card-header-split">
                    <h2 class="club-card-heading">Upcoming sessions & tournaments</h2>
                    <span class="club-sessions-count-badge"><?= count($sessions) ?> upcoming</span>
                </div>

                <?php if (empty($sessions)): ?>
                    <div class="club-empty-sessions">
                        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 14.14 14.14"/></svg>
                        <p>No upcoming sessions published for this club right now.</p>
                        <a href="<?= app_url('/sessions.php') ?>" class="btn-secondary" style="padding: 6px 16px; border-radius: 980px; font-size: 13px;">Browse all sessions</a>
                    </div>
                <?php else: ?>
                    <div class="club-sessions-list">
                        <?php foreach ($sessions as $s): 
                            $date_obj = new DateTime($s['session_date']);
                            $day_num = $date_obj->format('j');
                            $month_abbr = strtoupper($date_obj->format('M'));
                            $day_name = $date_obj->format('D');
                            $is_free = (float)$s['price'] <= 0;
                            $seat_info = get_session_seat_info($s['id']);
                        ?>
                            <a href="<?= app_url('/session-detail.php?id=' . $s['id']) ?>" class="club-session-row-item">
                                <div class="club-session-date-tile">
                                    <span class="date-day"><?= e($day_num) ?></span>
                                    <span class="date-month"><?= e($month_abbr) ?></span>
                                </div>
                                <div class="club-session-info-col">
                                    <div class="club-session-title-line">
                                        <h3 class="club-session-title"><?= e($s['title']) ?></h3>
                                        <?php if ($is_free): ?>
                                            <span class="pill-badge pill-free">Free entry</span>
                                        <?php else: ?>
                                            <span class="pill-badge pill-blue">Guests welcome</span>
                                        <?php endif; ?>
                                        <?php if (stripos($s['title'], 'tournament') !== false || stripos($s['title'], 'cup') !== false || stripos($s['title'], 'shootout') !== false): ?>
                                            <span class="pill-badge pill-purple">Tournament</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="club-session-subline">
                                        <span><?= e($day_name) ?> · <?= date('g:i A', strtotime($s['start_time'])) ?> · <?= e($s['venue']) ?></span>
                                    </div>
                                </div>
                                <div class="club-session-price-action">
                                    <span class="club-session-price">
                                        <?= $is_free ? 'Free' : ('From ' . format_price($s['price'])) ?>
                                    </span>
                                    <svg class="club-session-arrow" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Card 3: Committee -->
            <section class="club-content-card">
                <h2 class="club-card-heading">Committee</h2>
                <div class="club-committee-grid">
                    <?php foreach ($meta['committee'] as $member): ?>
                        <div class="committee-member-item">
                            <div class="committee-avatar" style="background: <?= $member['color'] ?>; color: <?= $member['text'] ?>;">
                                <?= e($member['initials']) ?>
                            </div>
                            <div class="committee-details">
                                <div class="committee-name"><?= e($member['name']) ?></div>
                                <div class="committee-role"><?= e($member['role']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <!-- Right Sidebar Column -->
        <aside class="club-grid-sidebar">
            <!-- Membership Card -->
            <div class="club-sidebar-card membership-card">
                <div class="sidebar-card-label">MEMBERSHIP</div>
                <div class="membership-price-row">
                    <span class="membership-currency-amount">RM <?= number_format($meta['membership_price'], 0) ?></span>
                    <span class="membership-period">/ 6 months</span>
                </div>

                <ul class="membership-perks-list">
                    <li>
                        <svg class="perk-check-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>Member price on sessions: <?= format_price(max(0, $meta['membership_price'] > 30 ? 8 : 5)) ?> instead of <?= format_price(max(0, $meta['membership_price'] > 30 ? 12 : 8)) ?></span>
                    </li>
                    <li>
                        <svg class="perk-check-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>Join members-only sparring nights & team ladders</span>
                    </li>
                    <li>
                        <svg class="perk-check-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>Membership is valid for 6 months from approval</span>
                    </li>
                </ul>

                <div class="membership-notice-box">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    <p>Guests can still join sessions that allow guests without a club membership.</p>
                </div>

                <div class="membership-status-row">
                    <span class="status-label">Your status</span>
                    <span class="status-badge-not-member">
                        <span class="status-dot"></span> Not a member
                    </span>
                </div>

                <button type="button" class="btn-sidebar-apply" onclick="showClubApplyModal()">
                    Apply for membership
                </button>
            </div>

            <!-- Club Info Card -->
            <div class="club-sidebar-card club-info-card">
                <div class="sidebar-card-label">CLUB INFO</div>
                <div class="club-info-rows">
                    <div class="club-info-row">
                        <svg class="info-icon" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                        <span class="info-text"><?= e($meta['schedule']) ?></span>
                    </div>
                    <div class="club-info-row">
                        <svg class="info-icon" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <a href="mailto:<?= e($meta['email']) ?>" class="info-text info-link"><?= e($meta['email']) ?></a>
                    </div>
                    <div class="club-info-row">
                        <svg class="info-icon" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <span class="info-text"><?= e($meta['social']) ?></span>
                    </div>
                    <?php if (!empty($club['bank_name'])): ?>
                        <div class="club-info-row">
                            <svg class="info-icon" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                            <span class="info-text"><?= e($club['bank_name']) ?>: <?= e($club['account_number']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
function shareClubLink() {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(window.location.href).then(() => {
            if (window.showToast) {
                showToast('Club link copied to clipboard!', 'success');
            } else {
                alert('Club link copied to clipboard!');
            }
        });
    } else {
        alert('Club link: ' + window.location.href);
    }
}

function showClubApplyModal() {
    if (window.showToast) {
        showToast('Membership registration opens at the start of each semester via SportsHub.', 'info');
    } else {
        alert('Membership registration for <?= addslashes($club['name']) ?> opens at the start of each semester via SportsHub.');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
