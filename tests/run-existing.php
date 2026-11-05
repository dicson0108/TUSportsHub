<?php
// Existing seed-dependent checks run against a fresh disposable DB, never the working app data.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
if ((int) ini_get("zend.assertions") !== 1) {
    throw new RuntimeException(
        "Run php -d zend.assertions=1 -d assert.exception=1 tests/run-existing.php test_e2e.php",
    );
}
$name = $argv[1] ?? "test_e2e.php";
if (!in_array($name, ["test_m3.php", "test_m4.php", "test_m5.php", "test_m6.php", "test_e2e.php", "test_receipt_history.php"], true)) {
    throw new RuntimeException("Unknown test");
}
require_once __DIR__ . "/../includes/helpers.php";
$db = get_db_connection();
$scratch = "tu_sportshub_ux_legacy_" . bin2hex(random_bytes(6));
$db->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4");
try {
    $db->exec("USE `$scratch`");
    foreach (["schema", "seed"] as $script) {
        $sql = file_get_contents(__DIR__ . "/../database/" . $script . ".sql");
        $sql = preg_replace('/^(?:CREATE DATABASE[^\r\n]*|USE[^\r\n]*);\r?\n/m', "", $sql);
        $db->exec($sql);
    }
    initialize_receipt_history($db);
    ob_start();
    try {
        require __DIR__ . "/" . $name;
    } finally {
        $output = ob_get_clean();
        echo $output;
    }
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $db->exec("USE `" . DB_NAME . "`");
    $db->exec("DROP DATABASE `$scratch`");
}
