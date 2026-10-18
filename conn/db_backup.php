<?php
function backup_dir_path() {
    $configured = getenv('QRYPT_BACKUP_DIR');
    if (is_string($configured) && trim($configured) !== '') {
        return rtrim(str_replace('\\', '/', trim($configured)), '/');
    }

    // Local XAMPP preference.
    $localSecurePath = 'C:/xampp/secure-data/qr-attendance/backups';
    if (DIRECTORY_SEPARATOR === '\\' && is_dir('C:/xampp')) {
        return $localSecurePath;
    }

    // Hosting-safe fallback (relative to project root).
    return str_replace('\\', '/', dirname(__DIR__) . '/backups');
}

function ensure_backup_directory() {
    $dir = backup_dir_path();
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
    }
    return true;
}

function sql_dump_value(PDO $conn, $value) {
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return $conn->quote((string) $value);
}

function generate_sql_dump(PDO $conn) {
    $dbName = (string) $conn->query('SELECT DATABASE()')->fetchColumn();
    $lines = [];
    $lines[] = '-- QRYPT backup';
    $lines[] = '-- Generated at: ' . date('Y-m-d H:i:s');
    $lines[] = '-- Database: ' . $dbName;
    $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
    $lines[] = '';

    $tables = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);

    foreach ($tables as $tableRow) {
        $table = (string) ($tableRow[0] ?? '');
        if ($table === '') {
            continue;
        }

        $createRow = $conn->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_ASSOC);
        if (!is_array($createRow) || !isset($createRow['Create Table'])) {
            continue;
        }

        $lines[] = '-- Table structure for `' . $table . '`';
        $lines[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
        $lines[] = $createRow['Create Table'] . ';';
        $lines[] = '';

        $rows = $conn->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`', PDO::FETCH_ASSOC);
        $rowCount = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowCount++;
            $columns = array_keys($row);
            $columnSql = [];
            foreach ($columns as $col) {
                $columnSql[] = '`' . str_replace('`', '``', (string) $col) . '`';
            }

            $valuesSql = [];
            foreach ($row as $val) {
                $valuesSql[] = sql_dump_value($conn, $val);
            }

            $lines[] = 'INSERT INTO `' . $table . '` (' . implode(', ', $columnSql) . ') VALUES (' . implode(', ', $valuesSql) . ');';
        }

        if ($rowCount === 0) {
            $lines[] = '-- No data for `' . $table . '`';
        }
        $lines[] = '';
    }

    $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
    $lines[] = '';

    return implode("\n", $lines);
}

function create_backup_file(PDO $conn, $fileNamePrefix = 'backup') {
    if (!ensure_backup_directory()) {
        return false;
    }

    $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $fileNamePrefix);
    if ($safePrefix === '') {
        $safePrefix = 'backup';
    }

    $fileName = $safePrefix . '_' . date('Ymd_His') . '.sql';
    $path = backup_dir_path() . '/' . $fileName;
    $dump = generate_sql_dump($conn);
    $bytes = @file_put_contents($path, $dump, LOCK_EX);

    if ($bytes === false) {
        return false;
    }

    return $path;
}

function ensure_daily_backup(PDO $conn) {
    if (!ensure_backup_directory()) {
        return false;
    }

    $dailyPath = backup_dir_path() . '/daily_' . date('Ymd') . '.sql';
    if (is_file($dailyPath)) {
        return $dailyPath;
    }

    $dump = generate_sql_dump($conn);
    $bytes = @file_put_contents($dailyPath, $dump, LOCK_EX);
    if ($bytes === false) {
        return false;
    }

    return $dailyPath;
}

function split_sql_statements($sql) {
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        $next = ($i + 1 < $length) ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
                $buffer .= $ch;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($ch === '-' && $next === '-') {
                $inLineComment = true;
                $i++;
                continue;
            }
            if ($ch === '#') {
                $inLineComment = true;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($ch === "'" && !$inDouble && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inSingle = !$inSingle;
            }
            $buffer .= $ch;
            continue;
        }

        if ($ch === '"' && !$inSingle && !$inBacktick) {
            $escaped = $i > 0 && $sql[$i - 1] === '\\';
            if (!$escaped) {
                $inDouble = !$inDouble;
            }
            $buffer .= $ch;
            continue;
        }

        if ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            $buffer .= $ch;
            continue;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function statement_starts_with($statement, $prefix) {
    $statement = ltrim((string) $statement);
    $prefix = (string) $prefix;
    return strncasecmp($statement, $prefix, strlen($prefix)) === 0;
}

function is_allowed_restore_statement($statement) {
    $stmt = ltrim((string) $statement);
    if ($stmt === '') {
        return false;
    }

    $allowedPrefixes = [
        'SET FOREIGN_KEY_CHECKS',
        'DROP TABLE IF EXISTS',
        'CREATE TABLE',
        'INSERT INTO',
        'ALTER TABLE',
        'UPDATE',
    ];

    foreach ($allowedPrefixes as $prefix) {
        if (statement_starts_with($stmt, $prefix)) {
            return true;
        }
    }

    return false;
}

function validate_restore_sql_content($sqlContent) {
    if (!is_string($sqlContent) || trim($sqlContent) === '') {
        return [false, 'SQL content is empty.'];
    }

    // `DELIMITER` and routine statements are client-side constructs not supported by this restore flow.
    if (preg_match('/^\s*DELIMITER\b/im', $sqlContent) === 1) {
        return [false, 'Unsupported SQL dump: DELIMITER statements are not allowed.'];
    }

    if (preg_match('/\b(CREATE|ALTER)\s+(PROCEDURE|FUNCTION|TRIGGER|EVENT)\b/i', $sqlContent) === 1) {
        return [false, 'Unsupported SQL dump: routines, triggers, and events are not allowed.'];
    }

    $statements = split_sql_statements($sqlContent);
    if (count($statements) === 0) {
        return [false, 'No valid SQL statements found.'];
    }

    foreach ($statements as $statement) {
        if (!is_allowed_restore_statement($statement)) {
            return [false, 'Unsupported restore statement detected.'];
        }
    }

    return [true, null];
}

function restore_from_sql_content(PDO $conn, $sqlContent) {
    [$isValid, $error] = validate_restore_sql_content($sqlContent);
    if (!$isValid) {
        throw new RuntimeException((string) $error);
    }

    $statements = split_sql_statements($sqlContent);
    try {
        if (!$conn->inTransaction()) {
            $conn->beginTransaction();
        }

        foreach ($statements as $statement) {
            $conn->exec($statement);
        }

        if ($conn->inTransaction()) {
            $conn->commit();
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}
?>
