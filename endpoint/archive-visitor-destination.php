<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/attendance_tables.php');
include('../conn/audit_log.php');

require_login();
verify_csrf_or_reject();

ensure_attendance_tables($conn);
ensure_audit_log_table($conn);

$destinationId = isset($_POST['visitor_destination_id']) ? (int) $_POST['visitor_destination_id'] : 0;
$redirectPage = trim((string) ($_POST['redirect_page'] ?? 'visitor-destination.php'));
$allowedRedirects = ['visitor-destination.php', 'visitor-destination.php?view=archive', 'visitor-destination-trash.php'];

if (!in_array($redirectPage, $allowedRedirects, true)) {
    $redirectPage = 'visitor-destination.php';
}

if ($destinationId < 1) {
    redirect_to($redirectPage);
}

$archivedBy = isset($_SESSION['username']) && is_string($_SESSION['username']) && trim($_SESSION['username']) !== ''
    ? trim($_SESSION['username'])
    : auth_username();
$now = date('Y-m-d H:i:s');

$stmt = $conn->prepare('UPDATE tbl_visitor_destination
    SET is_archived = 1,
        archived_at = :archived_at,
        archived_by = :archived_by
    WHERE tbl_visitor_destination_id = :destination_id
      AND is_archived = 0');
$stmt->bindValue(':archived_at', $now, PDO::PARAM_STR);
$stmt->bindValue(':archived_by', $archivedBy, PDO::PARAM_STR);
$stmt->bindValue(':destination_id', $destinationId, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    record_audit_log($conn, 'ARCHIVE_VISITOR_DESTINATION', 'visitor_destination', $destinationId, 'Archived visitor destination record');
}

redirect_to($redirectPage);
