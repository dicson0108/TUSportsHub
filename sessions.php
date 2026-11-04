<?php
$page_title = "Explore Sessions · Taylor's SportsHub";
require_once __DIR__ . '/includes/header.php';

$db = get_db_connection();
expire_overdue_reservations();

// Filter parameters
$search = trim($_GET['q'] ?? '');
$sport_filter = trim($_GET['sport'] ?? '');
$date_filter = trim($_GET['date'] ?? '');
$price_filter = trim($_GET['price'] ?? '');
$sort = trim($_GET['sort'] ?? 'date_asc');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 6;
$offset = ($page - 1) * $per_page;

// Build query
$where = ["s.status = 'published'"];
$params = [];

if ($search !== '') {
    $where[] = "(s.title LIKE :search_title OR s.description LIKE :search_description OR s.venue LIKE :search_venue OR c.name LIKE :search_club)";
    foreach (['title', 'description', 'venue', 'club'] as $field) {
        $params[':search_' . $field] = "%{$search}%";
    }
}

if ($sport_filter !== '') {
    $where[] = "s.sport_type = :sport";
    $params[':sport'] = $sport_filter;
}

if ($price_filter === 'free') {
    $where[] = "s.price <= 0.001";
} elseif ($price_filter === 'paid') {
    $where[] = "s.price > 0.001";
}

if ($date_filter === 'today') {
    $where[] = "s.session_date = CURDATE()";
} elseif ($date_filter === 'week') {
    $where[] = "s.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'november') {
    $where[] = "s.session_date BETWEEN '2026-11-01' AND '2026-11-30'";
} elseif ($date_filter === 'december') {
    $where[] = "s.session_date BETWEEN '2026-12-01' AND '2026-12-31'";
}

$where_clause = implode(" AND ", $where);

// Sort order
$order_clause = "s.session_date ASC, s.start_time ASC";
if ($sort === 'date_desc') {
    $order_clause = "s.session_date DESC, s.start_time DESC";
} elseif ($sort === 'popular') {
    $order_clause = "(SELECT COUNT(*) FROM registrations r WHERE r.session_id = s.id AND r.status = 'confirmed') DESC, s.session_date ASC";
}

// Total count
$count_sql = "SELECT COUNT(*) FROM sessions s INNER JOIN clubs c ON s.club_id = c.id WHERE {$where_clause}";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_sessions = (int) $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_sessions / $per_page));

// Fetch paginated sessions
$sql = "SELECT s.*, c.name AS club_name, c.slug AS club_slug, c.logo_path AS club_logo, c.banner_path AS club_banner
        FROM sessions s 
        INNER JOIN clubs c ON s.club_id = c.id 
        WHERE {$where_clause}
        ORDER BY {$order_clause}
        LIMIT {$per_page} OFFSET {$offset}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

// Get distinct sports for dropdown
$sports = $db->query("SELECT DISTINCT sport_type FROM sessions WHERE status = 'published' ORDER BY sport_type ASC")->fetchAll(PDO::FETCH_COLUMN);

// Helper function to build pagination query URL
function pagination_url(int $p): string {
    $q = $_GET;
    $q['page'] = $p;
    return app_url('/sessions.php?' . http_build_query($q));
}
?>

