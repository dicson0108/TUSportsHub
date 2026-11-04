<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . app_url('/my-activities.php'));
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: " . app_url('/my-activities.php?msg=csrf_error'));
    exit;
}

$reg_id = (int)($_POST['registration_id'] ?? 0);
$db = get_db_connection();

expire_overdue_reservations();

$stmt = $db->prepare("SELECT r.*, s.session_date, s.start_time 
                      FROM registrations r 
                      INNER JOIN sessions s ON r.session_id = s.id 
                      WHERE r.id = :id AND r.user_id = :uid LIMIT 1");
$stmt->execute([':id' => $reg_id, ':uid' => $user['id']]);
$reg = $stmt->fetch();

if (!$reg) {
    render_error_page(404);
}

// Can only upload if awaiting payment or rejected
if (!in_array($reg['status'], ['awaiting_payment', 'payment_rejected'])) {
    header("Location: " . app_url('/payment.php?id=' . $reg_id . '&msg=invalid_status'));
    exit;
}

// Check deadline and session start
$deadline_ts = strtotime($reg['payment_deadline'] ?? '');
$session_start = strtotime($reg['session_date'] . ' ' . $reg['start_time']);

if (($deadline_ts && $deadline_ts <= time()) || $session_start <= time()) {
    $stmt = $db->prepare("UPDATE registrations SET status = 'expired' WHERE id = :id");
    $stmt->execute([':id' => $reg_id]);
    header("Location: " . app_url('/payment.php?id=' . $reg_id . '&msg=deadline_passed'));
    exit;
}

// Validate uploaded file
if (empty($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    header("Location: " . app_url('/payment.php?id=' . $reg_id . '&msg=upload_failed'));
    exit;
}

$file = $_FILES['receipt'];
$max_size = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $max_size) {
    header("Location: " . app_url('/payment.php?id=' . $reg_id . '&msg=file_too_large'));
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$allowed_mimes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];

if (!isset($allowed_mimes[$mime])) {
    header("Location: " . app_url('/payment.php?id=' . $reg_id . '&msg=invalid_file_type'));
    exit;
}

$ext = $allowed_mimes[$mime];
$filename = 'receipt_' . $reg_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$target_dir = __DIR__ . '/assets/uploads/receipts';
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

$target_path = $target_dir . '/' . $filename;
$relative_path = 'assets/uploads/receipts/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    header("Location: " . app_url('/payment.php?id=' . $reg_id . '&msg=save_failed'));
    exit;
}

// Update registration to awaiting_approval
$stmt = $db->prepare("UPDATE registrations 
                      SET status = 'awaiting_approval', receipt_path = :path, receipt_uploaded_at = NOW(), updated_at = NOW() 
                      WHERE id = :id");
$stmt->execute([
    ':path' => $relative_path,
    ':id' => $reg_id,
]);

header("Location: " . app_url('/payment.php?id=' . $reg_id . '&msg=uploaded'));
exit;
