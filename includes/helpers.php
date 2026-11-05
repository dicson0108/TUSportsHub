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

function format_deadline(?string $value): string {
    return $value ? date('j M Y · g:i A', strtotime($value)) . ' MYT' : 'Not applicable';
}

function can_cancel_registration(array $reg): bool {
    return !($reg['status'] === 'awaiting_approval' && !empty($reg['cancellation_requested_at']))
        && ($reg['session_status'] ?? '') !== 'cancelled'
        && in_array($reg['status'], ['confirmed', 'awaiting_payment', 'awaiting_approval', 'payment_rejected'], true)
        && strtotime($reg['session_date'] . ' ' . $reg['start_time']) > time();
}

function registration_next_step(array $reg): string {
    if ($reg['status'] === 'awaiting_approval' && !empty($reg['cancellation_requested_at'])) {
        return 'Cancellation requested. The organiser must check external settlement before resolving any refund. Your place stays held during review.';
    }
    return match ($reg['status']) {
        'awaiting_payment' => 'Your place is held. Transfer the quoted amount, then submit a receipt before the deadline.',
        'awaiting_approval' => 'Your place is held during review, including after the session starts. Receipt submission does not confirm payment. Wait for the organiser’s decision.',
        'payment_rejected' => 'Check the rejection reason and resubmit evidence before the deadline. A rejected receipt does not prove that no money arrived.',
        'confirmed' => (float)$reg['price_snapshot'] > 0 ? 'Payment approved. Your registration is confirmed.' : 'Your place is confirmed. No payment is required.',
        'cancellation_pending' => 'Your cancellation is pending a full external refund. Your place stays occupied until the refund is fully recorded.',
        'expired' => 'The receipt deadline passed. Your place was released. Check session availability before reserving again.',
        'cancelled' => 'This registration is cancelled and no longer holds a place. Check the cancellation and refund details below.',
        default => 'View your current registration details.'
    };
}

// Allowlisted feedback: never echo arbitrary query-string messages as status.
function journey_feedback(): void {
    $messages = [
        'uploaded' => ['success', 'Receipt submitted. Your place is held while the organiser checks payment.'],
        'confirmed' => ['success', 'Your place is confirmed. No payment is required for this session.'],
        'approved' => ['success', 'Payment decision saved. The participant’s current registration and refund status are shown below.'],
        'rejected' => ['success', 'Decision saved. The participant can see the rejection reason and deadline.'],
        'refund_logged' => ['success', 'Refund record saved. Check the remaining balance before recording another transfer.'],
        'cancelled' => ['success', 'Registration cancelled. Your place has been released.'],
        'cancellation_pending' => ['success', 'Cancellation requested. View your registration for payment review or refund progress.'],
        'upload_failed' => ['error', 'Receipt was not uploaded. Choose your file again, then retry.'],
        'file_too_large' => ['error', 'File exceeds 5 MB. Choose a smaller JPG, PNG, WebP or PDF, then retry.'],
        'invalid_file_type' => ['error', 'File type not supported. Choose a JPG, PNG, WebP or PDF, then retry.'],
        'save_failed' => ['error', 'Receipt could not be saved. Your registration is unchanged. Choose the file again and retry.'],
        'deadline_passed' => ['error', 'Receipt deadline passed. Uploads are closed; check your registration status.'],
        'invalid_status' => ['error', 'This action is no longer available. Your current registration status is shown below.'],
        'history_retained' => ['error', 'Previous payment or refund evidence is retained for this session. Contact the organiser before making another reservation or transfer.'],
        'session_cancelled' => ['error', 'The session is cancelled. New reservations and receipt uploads are closed.'],
        'session_full' => ['error', 'The last place was reserved before your request completed. Check another session or try again later.'],
        'session_started' => ['error', 'This session has started. New reservations are closed.'],
        'not_published' => ['error', 'Signup is closed. Existing payment deadlines remain valid unless the session is cancelled.'],
        'closed' => ['success', 'Signup closed. Existing receipt deadlines remain valid; new reservations are blocked.'],
        'csrf_error' => ['error', 'Your form session expired. Reload this page and try again.'],
        'cannot_cancel_past_start' => ['error', 'Self-service cancellation closes at session start. Contact your organiser for an exceptional withdrawal.'],
    ];
    $key = $_GET['msg'] ?? '';
    if (!isset($messages[$key])) return;
    [$type, $text] = $messages[$key];
    echo '<div class="journey-notice notice-' . $type . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">' . e($text) . '</div>';
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
        'full'                 => ['Full', 'badge-warning'],
        'started'              => ['Started', 'badge-muted'],
    ];
    [$label, $class] = $labels[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'badge-muted'];
    return '<span class="status-badge ' . $class . '">' . htmlspecialchars($label) . '</span>';
}

