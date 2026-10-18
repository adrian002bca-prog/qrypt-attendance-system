<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/student_table.php');
include('../conn/attendance_tables.php');

require_kiosk_or_login();
verify_csrf_or_reject();

ensure_student_table($conn);
ensure_attendance_tables($conn);

$qrCode = trim($_POST['qr_code'] ?? '');
if ($qrCode === '' || strlen($qrCode) > 255) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Invalid QR code.']);
    exit();
}

try {
    $stmt = $conn->prepare('SELECT tbl_student_id, student_name, student_id_number, course_section, photo_url FROM tbl_student WHERE generated_code = :generated_code LIMIT 1');
    $stmt->bindValue(':generated_code', $qrCode, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch();

    header('Content-Type: application/json; charset=utf-8');

    if ($row === false) {
        echo json_encode(['ok' => false, 'message' => 'Student not found for this QR code.']);
        exit();
    }

    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');
    $attendanceStmt = $conn->prepare('SELECT tbl_attendance_id, time_out FROM tbl_attendance WHERE tbl_student_id = :tbl_student_id AND time_in BETWEEN :today_start AND :today_end ORDER BY tbl_attendance_id DESC LIMIT 1');
    $attendanceStmt->bindValue(':tbl_student_id', (int) ($row['tbl_student_id'] ?? 0), PDO::PARAM_INT);
    $attendanceStmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
    $attendanceStmt->bindValue(':today_end', $todayEnd, PDO::PARAM_STR);
    $attendanceStmt->execute();
    $attendanceRow = $attendanceStmt->fetch();

    $nextAction = 'time_in';
    $alreadyRecorded = false;
    if ($attendanceRow !== false && empty($attendanceRow['time_out'])) {
        $nextAction = 'time_out';
    } elseif ($attendanceRow !== false && !empty($attendanceRow['time_out'])) {
        $nextAction = 'already_recorded';
        $alreadyRecorded = true;
    }

    echo json_encode([
        'ok' => true,
        'student' => [
            'id' => (int) ($row['tbl_student_id'] ?? 0),
            'name' => (string) ($row['student_name'] ?? ''),
            'student_id_number' => (string) ($row['student_id_number'] ?? ''),
            'course_section' => (string) ($row['course_section'] ?? ''),
            'photo_url' => (string) ($row['photo_url'] ?? ''),
            'next_action' => $nextAction,
            'already_recorded' => $alreadyRecorded,
        ],
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Failed to fetch student.']);
}
?>
