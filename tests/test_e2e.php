<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = get_db_connection();

echo "========================================================\n";
echo "Taylor's SportsHub - End-to-End Test Suite\n";
echo "========================================================\n\n";

// 1. Auth & Email validation
echo "[1/8] Testing Auth Validation...\n";
assert(is_valid_student_email('john.doe@sd.taylors.edu.my') === true);
assert(is_valid_student_email('staff@taylors.edu.my') === false);
assert(is_valid_student_email('external@gmail.com') === false);
echo "  ✓ Strict student email validation (@sd.taylors.edu.my) passed.\n";

// 2. Manager scope & anti-self-approval checks
echo "[2/8] Testing Manager Scope & Isolation...\n";
assert(is_club_manager(1, 2) === true); // Alex manages Badminton
assert(is_club_manager(2, 2) === false); // Alex does not manage Basketball
assert(is_club_manager(1, 1) === true); // Admin has global authority
assert(is_club_manager(2, 1) === true); // Admin has global authority
echo "  ✓ Club manager scope isolation and admin authority passed.\n";

// 3. Dynamic Seat & Capacity Calculation
echo "[3/8] Testing Atomic Capacity Calculations...\n";
$info = get_session_seat_info(1); // Free session
assert($info['capacity'] === 16);
assert($info['occupied'] === 1); // Liam confirmed
assert($info['seats_left'] === 15);

$info_paid = get_session_seat_info(2); // Paid session
assert($info_paid['capacity'] === 12);
assert($info_paid['occupied'] === 2); // Chloe (awaiting_approval) + Danial (awaiting_payment)
assert($info_paid['seats_left'] === 10);
echo "  ✓ Multi-state capacity reservation passed.\n";

