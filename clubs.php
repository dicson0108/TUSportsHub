<?php
$page_title = "Sports Clubs";
require_once __DIR__ . '/includes/header.php';

$db = get_db_connection();
$stmt = $db->query("SELECT c.*, 
                    (SELECT COUNT(*) FROM sessions s WHERE s.club_id = c.id AND s.status = 'published') AS session_count,
                    (SELECT u.name FROM club_managers cm INNER JOIN users u ON cm.user_id = u.id WHERE cm.club_id = c.id LIMIT 1) AS manager_name
                    FROM clubs c 
                    WHERE c.status = 'active' 
                    ORDER BY c.name ASC");
$clubs = $stmt->fetchAll();
?>

<div class="clubs-page-container">
    <div class="clubs-header">
        <div class="clubs-eyebrow">CAMPUS ORGANISATIONS</div>
        <h1 class="clubs-title">Taylor's Sports Clubs</h1>
        <p class="clubs-subtitle">
            Explore approved student sports clubs organizing regular campus sessions.
        </p>
    </div>

    <?php if (empty($clubs)): ?>
        <div class="empty-state-box">
            <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 14.14 14.14"/></svg>
            <h3 class="empty-state-title">No clubs available</h3>
            <p class="empty-state-desc">
                Currently no sports clubs are active on Taylor's SportsHub.
            </p>
            <a href="<?= app_url('/sessions.php') ?>" class="btn-primary" style="display: inline-block; padding: 0.65rem 1.5rem; border-radius: 980px;">
                Explore Sessions →
            </a>
        </div>
    <?php else: ?>
        <div class="clubs-grid">
            <?php foreach ($clubs as $club): 
                $club_banner = get_club_banner_url($club);
            ?>
                <a href="<?= app_url('/club.php?id=' . $club['id']) ?>" class="club-card-link">
                    <div class="club-card-media">
                        <img src="<?= e($club_banner) ?>" alt="<?= e($club['name']) ?>" loading="lazy">
                        <div class="club-card-badge">
                            <?= e($club['sport_type']) ?>
                        </div>
                    </div>

                    <div class="club-card-body">
                        <div>
                            <div class="club-card-header">
                                <h2 class="club-card-name">
                                    <?= e($club['name']) ?>
                                </h2>
                                <span class="club-card-count">
                                    <?= $club['session_count'] ?> active <?= $club['session_count'] === 1 ? 'session' : 'sessions' ?>
                                </span>
                            </div>
                            <p class="club-card-desc">
                                <?= e($club['description']) ?>
                            </p>

                            <?php if (!empty($club['manager_name'])): ?>
                                <div class="club-card-mgr">
                                    <strong>Club Manager:</strong> <?= e($club['manager_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="club-card-footer">
                            <span>Explore club</span>
                            <span class="club-card-footer-arrow">
                                <span>View sessions</span>
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
