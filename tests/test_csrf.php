<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ((int)ini_get('zend.assertions') !== 1 || !(bool)ini_get('assert.exception') || !(bool)ini_get('assert.active')) {
    throw new RuntimeException('Run php -d zend.assertions=1 -d assert.exception=1 -d assert.active=1 tests/test_csrf.php');
}
// CSRF helpers must work without loading authentication or opening a database.
require_once __DIR__ . '/../includes/csrf.php';
start_session_safe();
$previous = $_SESSION;
try {
    unset($_SESSION['csrf_token']);
    assert(!verify_csrf_token(null));
    assert(!verify_csrf_token(''));
    assert(!verify_csrf_token('invalid'));
    $token = csrf_token();
    assert(strlen($token) === 64 && ctype_xdigit($token));
    assert(csrf_token() === $token, 'Token remains stable within the session');
    assert(verify_csrf_token($token));
    assert(!verify_csrf_token('invalid'));
    assert(csrf_field() === '<input type="hidden" name="csrf_token" value="' . $token . '">');
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    assert(!verify_csrf_token($token), 'A token from another session is rejected');
    assert(verify_csrf_token($_SESSION['csrf_token']));
} finally {
    $_SESSION = $previous;
}
echo "PASS CSRF: standalone loading, token generation/reuse, hidden field, missing/invalid/foreign token rejection\n";
