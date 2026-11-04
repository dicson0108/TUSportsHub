<?php
$page_title = "Home";
require_once __DIR__ . '/includes/header.php';

$db = get_db_connection();
expire_overdue_reservations();

// Fetch upcoming published sessions
$stmt = $db->query("SELECT s.*, c.name AS club_name, c.slug AS club_slug, c.logo_path AS club_logo 
                    FROM sessions s 
                    INNER JOIN clubs c ON s.club_id = c.id 
                    WHERE s.status = 'published' AND CONCAT(s.session_date, ' ', s.start_time) >= NOW() 
                    ORDER BY s.session_date ASC, s.start_time ASC 
                    LIMIT 6");
$upcoming_sessions = $stmt->fetchAll();

// Fetch active clubs
$stmt = $db->query("SELECT c.*, (SELECT COUNT(*) FROM sessions s WHERE s.club_id = c.id AND s.status = 'published') AS session_count 
                    FROM clubs c 
                    WHERE c.status = 'active' 
                    ORDER BY c.name ASC");
$clubs = $stmt->fetchAll();
?>

<style>
    .hero-section {
        background: radial-gradient(circle at 80% 20%, rgba(200, 16, 46, 0.08) 0%, rgba(255, 255, 255, 0) 60%), #ffffff;
        border-bottom: 1px solid rgba(0,0,0,0.06);
        padding: 5rem 1.5rem 4rem;
        text-align: center;
    }
    .hero-container { max-width: 860px; margin: 0 auto; }
    .hero-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.9rem;
        background: #fdf2f4;
        color: #c8102e;
        border-radius: 980px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
    }
    .hero-title {
        font-size: 3.25rem;
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: #1d1d1f;
        margin: 0 0 1.25rem;
    }
    .hero-title span { color: #c8102e; }
    .hero-subtitle {
        font-size: 1.2rem;
        color: #6e6e73;
        line-height: 1.5;
        max-width: 640px;
        margin: 0 auto 2.5rem;
    }
    .hero-ctas {
        display: flex;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .btn-hero-primary {
        background: #c8102e;
        color: #fff;
        text-decoration: none;
        padding: 0.85rem 1.75rem;
        border-radius: 980px;
        font-weight: 600;
        font-size: 1rem;
        transition: transform 0.1s ease, background 0.15s ease;
    }
    .btn-hero-primary:hover { background: #a60d26; transform: translateY(-1px); }
    .btn-hero-secondary {
        background: #f5f5f7;
        color: #1d1d1f;
        text-decoration: none;
        padding: 0.85rem 1.75rem;
        border-radius: 980px;
        font-weight: 600;
        font-size: 1rem;
        border: 1px solid #d2d2d7;
        transition: background 0.15s ease;
    }
    .btn-hero-secondary:hover { background: #e8e8ed; }

    /* Content Layout */
    .section-wrap {
        max-width: 1200px;
        margin: 0 auto;
        padding: 4rem 1.5rem;
    }
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 2rem;
    }
    .section-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1d1d1f;
        letter-spacing: -0.02em;
        margin: 0 0 0.35rem;
    }
    .section-desc { font-size: 0.95rem; color: #6e6e73; margin: 0; }
    .section-link {
        color: #c8102e;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.95rem;
    }
    .section-link:hover { text-decoration: underline; }

    /* Session Grid Cards */
    .session-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1.75rem;
    }
    .session-card {
        background: #ffffff;
        border-radius: 18px;
        border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        text-decoration: none;
        color: inherit;
    }
    .session-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.08);
        border-color: rgba(200, 16, 46, 0.2);
    }
    .card-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .sport-tag {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #c8102e;
        background: #fdf2f4;
        padding: 0.3rem 0.65rem;
        border-radius: 980px;
    }
    .price-tag {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1d1d1f;
    }
    .card-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1d1d1f;
        margin: 0 0 0.5rem;
        line-height: 1.3;
    }
    .card-club {
        font-size: 0.88rem;
        color: #86868b;
        margin-bottom: 1rem;
        font-weight: 500;
    }
    .card-meta {
        font-size: 0.88rem;
        color: #424245;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        margin-bottom: 1.25rem;
    }
    .card-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid #f0f0f2;
        padding-top: 1rem;
        font-size: 0.88rem;
    }
    .seats-count {
        font-weight: 600;
        color: #248a3d;
    }
    .seats-count.low { color: #d70015; }

    /* Clubs Grid */
    .club-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }
    .club-card {
        background: #ffffff;
        border-radius: 18px;
        padding: 1.5rem;
        border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 4px 18px rgba(0,0,0,0.03);
        text-decoration: none;
        color: inherit;
        transition: transform 0.15s ease;
    }
    .club-card:hover { transform: translateY(-2px); }
    .club-card-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1d1d1f;
        margin: 0 0 0.5rem;
    }
    .club-card-desc {
        font-size: 0.88rem;
        color: #6e6e73;
        line-height: 1.5;
        margin: 0 0 1rem;
    }
