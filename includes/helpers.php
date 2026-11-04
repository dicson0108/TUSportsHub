<?php
require_once __DIR__ . '/../config/db.php';

// Safe HTML escaping
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Format price (RM or Free)
function format_price($price): string {
    $val = (float) $price;
    if ($val <= 0.001) {
        return 'Free';
    }
    return 'RM ' . number_format($val, 2);
}

// Date and time formatters (Malaysia standard)
function format_session_date(string $date_str): string {
    return date('D, j M Y', strtotime($date_str));
}

function format_session_time(string $time_str): string {
    return date('g:i A', strtotime($time_str));
}

function format_session_time_range(string $start_time, string $end_time): string {
    return format_session_time($start_time) . ' – ' . format_session_time($end_time) . ' MYT';
}

function get_initials(string $name): string {
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Expire overdue reservations (older than deadline, or after session start)
function expire_overdue_reservations(?int $session_id = null): void {
    $db = get_db_connection();
    $sql = "UPDATE registrations r
            INNER JOIN sessions s ON r.session_id = s.id
            SET r.status = 'expired'
            WHERE r.status IN ('awaiting_payment', 'payment_rejected')
              AND (r.payment_deadline <= NOW() OR CONCAT(s.session_date, ' ', s.start_time) <= NOW())";
    if ($session_id !== null) {
        $sql .= " AND r.session_id = " . (int)$session_id;
    }
    $db->exec($sql);
}

// Calculate seat occupancy adhering strictly to approved MVP rules
function get_session_seat_info(int $session_id): array {
    expire_overdue_reservations($session_id);
    
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT id, capacity, status, session_date, start_time, end_time, price FROM sessions WHERE id = :id");
    $stmt->execute([':id' => $session_id]);
    $session = $stmt->fetch();
    if (!$session) {
        return ['capacity' => 0, 'occupied' => 0, 'seats_left' => 0, 'is_full' => true, 'is_started' => true, 'status' => 'cancelled'];
    }

    $capacity = (int) $session['capacity'];
    $session_start = strtotime($session['session_date'] . ' ' . $session['start_time']);
    $is_started = ($session_start <= time());

    // Occupying states: confirmed, awaiting_payment, awaiting_approval, payment_rejected, cancellation_pending
    $stmt = $db->prepare("SELECT COUNT(*) FROM registrations 
                          WHERE session_id = :id 
                            AND status IN ('confirmed', 'awaiting_payment', 'awaiting_approval', 'payment_rejected', 'cancellation_pending')");
    $stmt->execute([':id' => $session_id]);
    $occupied = (int) $stmt->fetchColumn();

    $seats_left = max(0, $capacity - $occupied);
    $is_full = ($occupied >= $capacity);

    return [
        'capacity' => $capacity,
        'occupied' => $occupied,
        'seats_left' => $seats_left,
        'is_full' => $is_full,
        'is_started' => $is_started,
        'status' => $session['status'],
        'price' => (float)$session['price'],
    ];
}

// Get user registration for a session
function get_user_session_registration(int $session_id, int $user_id): ?array {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM registrations WHERE session_id = :session_id AND user_id = :user_id LIMIT 1");
    $stmt->execute([':session_id' => $session_id, ':user_id' => $user_id]);
    $reg = $stmt->fetch();
    return $reg ?: null;
}

// Status badge helper with Apple-inspired clean styling
function get_status_badge(string $status): string {
    $labels = [
        'confirmed'            => ['Confirmed', 'badge-confirmed'],
        'awaiting_payment'     => ['Awaiting Payment', 'badge-warning'],
        'awaiting_approval'    => ['Awaiting Approval', 'badge-info'],
        'payment_rejected'     => ['Payment Rejected', 'badge-danger'],
        'cancellation_pending' => ['Cancellation Pending', 'badge-warning'],
        'cancelled'            => ['Cancelled', 'badge-muted'],
        'expired'              => ['Expired', 'badge-muted'],
        'draft'                => ['Draft', 'badge-muted'],
        'published'            => ['Open', 'badge-confirmed'],
        'closed'               => ['Closed', 'badge-danger'],
    ];
    [$label, $class] = $labels[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'badge-muted'];
    return '<span class="status-badge ' . $class . '">' . htmlspecialchars($label) . '</span>';
}
