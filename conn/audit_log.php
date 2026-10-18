<?php
function ensure_audit_log_table(PDO $conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_audit_log` (
        `tbl_audit_log_id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(100) NOT NULL,
        `action` varchar(100) NOT NULL,
        `entity_type` varchar(100) NOT NULL,
        `entity_id` int(11) DEFAULT NULL,
        `details` text DEFAULT NULL,
        `ip_address` varchar(64) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`tbl_audit_log_id`),
        KEY `idx_audit_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($sql);
}

function record_audit_log(PDO $conn, $action, $entityType, $entityId = null, $details = null) {
    if (!is_string($action) || trim($action) === '' || !is_string($entityType) || trim($entityType) === '') {
        return;
    }

    $username = '';
    if (isset($_SESSION['username']) && is_string($_SESSION['username'])) {
        $username = trim($_SESSION['username']);
    }
    if ($username === '') {
        if (function_exists('is_kiosk_mode') && is_kiosk_mode()) {
            $username = 'KIOSK_USER';
        } else {
            $username = 'unknown';
        }
    }
    $ipAddress = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    $entityIdValue = is_int($entityId) ? $entityId : (is_numeric($entityId) ? (int) $entityId : null);
    $detailText = $details === null ? null : (string) $details;

    $stmt = $conn->prepare('INSERT INTO tbl_audit_log (username, action, entity_type, entity_id, details, ip_address) VALUES (:username, :action, :entity_type, :entity_id, :details, :ip_address)');
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->bindValue(':action', trim($action), PDO::PARAM_STR);
    $stmt->bindValue(':entity_type', trim($entityType), PDO::PARAM_STR);
    if ($entityIdValue === null) {
        $stmt->bindValue(':entity_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':entity_id', $entityIdValue, PDO::PARAM_INT);
    }
    if ($detailText === null || $detailText === '') {
        $stmt->bindValue(':details', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':details', $detailText, PDO::PARAM_STR);
    }
    if ($ipAddress === null || $ipAddress === '') {
        $stmt->bindValue(':ip_address', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
    }
    $stmt->execute();
}
?>