</style>

<section class="hero-section">
    <div class="hero-container">
        <div class="hero-pill">🏸 Taylor's University SportsHub</div>
        <h1 class="hero-title">Play. Train. <span>Compete.</span></h1>
        <p class="hero-subtitle">
            The official portal for Taylor's student sports sessions. Reserve individual spots in social games, training clinics, and club tournaments.
        </p>
        <div class="hero-ctas">
            <a href="<?= APP_BASE_PATH ?>/sessions.php" class="btn-hero-primary">Explore Sessions</a>
            <a href="<?= APP_BASE_PATH ?>/clubs.php" class="btn-hero-secondary">View Sports Clubs</a>
        </div>
    </div>
</section>

<section class="section-wrap">
    <div class="section-header">
        <div>
            <h2 class="section-title">Upcoming Sessions</h2>
            <p class="section-desc">Join open social sessions and structured coaching</p>
        </div>
        <a href="<?= APP_BASE_PATH ?>/sessions.php" class="section-link">View all sessions →</a>
    </div>

    <div class="session-grid">
        <?php if (empty($upcoming_sessions)): ?>
            <p style="color: #6e6e73; grid-column: 1/-1;">No upcoming sessions scheduled right now. Check back soon!</p>
        <?php else: ?>
            <?php foreach ($upcoming_sessions as $sess): ?>
                <?php 
                $seat_info = get_session_seat_info($sess['id']);
                ?>
                <a href="<?= APP_BASE_PATH ?>/session-detail.php?id=<?= $sess['id'] ?>" class="session-card">
                    <div>
                        <div class="card-top">
                            <span class="sport-tag"><?= e($sess['sport_type']) ?></span>
                            <span class="price-tag"><?= format_price($sess['price']) ?></span>
                        </div>
                        <h3 class="card-title"><?= e($sess['title']) ?></h3>
                        <div class="card-club">Hosted by <?= e($sess['club_name']) ?></div>
                        <div class="card-meta">
                            <div>📅 <?= format_session_date($sess['session_date']) ?></div>
                            <div>⏰ <?= format_session_time_range($sess['start_time'], $sess['end_time']) ?></div>
                            <div>📍 <?= e($sess['venue']) ?></div>
                        </div>
                    </div>
                    <div class="card-bottom">
                        <span class="seats-count <?= $seat_info['seats_left'] <= 3 ? 'low' : '' ?>">
                            <?= $seat_info['seats_left'] ?> <?= $seat_info['seats_left'] === 1 ? 'place' : 'places' ?> left (of <?= $seat_info['capacity'] ?>)
                        </span>
                        <span style="color: #c8102e; font-weight: 600;">Details →</span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="section-wrap" style="background: #fafafa; border-radius: 24px; margin-bottom: 2rem;">
    <div class="section-header">
        <div>
            <h2 class="section-title">Campus Sports Clubs</h2>
            <p class="section-desc">Appointed student leaders and regular training communities</p>
        </div>
        <a href="<?= APP_BASE_PATH ?>/clubs.php" class="section-link">All clubs →</a>
    </div>

    <div class="club-grid">
        <?php foreach ($clubs as $club): ?>
            <a href="<?= APP_BASE_PATH ?>/club.php?id=<?= $club['id'] ?>" class="club-card">
                <span class="sport-tag" style="margin-bottom: 0.75rem; display: inline-block;"><?= e($club['sport_type']) ?></span>
                <h3 class="club-card-title"><?= e($club['name']) ?></h3>
                <p class="club-card-desc"><?= e($club['description']) ?></p>
                <div style="font-size: 0.85rem; color: #86868b;">
                    <?= $club['session_count'] ?> active <?= $club['session_count'] === 1 ? 'session' : 'sessions' ?> available
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
