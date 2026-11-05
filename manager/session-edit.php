<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();
$managed_clubs = get_user_managed_clubs($user['id']);
$is_admin = ($user['role'] === 'admin');

if (empty($managed_clubs) && !$is_admin) {
    render_error_page(403);
}

$db = get_db_connection();
$session_id = (int)($_GET['id'] ?? 0);
$session = null;

if ($session_id > 0) {
    $stmt = $db->prepare("SELECT * FROM sessions WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $session_id]);
    $session = $stmt->fetch();
    if (!$session) {
        render_error_page(404);
    }
    if (!is_club_manager($session['club_id'], $user['id'])) {
        render_error_page(403);
    }
}

// Available clubs for user
if ($is_admin) {
    $clubs = $db->query("SELECT * FROM clubs WHERE status = 'active' ORDER BY name ASC")->fetchAll();
} else {
    $clubs = $managed_clubs;
}

$error = null;
$title_val = $session['title'] ?? ($_POST['title'] ?? '');
$club_id_val = $session['club_id'] ?? ($_POST['club_id'] ?? ($clubs[0]['id'] ?? 0));
$sport_val = $session['sport_type'] ?? ($_POST['sport_type'] ?? ($clubs[0]['sport_type'] ?? 'Badminton'));
$venue_val = $session['venue'] ?? ($_POST['venue'] ?? '');
$date_val = $session['session_date'] ?? ($_POST['session_date'] ?? date('Y-m-d', strtotime('+3 days')));
$start_val = $session['start_time'] ?? ($_POST['start_time'] ?? '18:00');
$end_val = $session['end_time'] ?? ($_POST['end_time'] ?? '20:00');
$price_val = isset($session['price']) ? (float)$session['price'] : (float)($_POST['price'] ?? 0.00);
$capacity_val = $session['capacity'] ?? (int)($_POST['capacity'] ?? 16);
$desc_val = $session['description'] ?? ($_POST['description'] ?? '');
$image_path_val = $session['image_path'] ?? ($_POST['image_path'] ?? 'assets/images/sports/badminton-open-play.webp');

$is_published = ($session && $session['status'] === 'published');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please try again.";
    } else {
        $title_val = trim($_POST['title'] ?? '');
        $club_id_val = (int)($_POST['club_id'] ?? 0);
        $sport_val = trim($_POST['sport_type'] ?? '');
        $venue_val = trim($_POST['venue'] ?? '');
        $date_val = trim($_POST['session_date'] ?? '');
        $start_val = trim($_POST['start_time'] ?? '');
        $end_val = trim($_POST['end_time'] ?? '');
        $price_val = max(0.00, (float)($_POST['price'] ?? 0.00));
        $capacity_val = max(1, (int)($_POST['capacity'] ?? 1));
        $desc_val = trim($_POST['description'] ?? '');
        $image_path_val = trim($_POST['image_path'] ?? 'assets/images/sports/badminton-open-play.webp');
        $action = $_POST['action'] ?? 'draft';

        if (!is_club_manager($club_id_val, $user['id'])) {
            render_error_page(403);
        }

        if (empty($title_val) || empty($venue_val) || empty($date_val) || empty($start_val) || empty($end_val) || empty($desc_val)) {
            $error = "Please fill in all required fields.";
        } elseif (strtotime($date_val . ' ' . $start_val) <= time()) {
            $error = "Session start time must be in the future.";
        } elseif (strtotime($start_val) >= strtotime($end_val)) {
            $error = "End time must be after start time.";
        } else {
            $status = ($action === 'publish') ? 'published' : 'draft';

            if ($session_id > 0) {
                if ($is_published) {
                    // Description and visual cover can be updated for published sessions
                    $stmt = $db->prepare("UPDATE sessions SET description = :desc, image_path = :image_path, updated_at = NOW() WHERE id = :id");
                    $stmt->execute([':desc' => $desc_val, ':image_path' => $image_path_val, ':id' => $session_id]);
                } else {
                    $stmt = $db->prepare("UPDATE sessions SET club_id = :club_id, title = :title, description = :desc, sport_type = :sport, 
                                          venue = :venue, image_path = :image_path, session_date = :sdate, start_time = :stime, end_time = :etime, 
                                          price = :price, capacity = :cap, status = :st, updated_at = NOW() 
                                          WHERE id = :id");
                    $stmt->execute([
                        ':club_id' => $club_id_val,
                        ':title' => $title_val,
                        ':desc' => $desc_val,
                        ':sport' => $sport_val,
                        ':venue' => $venue_val,
                        ':image_path' => $image_path_val,
                        ':sdate' => $date_val,
                        ':stime' => $start_val,
                        ':etime' => $end_val,
                        ':price' => $price_val,
                        ':cap' => $capacity_val,
                        ':st' => $status,
                        ':id' => $session_id,
                    ]);
                }
            } else {
                $stmt = $db->prepare("INSERT INTO sessions (club_id, title, description, sport_type, venue, image_path, session_date, start_time, end_time, price, capacity, status, created_by) 
                                      VALUES (:club_id, :title, :desc, :sport, :venue, :image_path, :sdate, :stime, :etime, :price, :cap, :st, :uid)");
                $stmt->execute([
                    ':club_id' => $club_id_val,
                    ':title' => $title_val,
                    ':desc' => $desc_val,
                    ':sport' => $sport_val,
                    ':venue' => $venue_val,
                    ':image_path' => $image_path_val,
                    ':sdate' => $date_val,
                    ':stime' => $start_val,
                    ':etime' => $end_val,
                    ':price' => $price_val,
                    ':cap' => $capacity_val,
                    ':st' => $status,
                    ':uid' => $user['id'],
                ]);
            }

            header("Location: " . app_url('/manager/sessions.php'));
            exit;
        }
    }
}

$page_title = $session ? "Edit Session · Manager" : "Create Session · Manager";
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto; padding: 3rem 1.5rem;">
    <div style="margin-bottom: 2rem;">
        <a href="<?= app_url('/manager/sessions.php') ?>" style="color: #6e6e73; text-decoration: none; font-size: 0.88rem;">← Back to Sessions</a>
        <h1 style="font-size: 2rem; font-weight: 800; color: #1d1d1f; margin: 0.5rem 0 0; letter-spacing: -0.02em;">
            <?= $session ? ($is_published ? "Manage Published Session" : "Edit Draft Session") : "Create New Session" ?>
        </h1>
    </div>

    <?php if ($error): ?>
        <div style="background: #fff2f2; border: 1px solid #fecdd3; color: #c81e27; padding: 0.85rem 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($is_published): ?>
        <div style="background: #fafafa; border: 1px solid #e5e5ea; border-radius: 14px; padding: 1.25rem; margin-bottom: 2rem; font-size: 0.9rem; color: #424245;">
            <strong>Note on Published Sessions:</strong> Per sports hub governance, date, time, venue, price, and capacity cannot be altered once published to protect registered students. To change session schedule, cancel this session and publish a replacement.
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" style="background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.06); box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 2.5rem;">
        <?= csrf_field() ?>

        <div style="margin-bottom: 1.5rem;">
            <label for="club_id" style="display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f;">Organising Club</label>
            <select name="club_id" id="club_id" <?= $is_published ? 'disabled' : '' ?> style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; background: #fff;">
                <?php foreach ($clubs as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int)$club_id_val === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['sport_type']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label for="title" style="display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f;">Session Title</label>
            <input type="text" id="title" name="title" value="<?= e($title_val) ?>" placeholder="e.g. Sunday Open Play & Social Smash" required <?= $is_published ? 'readonly' : '' ?> style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <label for="sport_type" style="display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f;">Sport</label>
                <input type="text" id="sport_type" name="sport_type" value="<?= e($sport_val) ?>" placeholder="e.g. Badminton" required <?= $is_published ? 'readonly' : '' ?> style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
            </div>
            <div>
                <label for="venue" style="display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f;">Venue / Court</label>
                <input type="text" id="venue" name="venue" value="<?= e($venue_val) ?>" placeholder="e.g. Sports Complex Court 1-3" required <?= $is_published ? 'readonly' : '' ?> style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
            </div>
        </div>

        <!-- Session Cover Visual Image Picker -->
        <div style="margin-bottom: 1.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.4rem;">
                <label style="font-size: 0.88rem; font-weight: 600; color: #1d1d1f;">Session Cover Image</label>
                <span style="font-size: 0.8rem; color: #86868b;">Select high-resolution WebP hero visual</span>
            </div>

            <?php
            $image_presets = [
                [
                    'path' => 'assets/images/sports/badminton-open-play.webp',
                    'title' => 'Badminton Open Play & Social',
                    'badge' => 'Badminton'
                ],
                [
                    'path' => 'assets/images/sports/badminton-footwork.webp',
                    'title' => 'Footwork & Training Drills',
                    'badge' => 'Clinic'
                ],
                [
                    'path' => 'assets/images/sports/rally-poster.webp',
                    'title' => 'Badminton Tournament Smash',
                    'badge' => 'Tournament'
                ],
                [
                    'path' => 'assets/images/sports/basketball-run.webp',
                    'title' => 'Basketball 3v3 Twilight Run',
                    'badge' => 'Basketball'
                ],
                [
                    'path' => 'assets/images/sports/pickleball-rally.webp',
                    'title' => 'Pickleball Social Rally',
                    'badge' => 'Pickleball'
                ],
                [
                    'path' => 'assets/images/sports/pickleball-clinic.webp',
                    'title' => 'Pickleball Coaching Clinic',
                    'badge' => 'Coaching'
                ],
                [
                    'path' => 'assets/images/sports/pickleball-ladder.webp',
                    'title' => 'Pickleball Ladder Shootout',
                    'badge' => 'Tournament'
                ],
                [
                    'path' => 'assets/images/sports/sports-complex-banner.webp',
                    'title' => 'Taylor\'s Sports Complex Arena',
                    'badge' => 'Arena'
                ],
            ];
            ?>

            <div class="image-picker-grid">
                <?php foreach ($image_presets as $idx => $preset): 
                    $isSelected = ($image_path_val === $preset['path']);
                    $preset_v = file_exists(__DIR__ . '/../' . $preset['path']) ? filemtime(__DIR__ . '/../' . $preset['path']) : '1';
                ?>
                    <label class="image-picker-option <?= $isSelected ? 'selected' : '' ?>">
                        <input type="radio" name="image_path" value="<?= e($preset['path']) ?>" <?= $isSelected ? 'checked' : '' ?>>
                        <img src="<?= app_url('/' . $preset['path']) ?>?v=<?= $preset_v ?>" alt="<?= e($preset['title']) ?>" class="image-picker-thumb" loading="lazy">
                        <div class="image-picker-label">
                            <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #c8102e; display: block; margin-bottom: 2px;">
                                <?= e($preset['badge']) ?>
                            </span>
                            <?= e($preset['title']) ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <label for="session_date" style="display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f;">Date</label>
                <input type="date" id="session_date" name="session_date" value="<?= e($date_val) ?>" required <?= $is_published ? 'readonly' : '' ?> style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
            </div>
            <div>
                <label for="start_time" style="display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f;">Start</label>
                <input type="time" id="start_time" name="start_time" value="<?= e($start_val) ?>" required <?= $is_published ? 'readonly' : '' ?> style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
            </div>
            <div>
                <label for="end_time" style="display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f;">End</label>
                <input type="time" id="end_time" name="end_time" value="<?= e($end_val) ?>" required <?= $is_published ? 'readonly' : '' ?> style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <label for="price" style="display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f;">Price (MYR, 0 for Free)</label>
                <input type="number" step="0.50" min="0" id="price" name="price" value="<?= e((string)$price_val) ?>" required <?= $is_published ? 'readonly' : '' ?> style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
            </div>
            <div>
                <label for="capacity" style="display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f;">Capacity (Max participants)</label>
                <input type="number" min="1" id="capacity" name="capacity" value="<?= e((string)$capacity_val) ?>" required <?= $is_published ? 'readonly' : '' ?> style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box;">
            </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <label for="description" style="display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 0.4rem; color: #1d1d1f;">Session Description</label>
            <textarea id="description" name="description" rows="5" required placeholder="Outline format, drills, who should attend, and what equipment is provided..." style="width: 100%; padding: 0.8rem 1rem; border-radius: 12px; border: 1px solid #d2d2d7; font-size: 0.95rem; box-sizing: border-box; font-family: inherit;"><?= e($desc_val) ?></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
            <?php if (!$is_published): ?>
                <button type="submit" name="action" value="draft" style="padding: 0.85rem 1.5rem; background: #f5f5f7; color: #1d1d1f; border: 1px solid #d2d2d7; border-radius: 980px; font-weight: 600; font-size: 0.95rem; cursor: pointer;">
                    Save Draft
                </button>
            <?php endif; ?>

            <button type="submit" name="action" value="<?= $is_published ? 'save' : 'publish' ?>" class="btn-hero-primary" style="border: none; cursor: pointer;">
                <?= $is_published ? 'Update Description' : 'Publish Session' ?>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
