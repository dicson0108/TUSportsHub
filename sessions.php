<?php
$page_title = "Explore Sessions";
require_once __DIR__ . '/includes/header.php';

$db = get_db_connection();
expire_overdue_reservations();

$keyword = trim($_GET['q'] ?? '');
$sport_filter = trim($_GET['sport'] ?? '');
$price_filter = trim($_GET['price'] ?? '');

$sql = "SELECT s.*, c.name AS club_name, c.slug AS club_slug 
        FROM sessions s 
        INNER JOIN clubs c ON s.club_id = c.id 
        WHERE s.status = 'published'";
$params = [];

if ($keyword !== '') {
    $sql .= " AND (s.title LIKE :kw OR s.venue LIKE :kw OR s.description LIKE :kw OR c.name LIKE :kw)";
    $params[':kw'] = '%' . $keyword . '%';
}
if ($sport_filter !== '') {
    $sql .= " AND s.sport_type = :sport";
    $params[':sport'] = $sport_filter;
}
if ($price_filter === 'free') {
    $sql .= " AND s.price <= 0.001";
} elseif ($price_filter === 'paid') {
    $sql .= " AND s.price > 0.001";
}

$sql .= " ORDER BY s.session_date ASC, s.start_time ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

// Get distinct sports for filters
$sports = $db->query("SELECT DISTINCT sport_type FROM sessions WHERE status = 'published'")->fetchAll(PDO::FETCH_COLUMN);
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 3rem 1.5rem;">
    <div style="margin-bottom: 2.5rem;">
        <h1 style="font-size: 2.25rem; font-weight: 800; color: #1d1d1f; margin: 0 0 0.5rem; letter-spacing: -0.02em;">
            Explore Sports Sessions
        </h1>
        <p style="color: #6e6e73; font-size: 1.05rem; margin: 0;">
            Discover club-led practice sessions, open games, and training across campus.
        </p>
    </div>

    <!-- Filters & Search Bar -->
    <form method="GET" action="<?= app_url('/sessions.php') ?>" style="background: #fff; padding: 1.25rem; border-radius: 16px; border: 1px solid #e5e5ea; box-shadow: 0 4px 16px rgba(0,0,0,0.03); margin-bottom: 2.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;">
        <div style="flex: 1; min-width: 220px;">
            <input type="text" name="q" value="<?= e($keyword) ?>" placeholder="Search by session title, venue, or club..." style="width: 100%; padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid #d2d2d7; font-size: 0.92rem; box-sizing: border-box;">
        </div>

        <div>
            <select name="sport" style="padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid #d2d2d7; font-size: 0.92rem; background: #fff; color: #1d1d1f;">
                <option value="">All Sports</option>
                <?php foreach ($sports as $sp): ?>
                    <option value="<?= e($sp) ?>" <?= $sport_filter === $sp ? 'selected' : '' ?>><?= e($sp) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <select name="price" style="padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid #d2d2d7; font-size: 0.92rem; background: #fff; color: #1d1d1f;">
                <option value="">All Prices</option>
                <option value="free" <?= $price_filter === 'free' ? 'selected' : '' ?>>Free only</option>
                <option value="paid" <?= $price_filter === 'paid' ? 'selected' : '' ?>>Paid only</option>
            </select>
        </div>

        <button type="submit" style="padding: 0.75rem 1.5rem; background: #c8102e; color: #fff; border: none; border-radius: 980px; font-weight: 600; font-size: 0.92rem; cursor: pointer;">
            Search
        </button>
        <?php if ($keyword !== '' || $sport_filter !== '' || $price_filter !== ''): ?>
            <a href="<?= app_url('/sessions.php') ?>" style="color: #6e6e73; text-decoration: none; font-size: 0.88rem; padding: 0.75rem 0.5rem;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Sessions List -->
    <?php if (empty($sessions)): ?>
        <div style="background: #fff; border-radius: 18px; padding: 4rem 2rem; text-align: center; border: 1px solid #e5e5ea;">
            <div style="font-size: 2.5rem; margin-bottom: 1rem;">🔍</div>
            <h3 style="font-size: 1.25rem; font-weight: 600; color: #1d1d1f; margin: 0 0 0.5rem;">No sessions match your search</h3>
            <p style="color: #6e6e73; font-size: 0.95rem; margin: 0 0 1.5rem;">Try adjusting your filters or search keywords.</p>
            <a href="<?= app_url('/sessions.php') ?>" style="display: inline-block; padding: 0.65rem 1.25rem; background: #f5f5f7; color: #1d1d1f; border-radius: 980px; text-decoration: none; font-weight: 500; font-size: 0.9rem;">View all sessions</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.75rem;">
            <?php foreach ($sessions as $sess): ?>
                <?php 
                $seat_info = get_session_seat_info($sess['id']);
                $cover_url = get_session_cover_url($sess);
                ?>
                <a href="<?= app_url('/session-detail.php?id=' . $sess['id']) ?>" style="background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 4px 18px rgba(0,0,0,0.04); overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; text-decoration: none; color: inherit; transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease;">
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
                                Hosted by <?= e($sess['club_name']) ?>
                            </div>
                            <div style="font-size: 0.88rem; color: #424245; display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 1.25rem;">
                                <div>📅 <?= format_session_date($sess['session_date']) ?></div>
                                <div>⏰ <?= format_session_time_range($sess['start_time'], $sess['end_time']) ?></div>
                                <div>📍 <?= e($sess['venue']) ?></div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f0f0f2; padding-top: 1rem; font-size: 0.88rem;">
                            <span style="font-weight: 600; color: <?= $seat_info['is_full'] ? '#d70015' : '#248a3d' ?>;">
                                <?php if ($seat_info['is_full']): ?>
                                    Full (<?= $seat_info['capacity'] ?> / <?= $seat_info['capacity'] ?>)
                                <?php else: ?>
                                    <?= $seat_info['seats_left'] ?> places left
                                <?php endif; ?>
                            </span>
                            <span style="color: #c8102e; font-weight: 600;">View & Join →</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
