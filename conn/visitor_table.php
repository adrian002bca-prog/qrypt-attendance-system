<?php
function visitor_column_exists(PDO $conn, $columnName) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $stmt->bindValue(':table_name', 'tbl_visitor', PDO::PARAM_STR);
    $stmt->bindValue(':column_name', $columnName, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function ensure_visitor_table(PDO $conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_visitor` (
        `tbl_visitor_id` int(11) NOT NULL AUTO_INCREMENT,
        `visitor_name` varchar(255) NOT NULL,
        `purpose` varchar(255) NOT NULL,
        `contact_no` varchar(50) NOT NULL,
        `generated_code` varchar(255) NOT NULL,
        `qr_used` tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`tbl_visitor_id`),
        UNIQUE KEY `uq_visitor_generated_code` (`generated_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($sql);

    if (!visitor_column_exists($conn, 'qr_used')) {
        $conn->exec('ALTER TABLE `tbl_visitor` ADD COLUMN `qr_used` tinyint(1) NOT NULL DEFAULT 0 AFTER `generated_code`');
    }
}
?>
