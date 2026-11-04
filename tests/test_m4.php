<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = get_db_connection();

echo "Testing Milestone 4: Reservation & Capacity logic...\n";

// Test 1: Seat count check
$seat_info = get_session_seat_info(1);
assert($seat_info['capacity'] === 16);
assert($seat_info['occupied'] === 1);
assert($seat_info['seats_left'] === 15);
echo "✓ Seat info calculation verified.\n";

// Test 2: Paid session seat info
$seat_info_paid = get_session_seat_info(2);
assert($seat_info_paid['capacity'] === 12);
// Chloe (awaiting_approval) + Danial (awaiting_payment) both hold seats -> occupied = 2
assert($seat_info_paid['occupied'] === 2);
assert($seat_info_paid['seats_left'] === 10);
echo "✓ Multi-state capacity reservation (awaiting_approval + awaiting_payment) verified.\n";

// Test 3: Overdue reservation expiry test
// Create a fake test registration with expired deadline
$stmt = $db->prepare("INSERT INTO registrations (session_id, user_id, status, price_snapshot, payment_deadline) 
                      VALUES (4, 3, 'awaiting_payment', 10.00, '2026-11-01 10:00:00') 
                      ON DUPLICATE KEY UPDATE status = 'awaiting_payment', payment_deadline = '2026-11-01 10:00:00'");
$stmt->execute();

expire_overdue_reservations(4);

$stmt = $db->prepare("SELECT status FROM registrations WHERE session_id = 4 AND user_id = 3");
$stmt->execute();
$status = $stmt->fetchColumn();
assert($status === 'expired');
echo "✓ Expired reservation sweep verified.\n";

// Clean up test registration
$db->exec("DELETE FROM registrations WHERE session_id = 4 AND user_id = 3");

echo "All M4 tests passed successfully!\n";
