<?php
require_once __DIR__ . '/attendance_tables.php';
require_once __DIR__ . '/audit_log.php';
require_once __DIR__ . '/system_settings.php';

function normalize_auto_close_cutoff($value) {
    $raw = trim((string) $value);
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $raw) === 1) {
        return $raw;
    }
    return '23:59';
}

function get_auto_close_cutoff_time(PDO $conn) {
    ensure_system_settings_table($conn);
    $raw = get_system_setting($conn, 'auto_close_cutoff_time', '23:59');
    return normalize_auto_close_cutoff($raw);
}

function ensure_daily_auto_close(PDO $conn) {
    ensure_attendance_tables($conn);
    ensure_audit_log_table($conn);
    ensure_system_settings_table($conn);

    $today = date('Y-m-d');
    $todayStart = $today . ' 00:00:00';
    $lastRunDate = trim((string) get_system_setting($conn, 'auto_close_last_run_date', ''));
    if ($lastRunDate === $today) {
        return ['ran' => false, 'closed_count' => 0];
    }

    $cutoffTime = get_auto_close_cutoff_time($conn);
    $closedCount = 0;

    try {
        $conn->beginTransaction();

        $studentStmt = $conn->prepare('SELECT tbl_attendance_id, time_in FROM tbl_attendance WHERE time_out IS NULL AND time_in < :today_start FOR UPDATE');
        $studentStmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
        $studentStmt->execute();
        $studentRows = $studentStmt->fetchAll();

        foreach ($studentRows as $row) {
            $attendanceId = (int) ($row['tbl_attendance_id'] ?? 0);
            $timeInRaw = (string) ($row['time_in'] ?? '');
            if ($attendanceId <= 0 || $timeInRaw === '') {
                continue;
            }

            $timeIn = new DateTime($timeInRaw, new DateTimeZone('Asia/Manila'));
            $closeAt = new DateTime($timeIn->format('Y-m-d') . ' ' . $cutoffTime . ':00', new DateTimeZone('Asia/Manila'));
            if ($closeAt < $timeIn) {
                $closeAt = clone $timeIn;
            }

            $updateStmt = $conn->prepare('UPDATE tbl_attendance SET time_out = :time_out WHERE tbl_attendance_id = :id AND time_out IS NULL');
            $updateStmt->bindValue(':time_out', $closeAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $updateStmt->bindValue(':id', $attendanceId, PDO::PARAM_INT);
            $updateStmt->execute();

            if ($updateStmt->rowCount() > 0) {
                $closedCount++;
                record_audit_log($conn, 'AUTO_CLOSE', 'student_attendance', $attendanceId, 'Auto-closed unresolved IN record using cutoff ' . $cutoffTime);
            }
        }

        $visitorStmt = $conn->prepare('SELECT tbl_visitor_attendance_id, time_in FROM tbl_visitor_attendance WHERE time_out IS NULL AND time_in < :today_start FOR UPDATE');
        $visitorStmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
        $visitorStmt->execute();
        $visitorRows = $visitorStmt->fetchAll();

        foreach ($visitorRows as $row) {
            $attendanceId = (int) ($row['tbl_visitor_attendance_id'] ?? 0);
            $timeInRaw = (string) ($row['time_in'] ?? '');
            if ($attendanceId <= 0 || $timeInRaw === '') {
                continue;
            }

            $timeIn = new DateTime($timeInRaw, new DateTimeZone('Asia/Manila'));
            $closeAt = new DateTime($timeIn->format('Y-m-d') . ' ' . $cutoffTime . ':00', new DateTimeZone('Asia/Manila'));
            if ($closeAt < $timeIn) {
                $closeAt = clone $timeIn;
            }

            $updateStmt = $conn->prepare('UPDATE tbl_visitor_attendance SET time_out = :time_out WHERE tbl_visitor_attendance_id = :id AND time_out IS NULL');
            $updateStmt->bindValue(':time_out', $closeAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $updateStmt->bindValue(':id', $attendanceId, PDO::PARAM_INT);
            $updateStmt->execute();

            if ($updateStmt->rowCount() > 0) {
                $closedCount++;
                record_audit_log($conn, 'AUTO_CLOSE', 'visitor_attendance', $attendanceId, 'Auto-closed unresolved IN record using cutoff ' . $cutoffTime);
            }
        }

        set_system_setting($conn, 'auto_close_last_run_date', $today);
        $conn->commit();

        return ['ran' => true, 'closed_count' => $closedCount];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return ['ran' => false, 'closed_count' => 0];
    }
}
?>
