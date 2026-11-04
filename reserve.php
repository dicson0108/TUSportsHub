<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . app_url('/sessions.php'));
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: " . app_url('/sessions.php?msg=csrf_error'));
    exit;
}

$session_id = (int)($_POST['session_id'] ?? 0);
$db = get_db_connection();

expire_overdue_reservations($session_id);

$db->beginTransaction();
try {
    // Lock session row for atomic capacity check
    $stmt = $db->prepare("SELECT s.*, c.bank_name, c.account_holder, c.account_number 
                          FROM sessions s 
                          INNER JOIN clubs c ON s.club_id = c.id 
                          WHERE s.id = :id FOR UPDATE");
    $stmt->execute([':id' => $session_id]);
    $session = $stmt->fetch();

    if (!$session) {
        $db->rollBack();
        header("Location: " . app_url('/sessions.php?msg=not_found'));
        exit;
    }

    if ($session['status'] !== 'published') {
        $db->rollBack();
        header("Location: " . app_url('/session-detail.php?id=' . $session_id . '&msg=not_published'));
        exit;
    }

    $session_start = strtotime($session['session_date'] . ' ' . $session['start_time']);
    if ($session_start <= time()) {
        $db->rollBack();
        header("Location: " . app_url('/session-detail.php?id=' . $session_id . '&msg=session_started'));
        exit;
    }

    // Check existing registration
    $stmt = $db->prepare("SELECT * FROM registrations WHERE session_id = :session_id AND user_id = :user_id FOR UPDATE");
    $stmt->execute([':session_id' => $session_id, ':user_id' => $user['id']]);
    $existing = $stmt->fetch();

    if ($existing && in_array($existing['status'], ['confirmed', 'awaiting_payment', 'awaiting_approval', 'payment_rejected', 'cancellation_pending'])) {
        $db->rollBack();
        if ($existing['status'] === 'awaiting_payment' || $existing['status'] === 'payment_rejected') {
            header("Location: " . app_url('/payment.php?id=' . $existing['id']));
        } else {
            header("Location: " . app_url('/session-detail.php?id=' . $session_id . '&msg=already_registered'));
        }
        exit;
    }

    // Check capacity atomically
    $stmt = $db->prepare("SELECT COUNT(*) FROM registrations 
                          WHERE session_id = :id 
                            AND status IN ('confirmed', 'awaiting_payment', 'awaiting_approval', 'payment_rejected', 'cancellation_pending') FOR UPDATE");
    $stmt->execute([':id' => $session_id]);
    $occupied = (int) $stmt->fetchColumn();

    if ($occupied >= (int)$session['capacity']) {
        $db->rollBack();
        header("Location: " . app_url('/session-detail.php?id=' . $session_id . '&msg=session_full'));
        exit;
    }

    $price = (float)$session['price'];
    $payment_instructions = "Bank: " . $session['bank_name'] . "\nAccount Name: " . $session['account_holder'] . "\nAccount Number: " . $session['account_number'];

    if ($price <= 0.001) {
        // Free session -> immediately confirmed
        if ($existing) {
            $stmt = $db->prepare("UPDATE registrations 
                                  SET status = 'confirmed', price_snapshot = 0.00, payment_instructions_snapshot = 'Free session', 
                                      updated_at = NOW() 
                                  WHERE id = :id");
            $stmt->execute([':id' => $existing['id']]);
            $reg_id = $existing['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO registrations (session_id, user_id, status, price_snapshot, payment_instructions_snapshot) 
                                  VALUES (:session_id, :user_id, 'confirmed', 0.00, 'Free session')");
            $stmt->execute([':session_id' => $session_id, ':user_id' => $user['id']]);
            $reg_id = (int)$db->lastInsertId();
        }
        $db->commit();
        header("Location: " . app_url('/session-detail.php?id=' . $session_id . '&msg=confirmed'));
        exit;
    } else {
        // Paid session -> calculate 24h deadline capped at session start
        $twenty_four_hours_later = time() + (24 * 3600);
        $deadline_ts = min($twenty_four_hours_later, $session_start);
        $deadline_dt = date('Y-m-d H:i:s', $deadline_ts);

        if ($existing) {
            $stmt = $db->prepare("UPDATE registrations 
                                  SET status = 'awaiting_payment', price_snapshot = :price, 
                                      payment_instructions_snapshot = :instr, payment_deadline = :deadline, 
                                      receipt_path = NULL, receipt_uploaded_at = NULL, rejection_reason = NULL, 
                                      updated_at = NOW() 
                                  WHERE id = :id");
            $stmt->execute([
                ':price' => $price,
                ':instr' => $payment_instructions,
                ':deadline' => $deadline_dt,
                ':id' => $existing['id']
            ]);
            $reg_id = $existing['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO registrations (session_id, user_id, status, price_snapshot, payment_instructions_snapshot, payment_deadline) 
                                  VALUES (:session_id, :user_id, 'awaiting_payment', :price, :instr, :deadline)");
            $stmt->execute([
                ':session_id' => $session_id,
                ':user_id' => $user['id'],
                ':price' => $price,
                ':instr' => $payment_instructions,
                ':deadline' => $deadline_dt,
            ]);
            $reg_id = (int)$db->lastInsertId();
        }
        $db->commit();
        header("Location: " . app_url('/payment.php?id=' . $reg_id));
        exit;
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}