<div class="app-container" style="max-width: 1200px; margin: 0 auto; padding: 32px 1.5rem 64px;">
    <!-- Page Header -->
    <div style="margin-bottom: 28px;">
        <div class="section-label" style="font-size: 11.5px; font-weight: 700; color: #c8102e; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 8px;">
            DISCOVER / CAMPUS SPORTS 2026
        </div>
        <h1 style="font-size: 40px; font-weight: 800; letter-spacing: -0.03em; margin: 0 0 8px; color: #1d1d1f; line-height: 1.1;">
            Find your next session.
        </h1>
        <p style="font-size: 15.5px; color: var(--text-secondary, #424245); margin: 0; max-width: 600px; line-height: 1.5;">
            Explore approved student sports sessions across campus, from casual open rallies to structured clinics.
        </p>
    </div>

    <!-- Filter Form (Apple Frosted Glass Panel matching 26TUEventManagement) -->
    <div class="filter-container">
        <form method="GET" action="<?= APP_BASE_PATH ?>/sessions.php">
            <div class="search-field-main">
                <div class="search-input-wrap">
                    <svg class="search-icon-inside" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by session title, venue, or club" class="input-text-standard" aria-label="Search sessions">
                </div>
                <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 14.5px; font-weight: 600; border-radius: 980px; background: #c8102e; color: #fff; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <span>Search sessions</span>
                </button>
            </div>

            <div class="filter-row">
                <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
                    <label class="form-label" style="font-size: 12.5px; font-weight: 600; color: #1d1d1f; text-transform: uppercase; letter-spacing: 0.04em;">Sport</label>
                    <select name="sport" class="form-select" onchange="this.form.submit()" style="padding: 10px 14px; border-radius: 12px; border: 1px solid #d2d2d7; background: #fff; font-size: 14px; color: #1d1d1f;">
                        <option value="">All sports</option>
                        <?php foreach ($sports as $sp): ?>
                            <option value="<?= e($sp) ?>" <?= $sport_filter === $sp ? 'selected' : '' ?>>
                                <?= e($sp) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
                    <label class="form-label" style="font-size: 12.5px; font-weight: 600; color: #1d1d1f; text-transform: uppercase; letter-spacing: 0.04em;">Date</label>
                    <select name="date" class="form-select" onchange="this.form.submit()" style="padding: 10px 14px; border-radius: 12px; border: 1px solid #d2d2d7; background: #fff; font-size: 14px; color: #1d1d1f;">
                        <option value="">Any upcoming date</option>
                        <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>This week</option>
                        <option value="november" <?= $date_filter === 'november' ? 'selected' : '' ?>>November 2026</option>
                        <option value="december" <?= $date_filter === 'december' ? 'selected' : '' ?>>December 2026</option>
                    </select>
                </div>

                <div class="form-group" style="display: flex; flex-direction: column; gap: 6px;">
                    <label class="form-label" style="font-size: 12.5px; font-weight: 600; color: #1d1d1f; text-transform: uppercase; letter-spacing: 0.04em;">Price</label>
                    <select name="price" class="form-select" onchange="this.form.submit()" style="padding: 10px 14px; border-radius: 12px; border: 1px solid #d2d2d7; background: #fff; font-size: 14px; color: #1d1d1f;">
                        <option value="">All prices</option>
                        <option value="free" <?= $price_filter === 'free' ? 'selected' : '' ?>>Free only</option>
                        <option value="paid" <?= $price_filter === 'paid' ? 'selected' : '' ?>>Paid only</option>
                    </select>
                </div>

                <div>
                    <a href="<?= APP_BASE_PATH ?>/sessions.php" class="btn-link" style="color: #c8102e; font-size: 13.5px; font-weight: 600; text-decoration: none; padding-bottom: 10px; display: inline-block;">Reset filters</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Meta Bar -->
    <div class="filter-results-bar">
        <div>
            <strong><?= $total_sessions ?></strong> upcoming <?= $total_sessions === 1 ? 'session' : 'sessions' ?> · Showing <?= $total_sessions > 0 ? ($offset + 1) . '–' . min($total_sessions, $offset + count($sessions)) : '0' ?>
        </div>

        <div class="sort-group">
            <span style="font-size: 13px; color: var(--text-muted, #6e6e73);">Sort by</span>
            <form method="GET" action="<?= APP_BASE_PATH ?>/sessions.php" style="display: inline-flex; align-items: center; gap: 8px; margin: 0;">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <input type="hidden" name="sport" value="<?= e($sport_filter) ?>">
                <input type="hidden" name="date" value="<?= e($date_filter) ?>">
                <input type="hidden" name="price" value="<?= e($price_filter) ?>">
                <select name="sort" class="form-select" style="padding: 6px 12px; font-size: 13px; border-radius: 10px; border: 1px solid #d2d2d7; background: #fff; color: #1d1d1f;" onchange="this.form.submit()">
                    <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Date: soonest first</option>
                    <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Date: latest first</option>
                    <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most popular</option>
                </select>
            </form>
            <a href="<?= APP_BASE_PATH ?>/sessions.php?sort=popular" class="btn-link" style="font-size: 13px; color: #c8102e; font-weight: 600; text-decoration: none;">Most popular</a>
        </div>
    </div>

    <!-- Sessions Cards Grid -->
    <?php if (empty($sessions)): ?>
        <div style="background: #fff; border-radius: 20px; padding: 4rem 2rem; text-align: center; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
            <div style="font-size: 2.75rem; margin-bottom: 1rem;">🔍</div>
            <h3 style="font-size: 1.35rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.5rem; letter-spacing: -0.02em;">No sessions match your search</h3>
            <p style="color: #6e6e73; font-size: 0.95rem; margin: 0 0 1.5rem; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.5;">Try adjusting your keywords, sport categories, or date filters to find upcoming games.</p>
            <div style="display: flex; gap: 0.75rem; justify-content: center; align-items: center; flex-wrap: wrap;">
                <a href="<?= APP_BASE_PATH ?>/sessions.php" style="display: inline-block; padding: 0.65rem 1.5rem; background: #f5f5f7; color: #1d1d1f; border-radius: 980px; text-decoration: none; font-weight: 600; font-size: 0.92rem; border: 1px solid #d2d2d7;">Reset all filters</a>
                <a href="<?= APP_BASE_PATH ?>/clubs.php" class="btn-hero-primary" style="display: inline-block; padding: 0.65rem 1.5rem; font-size: 0.92rem;">Explore Sports Clubs →</a>
            </div>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 24px; margin-bottom: 48px;">
            <?php foreach ($sessions as $sess): 
                $seat_info = get_session_seat_info($sess['id']);
                $cover_url = get_session_cover_url($sess);
            ?>
                <article class="session-card" style="background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease;">
                    <div style="position: relative; width: 100%; aspect-ratio: 16 / 10; overflow: hidden; background: #141824;">
                        <img src="<?= e($cover_url) ?>" alt="<?= e($sess['title']) ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;" loading="lazy">
                        <div style="position: absolute; top: 12px; left: 12px; right: 12px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #fff; background: rgba(0,0,0,0.65); backdrop-filter: blur(8px); padding: 0.3rem 0.65rem; border-radius: 980px;">
                                <?= e($sess['sport_type']) ?>
                            </span>
                            <span style="font-size: 0.88rem; font-weight: 700; color: #1d1d1f; background: #fff; padding: 0.3rem 0.75rem; border-radius: 980px; box-shadow: 0 2px 6px rgba(0,0,0,0.12);">
                                <?= format_price($sess['price']) ?>
                            </span>
                        </div>
                    </div>
                    <div style="padding: 1.5rem; display: flex; flex-direction: column; flex: 1; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: 1.25rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.4rem; line-height: 1.3;">
                                <?= e($sess['title']) ?>
                            </h3>
                            <div style="font-size: 0.88rem; color: #86868b; margin-bottom: 0.85rem;">
                                Hosted by <a href="<?= APP_BASE_PATH ?>/club.php?id=<?= $sess['club_id'] ?>" style="color: #424245; text-decoration: none; font-weight: 500;"><?= e($sess['club_name']) ?></a>
                            </div>
                            <div style="font-size: 0.88rem; color: #424245; display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span>📅</span>
                                    <span><?= format_session_date($sess['session_date']) ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span>⏰</span>
                                    <span><?= format_session_time_range($sess['start_time'], $sess['end_time']) ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span>📍</span>
                                    <span><?= e($sess['venue']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 1rem; font-size: 0.88rem;">
                            <span style="font-weight: 600; color: <?= $seat_info['is_full'] ? '#d70015' : '#248a3d' ?>;">
                                <?php if ($seat_info['is_full']): ?>
                                    Full (<?= $seat_info['capacity'] ?> / <?= $seat_info['capacity'] ?>)
                                <?php else: ?>
                                    <?= $seat_info['seats_left'] ?> places left
                                <?php endif; ?>
                            </span>
                            <a href="<?= APP_BASE_PATH ?>/session-detail.php?id=<?= $sess['id'] ?>" class="btn-primary" style="padding: 7px 16px; font-size: 13.5px; text-decoration: none; border-radius: 980px; background: #c8102e; color: #fff; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                View &amp; Join →
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination (Matching 26TUEventManagement Apple-styled pagination) -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrap">
                <a href="<?= $page > 1 ? pagination_url($page - 1) : '#' ?>" 
                   class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                   <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    ← Previous
                </a>
                
                <span style="font-size: 13.5px; color: var(--text-muted, #6e6e73); padding: 0 12px;">
                    Page <?= $page ?> of <?= $total_pages ?>
                </span>

                <a href="<?= $page < $total_pages ? pagination_url($page + 1) : '#' ?>" 
                   class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
                   <?= $page >= $total_pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    Next →
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
