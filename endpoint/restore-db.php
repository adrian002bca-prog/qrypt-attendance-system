<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/db_backup.php');
include('../conn/audit_log.php');

require_login();
verify_csrf_or_reject();
ensure_audit_log_table($conn);

$confirmText = trim((string) ($_POST['confirm_restore'] ?? ''));
if ($confirmText !== 'CONFIRM') {
    exit('Type CONFIRM to proceed with restore.');
}

if (!isset($_FILES['backup_file'])) {
    exit('Please upload a SQL file.');
}

$uploadErr = (int) ($_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadErr !== UPLOAD_ERR_OK) {
    exit('Failed to upload backup file.');
}

$maxRestoreBytes = 20 * 1024 * 1024;
$fileSize = (int) ($_FILES['backup_file']['size'] ?? 0);
if ($fileSize <= 0 || $fileSize > $maxRestoreBytes) {
    exit('Backup file must be 20 MB or less.');
}

$fileName = (string) ($_FILES['backup_file']['name'] ?? '');
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($ext !== 'sql') {
    exit('Only .sql backup file is allowed.');
}

$tmpPath = (string) ($_FILES['backup_file']['tmp_name'] ?? '');
if (!is_uploaded_file($tmpPath)) {
    exit('Invalid uploaded file.');
}

$sqlContent = @file_get_contents($tmpPath);
if (!is_string($sqlContent) || trim($sqlContent) === '') {
    exit('Uploaded SQL file is empty.');
}

$preRestorePath = create_backup_file($conn, 'pre_restore');
if ($preRestorePath === false) {
    exit('Failed to create pre-restore backup.');
}

try {
    restore_from_sql_content($conn, $sqlContent);
    ensure_audit_log_table($conn);
    record_audit_log(
        $conn,
        'RESTORE',
        'database',
        null,
        'Database restored from ' . $fileName . ' (pre-backup: ' . basename($preRestorePath) . ')'
    );
    redirect_to('settings.php');
} catch (Throwable $e) {
    http_response_code(500);
    exit('Restore failed: ' . $e->getMessage() . ' Use pre-restore backup if needed.');
}
?>
