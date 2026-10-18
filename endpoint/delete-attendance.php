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

if (!$attendanceId) {
    exit('Invalid attendance id.');
}

if (!in_array($personType, ['student', 'visitor'], true)) {
    exit('Invalid attendance type.');
}

try {
    if ($personType === 'student') {
        $stmt = $conn->prepare('DELETE FROM tbl_attendance WHERE tbl_attendance_id = :id');
    } else {
        $stmt = $conn->prepare('DELETE FROM tbl_visitor_attendance WHERE tbl_visitor_attendance_id = :id');
    }

    $stmt->bindValue(':id', $attendanceId, PDO::PARAM_INT);
    $stmt->execute();
    record_audit_log(
        $conn,
        'DELETE',
        $personType === 'student' ? 'student_attendance' : 'visitor_attendance',
        $attendanceId,
        'Attendance record deleted'
    );

    redirect_to('index.php');
} catch (PDOException $e) {
    http_response_code(500);
    exit('Failed to delete attendance.');
}
?>
