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
    <!-- Club Banner Card -->
    <div style="background: #fff; border-radius: 24px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 4px 24px rgba(0,0,0,0.04); margin-bottom: 3rem;">
        <div style="width: 100%; height: 260px; background: #141824; position: relative; overflow: hidden;">
            <img src="<?= e(get_club_banner_url($club)) ?>" alt="<?= e($club['name']) ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
            <div style="position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.65) 100%); display: flex; align-items: flex-end; padding: 24px;">
                <div>
                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #fff; background: #c8102e; padding: 0.35rem 0.75rem; border-radius: 980px; display: inline-block; margin-bottom: 8px;">
                        <?= e($club['sport_type']) ?>
                    </span>
                    <h1 style="font-size: 2.25rem; font-weight: 800; color: #fff; margin: 0; letter-spacing: -0.02em;">
                        <?= e($club['name']) ?>
                    </h1>
                </div>
            </div>
        </div>

        <div style="padding: 2.25rem;">
            <p style="font-size: 1.1rem; color: #424245; line-height: 1.6; max-width: 800px; margin: 0 0 2rem;">
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
    </div>

    <!-- Sessions Hosted by this Club -->
    <div>
        <h2 style="font-size: 1.75rem; font-weight: 700; color: #1d1d1f; margin: 0 0 1.5rem; letter-spacing: -0.02em;">
            Sessions by <?= e($club['name']) ?>
        </h2>

        <?php if (empty($sessions)): ?>
            <div style="background: #fff; border-radius: 20px; padding: 4rem 2rem; text-align: center; border: 1px solid #e5e5ea; box-shadow: 0 4px 18px rgba(0,0,0,0.03);">
                <div style="font-size: 2.75rem; margin-bottom: 0.75rem;">🏸</div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1d1d1f; margin: 0 0 0.5rem;">No upcoming sessions published</h3>
                <p style="color: #6e6e73; font-size: 0.95rem; margin: 0 0 1.5rem; max-width: 420px; margin-left: auto; margin-right: auto;">
                    This club doesn't have active sessions scheduled right now. Check out all upcoming sessions across the campus!
                </p>
                <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                    <a href="<?= app_url('/sessions.php') ?>" class="btn-hero-primary" style="display: inline-block;">Browse All Sessions →</a>
                    <a href="<?= app_url('/clubs.php') ?>" style="display: inline-block; padding: 0.65rem 1.4rem; background: #f5f5f7; color: #1d1d1f; border-radius: 980px; text-decoration: none; font-weight: 600; font-size: 0.92rem; border: 1px solid #d2d2d7;">All Clubs</a>
                </div>
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
                                <span style="color: #c8102e; font-weight: 600;">View & Join →</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
