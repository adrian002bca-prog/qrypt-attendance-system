<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/visitor_table.php');
include('../conn/attendance_tables.php');
include('../conn/audit_log.php');

require_login();
ensure_visitor_table($conn);
ensure_attendance_tables($conn);
ensure_audit_log_table($conn);
verify_csrf_or_reject();

$visitorId = filter_input(INPUT_POST, 'visitor', FILTER_VALIDATE_INT);
if (!$visitorId) {
    exit('Invalid visitor id.');
}

try {
    $conn->beginTransaction();

    $visitorStmt = $conn->prepare('SELECT visitor_name FROM tbl_visitor WHERE tbl_visitor_id = :tbl_visitor_id LIMIT 1');
    $visitorStmt->bindValue(':tbl_visitor_id', $visitorId, PDO::PARAM_INT);
    $visitorStmt->execute();
    $visitorRow = $visitorStmt->fetch();

    $deleteAttendanceStmt = $conn->prepare('DELETE FROM tbl_visitor_attendance WHERE tbl_visitor_id = :tbl_visitor_id');
    $deleteAttendanceStmt->bindValue(':tbl_visitor_id', $visitorId, PDO::PARAM_INT);
    $deleteAttendanceStmt->execute();

    $deleteVisitorStmt = $conn->prepare('DELETE FROM tbl_visitor WHERE tbl_visitor_id = :tbl_visitor_id');
    $deleteVisitorStmt->bindValue(':tbl_visitor_id', $visitorId, PDO::PARAM_INT);
    $deleteVisitorStmt->execute();

    $visitorName = trim((string) ($visitorRow['visitor_name'] ?? ''));
    record_audit_log(
        $conn,
        'DELETE',
        'visitor',
        $visitorId,
        'Deleted visitor' . ($visitorName !== '' ? ': ' . $visitorName : '')
    );

    $conn->commit();

    redirect_to('visitorlist.php');
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    exit('Failed to delete visitor.');
}
?>


