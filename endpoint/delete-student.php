<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/student_table.php');
include('../conn/attendance_tables.php');
include('../conn/audit_log.php');

require_login();

verify_csrf_or_reject();
ensure_student_table($conn);
ensure_attendance_tables($conn);
ensure_audit_log_table($conn);

$studentId = filter_input(INPUT_POST, 'student', FILTER_VALIDATE_INT);
if (!$studentId) {
    exit('Invalid student id.');
}

try {
    $conn->beginTransaction();

    $photoStmt = $conn->prepare('SELECT student_name, photo_url FROM tbl_student WHERE tbl_student_id = :tbl_student_id LIMIT 1');
    $photoStmt->bindValue(':tbl_student_id', $studentId, PDO::PARAM_INT);
    $photoStmt->execute();
    $photoRow = $photoStmt->fetch();

    $deleteAttendanceStmt = $conn->prepare('DELETE FROM tbl_attendance WHERE tbl_student_id = :tbl_student_id');
    $deleteAttendanceStmt->bindValue(':tbl_student_id', $studentId, PDO::PARAM_INT);
    $deleteAttendanceStmt->execute();

    $deleteStudentStmt = $conn->prepare('DELETE FROM tbl_student WHERE tbl_student_id = :tbl_student_id');
    $deleteStudentStmt->bindValue(':tbl_student_id', $studentId, PDO::PARAM_INT);
    $deleteStudentStmt->execute();

    $studentName = trim((string) ($photoRow['student_name'] ?? ''));
    record_audit_log(
        $conn,
        'DELETE',
        'student',
        $studentId,
        'Deleted student' . ($studentName !== '' ? ': ' . $studentName : '')
    );

    $conn->commit();

    $photoUrl = trim((string) ($photoRow['photo_url'] ?? ''));
    if ($photoUrl !== '' && strpos($photoUrl, 'uploads/student_photos/') === 0) {
        $photoPath = dirname(__DIR__) . '/' . $photoUrl;
        if (is_file($photoPath)) {
            @unlink($photoPath);
        }
    }

    redirect_to('masterlist.php');
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    exit('Failed to delete student.');
}
?>


