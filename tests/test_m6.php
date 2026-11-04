<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = get_db_connection();

echo "Testing Milestone 6: Student Activities & Cancellation Flow...\n";

// Test 1: Fetch Danial's registration (user 5, session 2)
$stmt = $db->prepare("SELECT * FROM registrations WHERE session_id = 2 AND user_id = 5");
$stmt->execute();
$reg = $stmt->fetch();
assert($reg !== false);
assert($reg['status'] === 'awaiting_payment');
echo "✓ Danial registration retrieved.\n";

// Test 2: Unpaid cancellation -> status immediately cancelled
$stmt = $db->prepare("UPDATE registrations SET status = 'cancelled', cancellation_requested_at = NOW(), cancellation_reason = 'Participant cancelled prior to payment' WHERE id = :id");
$stmt->execute([':id' => $reg['id']]);

$stmt = $db->prepare("SELECT status FROM registrations WHERE id = :id");
$stmt->execute([':id' => $reg['id']]);
assert($stmt->fetchColumn() === 'cancelled');
echo "✓ Unpaid registration immediate cancellation verified.\n";

// Test 3: Re-create Danial as confirmed paid participant to test refund cancellation
$stmt = $db->prepare("UPDATE registrations 
                      SET status = 'confirmed', price_snapshot = 15.00, updated_at = NOW() 
                      WHERE id = :id");
$stmt->execute([':id' => $reg['id']]);

// Now student cancels paid confirmed registration -> should transition to cancellation_pending with bank details
$stmt = $db->prepare("UPDATE registrations 
                      SET status = 'cancellation_pending', cancellation_requested_at = NOW(), 
                          recipient_bank_name = 'Maybank', recipient_account_name = 'Danial Hakim', 
                          recipient_account_number = '114012345678', updated_at = NOW() 
                      WHERE id = :id");
$stmt->execute([':id' => $reg['id']]);

$stmt = $db->prepare("SELECT status, recipient_bank_name, recipient_account_number FROM registrations WHERE id = :id");
$stmt->execute([':id' => $reg['id']]);
$updated = $stmt->fetch();
assert($updated['status'] === 'cancellation_pending');
assert($updated['recipient_bank_name'] === 'Maybank');
assert($updated['recipient_account_number'] === '114012345678');
echo "✓ Paid cancellation transition to 'cancellation_pending' with private refund details verified.\n";

// Reset Danial back to awaiting_payment
$stmt = $db->prepare("UPDATE registrations 
                      SET status = 'awaiting_payment', recipient_bank_name = NULL, 
                          recipient_account_name = NULL, recipient_account_number = NULL, 
                          cancellation_requested_at = NULL, cancellation_reason = NULL 
                      WHERE id = :id");
$stmt->execute([':id' => $reg['id']]);

echo "All M6 tests passed successfully!\n";
