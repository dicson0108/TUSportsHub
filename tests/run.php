<?php
// CLI helper/migration checks use a disposable database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
if ((int)ini_get('zend.assertions') !== 1 || !(bool)ini_get('assert.exception') || !(bool)ini_get('assert.active')) {
    throw new RuntimeException('Run php -d zend.assertions=1 -d assert.exception=1 -d assert.active=1 tests/run.php');
}
$names = array_slice($argv, 1) ?: ['test_portability.php', 'test_helpers.php', 'test_receipt_history.php'];
foreach ($names as $name) {
    if (!in_array($name, ['test_portability.php', 'test_helpers.php', 'test_receipt_history.php'], true)) {
        throw new RuntimeException('Unknown test');
    }
}
require_once __DIR__ . '/../includes/helpers.php';
$db = get_db_connection();
$scratch = 'tu_sportshub_test_' . bin2hex(random_bytes(6));
$db->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4");
try {
    $db->exec("USE `$scratch`");
    foreach (['schema', 'seed'] as $script) {
        $sql = file_get_contents(__DIR__ . '/../database/' . $script . '.sql');
        $sql = preg_replace('/^(?:CREATE DATABASE[^\r\n]*|USE[^\r\n]*);\r?\n/m', '', $sql);
        $db->exec($sql);
    }
    // Rebase disposable fixtures only; demonstration seed files stay unchanged.
    $db->exec('UPDATE sessions SET session_date=DATE_ADD(CURDATE(),INTERVAL 7 DAY)');
    $db->exec('UPDATE registrations SET payment_deadline=DATE_ADD(NOW(),INTERVAL 1 DAY),receipt_uploaded_at=IF(receipt_uploaded_at IS NULL,NULL,NOW())');
    initialize_receipt_history($db);
    ob_start();
    try {
        foreach ($names as $name) require __DIR__ . '/' . $name;
    } finally {
        echo ob_get_clean();
    }
} finally {
    if ($db->inTransaction()) $db->rollBack();
    $db->exec('USE `' . DB_NAME . '`');
    $db->exec("DROP DATABASE `$scratch`");
}
