<?php
// Run only through run.php, which supplies a disposable database.
if (PHP_SAPI !== 'cli' || !isset($scratch) || !str_starts_with($scratch, 'tu_sportshub_test_')) {
    throw new RuntimeException('Use tests/run.php test_receipt_history.php');
}
$db->exec('DELETE FROM payment_receipts');
$db->exec("UPDATE registrations SET receipt_path=NULL");
$db->exec("UPDATE registrations SET receipt_path='assets/uploads/receipts/legacy.png', status='payment_rejected', receipt_uploaded_at=DATE_SUB(NOW(),INTERVAL 1 HOUR), rejection_reason='Legacy unreadable reference' WHERE id=2");
initialize_receipt_history($db);
initialize_receipt_history($db);
$rows = $db->query('SELECT * FROM payment_receipts WHERE registration_id=2')->fetchAll();
assert(count($rows) === 1, 'Repeated upgrade must not duplicate legacy evidence');
assert($rows[0]['decision'] === 'rejected');
assert($rows[0]['rejection_reason'] === 'Legacy unreadable reference');
assert($rows[0]['reviewed_by'] === null && $rows[0]['reviewed_at'] === null, 'Never invent legacy reviewer identity/time');
$db->exec("UPDATE registrations SET receipt_path='assets/uploads/receipts/replacement.png', status='awaiting_approval' WHERE id=2");
initialize_receipt_history($db);
$rows = $db->query('SELECT * FROM payment_receipts WHERE registration_id=2 ORDER BY id')->fetchAll();
assert(count($rows) === 2 && $rows[0]['decision'] === 'rejected' && $rows[1]['decision'] === 'awaiting_review');
assert($rows[0]['receipt_path'] === 'assets/uploads/receipts/legacy.png');
echo "Receipt history upgrade: legacy backfill, idempotency and retained decisions passed.\n";
