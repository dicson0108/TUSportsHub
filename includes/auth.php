<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/csrf.php';

// Student email validation - student accounts must use @sd.taylors.edu.my
function is_valid_student_email(string $email): bool {
    $email = strtolower(trim($email));
    return (bool) preg_match('/^[a-zA-Z0-9._%+-]+@sd\.taylors\.edu\.my$/', $email);
}

function is_logged_in(): bool {
    start_session_safe();
    return !empty($_SESSION['user_id']);
}

function current_user(bool $reload = false): ?array {
    if (!is_logged_in()) {
        return null;
    }
    
    static $cached_user = null;
    if (!$reload && $cached_user !== null && $cached_user['id'] === (int)$_SESSION['user_id']) {
        return $cached_user;
    }

    $db = get_db_connection();
    $stmt = $db->prepare("SELECT id, name, preferred_name, email, role, status, phone, faculty, created_at FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] === 'suspended') {
        logout_user();
        return null;
    }

    $cached_user = $user;
    return $user;
}

function login_user(array $user): void {
    start_session_safe();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout_user(): void {
    start_session_safe();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function require_login(?string $redirect = null): array {
    if (!is_logged_in()) {
        $target = $redirect ?? $_SERVER['REQUEST_URI'] ?? '/';
        header("Location: " . app_url("/login.php?redirect=") . urlencode($target));
        exit;
    }
    $user = current_user();
    if (!$user) {
        header("Location: " . app_url("/login.php?msg=") . urlencode("Account inactive or not found"));
        exit;
    }
    return $user;
}

function get_user_managed_clubs(int $user_id): array {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT c.* FROM clubs c INNER JOIN club_managers cm ON c.id = cm.club_id WHERE cm.user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll();
}

function is_club_manager(int $club_id, int $user_id): bool {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $user_id]);
    if ($stmt->fetchColumn() === 'admin') return true;

    $stmt = $db->prepare("SELECT 1 FROM club_managers WHERE club_id = :club_id AND user_id = :user_id LIMIT 1");
    $stmt->execute([':club_id' => $club_id, ':user_id' => $user_id]);
    return (bool) $stmt->fetchColumn();
}
