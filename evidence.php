<?php
// Uploaded financial evidence is served only after ownership / club-scope checks.
require_once __DIR__ . "/includes/auth.php";
$user = require_login();
$db = get_db_connection();
$id = (int) ($_GET["registration_id"] ?? 0);
$q = $db->prepare(
    "SELECT r.user_id,r.receipt_path,s.club_id FROM registrations r INNER JOIN sessions s ON r.session_id=s.id WHERE r.id=?",
);
$q->execute([$id]);
$r = $q->fetch();
if (!$r) {
    render_error_page(404);
}
if ($r["user_id"] !== $user["id"] && !is_club_manager($r["club_id"], $user["id"])) {
    render_error_page(403);
}
$path = $r["receipt_path"];
if (isset($_GET['receipt_id'])) {
    $q = $db->prepare('SELECT receipt_path FROM payment_receipts WHERE id=? AND registration_id=?');
    $q->execute([(int) $_GET['receipt_id'], $id]);
    $path = $q->fetchColumn();
} elseif (isset($_GET["refund_id"])) {
    $q = $db->prepare("SELECT proof_path FROM refund_records WHERE id=? AND registration_id=?");
    $q->execute([(int) $_GET["refund_id"], $id]);
    $path = $q->fetchColumn();
}
$file = $path ? realpath(__DIR__ . "/" . $path) : false;
$uploads = realpath(__DIR__ . "/assets/uploads");
if (
    !$file ||
    !$uploads ||
    !str_starts_with(str_replace("\\", "/", $file), str_replace("\\", "/", $uploads) . "/") ||
    !is_file($file)
) {
    render_error_page(404);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ["image/jpeg", "image/png", "image/webp", "application/pdf"], true)) {
    render_error_page(404);
}
header("Content-Type: " . $mime);
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store");
header('Content-Disposition: inline; filename="evidence.' . pathinfo($file, PATHINFO_EXTENSION) . '"');
readfile($file);
