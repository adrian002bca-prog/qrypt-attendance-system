<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/attendance_tables.php');
include('../conn/audit_log.php');

require_login();
verify_csrf_or_reject();

ensure_attendance_tables($conn);
ensure_audit_log_table($conn);

$destinationId = filter_input(INPUT_POST, 'visitor_destination_id', FILTER_VALIDATE_INT);
$redirectPath = trim((string) ($_POST['redirect_path'] ?? 'visitor-destination.php?view=archive'));
$allowedRedirects = [
    'visitor-destination.php',
    'visitor-destination.php?view=archive',
    'visitor-destination-trash.php',
];

if (!in_array($redirectPath, $allowedRedirects, true)) {
    $redirectPath = 'visitor-destination.php?view=archive';
}

if (!$destinationId) {
    redirect_to($redirectPath);
}

try {
    $stmt = $conn->prepare('DELETE FROM tbl_visitor_destination WHERE tbl_visitor_destination_id = :destination_id AND is_archived = 1');
    $stmt->bindValue(':destination_id', $destinationId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        record_audit_log($conn, 'DELETE', 'visitor_destination', $destinationId, 'Deleted archived visitor destination record');
    }

    redirect_to($redirectPath);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Failed to delete archived visitor destination.');
}
