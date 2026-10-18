<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/attendance_tables.php');
include('../conn/audit_log.php');

require_login();
verify_csrf_or_reject();

ensure_attendance_tables($conn);
ensure_audit_log_table($conn);

$attendanceId = filter_input(INPUT_POST, 'attendance', FILTER_VALIDATE_INT);
$personType = trim($_POST['person_type'] ?? 'student');
$timeInInput = trim($_POST['time_in'] ?? '');
$timeOutInput = trim($_POST['time_out'] ?? '');

if (!$attendanceId) {
    exit('Invalid attendance id.');
}

if (!in_array($personType, ['student', 'visitor'], true)) {
    exit('Invalid attendance type.');
}

$timeIn = DateTime::createFromFormat('Y-m-d\TH:i', $timeInInput);
if ($timeIn === false) {
    exit('Invalid Time In value.');
}

$timeOut = null;
if ($timeOutInput !== '') {
    $timeOutParsed = DateTime::createFromFormat('Y-m-d\TH:i', $timeOutInput);
    if ($timeOutParsed === false) {
        exit('Invalid Time Out value.');
    }

    if ($timeOutParsed < $timeIn) {
        exit('Time Out cannot be earlier than Time In.');
    }

    $timeOut = $timeOutParsed;
}

try {
    if ($personType === 'student') {
        $stmt = $conn->prepare('UPDATE tbl_attendance SET time_in = :time_in, time_out = :time_out WHERE tbl_attendance_id = :id');
    } else {
        $stmt = $conn->prepare('UPDATE tbl_visitor_attendance SET time_in = :time_in, time_out = :time_out WHERE tbl_visitor_attendance_id = :id');
    }

    $stmt->bindValue(':time_in', $timeIn->format('Y-m-d H:i:s'), PDO::PARAM_STR);
    if ($timeOut === null) {
        $stmt->bindValue(':time_out', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':time_out', $timeOut->format('Y-m-d H:i:s'), PDO::PARAM_STR);
    }
    $stmt->bindValue(':id', $attendanceId, PDO::PARAM_INT);
    $stmt->execute();
    record_audit_log(
        $conn,
        'UPDATE',
        $personType === 'student' ? 'student_attendance' : 'visitor_attendance',
        $attendanceId,
        'Attendance time updated manually'
    );

    redirect_to('index.php');
} catch (PDOException $e) {
    http_response_code(500);
    exit('Failed to update attendance.');
}
?>