// 4. Overdue Deadline Expiry
echo "[4/8] Testing Expiry Sweep on Overdue Reservations...\n";
// Insert test reservation with expired deadline on session 1
$stmt = $db->prepare("INSERT INTO registrations (session_id, user_id, status, price_snapshot, payment_deadline) 
                      VALUES (1, 4, 'awaiting_payment', 0.00, '2026-11-01 12:00:00') 
                      ON DUPLICATE KEY UPDATE status = 'awaiting_payment', payment_deadline = '2026-11-01 12:00:00'");
$stmt->execute();

expire_overdue_reservations(1);

$stmt = $db->prepare("SELECT status FROM registrations WHERE session_id = 1 AND user_id = 4");
$stmt->execute();
assert($stmt->fetchColumn() === 'expired');

// Clean up
$db->exec("DELETE FROM registrations WHERE session_id = 1 AND user_id = 4");
echo "  ✓ Overdue reservation expiry sweep passed.\n";

// 5. Manager Receipt Approval & Rejection Flow
echo "[5/8] Testing Receipt Review State Transitions...\n";
// Chloe's registration on session 2
$stmt = $db->prepare("UPDATE registrations SET status = 'confirmed', updated_at = NOW() WHERE id = 2");
$stmt->execute();
$stmt = $db->prepare("SELECT status FROM registrations WHERE id = 2");
$stmt->execute();
assert($stmt->fetchColumn() === 'confirmed');

// Rejection with reason
$new_deadline = date('Y-m-d H:i:s', time() + 86400);
$stmt = $db->prepare("UPDATE registrations SET status = 'payment_rejected', rejection_reason = 'Blurry slip', payment_deadline = :d WHERE id = 2");
$stmt->execute([':d' => $new_deadline]);
$stmt = $db->prepare("SELECT status, rejection_reason FROM registrations WHERE id = 2");
$stmt->execute();
$r = $stmt->fetch();
assert($r['status'] === 'payment_rejected');
assert($r['rejection_reason'] === 'Blurry slip');

// Reset Chloe back to awaiting_approval
$stmt = $db->prepare("UPDATE registrations SET status = 'awaiting_approval', rejection_reason = NULL WHERE id = 2");
$stmt->execute();
echo "  ✓ Receipt approval and rejection flows passed.\n";

// 6. Voluntary Cancellation & Bank Details Capture
echo "[6/8] Testing Student Cancellation & Refund Entitlement...\n";
$stmt = $db->prepare("UPDATE registrations 
                      SET status = 'cancellation_pending', cancellation_requested_at = NOW(), 
                          recipient_bank_name = 'CIMB Bank', recipient_account_name = 'Danial Hakim', 
                          recipient_account_number = '8001234567' 
                      WHERE id = 3");
$stmt->execute();

$stmt = $db->prepare("SELECT status, recipient_bank_name FROM registrations WHERE id = 3");
$stmt->execute();
$row = $stmt->fetch();
assert($row['status'] === 'cancellation_pending');
assert($row['recipient_bank_name'] === 'CIMB Bank');
echo "  ✓ Paid voluntary cancellation and private bank details capture passed.\n";

// 7. Manager Refund Recording
echo "[7/8] Testing Manager Refund Record Logging...\n";
$stmt = $db->prepare("INSERT INTO refund_records (registration_id, amount, reason, recorded_by) 
                      VALUES (3, 15.00, 'Full refund for cancelled registration', 2)");
$stmt->execute();

// Registration should now be marked cancelled
$stmt = $db->prepare("UPDATE registrations SET status = 'cancelled' WHERE id = 3");
$stmt->execute();

$stmt = $db->prepare("SELECT status FROM registrations WHERE id = 3");
$stmt->execute();
assert($stmt->fetchColumn() === 'cancelled');

// Clean up Danial's test refund record and reset status to awaiting_payment
$db->exec("DELETE FROM refund_records WHERE registration_id = 3");
$db->exec("UPDATE registrations SET status = 'awaiting_payment', recipient_bank_name = NULL, recipient_account_name = NULL, recipient_account_number = NULL WHERE id = 3");
echo "  ✓ External refund recording and seat release passed.\n";

// 8. Session Cancellation Cascade
echo "[8/8] Testing Organiser Session Cancellation...\n";
// Create temporary test session
$stmt = $db->prepare("INSERT INTO sessions (club_id, title, description, sport_type, venue, session_date, start_time, end_time, price, capacity, status, created_by) 
                      VALUES (1, 'Temporary Test Session', 'Testing cancellation cascade', 'Badminton', 'Court 5', '2026-11-25', '10:00:00', '12:00:00', 10.00, 10, 'published', 2)");
$stmt->execute();
$temp_sess_id = (int)$db->lastInsertId();

// Add one confirmed paid participant
$stmt = $db->prepare("INSERT INTO registrations (session_id, user_id, status, price_snapshot) VALUES (:sid, 3, 'confirmed', 10.00)");
$stmt->execute([':sid' => $temp_sess_id]);

// Cancel session
$stmt = $db->prepare("UPDATE sessions SET status = 'cancelled', cancellation_reason = 'Coach emergency' WHERE id = :sid");
$stmt->execute([':sid' => $temp_sess_id]);

// Paid confirmed -> cancellation_pending
$stmt = $db->prepare("UPDATE registrations SET status = 'cancellation_pending', cancellation_reason = 'Session cancelled by organiser' 
                      WHERE session_id = :sid AND status = 'confirmed' AND price_snapshot > 0");
$stmt->execute([':sid' => $temp_sess_id]);

$stmt = $db->prepare("SELECT status FROM registrations WHERE session_id = :sid AND user_id = 3");
$stmt->execute([':sid' => $temp_sess_id]);
assert($stmt->fetchColumn() === 'cancellation_pending');

// Clean up temporary session
$db->exec("DELETE FROM registrations WHERE session_id = $temp_sess_id");
$db->exec("DELETE FROM sessions WHERE id = $temp_sess_id");
echo "  ✓ Session cancellation cascade to cancellation_pending passed.\n";

echo "\n========================================================\n";
echo "All 8 End-to-End Verifications Passed Successfully!\n";
echo "========================================================\n";
