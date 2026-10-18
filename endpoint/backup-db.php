<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/db_backup.php');
include('../conn/audit_log.php');

require_login();
verify_csrf_or_reject();
ensure_audit_log_table($conn);

$filePath = create_backup_file($conn, 'manual_backup');
if ($filePath === false || !is_file($filePath)) {
    http_response_code(500);
    exit('Failed to create backup file.');
}

$fileName = basename($filePath);
record_audit_log($conn, 'BACKUP', 'database', null, 'Database backup downloaded: ' . $fileName);

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . (string) filesize($filePath));
readfile($filePath);
exit();
?>
