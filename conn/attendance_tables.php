<?php
function column_exists(PDO $conn, $tableName, $columnName) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $stmt->bindValue(':table_name', $tableName, PDO::PARAM_STR);
    $stmt->bindValue(':column_name', $columnName, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function table_exists(PDO $conn, $tableName) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
    $stmt->bindValue(':table_name', $tableName, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function foreign_key_exists(PDO $conn, $tableName, $constraintName) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND CONSTRAINT_NAME = :constraint_name AND CONSTRAINT_TYPE = "FOREIGN KEY"');
    $stmt->bindValue(':table_name', $tableName, PDO::PARAM_STR);
    $stmt->bindValue(':constraint_name', $constraintName, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function try_exec_sql(PDO $conn, $sql) {
    try {
        $conn->exec($sql);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function ensure_student_attendance_table(PDO $conn) {
    $createSql = "CREATE TABLE IF NOT EXISTS `tbl_attendance` (
        `tbl_attendance_id` int(11) NOT NULL AUTO_INCREMENT,
        `tbl_student_id` int(11) NOT NULL,
        `time_in` datetime NOT NULL DEFAULT current_timestamp(),
        `time_out` datetime DEFAULT NULL,
        PRIMARY KEY (`tbl_attendance_id`),
        KEY `idx_attendance_student_id` (`tbl_student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($createSql);

    if (!column_exists($conn, 'tbl_attendance', 'time_out')) {
        $conn->exec('ALTER TABLE `tbl_attendance` ADD COLUMN `time_out` datetime DEFAULT NULL AFTER `time_in`');
    }

    $conn->exec('ALTER TABLE `tbl_attendance` MODIFY COLUMN `time_in` datetime NOT NULL DEFAULT current_timestamp()');

    if (table_exists($conn, 'tbl_student') && !foreign_key_exists($conn, 'tbl_attendance', 'fk_attendance_student_id')) {
        $conn->exec('DELETE a FROM `tbl_attendance` a LEFT JOIN `tbl_student` s ON s.tbl_student_id = a.tbl_student_id WHERE s.tbl_student_id IS NULL');
        $conn->exec('ALTER TABLE `tbl_attendance` ADD CONSTRAINT `fk_attendance_student_id` FOREIGN KEY (`tbl_student_id`) REFERENCES `tbl_student`(`tbl_student_id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }
}

function ensure_visitor_attendance_table(PDO $conn) {
    $createSql = "CREATE TABLE IF NOT EXISTS `tbl_visitor_attendance` (
        `tbl_visitor_attendance_id` int(11) NOT NULL AUTO_INCREMENT,
        `tbl_visitor_id` int(11) NOT NULL,
        `time_in` datetime NOT NULL DEFAULT current_timestamp(),
        `time_out` datetime DEFAULT NULL,
        PRIMARY KEY (`tbl_visitor_attendance_id`),
        KEY `idx_visitor_attendance_visitor_id` (`tbl_visitor_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($createSql);

    if (!column_exists($conn, 'tbl_visitor_attendance', 'time_out')) {
        $conn->exec('ALTER TABLE `tbl_visitor_attendance` ADD COLUMN `time_out` datetime DEFAULT NULL AFTER `time_in`');
    }

    $conn->exec('ALTER TABLE `tbl_visitor_attendance` MODIFY COLUMN `time_in` datetime NOT NULL DEFAULT current_timestamp()');

    if (table_exists($conn, 'tbl_visitor') && !foreign_key_exists($conn, 'tbl_visitor_attendance', 'fk_visitor_attendance_visitor_id')) {
        $conn->exec('DELETE va FROM `tbl_visitor_attendance` va LEFT JOIN `tbl_visitor` v ON v.tbl_visitor_id = va.tbl_visitor_id WHERE v.tbl_visitor_id IS NULL');
        $conn->exec('ALTER TABLE `tbl_visitor_attendance` ADD CONSTRAINT `fk_visitor_attendance_visitor_id` FOREIGN KEY (`tbl_visitor_id`) REFERENCES `tbl_visitor`(`tbl_visitor_id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }
}

function ensure_visitor_destination_table(PDO $conn) {
    $createSql = "CREATE TABLE IF NOT EXISTS `tbl_visitor_destination` (
        `tbl_visitor_destination_id` int(11) NOT NULL AUTO_INCREMENT,
        `tbl_visitor_attendance_id` int(11) NOT NULL,
        `tbl_visitor_id` int(11) NOT NULL,
        `destination_name` varchar(255) NOT NULL,
        `purpose_snapshot` varchar(255) NOT NULL DEFAULT '',
        `recorded_by` varchar(100) DEFAULT NULL,
        `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
        `is_archived` tinyint(1) NOT NULL DEFAULT 0,
        `archived_at` datetime DEFAULT NULL,
        `archived_by` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`tbl_visitor_destination_id`),
        KEY `idx_visitor_destination_attendance_id` (`tbl_visitor_attendance_id`),
        KEY `idx_visitor_destination_visitor_id` (`tbl_visitor_id`),
        KEY `idx_visitor_destination_recorded_at` (`recorded_at`),
        KEY `idx_visitor_destination_archived` (`is_archived`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($createSql);

    if (!column_exists($conn, 'tbl_visitor_destination', 'recorded_by')) {
        $conn->exec('ALTER TABLE `tbl_visitor_destination` ADD COLUMN `recorded_by` varchar(100) DEFAULT NULL AFTER `destination_name`');
    }

    if (!column_exists($conn, 'tbl_visitor_destination', 'purpose_snapshot')) {
        try_exec_sql($conn, 'ALTER TABLE `tbl_visitor_destination` ADD COLUMN `purpose_snapshot` varchar(255) NOT NULL DEFAULT "" AFTER `destination_name`');
    }
    if (column_exists($conn, 'tbl_visitor_destination', 'purpose_snapshot')) {
        try_exec_sql($conn, 'UPDATE `tbl_visitor_destination` vd INNER JOIN `tbl_visitor` v ON v.tbl_visitor_id = vd.tbl_visitor_id SET vd.purpose_snapshot = v.purpose WHERE TRIM(COALESCE(vd.purpose_snapshot, "")) = ""');
    }

    if (!column_exists($conn, 'tbl_visitor_destination', 'recorded_at')) {
        $conn->exec('ALTER TABLE `tbl_visitor_destination` ADD COLUMN `recorded_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `recorded_by`');
    }

    if (!column_exists($conn, 'tbl_visitor_destination', 'is_archived')) {
        $conn->exec('ALTER TABLE `tbl_visitor_destination` ADD COLUMN `is_archived` tinyint(1) NOT NULL DEFAULT 0 AFTER `recorded_at`');
    }

    if (!column_exists($conn, 'tbl_visitor_destination', 'archived_at')) {
        $conn->exec('ALTER TABLE `tbl_visitor_destination` ADD COLUMN `archived_at` datetime DEFAULT NULL AFTER `is_archived`');
    }

    if (!column_exists($conn, 'tbl_visitor_destination', 'archived_by')) {
        $conn->exec('ALTER TABLE `tbl_visitor_destination` ADD COLUMN `archived_by` varchar(100) DEFAULT NULL AFTER `archived_at`');
    }

    if (table_exists($conn, 'tbl_visitor_attendance') && !foreign_key_exists($conn, 'tbl_visitor_destination', 'fk_visitor_destination_attendance_id')) {
        $conn->exec('DELETE vd FROM `tbl_visitor_destination` vd LEFT JOIN `tbl_visitor_attendance` va ON va.tbl_visitor_attendance_id = vd.tbl_visitor_attendance_id WHERE va.tbl_visitor_attendance_id IS NULL');
        $conn->exec('ALTER TABLE `tbl_visitor_destination` ADD CONSTRAINT `fk_visitor_destination_attendance_id` FOREIGN KEY (`tbl_visitor_attendance_id`) REFERENCES `tbl_visitor_attendance`(`tbl_visitor_attendance_id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    if (table_exists($conn, 'tbl_visitor') && !foreign_key_exists($conn, 'tbl_visitor_destination', 'fk_visitor_destination_visitor_id')) {
        $conn->exec('DELETE vd FROM `tbl_visitor_destination` vd LEFT JOIN `tbl_visitor` v ON v.tbl_visitor_id = vd.tbl_visitor_id WHERE v.tbl_visitor_id IS NULL');
        $conn->exec('ALTER TABLE `tbl_visitor_destination` ADD CONSTRAINT `fk_visitor_destination_visitor_id` FOREIGN KEY (`tbl_visitor_id`) REFERENCES `tbl_visitor`(`tbl_visitor_id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }
}

function ensure_attendance_tables(PDO $conn) {
    ensure_student_attendance_table($conn);
    ensure_visitor_attendance_table($conn);
    ensure_visitor_destination_table($conn);
}
?>
