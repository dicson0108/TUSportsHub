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

<div style="max-width: 1200px; margin: 0 auto; padding: 3rem 1.5rem;">
    <!-- Club Banner -->
    <div style="background: #fff; border-radius: 24px; padding: 2.5rem; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 24px rgba(0,0,0,0.04); margin-bottom: 3rem;">
        <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: #c8102e; background: #fdf2f4; padding: 0.35rem 0.75rem; border-radius: 980px; display: inline-block; margin-bottom: 1rem;">
            <?= e($club['sport_type']) ?>
        </span>
        <h1 style="font-size: 2.5rem; font-weight: 800; color: #1d1d1f; margin: 0 0 1rem; letter-spacing: -0.02em;">
            <?= e($club['name']) ?>
        </h1>
        <p style="font-size: 1.1rem; color: #6e6e73; line-height: 1.6; max-width: 800px; margin: 0 0 2rem;">
            <?= e($club['description']) ?>
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; border-top: 1px solid #f0f0f2; padding-top: 1.5rem;">
            <div>
                <h4 style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: #86868b; margin: 0 0 0.5rem; letter-spacing: 0.04em;">Appointed Club Managers</h4>
                <?php if (empty($managers)): ?>
                    <div style="font-size: 0.92rem; color: #6e6e73;">Seeded management pending.</div>
                <?php else: ?>
                    <?php foreach ($managers as $mgr): ?>
                        <div style="font-size: 0.95rem; font-weight: 600; color: #1d1d1f;">
                            <?= e($mgr['name']) ?> <span style="font-size: 0.85rem; font-weight: 400; color: #86868b;">(<?= e($mgr['email']) ?>)</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div>
                <h4 style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: #86868b; margin: 0 0 0.5rem; letter-spacing: 0.04em;">Official Payment Details</h4>
                <div style="font-size: 0.95rem; color: #1d1d1f; font-weight: 500;">
                    <?= e($club['bank_name']) ?> · <?= e($club['account_number']) ?>
                </div>
                <div style="font-size: 0.85rem; color: #6e6e73;">Holder: <?= e($club['account_holder']) ?></div>
            </div>
        </div>
    </div>

    <!-- Sessions Hosted by this Club -->
    <div>
        <h2 style="font-size: 1.75rem; font-weight: 700; color: #1d1d1f; margin: 0 0 1.5rem; letter-spacing: -0.02em;">
            Sessions by <?= e($club['name']) ?>
        </h2>

        <?php if (empty($sessions)): ?>
            <div style="background: #fff; border-radius: 18px; padding: 3rem 2rem; text-align: center; border: 1px solid #e5e5ea; color: #6e6e73;">
                No sessions published by this club yet.
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.75rem;">
                <?php foreach ($sessions as $sess): ?>
                    <?php $seat_info = get_session_seat_info($sess['id']); ?>
                    <a href="<?= app_url('/session-detail.php?id=' . $sess['id']) ?>" style="background: #fff; border-radius: 18px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 18px rgba(0,0,0,0.04); padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; text-decoration: none; color: inherit; transition: transform 0.15s ease;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #c8102e; background: #fdf2f4; padding: 0.3rem 0.65rem; border-radius: 980px;">
                                    <?= e($sess['sport_type']) ?>
                                </span>
                                <span style="font-size: 0.95rem; font-weight: 700; color: #1d1d1f;">
                                    <?= format_price($sess['price']) ?>
                                </span>
                            </div>
                            <h3 style="font-size: 1.2rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.5rem; line-height: 1.3;">
                                <?= e($sess['title']) ?>
                            </h3>
                            <div style="font-size: 0.88rem; color: #424245; display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 1.25rem;">
                                <div>📅 <?= format_session_date($sess['session_date']) ?></div>
                                <div>⏰ <?= format_session_time_range($sess['start_time'], $sess['end_time']) ?></div>
                                <div>📍 <?= e($sess['venue']) ?></div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f0f0f2; padding-top: 1rem; font-size: 0.88rem;">
                            <span style="font-weight: 600; color: <?= $seat_info['is_full'] ? '#d70015' : '#248a3d' ?>;">
                                <?= $seat_info['is_full'] ? 'Full' : $seat_info['seats_left'] . ' places left' ?>
                            </span>
                            <span style="color: #c8102e; font-weight: 600;">View Session →</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