// Brand site logo HTML helper
function get_site_logo_html(): string {
    static $logo_html = null;
    if ($logo_html !== null) {
        return $logo_html;
    }
    $logo_path = '/assets/images/logo/logo.webp';
    if (file_exists(__DIR__ . '/..' . $logo_path)) {
        $logo_html = '<img src="' . e(app_url($logo_path)) . '" alt="Taylor\'s University" class="brand-logo-img">';
    } else {
        $logo_html = '<div class="brand-logo-icon" aria-hidden="true">t</div>';
    }
    return $logo_html;
}

// Session cover image resolver
function get_session_cover_url(array $session): string {
    $p = '';
    if (!empty($session['image_path'])) {
        $candidate = '/' . ltrim($session['image_path'], '/');
        if (file_exists(__DIR__ . '/..' . $candidate)) {
            $p = $candidate;
        }
    }
    if ($p === '') {
        $id = (int)($session['id'] ?? 0);
        $sport = strtolower($session['sport_type'] ?? '');
        if ($sport === 'basketball') {
            $p = '/assets/images/sports/basketball-run.webp';
        } elseif ($sport === 'pickleball') {
            $p = '/assets/images/sports/pickleball-rally.webp';
        } elseif ($id === 2 || str_contains(strtolower($session['title'] ?? ''), 'footwork')) {
            $p = '/assets/images/sports/badminton-footwork.webp';
        } elseif ($id === 3 || str_contains(strtolower($session['title'] ?? ''), 'cup') || str_contains(strtolower($session['title'] ?? ''), 'tournament')) {
            $p = '/assets/images/sports/rally-poster.webp';
        } else {
            $p = '/assets/images/sports/badminton-open-play.webp';
        }
    }
    $full_path = __DIR__ . '/..' . $p;
    $v = file_exists($full_path) ? filemtime($full_path) : '1';
    return app_url($p) . '?v=' . $v;
}

// Club banner image resolver
function get_club_banner_url(array $club): string {
    $p = '';
    if (!empty($club['banner_path'])) {
        $candidate = '/' . ltrim($club['banner_path'], '/');
        if (file_exists(__DIR__ . '/..' . $candidate)) {
            $p = $candidate;
        }
    }
    if ($p === '') {
        $sport = strtolower($club['sport_type'] ?? '');
        if ($sport === 'basketball') {
            $p = '/assets/images/sports/basketball-banner.webp';
        } elseif ($sport === 'pickleball') {
            $p = '/assets/images/sports/pickleball-banner.webp';
        } else {
            $p = '/assets/images/sports/badminton-banner.webp';
        }
    }
    $full_path = __DIR__ . '/..' . $p;
    $v = file_exists($full_path) ? filemtime($full_path) : '1';
    return app_url($p) . '?v=' . $v;
}

// Validate Taylor's University email domain
function is_valid_taylors_email(string $email): bool {
    $email = strtolower(trim($email));
    return str_ends_with($email, '@sd.taylors.edu.my') || str_ends_with($email, '@taylors.edu.my');
}

