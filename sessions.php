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

<div class="sessions-container">
    <!-- Page Header -->
    <div class="sessions-header">
        <div class="sessions-eyebrow">
            DISCOVER / CAMPUS SPORTS 2026
        </div>
        <h1 class="sessions-title">
            Find your next session.
        </h1>
        <p class="sessions-subtitle">
            Explore approved student sports sessions across campus, from casual open rallies to structured clinics.
        </p>
    </div>

    <!-- Filter Form (Matching 26TUEventManagement Apple-styled frosted glass) -->
    <div class="filter-container">
        <form method="GET" action="<?= APP_BASE_PATH ?>/sessions.php">
            <div class="search-field-main">
                <div class="search-input-wrap">
                    <svg class="search-icon-inside" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by session title, venue, or club" class="input-text-standard" aria-label="Search sessions">
                </div>
                <button type="submit" class="btn-filter-search">
                    <span>Search sessions</span>
                </button>
            </div>

            <div class="filter-row">
                <div class="filter-group">
                    <label class="form-label">Sport</label>
                    <select name="sport" class="form-select" onchange="this.form.submit()">
                        <option value="">All sports</option>
                        <?php foreach ($sports as $sp): ?>
                            <option value="<?= e($sp) ?>" <?= $sport_filter === $sp ? 'selected' : '' ?>>
                                <?= e($sp) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">Date</label>
                    <select name="date" class="form-select" onchange="this.form.submit()">
                        <option value="">Any upcoming date</option>
                        <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>This week</option>
                        <option value="november" <?= $date_filter === 'november' ? 'selected' : '' ?>>November 2026</option>
                        <option value="december" <?= $date_filter === 'december' ? 'selected' : '' ?>>December 2026</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="form-label">Price</label>
                    <select name="price" class="form-select" onchange="this.form.submit()">
                        <option value="">All prices</option>
                        <option value="free" <?= $price_filter === 'free' ? 'selected' : '' ?>>Free only</option>
                        <option value="paid" <?= $price_filter === 'paid' ? 'selected' : '' ?>>Paid only</option>
                    </select>
                </div>

                <div>
                    <a href="<?= APP_BASE_PATH ?>/sessions.php" class="filter-reset-link">Reset filters</a>
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
            <span>Sort by</span>
            <form method="GET" action="<?= APP_BASE_PATH ?>/sessions.php" style="display: inline-flex; align-items: center; gap: 8px; margin: 0;">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <input type="hidden" name="sport" value="<?= e($sport_filter) ?>">
                <input type="hidden" name="date" value="<?= e($date_filter) ?>">
                <input type="hidden" name="price" value="<?= e($price_filter) ?>">
                <select name="sort" class="sort-select" onchange="this.form.submit()">
                    <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Date: soonest first</option>
                    <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Date: latest first</option>
                    <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most popular</option>
                </select>
            </form>
            <a href="<?= APP_BASE_PATH ?>/sessions.php?sort=popular" class="btn-link" style="font-size: 13px; color: #c8102e; font-weight: 600; text-decoration: none;">Most popular</a>
        </div>
    </div>

    <!-- Sessions Cards Grid (Entire card clickable, no inner button, refer to 26TUEventManagement) -->
    <?php if (empty($sessions)): ?>
        <div class="empty-state-box">
            <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <h3 class="empty-state-title">No sessions match your search</h3>
            <p class="empty-state-desc">Try adjusting your keywords, sport categories, or date filters to find upcoming games.</p>
            <div style="display: flex; gap: 0.75rem; justify-content: center; align-items: center; flex-wrap: wrap;">
                <a href="<?= APP_BASE_PATH ?>/sessions.php" class="btn-secondary" style="padding: 0.65rem 1.5rem; border-radius: 980px;">Reset all filters</a>
                <a href="<?= APP_BASE_PATH ?>/clubs.php" class="btn-primary" style="padding: 0.65rem 1.5rem; border-radius: 980px;">Explore Sports Clubs →</a>
            </div>
        </div>
    <?php else: ?>
        <div class="sessions-grid">
            <?php foreach ($sessions as $sess): 
                $seat_info = get_session_seat_info($sess['id']);
                $cover_url = get_session_cover_url($sess);
            ?>
                <a href="<?= APP_BASE_PATH ?>/session-detail.php?id=<?= $sess['id'] ?>" class="session-card-link">
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
                            <div class="session-card-host">
                                Hosted by <?= e($sess['club_name']) ?>
                            </div>
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
                                <?php if ($seat_info['is_full']): ?>
                                    Full (<?= $seat_info['capacity'] ?> / <?= $seat_info['capacity'] ?>)
                                <?php else: ?>
                                    <?= $seat_info['seats_left'] ?> places left
                                <?php endif; ?>
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
