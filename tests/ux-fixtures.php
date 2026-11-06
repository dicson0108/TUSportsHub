<?php
// CLI-only disposable fixtures for both browser suites. Never edits seeded users/sessions.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
require_once __DIR__ . "/../includes/helpers.php";
$db = get_db_connection();
$action = $argv[1] ?? "";
$state_file = sys_get_temp_dir() . "/sportshub-ux-fixture.json";
if ($action === "create") {
    if (file_exists($state_file)) {
        throw new RuntimeException("Clean up previous UX fixtures first.");
    }
    $db->beginTransaction();
    try {
        $users = [];
        foreach (["student", "manager", "outsider"] as $role) {
            $email = "ux-" . $role . "-" . bin2hex(random_bytes(4)) . "@sd.taylors.edu.my";
            $q = $db->prepare("INSERT INTO users (name,email,password_hash) VALUES (?,?,?)");
            $q->execute(["UX Test " . ucfirst($role), $email, password_hash("UxFixture123!", PASSWORD_DEFAULT)]);
            $users[$role] = [
                "id" => (int) $db->lastInsertId(),
                "email" => $email,
            ];
        }
        $q = $db->prepare(
            "INSERT INTO clubs (name,slug,description,sport_type,bank_name,account_holder,account_number) VALUES (?,?,'Synthetic UX fixture','Badminton','DEMO BANK','SYNTHETIC — DO NOT TRANSFER','DEMO-ACCOUNT')",
        );
        $suffix = bin2hex(random_bytes(5));
        $q->execute(["UX Fixture " . $suffix, "ux-fixture-" . $suffix]);
        $club = (int) $db->lastInsertId();
        $q->execute(["UX Other Club " . $suffix, "ux-other-club-" . $suffix]);
        $other_club = (int) $db->lastInsertId();
        $db->prepare("INSERT INTO club_managers (club_id,user_id,appointed_by) VALUES (?,?,?)")->execute([
            $other_club, $users['outsider']['id'], $users['manager']['id'],
        ]);
        $db->prepare("INSERT INTO club_managers (club_id,user_id,appointed_by) VALUES (?,?,?)")->execute([
            $club,
            $users["manager"]["id"],
            $users["manager"]["id"],
        ]);
        $sessions = [];
        foreach (["paid" => 15, "free" => 0, "review" => 15, "race" => 15] as $name => $price) {
            $q = $db->prepare(
                "INSERT INTO sessions (club_id,title,description,sport_type,venue,session_date,start_time,end_time,price,capacity,status,created_by) VALUES (?,?,?,'Badminton','Synthetic Court',DATE_ADD(CURDATE(),INTERVAL 7 DAY),'18:00:00','20:00:00',?,2,'published',?)",
            );
            $q->execute([
                $club,
                "UX " . ucfirst($name) . " Session",
                "Synthetic journey test. No real payment.",
                $price,
                $users["manager"]["id"],
            ]);
            $sessions[$name] = (int) $db->lastInsertId();
        }
        $fixture = [
            "users" => $users,
            "club" => $club,
            "other_club" => $other_club,
            "sessions" => $sessions,
        ];
        // Persist cleanup IDs before commit so a failed write cannot orphan fixtures.
        if (file_put_contents($state_file, json_encode($fixture), LOCK_EX) === false) {
            throw new RuntimeException('Could not save UX fixture state');
        }
        $db->commit();
        echo json_encode($fixture);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if (file_exists($state_file)) unlink($state_file);
        throw $e;
    }
} else {
    $f = json_decode(file_get_contents($state_file), true, 512, JSON_THROW_ON_ERROR);
    $sid = $f["sessions"]["paid"];
    $uid = $f["users"]["student"]["id"];
    if ($action === "read") {
        $q = $db->prepare(
            "SELECT r.*, (SELECT COALESCE(SUM(amount),0) FROM refund_records WHERE registration_id=r.id) AS refunded FROM registrations r WHERE session_id=? AND user_id=?",
        );
        $q->execute([$sid, $uid]);
        echo json_encode($q->fetch());
    } elseif ($action === "set") {
        // Fixed, test-only state controls; no arbitrary SQL accepted.
        $state = $argv[2] ?? "";
        if (
            !in_array(
                $state,
                ["expired", "awaiting_approval", "confirmed", "awaiting_payment", "payment_rejected"],
                true,
            )
        ) {
            throw new RuntimeException("Invalid fixture state");
        }
        $db->prepare(
            "UPDATE registrations SET status=?, payment_deadline=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE session_id=? AND user_id=?",
        )->execute([$state, $sid, $uid]);
    } elseif ($action === "expire") {
        $db->prepare(
            "UPDATE registrations SET payment_deadline=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE session_id=? AND user_id=?",
        )->execute([$sid, $uid]);
    } elseif ($action === "bank-change") {
        $db->prepare("UPDATE clubs SET account_number='CHANGED-AFTER-RESERVATION' WHERE id=?")->execute([$f["club"]]);
    } elseif ($action === "review-read") {
        $q = $db->prepare("SELECT * FROM registrations WHERE session_id=? AND user_id=?");
        $q->execute([$f["sessions"]["review"], $uid]);
        echo json_encode($q->fetch());
    } elseif ($action === "session-state") {
        $q=$db->prepare('SELECT status FROM sessions WHERE id=?');$q->execute([$sid]);echo $q->fetchColumn();
    } elseif ($action === "review-reset") {
        // Separate synthetic submission for each independent review scenario.
        // Never rewrite a prior receipt's recorded decision to reset a test.
        $q = $db->prepare('SELECT * FROM registrations WHERE session_id=? AND user_id=?');
        $q->execute([$f['sessions']['review'], $uid]);
        $reg = $q->fetch();
        $receipt_path = 'assets/uploads/receipts/receipt_' . $reg['id'] . '_' . bin2hex(random_bytes(8)) . '.png';
        if (!copy(__DIR__ . '/../' . $reg['receipt_path'], __DIR__ . '/../' . $receipt_path)) {
            throw new RuntimeException('Could not create synthetic receipt fixture.');
        }
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO payment_receipts (registration_id,receipt_path,submitted_at) VALUES (?,?,?)')->execute([$reg['id'], $receipt_path, $reg['receipt_uploaded_at']]);
            $db->prepare("UPDATE registrations SET status='awaiting_approval',receipt_path=?,rejection_reason=NULL,cancellation_requested_at=NULL,cancellation_reason=NULL WHERE id=?")->execute([$receipt_path, $reg['id']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            unlink(__DIR__ . '/../' . $receipt_path);
            throw $e;
        }
    } elseif ($action === "review-start") {
        $db->prepare(
            "UPDATE sessions SET session_date=DATE(DATE_SUB(NOW(),INTERVAL 1 MINUTE)),start_time=TIME(DATE_SUB(NOW(),INTERVAL 1 MINUTE)),end_time=TIME(DATE_ADD(NOW(),INTERVAL 1 HOUR)) WHERE id=?",
        )->execute([$f["sessions"]["review"]]);
    } elseif ($action === "race-capacity") {
        $db->prepare("UPDATE sessions SET capacity=1 WHERE id=?")->execute([$f["sessions"]["race"]]);
    } elseif ($action === "race-read") {
        $q = $db->prepare(
            "SELECT COUNT(*) FROM registrations WHERE session_id=? AND status IN ('awaiting_payment','awaiting_approval','payment_rejected','confirmed','cancellation_pending')",
        );
        $q->execute([$f["sessions"]["race"]]);
        echo $q->fetchColumn();
    } elseif ($action === "cleanup") {
        $ids = array_column($f["users"], "id");
        $q = $db->prepare("SELECT id FROM registrations WHERE user_id IN (?,?,?)");
        $q->execute($ids);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $registration_id) {
            foreach (glob(__DIR__ . "/../assets/uploads/receipts/receipt_" . (int) $registration_id . "_*") as $p) {
                unlink($p);
            }
        }
        $q = $db->prepare(
            "SELECT proof_path FROM refund_records WHERE registration_id IN (SELECT id FROM registrations WHERE user_id IN (?,?,?))",
        );
        $q->execute($ids);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $p) {
            if ($p && str_starts_with($p, "assets/uploads/refunds/refund_")) {
                @unlink(__DIR__ . "/../" . $p);
            }
        }
        $db->prepare("DELETE FROM clubs WHERE id IN (?,?)")->execute([$f["club"], $f['other_club'] ?? $f['club']]);
        $db->prepare("DELETE FROM users WHERE id IN (?,?,?)")->execute($ids);
        unlink($state_file);
        echo "Fixtures removed";
    }
}
