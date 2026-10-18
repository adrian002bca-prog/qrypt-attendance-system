<?php
require_once __DIR__ . '/system_settings.php';

function default_student_departments() {
    return ['BSIS', 'BSENTREP', 'BSTM', 'ICT', 'HE', 'ABM'];
}

function default_student_year_levels() {
    return [11, 12, 1, 2, 3, 4];
}

function normalize_student_department($value) {
    $dept = strtoupper(trim((string) $value));
    if ($dept === '') {
        return '';
    }

    $dept = preg_replace('/\s+/', ' ', $dept);
    if (!is_string($dept)) {
        return '';
    }

    if (preg_match('/^[A-Z0-9][A-Z0-9 &\-]{1,98}$/', $dept) !== 1) {
        return '';
    }

    return $dept;
}

function normalize_student_year_level($value) {
    if (is_string($value)) {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        if (!is_numeric($raw)) {
            if (preg_match('/\d+/', $raw, $match) !== 1) {
                return null;
            }
            $raw = $match[0];
        }
        $value = $raw;
    }

    if (!is_numeric($value)) {
        return null;
    }

    $year = (int) $value;
    if ($year <= 0 || $year > 99) {
        return null;
    }

    return $year;
}

function format_student_year_level_label($value) {
    $year = normalize_student_year_level($value);
    if ($year === null) {
        return '-';
    }

    if ($year === 11) {
        return 'Grade 11';
    }

    if ($year === 12) {
        return 'Grade 12';
    }

    return $year . ($year === 1 ? 'st' : ($year === 2 ? 'nd' : ($year === 3 ? 'rd' : 'th'))) . ' Year';
}

function build_student_course_section($department, $yearLevel) {
    $dept = normalize_student_department($department);
    $year = normalize_student_year_level($yearLevel);

    if ($dept === '' || $year === null) {
        return '';
    }

    return $dept . ' ' . $year;
}

function normalize_student_id_number($value) {
    $idNumber = strtoupper(trim((string) $value));
    if ($idNumber === '') {
        return '';
    }

    if (preg_match('/^T\d{4}-\d{4}$/', $idNumber) !== 1) {
        return '';
    }

    return $idNumber;
}

function parse_student_departments($raw) {
    $items = preg_split('/[,\r\n]+/', (string) $raw);
    $departments = [];

    foreach ($items as $item) {
        $dept = normalize_student_department($item);
        if ($dept === '' || in_array($dept, $departments, true)) {
            continue;
        }
        $departments[] = $dept;
    }

    return $departments;
}

function parse_student_year_levels($raw) {
    $items = preg_split('/[,\r\n]+/', (string) $raw);
    $levels = [];

    foreach ($items as $item) {
        $year = normalize_student_year_level($item);
        if ($year === null || in_array($year, $levels, true)) {
            continue;
        }
        $levels[] = $year;
    }

    return $levels;
}

function get_student_departments(PDO $conn = null) {
    $defaults = default_student_departments();
    if (!($conn instanceof PDO)) {
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            $conn = $GLOBALS['conn'];
        } else {
            return $defaults;
        }
    }

    ensure_system_settings_table($conn);
    $raw = get_system_setting($conn, 'student_departments', '');
    $departments = parse_student_departments($raw);

    return count($departments) > 0 ? $departments : $defaults;
}

function get_student_year_levels(PDO $conn = null) {
    $defaults = default_student_year_levels();
    if (!($conn instanceof PDO)) {
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            $conn = $GLOBALS['conn'];
        } else {
            return $defaults;
        }
    }

    ensure_system_settings_table($conn);
    $raw = get_system_setting($conn, 'student_year_levels', '');
    $levels = parse_student_year_levels($raw);

    return count($levels) > 0 ? $levels : $defaults;
}

function valid_student_year_levels(PDO $conn = null) {
    return get_student_year_levels($conn);
}

function is_student_department_allowed($value, PDO $conn) {
    $dept = normalize_student_department($value);
    if ($dept === '') {
        return false;
    }
    return in_array($dept, get_student_departments($conn), true);
}

function is_student_year_level_allowed($value, PDO $conn) {
    $year = normalize_student_year_level($value);
    if ($year === null) {
        return false;
    }
    return in_array($year, get_student_year_levels($conn), true);
}

function student_column_exists(PDO $conn, $columnName) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $stmt->bindValue(':table_name', 'tbl_student', PDO::PARAM_STR);
    $stmt->bindValue(':column_name', $columnName, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function student_index_exists(PDO $conn, $indexName) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name');
    $stmt->bindValue(':table_name', 'tbl_student', PDO::PARAM_STR);
    $stmt->bindValue(':index_name', $indexName, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $stmt->fetchColumn() > 0;
}

function ensure_student_table(PDO $conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_student` (
        `tbl_student_id` int(11) NOT NULL AUTO_INCREMENT,
        `student_name` varchar(255) NOT NULL,
        `course_section` varchar(255) NOT NULL,
        `generated_code` varchar(255) NOT NULL,
        `photo_url` varchar(500) DEFAULT NULL,
        PRIMARY KEY (`tbl_student_id`),
        UNIQUE KEY `uq_student_generated_code` (`generated_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($sql);

    if (!student_column_exists($conn, 'photo_url')) {
        $conn->exec('ALTER TABLE `tbl_student` ADD COLUMN `photo_url` varchar(500) DEFAULT NULL AFTER `generated_code`');
    }

    if (!student_column_exists($conn, 'department')) {
        $conn->exec('ALTER TABLE `tbl_student` ADD COLUMN `department` varchar(100) DEFAULT NULL AFTER `student_name`');
    }

    if (!student_column_exists($conn, 'year_level')) {
        $conn->exec('ALTER TABLE `tbl_student` ADD COLUMN `year_level` tinyint(3) unsigned DEFAULT NULL AFTER `department`');
    }

    if (!student_column_exists($conn, 'student_id_number')) {
        $conn->exec('ALTER TABLE `tbl_student` ADD COLUMN `student_id_number` varchar(10) DEFAULT NULL AFTER `student_name`');
    }

    if (student_column_exists($conn, 'section')) {
        $conn->exec('ALTER TABLE `tbl_student` DROP COLUMN `section`');
    }

    if (!student_index_exists($conn, 'uq_student_id_number')) {
        $conn->exec('ALTER TABLE `tbl_student` ADD UNIQUE KEY `uq_student_id_number` (`student_id_number`)');
    }
}
?>
