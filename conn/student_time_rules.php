<?php
require_once __DIR__ . '/system_settings.php';
require_once __DIR__ . '/student_table.php';

function normalize_hhmm_time($value, $default = '08:00') {
    $raw = trim((string) $value);
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $raw) === 1) {
        return $raw;
    }
    return $default;
}

function get_student_class_start_time(PDO $conn) {
    ensure_system_settings_table($conn);
    return normalize_hhmm_time(get_system_setting($conn, 'student_class_start_time', '08:00'), '08:00');
}

function get_student_class_end_time(PDO $conn) {
    ensure_system_settings_table($conn);
    return normalize_hhmm_time(get_system_setting($conn, 'student_class_end_time', '17:00'), '17:00');
}

function normalize_grace_minutes($value, $default = 0) {
    if (is_string($value)) {
        $value = trim($value);
    }
    if ($value === '' || $value === null) {
        return (int) $default;
    }
    if (!is_numeric($value)) {
        return (int) $default;
    }

    $minutes = (int) $value;
    if ($minutes < 0) {
        return 0;
    }
    if ($minutes > 180) {
        return 180;
    }

    return $minutes;
}

function get_student_late_grace_minutes(PDO $conn) {
    ensure_system_settings_table($conn);
    return normalize_grace_minutes(get_system_setting($conn, 'student_late_grace_minutes', '0'), 0);
}

function get_student_early_out_grace_minutes(PDO $conn) {
    ensure_system_settings_table($conn);
    return normalize_grace_minutes(get_system_setting($conn, 'student_early_out_grace_minutes', '0'), 0);
}

function normalize_student_room($value) {
    $room = trim((string) $value);
    if ($room === '') {
        return '';
    }
    // Allow letters, numbers, spaces, dash, slash, hash.
    if (preg_match('/^[A-Za-z0-9 \\-#\\/]{1,50}$/', $room) !== 1) {
        return '';
    }
    return $room;
}

function student_time_rule_column_exists(PDO $conn, $columnName) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $stmt->bindValue(':table_name', 'tbl_student_time_rule', PDO::PARAM_STR);
    $stmt->bindValue(':column_name', (string) $columnName, PDO::PARAM_STR);
    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

function student_time_rule_index_exists(PDO $conn, $indexName) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name');
    $stmt->bindValue(':table_name', 'tbl_student_time_rule', PDO::PARAM_STR);
    $stmt->bindValue(':index_name', (string) $indexName, PDO::PARAM_STR);
    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

