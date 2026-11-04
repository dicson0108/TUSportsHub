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

<div style="max-width: 1200px; margin: 0 auto; padding: 3rem 1.5rem;">
    <div style="margin-bottom: 2.5rem;">
        <h1 style="font-size: 2.25rem; font-weight: 800; color: #1d1d1f; margin: 0 0 0.5rem; letter-spacing: -0.02em;">
            Taylor's Sports Clubs
        </h1>
        <p style="color: #6e6e73; font-size: 1.05rem; margin: 0;">
            Explore approved student sports clubs organizing regular campus sessions.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 2rem;">
        <?php foreach ($clubs as $club): ?>
            <div style="background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 2rem; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #c8102e; background: #fdf2f4; padding: 0.35rem 0.75rem; border-radius: 980px;">
                            <?= e($club['sport_type']) ?>
                        </span>
                        <span style="font-size: 0.85rem; color: #86868b;">
                            <?= $club['session_count'] ?> active <?= $club['session_count'] === 1 ? 'session' : 'sessions' ?>
                        </span>
                    </div>

                    <h2 style="font-size: 1.4rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.75rem;">
                        <?= e($club['name']) ?>
                    </h2>
                    <p style="color: #6e6e73; font-size: 0.95rem; line-height: 1.5; margin: 0 0 1.5rem;">
                        <?= e($club['description']) ?>
                    </p>

                    <?php if (!empty($club['manager_name'])): ?>
                        <div style="font-size: 0.85rem; color: #424245; margin-bottom: 1.5rem; padding: 0.75rem; background: #f5f5f7; border-radius: 10px;">
                            <strong>Club Manager:</strong> <?= e($club['manager_name']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="border-top: 1px solid #f0f0f2; padding-top: 1.25rem;">
                    <a href="<?= app_url('/club.php?id=' . $club['id']) ?>" style="display: inline-block; width: 100%; text-align: center; padding: 0.75rem; background: #f5f5f7; color: #1d1d1f; text-decoration: none; border-radius: 980px; font-weight: 600; font-size: 0.92rem; border: 1px solid #d2d2d7; box-sizing: border-box; transition: background 0.15s ease;">
                        View Club Profile & Sessions →
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
