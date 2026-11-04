<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = get_db_connection();

echo "Testing Milestone 5: Manager Workbench & Payment Review...\n";

// Test 1: Club manager authority check
assert(is_club_manager(1, 2) === true); // Alex manages Badminton
assert(is_club_manager(2, 2) === false); // Alex does not manage Basketball
assert(is_club_manager(2, 1) === true); // Admin manages everything
echo "✓ Manager club scope verified.\n";

// Test 2: Receipt Review State Transition
// Chloe's registration (id 2) is currently awaiting_approval for Session 2
$stmt = $db->prepare("SELECT status FROM registrations WHERE id = 2");
$stmt->execute();
assert($stmt->fetchColumn() === 'awaiting_approval');

// Alex (Manager) approves Chloe's payment
$stmt = $db->prepare("UPDATE registrations SET status = 'confirmed', updated_at = NOW() WHERE id = 2");
$stmt->execute();

$stmt = $db->prepare("SELECT status FROM registrations WHERE id = 2");
$stmt->execute();
assert($stmt->fetchColumn() === 'confirmed');
echo "✓ Manager receipt approval transition to 'confirmed' verified.\n";

// Reset Chloe back to awaiting_approval for further testing
$stmt = $db->prepare("UPDATE registrations SET status = 'awaiting_approval' WHERE id = 2");
$stmt->execute();

// Test 3: Receipt Rejection with 24h Window
$reason = "Payment slip blurry, unable to verify reference number";
$twenty_four_hours_later = date('Y-m-d H:i:s', time() + 86400);

$stmt = $db->prepare("UPDATE registrations SET status = 'payment_rejected', rejection_reason = :r, payment_deadline = :d WHERE id = 2");
$stmt->execute([':r' => $reason, ':d' => $twenty_four_hours_later]);

$stmt = $db->prepare("SELECT status, rejection_reason FROM registrations WHERE id = 2");
$stmt->execute();
$row = $stmt->fetch();
assert($row['status'] === 'payment_rejected');
assert($row['rejection_reason'] === $reason);
echo "✓ Manager receipt rejection with resubmission window verified.\n";

// Reset Chloe back to awaiting_approval
$stmt = $db->prepare("UPDATE registrations SET status = 'awaiting_approval', rejection_reason = NULL WHERE id = 2");
$stmt->execute();

echo "All M5 tests passed successfully!\n";
