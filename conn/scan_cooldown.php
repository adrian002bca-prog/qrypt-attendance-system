<?php
function ensure_scan_cooldown_table(PDO $conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_scan_cooldown` (
        `tbl_scan_cooldown_id` int(11) NOT NULL AUTO_INCREMENT,
        `entity_type` varchar(50) NOT NULL,
        `entity_id` int(11) NOT NULL,
        `last_scanned_at` datetime NOT NULL,
        PRIMARY KEY (`tbl_scan_cooldown_id`),
        UNIQUE KEY `uq_scan_cooldown_entity` (`entity_type`, `entity_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($sql);
}

function check_and_touch_scan_cooldown(PDO $conn, $entityType, $entityId, $cooldownSeconds, $nowDateTime) {
    $entityType = trim((string) $entityType);
    $entityId = (int) $entityId;
    $cooldownSeconds = max(1, (int) $cooldownSeconds);

    $stmt = $conn->prepare('SELECT tbl_scan_cooldown_id, last_scanned_at FROM tbl_scan_cooldown WHERE entity_type = :entity_type AND entity_id = :entity_id LIMIT 1 FOR UPDATE');
    $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
    $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();

    $nowTs = strtotime((string) $nowDateTime);
    if ($nowTs === false) {
        $nowTs = time();
    }

    if ($row !== false) {
        $lastTs = strtotime((string) ($row['last_scanned_at'] ?? ''));
        if ($lastTs !== false) {
            $diff = $nowTs - $lastTs;
            if ($diff < $cooldownSeconds) {
                return [
                    'blocked' => true,
                    'remaining' => $cooldownSeconds - max(0, $diff),
                ];
            }
        }

        $updateStmt = $conn->prepare('UPDATE tbl_scan_cooldown SET last_scanned_at = :last_scanned_at WHERE tbl_scan_cooldown_id = :id');
        $updateStmt->bindValue(':last_scanned_at', (string) $nowDateTime, PDO::PARAM_STR);
        $updateStmt->bindValue(':id', (int) $row['tbl_scan_cooldown_id'], PDO::PARAM_INT);
        $updateStmt->execute();
    } else {
        $insertStmt = $conn->prepare('INSERT INTO tbl_scan_cooldown (entity_type, entity_id, last_scanned_at) VALUES (:entity_type, :entity_id, :last_scanned_at)');
        $insertStmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
        $insertStmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $insertStmt->bindValue(':last_scanned_at', (string) $nowDateTime, PDO::PARAM_STR);
        $insertStmt->execute();
    }

    return ['blocked' => false, 'remaining' => 0];
}
?>
