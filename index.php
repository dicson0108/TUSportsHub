<?php
$page_title = "Taylor's SportsHub · Student Sports & Club Sessions";
require_once __DIR__ . '/includes/header.php';

$db = get_db_connection();
expire_overdue_reservations();

// Fetch upcoming published sessions (active dates)
$stmt = $db->query("SELECT s.*, c.name AS club_name, c.slug AS club_slug, c.logo_path AS club_logo, c.banner_path AS club_banner 
                    FROM sessions s 
                    INNER JOIN clubs c ON s.club_id = c.id 
                    WHERE s.status = 'published' AND CONCAT(s.session_date, ' ', s.start_time) >= NOW() 
                    ORDER BY s.session_date ASC, s.start_time ASC");
$all_upcoming = $stmt->fetchAll();

// If none in the future (demo safe fallback), show published sessions
if (empty($all_upcoming)) {
    $stmt = $db->query("SELECT s.*, c.name AS club_name, c.slug AS club_slug, c.logo_path AS club_logo, c.banner_path AS club_banner 
                        FROM sessions s 
                        INNER JOIN clubs c ON s.club_id = c.id 
                        WHERE s.status = 'published' 
                        ORDER BY s.session_date ASC, s.start_time ASC");
    $all_upcoming = $stmt->fetchAll();
}

$spotlight = !empty($all_upcoming) ? $all_upcoming[0] : null;
$spotlight_seats = $spotlight ? get_session_seat_info((int)$spotlight['id']) : null;

// Display top 3 for the home cards grid
$upcoming_sessions = array_slice($all_upcoming, 0, 3);

// Fetch active clubs with session count & manager name
$stmt = $db->query("SELECT c.*, 
                    (SELECT COUNT(*) FROM sessions s WHERE s.club_id = c.id AND s.status = 'published') AS session_count,
                    (SELECT u.name FROM club_managers cm INNER JOIN users u ON cm.user_id = u.id WHERE cm.club_id = c.id LIMIT 1) AS manager_name
                    FROM clubs c 
                    WHERE c.status = 'active' 
                    ORDER BY c.name ASC");
$clubs = $stmt->fetchAll();
?>

<div class="app-container" style="max-width: 1200px; margin: 0 auto; padding: 0 1.5rem;">

    <!-- Hero Section (Matching 26TUEventManagement Apple-Calibrated Layout) -->
    <section class="hero-section" style="padding: 48px 0 24px 0;">
        <div class="hero-grid">
            <div>
                <div class="section-label">YOUR CAMPUS. YOUR COURT. / 2026</div>
                <h1 class="hero-heading">Get back<br>in the game.</h1>
                <p class="hero-lead">
                    Friendly rallies, new connections and a clear plan. Discover free and paid sessions from Taylor's Sports Clubs.
                </p>

                <form action="<?= APP_BASE_PATH ?>/sessions.php" method="GET" class="hero-search-bar" role="search">
                    <svg class="hero-search-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" name="q" placeholder="Find your next session, sport or club..." class="hero-search-input" aria-label="Search sessions">
                    <button type="submit" class="hero-search-btn" aria-label="Explore sessions">
                        <span>Explore</span>
                        <svg class="hero-btn-arrow" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                </form>

                <div class="hero-quick-tags">
                    <span class="quick-tags-label">Quick find:</span>
                    <a href="<?= APP_BASE_PATH ?>/sessions.php?sport=Badminton" class="quick-tag-chip">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 14.14 14.14"/></svg>
                        <span>Badminton</span>
                    </a>
                    <a href="<?= APP_BASE_PATH ?>/sessions.php?sport=Basketball" class="quick-tag-chip">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2.1 13.4A10 10 0 0 0 13.4 21.9M21.9 10.6A10 10 0 0 0 10.6 2.1"/></svg>
                        <span>Basketball</span>
                    </a>
                    <a href="<?= APP_BASE_PATH ?>/sessions.php?price=free" class="quick-tag-chip">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <span>Free Sessions</span>
                    </a>
                </div>

                <div class="hero-search-sub">
                    Individual signup · Taylor's students · Malaysia Time
                </div>
            </div>

            <?php if ($spotlight): ?>
                <div class="spotlight-card">
                    <div style="position: relative; aspect-ratio: 16 / 10; overflow: hidden; background: #141824;">
                        <img src="<?= e(get_session_cover_url($spotlight)) ?>" alt="<?= e($spotlight['title']) ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                        <div style="position: absolute; top: 16px; left: 16px;">
                            <span style="font-size: 10.5px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); color: #fff; padding: 4px 10px; border-radius: 980px;">
                                <?= e($spotlight['sport_type']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="spotlight-info-bar">
                        <div>
                            <div class="spotlight-label" style="font-size: 11px; font-weight: 700; letter-spacing: 0.1em; color: #fecdd3; text-transform: uppercase; margin-bottom: 2px;">Next on court</div>
                            <div style="font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 3px;"><?= e($spotlight['title']) ?></div>
                            <div class="spotlight-meta" style="font-size: 12.5px; color: #d4d4da;">
                                <?= e(format_session_date($spotlight['session_date'])) ?> · <?= e(format_price($spotlight['price'])) ?> · <?= $spotlight_seats['seats_left'] ?> places left
                            </div>
                        </div>
                        <a href="<?= APP_BASE_PATH ?>/session-detail.php?id=<?= $spotlight['id'] ?>" class="spotlight-cta-btn" style="background: #fff; color: #1d1d1f; padding: 8px 16px; border-radius: 980px; text-decoration: none; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                            <span>Explore session</span>
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 17L17 7M7 7h10v10"/></svg>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Wayfinding Tiles (From UIUX Designs) -->
    <section class="wayfinding-grid" aria-label="Campus Sports Wayfinding">
        <a href="<?= APP_BASE_PATH ?>/sessions.php" class="wayfinding-tile">
            <div class="wayfinding-tile-title">
                <span>Find a session</span>
                <span style="color: #c8102e;">→</span>
            </div>
            <div class="wayfinding-tile-desc">Free and paid individual sports sessions.</div>
        </a>
        <a href="<?= APP_BASE_PATH ?>/clubs.php" class="wayfinding-tile">
            <div class="wayfinding-tile-title">
                <span>Campus sports clubs</span>
                <span style="color: #c8102e;">→</span>
            </div>
            <div class="wayfinding-tile-desc">Meet official clubs. All abilities welcome.</div>
        </a>
        <a href="<?= APP_BASE_PATH ?>/my-activities.php" class="wayfinding-tile">
            <div class="wayfinding-tile-title">
                <span>Track your place</span>
                <span style="color: #c8102e;">→</span>
            </div>
            <div class="wayfinding-tile-desc">Registration, payment and cancellation status.</div>
        </a>
    </section>

    <!-- Upcoming Sessions Section (Replaced 'Coming up on court') -->
    <section style="margin-bottom: 56px;">
        <div class="section-header-row" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div>
                <div class="section-label">FIND YOUR NEXT SESSION</div>
                <h2 class="section-title" style="font-size: 28px; font-weight: 800; letter-spacing: -0.025em; color: #1d1d1f; margin: 0 0 4px;">Upcoming sessions</h2>
                <p class="section-desc" style="font-size: 15px; color: #6e6e73; margin: 0;">Pick a session, check the details and reserve your place.</p>
            </div>
            <a href="<?= APP_BASE_PATH ?>/sessions.php" style="color: #c8102e; font-weight: 600; text-decoration: none; font-size: 14.5px; display: inline-flex; align-items: center; gap: 4px;">
                <span>View all sessions</span>
                <span>→</span>
            </a>
        </div>

        <div class="session-cards-grid">
            <?php foreach ($upcoming_sessions as $session): 
                $seats = get_session_seat_info((int)$session['id']);
                $cover_url = get_session_cover_url($session);
            ?>
                <a href="<?= APP_BASE_PATH ?>/session-detail.php?id=<?= $session['id'] ?>" class="session-card">
                    <div class="session-card-media">
                        <img src="<?= e($cover_url) ?>" alt="<?= e($session['title']) ?>" loading="lazy">
                        <div class="session-card-badge-row">
                            <span style="background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); color: #fff; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 980px; letter-spacing: 0.05em; text-transform: uppercase;">
                                <?= e($session['sport_type']) ?>
                            </span>
                            <span style="background: #ffffff; color: #1d1d1f; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 980px; box-shadow: 0 2px 6px rgba(0,0,0,0.12);">
                                <?= format_price($session['price']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="session-card-content">
                        <h3 class="session-card-title"><?= e($session['title']) ?></h3>
                        <div class="session-card-meta">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                            <span><?= e(format_session_date($session['session_date'])) ?> · <?= e(format_session_time($session['start_time'])) ?></span>
                        </div>
                        <div class="session-card-venue">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span><?= e($session['venue']) ?></span>
                        </div>
                        <div class="session-card-footer">
                            <div style="font-size: 13px; font-weight: 600; color: <?= $seats['is_full'] ? '#d70015' : '#248a3d' ?>;">
                                <?= $seats['is_full'] ? 'Session Full' : $seats['seats_left'] . ' of ' . $seats['capacity'] . ' places left' ?>
                            </div>
                            <span style="color: #c8102e; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                <span>View</span>
                                <span>→</span>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Campus Sports Clubs Section -->
    <section style="margin-bottom: 56px;">
        <div class="section-header-row" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div>
                <div class="section-label">CAMPUS COMMUNITY</div>
                <h2 class="section-title" style="font-size: 28px; font-weight: 800; letter-spacing: -0.025em; color: #1d1d1f; margin: 0 0 4px;">Campus sports clubs</h2>
                <p class="section-desc" style="font-size: 15px; color: #6e6e73; margin: 0;">Approved student sports clubs organizing regular campus sessions.</p>
            </div>
            <a href="<?= APP_BASE_PATH ?>/clubs.php" style="color: #c8102e; font-weight: 600; text-decoration: none; font-size: 14.5px; display: inline-flex; align-items: center; gap: 4px;">
                <span>All sports clubs</span>
                <span>→</span>
            </a>
        </div>

        <div class="club-cards-grid">
            <?php foreach ($clubs as $club): 
                $club_banner = get_club_banner_url($club);
            ?>
                <a href="<?= APP_BASE_PATH ?>/club.php?id=<?= $club['id'] ?>" class="club-card">
                    <div class="club-card-banner">
                        <img src="<?= e($club_banner) ?>" alt="<?= e($club['name']) ?>" loading="lazy">
                        <div style="position: absolute; bottom: 12px; left: 16px;">
                            <span style="background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); color: #fff; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 980px; text-transform: uppercase;">
                                <?= e($club['sport_type']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="club-card-body">
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px;">
                            <h3 style="font-size: 19px; font-weight: 700; color: #1d1d1f; margin: 0;"><?= e($club['name']) ?></h3>
                        </div>
                        <p style="font-size: 14px; color: #6e6e73; line-height: 1.5; margin: 0 0 16px; flex: 1;">
                            <?= e($club['description']) ?>
                        </p>
                        <div style="border-top: 1px solid rgba(0,0,0,0.06); padding-top: 14px; display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 12.5px; color: #86868b; font-weight: 500;">
                                <?= $club['session_count'] ?> active <?= $club['session_count'] === 1 ? 'session' : 'sessions' ?>
                            </span>
                            <span style="color: #c8102e; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                <span>View Profile</span>
                                <span>→</span>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- More Than A Session Section (From UIUX Designs) -->
    <section style="margin-bottom: 32px;">
        <div class="community-invite-banner">
            <div class="community-invite-left">
                <span class="community-invite-eyebrow">MORE THAN A SESSION</span>
                <h2 class="community-invite-title">Find your people.<br>Make your next rally.</h2>
                <p class="community-invite-lead">
                    From your first practice to your next regular game, join at your pace. Your student account keeps every session in one place.
                </p>
                <div>
                    <?php if ($current_user): ?>
                        <a href="<?= APP_BASE_PATH ?>/sessions.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 980px; background: #c8102e; color: #fff; text-decoration: none; font-weight: 600; font-size: 14.5px;">
                            <span>Explore all sessions</span>
                            <span>→</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= APP_BASE_PATH ?>/register.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 980px; background: #c8102e; color: #fff; text-decoration: none; font-weight: 600; font-size: 14.5px;">
                            <span>Create student account</span>
                            <span>→</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="community-invite-right">
                <div class="community-invite-stat-title">One club.<br>More time to play.</div>
                <div class="community-invite-stat-desc">Free and paid individual sessions. No membership required.</div>
            </div>
        </div>

        <!-- 3-Step Journey Strip -->
        <div class="journey-steps-grid">
            <div class="journey-step-item">
                <span class="journey-step-num">01 /</span>
                <h4 class="journey-step-title">Discover your session</h4>
                <p class="journey-step-desc">Check the venue, Malaysia time, price and available places.</p>
            </div>
            <div class="journey-step-item">
                <span class="journey-step-num">02 /</span>
                <h4 class="journey-step-title">Reserve your place</h4>
                <p class="journey-step-desc">Free sessions confirm immediately. Paid sessions need a receipt and organiser review.</p>
            </div>
            <div class="journey-step-item">
                <span class="journey-step-num">03 /</span>
                <h4 class="journey-step-title">Keep track</h4>
                <p class="journey-step-desc">My Activities shows payment decisions, confirmation and cancellation progress.</p>
            </div>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