function normalize_student_rule_day_key($value) {
    if (is_string($value)) {
        $value = trim($value);
    }
    if ($value === '' || $value === null) {
        return 0;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $day = (int) $value;
    if ($day < 0 || $day > 7) {
        return null;
    }
    return $day;
}

function student_rule_day_label($dayKey) {
    $map = [
        0 => 'Any Day',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    $key = normalize_student_rule_day_key($dayKey);
    if ($key === null) {
        return 'Unknown';
    }

    return $map[$key];
}

function day_key_from_datetime_value($dateTimeValue) {
    try {
        $dt = new DateTime((string) $dateTimeValue, new DateTimeZone('Asia/Manila'));
        return (int) $dt->format('N');
    } catch (Exception $e) {
        return 0;
    }
}

function ensure_student_time_rules_table(PDO $conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_student_time_rule` (
        `tbl_student_time_rule_id` int(11) NOT NULL AUTO_INCREMENT,
        `department` varchar(100) NOT NULL,
        `year_level` tinyint(3) unsigned NOT NULL,
        `day_key` tinyint(3) unsigned NOT NULL DEFAULT 0,
        `class_start_time` char(5) NOT NULL,
        `class_end_time` char(5) NOT NULL,
        `room` varchar(100) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`tbl_student_time_rule_id`),
        UNIQUE KEY `uq_student_time_rule_dept_year_day` (`department`, `year_level`, `day_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($sql);

    if (!student_time_rule_column_exists($conn, 'day_key')) {
        $conn->exec('ALTER TABLE `tbl_student_time_rule` ADD COLUMN `day_key` tinyint(3) unsigned NOT NULL DEFAULT 0 AFTER `year_level`');
    }

    if (student_time_rule_index_exists($conn, 'uq_student_time_rule_dept_year')) {
        $conn->exec('ALTER TABLE `tbl_student_time_rule` DROP INDEX `uq_student_time_rule_dept_year`');
    }

    if (!student_time_rule_index_exists($conn, 'uq_student_time_rule_dept_year_day')) {
        $conn->exec('ALTER TABLE `tbl_student_time_rule` ADD UNIQUE KEY `uq_student_time_rule_dept_year_day` (`department`, `year_level`, `day_key`)');
    }

    if (!student_time_rule_column_exists($conn, 'room')) {
        $conn->exec('ALTER TABLE `tbl_student_time_rule` ADD COLUMN `room` varchar(100) DEFAULT NULL AFTER `class_end_time`');
    }
}

function get_student_time_rules(PDO $conn) {
    ensure_student_time_rules_table($conn);

    $stmt = $conn->prepare('SELECT tbl_student_time_rule_id, department, year_level, day_key, class_start_time, class_end_time, room FROM tbl_student_time_rule ORDER BY year_level ASC, department ASC, day_key ASC');
    $stmt->execute();

    return $stmt->fetchAll();
}

function upsert_student_time_rule(PDO $conn, $department, $yearLevel, $classStartTime, $classEndTime, $dayKey = 0, $room = '') {
    ensure_student_time_rules_table($conn);

    $dept = normalize_student_department($department);
    $year = normalize_student_year_level($yearLevel);
    $day = normalize_student_rule_day_key($dayKey);
    $start = normalize_hhmm_time($classStartTime, '');
    $end = normalize_hhmm_time($classEndTime, '');
    $roomVal = normalize_student_room($room);

    if ($dept === '' || $year === null || $day === null || $start === '' || $end === '' || $start >= $end) {
        return false;
    }

    $stmt = $conn->prepare('INSERT INTO tbl_student_time_rule (department, year_level, day_key, class_start_time, class_end_time, room) VALUES (:department, :year_level, :day_key, :class_start_time, :class_end_time, :room) ON DUPLICATE KEY UPDATE class_start_time = VALUES(class_start_time), class_end_time = VALUES(class_end_time), room = VALUES(room)');
    $stmt->bindValue(':department', $dept, PDO::PARAM_STR);
    $stmt->bindValue(':year_level', $year, PDO::PARAM_INT);
    $stmt->bindValue(':day_key', $day, PDO::PARAM_INT);
    $stmt->bindValue(':class_start_time', $start, PDO::PARAM_STR);
    $stmt->bindValue(':class_end_time', $end, PDO::PARAM_STR);
    if ($roomVal === '') {
        $stmt->bindValue(':room', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':room', $roomVal, PDO::PARAM_STR);
    }
    $stmt->execute();

    return true;
}

function delete_student_time_rule(PDO $conn, $ruleId) {
    ensure_student_time_rules_table($conn);
    $id = (int) $ruleId;
    if ($id <= 0) {
        return false;
    }

    $stmt = $conn->prepare('DELETE FROM tbl_student_time_rule WHERE tbl_student_time_rule_id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount() > 0;
}

function build_student_time_rule_map($ruleRows) {
    $map = [];
    if (!is_array($ruleRows)) {
        return $map;
    }

    foreach ($ruleRows as $row) {
        $dept = normalize_student_department($row['department'] ?? '');
        $year = normalize_student_year_level($row['year_level'] ?? null);
        $day = normalize_student_rule_day_key($row['day_key'] ?? 0);
        $start = normalize_hhmm_time($row['class_start_time'] ?? '', '');
        $end = normalize_hhmm_time($row['class_end_time'] ?? '', '');
        $room = normalize_student_room($row['room'] ?? '');

        if ($dept === '' || $year === null || $day === null || $start === '' || $end === '' || $start >= $end) {
            continue;
        }

        $key = $dept . '|' . $year . '|' . $day;
        $map[$key] = [
            'start' => $start,
            'end' => $end,
            'room' => $room,
        ];
    }

    return $map;
}

function resolve_student_schedule_from_map($ruleMap, $department, $yearLevel, $defaultStart, $defaultEnd, $dateTimeValue = '') {
    $dept = normalize_student_department($department);
    $year = normalize_student_year_level($yearLevel);
    $day = day_key_from_datetime_value($dateTimeValue);

    if ($dept !== '' && $year !== null) {
        $dayKey = $dept . '|' . $year . '|' . $day;
        $fallbackAnyDayKey = $dept . '|' . $year . '|0';

        if (is_array($ruleMap) && isset($ruleMap[$dayKey])) {
            return [
                'start' => $ruleMap[$dayKey]['start'],
                'end' => $ruleMap[$dayKey]['end'],
            ];
        }

        if (is_array($ruleMap) && isset($ruleMap[$fallbackAnyDayKey])) {
            return [
                'start' => $ruleMap[$fallbackAnyDayKey]['start'],
                'end' => $ruleMap[$fallbackAnyDayKey]['end'],
            ];
        }
    }

    return [
        'start' => normalize_hhmm_time($defaultStart, '08:00'),
        'end' => normalize_hhmm_time($defaultEnd, '17:00'),
    ];
}

function evaluate_student_time_rule($timeInRaw, $timeOutRaw, $classStartTime, $classEndTime, $lateGraceMinutes = 0, $earlyOutGraceMinutes = 0) {
    $result = [
        'arrival_label' => '-',
        'arrival_badge' => 'secondary',
        'departure_label' => '-',
        'departure_badge' => 'secondary',
    ];

    $lateGrace = normalize_grace_minutes($lateGraceMinutes, 0);
    $earlyGrace = normalize_grace_minutes($earlyOutGraceMinutes, 0);

    try {
        $timeIn = new DateTime((string) $timeInRaw, new DateTimeZone('Asia/Manila'));
        $classStart = new DateTime($timeIn->format('Y-m-d') . ' ' . $classStartTime . ':00', new DateTimeZone('Asia/Manila'));
        if ($lateGrace > 0) {
            $classStart->modify('+' . $lateGrace . ' minutes');
        }

        if ($timeIn > $classStart) {
            $result['arrival_label'] = 'LATE';
            $result['arrival_badge'] = 'danger';
        } else {
            $result['arrival_label'] = 'ON TIME';
            $result['arrival_badge'] = 'success';
        }
    } catch (Exception $e) {
        return $result;
    }

    if (!is_string($timeOutRaw) || trim($timeOutRaw) === '') {
        return $result;
    }

    try {
        $timeOut = new DateTime((string) $timeOutRaw, new DateTimeZone('Asia/Manila'));
        $classEnd = new DateTime($timeOut->format('Y-m-d') . ' ' . $classEndTime . ':00', new DateTimeZone('Asia/Manila'));
        if ($earlyGrace > 0) {
            $classEnd->modify('-' . $earlyGrace . ' minutes');
        }

        if ($timeOut < $classEnd) {
            $result['departure_label'] = 'EARLY OUT';
            $result['departure_badge'] = 'warning';
        } else {
            $result['departure_label'] = 'ON TIME';
            $result['departure_badge'] = 'success';
        }
    } catch (Exception $e) {
        return $result;
    }

    return $result;
}
?>
