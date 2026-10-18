<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/student_table.php');
include('../conn/visitor_table.php');
include('../conn/attendance_tables.php');
include('../conn/audit_log.php');
include('../conn/scan_cooldown.php');
require_once('../conn/system_settings.php');
include('../conn/auto_close.php');

require_kiosk_or_login();
verify_csrf_or_reject();

ensure_student_table($conn);
ensure_visitor_table($conn);
ensure_attendance_tables($conn);
ensure_audit_log_table($conn);
ensure_scan_cooldown_table($conn);
ensure_system_settings_table($conn);
ensure_daily_auto_close($conn);
$scanSoundEnabled = get_system_setting_int($conn, 'scan_sound_enabled', 1) === 1;

$qrCode = trim($_POST['qr_code'] ?? '');
$personType = trim($_POST['person_type'] ?? 'student');

if ($qrCode === '' || strlen($qrCode) > 255) {
    exit('Invalid QR code.');
}

if (!in_array($personType, ['student', 'visitor'], true)) {
    exit('Invalid attendance type.');
}

function kiosk_fail_and_redirect($message, $conn = null) {
    global $scanSoundEnabled;
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $safeMessage = json_encode((string) $message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $target = app_base_path() . '/user.php';
    $beepScript = '';
    if ($scanSoundEnabled) {
        $beepScript = "(function(){try{var A=window.AudioContext||window.webkitAudioContext;if(A){var c=new A();var n=c.currentTime;var g=c.createGain();g.connect(c.destination);var o1=c.createOscillator();o1.type='square';o1.frequency.setValueAtTime(220,n);o1.connect(g);g.gain.setValueAtTime(0.0001,n);g.gain.exponentialRampToValueAtTime(0.2,n+0.01);g.gain.exponentialRampToValueAtTime(0.0001,n+0.18);o1.start(n);o1.stop(n+0.18);var o2=c.createOscillator();o2.type='square';o2.frequency.setValueAtTime(170,n+0.22);o2.connect(g);g.gain.setValueAtTime(0.0001,n+0.22);g.gain.exponentialRampToValueAtTime(0.2,n+0.23);g.gain.exponentialRampToValueAtTime(0.0001,n+0.4);o2.start(n);o2.stop(n+0.4);setTimeout(function(){c.close();},550);}}catch(e){}})();";
    }
    echo "<script>" . $beepScript . "alert(" . $safeMessage . "); window.location.href = " . json_encode($target) . ";</script>";
    exit();
}

try {
    $now = date('Y-m-d H:i:s');
    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');
    $cooldownSeconds = get_system_setting_int($conn, 'scan_cooldown_seconds', 10);
    if ($cooldownSeconds < 1 || $cooldownSeconds > 120) {
        $cooldownSeconds = 10;
    }

    if ($personType === 'student') {
        $conn->beginTransaction();

        $selectStmt = $conn->prepare('SELECT tbl_student_id FROM tbl_student WHERE generated_code = :generated_code LIMIT 1 FOR UPDATE');
        $selectStmt->bindValue(':generated_code', $qrCode, PDO::PARAM_STR);
        $selectStmt->execute();

        $result = $selectStmt->fetch();
        if ($result === false) {
            kiosk_fail_and_redirect('No student found for this QR code.', $conn);
        }

        $studentID = (int) $result['tbl_student_id'];
        $cooldown = check_and_touch_scan_cooldown($conn, 'student', $studentID, $cooldownSeconds, $now);
        if (!empty($cooldown['blocked'])) {
            $remaining = (int) ($cooldown['remaining'] ?? $cooldownSeconds);
            $conn->rollBack();
            record_audit_log(
                $conn,
                'SCAN_BLOCKED',
                'student',
                $studentID,
                'Student scan blocked by cooldown (' . $remaining . 's remaining)'
            );
            kiosk_fail_and_redirect('Please wait ' . $remaining . ' seconds before scanning this student QR again.');
        }

        $latestStmt = $conn->prepare('SELECT tbl_attendance_id, time_out FROM tbl_attendance WHERE tbl_student_id = :tbl_student_id AND time_in BETWEEN :today_start AND :today_end ORDER BY tbl_attendance_id DESC LIMIT 1 FOR UPDATE');
        $latestStmt->bindValue(':tbl_student_id', $studentID, PDO::PARAM_INT);
        $latestStmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
        $latestStmt->bindValue(':today_end', $todayEnd, PDO::PARAM_STR);
        $latestStmt->execute();
        $latestAttendance = $latestStmt->fetch();

        if ($latestAttendance !== false && empty($latestAttendance['time_out'])) {
            $attendanceId = (int) $latestAttendance['tbl_attendance_id'];
            $updateStmt = $conn->prepare('UPDATE tbl_attendance SET time_out = :time_out WHERE tbl_attendance_id = :tbl_attendance_id');
            $updateStmt->bindValue(':time_out', $now, PDO::PARAM_STR);
            $updateStmt->bindValue(':tbl_attendance_id', $attendanceId, PDO::PARAM_INT);
            $updateStmt->execute();
            record_audit_log($conn, 'TIME_OUT', 'student_attendance', $attendanceId, 'Student timed out via scan');
        } elseif ($latestAttendance !== false && !empty($latestAttendance['time_out'])) {
            kiosk_fail_and_redirect('This QR code is already recorded', $conn);
        } else {
            $insertStmt = $conn->prepare('INSERT INTO tbl_attendance (tbl_student_id, time_in) VALUES (:tbl_student_id, :time_in)');
            $insertStmt->bindValue(':tbl_student_id', $studentID, PDO::PARAM_INT);
            $insertStmt->bindValue(':time_in', $now, PDO::PARAM_STR);
            $insertStmt->execute();
            $attendanceId = (int) $conn->lastInsertId();
            record_audit_log($conn, 'TIME_IN', 'student_attendance', $attendanceId, 'Student timed in via scan');
        }

        $conn->commit();
    } else {
        $conn->beginTransaction();

        $selectStmt = $conn->prepare('SELECT tbl_visitor_id, qr_used FROM tbl_visitor WHERE generated_code = :generated_code LIMIT 1 FOR UPDATE');
        $selectStmt->bindValue(':generated_code', $qrCode, PDO::PARAM_STR);
        $selectStmt->execute();

        $result = $selectStmt->fetch();
        if ($result === false) {
            kiosk_fail_and_redirect('No visitor found for this QR code.', $conn);
        }

        $visitorID = (int) $result['tbl_visitor_id'];
        $qrUsed = (int) ($result['qr_used'] ?? 0) === 1;
        $isPremadeVisitor = hash_equals(get_premade_visitor_qr_code($conn), $qrCode);

        if ($qrUsed && !$isPremadeVisitor) {
            kiosk_fail_and_redirect('This visitor QR code is already used and no longer valid.', $conn);
        }

        $cooldown = check_and_touch_scan_cooldown($conn, 'visitor', $visitorID, $cooldownSeconds, $now);
        if (!empty($cooldown['blocked'])) {
            $remaining = (int) ($cooldown['remaining'] ?? $cooldownSeconds);
            $conn->rollBack();
            record_audit_log(
                $conn,
                'SCAN_BLOCKED',
                'visitor',
                $visitorID,
                'Visitor scan blocked by cooldown (' . $remaining . 's remaining)'
            );
            kiosk_fail_and_redirect('Please wait ' . $remaining . ' seconds before scanning this visitor QR again.');
        }

        $latestStmt = $conn->prepare('SELECT tbl_visitor_attendance_id, time_out FROM tbl_visitor_attendance WHERE tbl_visitor_id = :tbl_visitor_id AND time_in BETWEEN :today_start AND :today_end ORDER BY tbl_visitor_attendance_id DESC LIMIT 1 FOR UPDATE');
        $latestStmt->bindValue(':tbl_visitor_id', $visitorID, PDO::PARAM_INT);
        $latestStmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
        $latestStmt->bindValue(':today_end', $todayEnd, PDO::PARAM_STR);
        $latestStmt->execute();
        $latestAttendance = $latestStmt->fetch();

        if ($latestAttendance !== false && empty($latestAttendance['time_out'])) {
            $attendanceId = (int) $latestAttendance['tbl_visitor_attendance_id'];
            $destinationCheckStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_visitor_destination WHERE tbl_visitor_attendance_id = :attendance_id AND tbl_visitor_id = :visitor_id');
            $destinationCheckStmt->bindValue(':attendance_id', $attendanceId, PDO::PARAM_INT);
            $destinationCheckStmt->bindValue(':visitor_id', $visitorID, PDO::PARAM_INT);
            $destinationCheckStmt->execute();
            $destinationCount = (int) $destinationCheckStmt->fetchColumn();
            if ($destinationCount === 0) {
                kiosk_fail_and_redirect('Visitor destination scan is required before time out.', $conn);
            }

            $updateStmt = $conn->prepare('UPDATE tbl_visitor_attendance SET time_out = :time_out WHERE tbl_visitor_attendance_id = :tbl_visitor_attendance_id');
            $updateStmt->bindValue(':time_out', $now, PDO::PARAM_STR);
            $updateStmt->bindValue(':tbl_visitor_attendance_id', $attendanceId, PDO::PARAM_INT);
            $updateStmt->execute();
            record_audit_log($conn, 'TIME_OUT', 'visitor_attendance', $attendanceId, 'Visitor timed out via scan');

            if (!$isPremadeVisitor) {
                $markUsedStmt = $conn->prepare('UPDATE tbl_visitor SET qr_used = 1 WHERE tbl_visitor_id = :tbl_visitor_id');
                $markUsedStmt->bindValue(':tbl_visitor_id', $visitorID, PDO::PARAM_INT);
                $markUsedStmt->execute();
            }
        } elseif ($latestAttendance !== false && !empty($latestAttendance['time_out']) && !$isPremadeVisitor) {
            kiosk_fail_and_redirect('This visitor is already timed out for today.', $conn);
        } else {
            $existingStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_visitor_attendance WHERE tbl_visitor_id = :tbl_visitor_id');
            $existingStmt->bindValue(':tbl_visitor_id', $visitorID, PDO::PARAM_INT);
            $existingStmt->execute();
            $existingCount = (int) $existingStmt->fetchColumn();

            if ($existingCount > 0 && !$isPremadeVisitor) {
                $markUsedStmt = $conn->prepare('UPDATE tbl_visitor SET qr_used = 1 WHERE tbl_visitor_id = :tbl_visitor_id');
                $markUsedStmt->bindValue(':tbl_visitor_id', $visitorID, PDO::PARAM_INT);
                $markUsedStmt->execute();
                $conn->commit();
                kiosk_fail_and_redirect('This visitor QR code is already used and no longer valid.');
            }

            $insertStmt = $conn->prepare('INSERT INTO tbl_visitor_attendance (tbl_visitor_id, time_in) VALUES (:tbl_visitor_id, :time_in)');
            $insertStmt->bindValue(':tbl_visitor_id', $visitorID, PDO::PARAM_INT);
            $insertStmt->bindValue(':time_in', $now, PDO::PARAM_STR);
            $insertStmt->execute();
            $attendanceId = (int) $conn->lastInsertId();
            record_audit_log($conn, 'TIME_IN', 'visitor_attendance', $attendanceId, 'Visitor timed in via scan');
        }

        $conn->commit();
    }

    redirect_to('user.php');
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    exit('Failed to add attendance.');
}
?>
