<?php
$_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';
$_SERVER['SCRIPT_NAME'] = '/MyProject/27TUSportsHub/index.php';
$_SERVER['PHP_SELF'] = '/MyProject/27TUSportsHub/index.php';

ob_start();
require __DIR__ . '/../index.php';
$out1 = ob_get_clean();
echo "index.php length: " . strlen($out1) . "\n";
assert(str_contains($out1, "Sunday Open Play &amp; Social Smash"));

$_SERVER['SCRIPT_NAME'] = '/MyProject/27TUSportsHub/sessions.php';
$_SERVER['PHP_SELF'] = '/MyProject/27TUSportsHub/sessions.php';
ob_start();
require __DIR__ . '/../sessions.php';
$out2 = ob_get_clean();
echo "sessions.php length: " . strlen($out2) . "\n";
assert(str_contains($out2, "Explore Sports Sessions"));

$_GET['id'] = 1;
$_SERVER['SCRIPT_NAME'] = '/MyProject/27TUSportsHub/session-detail.php';
$_SERVER['PHP_SELF'] = '/MyProject/27TUSportsHub/session-detail.php';
ob_start();
require __DIR__ . '/../session-detail.php';
$out3 = ob_get_clean();
echo "session-detail.php length: " . strlen($out3) . "\n";
assert(str_contains($out3, "Sunday Open Play &amp; Social Smash"));

echo "All M3 tests passed successfully!\n";
