<?php
function ensure_system_settings_table(PDO $conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_system_setting` (
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text NOT NULL,
        `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->exec($sql);

    // Backward compatibility: upgrade old varchar column to text for longer setting values.
    try {
        $columnStmt = $conn->query("SHOW COLUMNS FROM `tbl_system_setting` LIKE 'setting_value'");
        $column = $columnStmt ? $columnStmt->fetch(PDO::FETCH_ASSOC) : false;
        $type = strtolower((string) ($column['Type'] ?? ''));
        if ($type !== '' && strpos($type, 'varchar') === 0) {
            $conn->exec("ALTER TABLE `tbl_system_setting` MODIFY `setting_value` text NOT NULL");
        }
    } catch (Exception $e) {
        // Keep app running even if schema check fails in restricted environments.
    }
}

function get_system_setting(PDO $conn, $key, $defaultValue = null) {
    $stmt = $conn->prepare('SELECT setting_value FROM tbl_system_setting WHERE setting_key = :setting_key LIMIT 1');
    $stmt->bindValue(':setting_key', (string) $key, PDO::PARAM_STR);
    $stmt->execute();
    $value = $stmt->fetchColumn();

    if ($value === false) {
        return $defaultValue;
    }

    return (string) $value;
}

function get_system_setting_int(PDO $conn, $key, $defaultValue = 0) {
    $value = get_system_setting($conn, $key, (string) $defaultValue);
    if (!is_numeric($value)) {
        return (int) $defaultValue;
    }
    return (int) $value;
}

function set_system_setting(PDO $conn, $key, $value) {
    $stmt = $conn->prepare('INSERT INTO tbl_system_setting (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->bindValue(':setting_key', (string) $key, PDO::PARAM_STR);
    $stmt->bindValue(':setting_value', (string) $value, PDO::PARAM_STR);
    $stmt->execute();
}

function get_default_visitor_purpose_options() {
    return [
        'Accounting/cashier',
        'Registrar',
        'Admin/Front desk',
        'Principal concern/office',
        'Tesda program',
        'Clinic',
        'Others: (Campus tour/Marketing/Stakeholders Activity)',
        'HR appointment',
    ];
}

function parse_visitor_purpose_options_text($rawText) {
    $text = trim((string) $rawText);
    if ($text === '') {
        return [];
    }

    $parts = preg_split('/[\r\n,]+/', $text);
    if (!is_array($parts)) {
        return [];
    }

    $clean = [];
    $seen = [];
    foreach ($parts as $part) {
        $value = trim((string) $part);
        if ($value === '') {
            continue;
        }

        // Normalize spacing while preserving punctuation/case.
        $value = preg_replace('/\s+/', ' ', $value);
        if (!is_string($value)) {
            continue;
        }
        $value = trim($value);
        if ($value === '') {
            continue;
        }

        if (strlen($value) > 120) {
            $value = substr($value, 0, 120);
        }

        $key = strtolower($value);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $clean[] = $value;
    }

    return $clean;
}

function get_visitor_purpose_options(PDO $conn) {
    $stored = get_system_setting($conn, 'visitor_purpose_options', '');
    $parsed = parse_visitor_purpose_options_text($stored);
    if (count($parsed) === 0) {
        return get_default_visitor_purpose_options();
    }
    return $parsed;
}

function get_premade_visitor_qr_code(PDO $conn) {
    $defaultCode = 'QRYPT_VISITOR_PREMADE_UNLIMITED';
    $stored = trim((string) get_system_setting($conn, 'premade_visitor_qr_code', $defaultCode));
    if ($stored === '') {
        return $defaultCode;
    }

    if (!preg_match('/^[A-Za-z0-9_-]{8,120}$/', $stored)) {
        return $defaultCode;
    }

    return $stored;
}

function ensure_premade_visitor_profile(PDO $conn) {
    $premadeCode = get_premade_visitor_qr_code($conn);

    $selectStmt = $conn->prepare('SELECT tbl_visitor_id FROM tbl_visitor WHERE generated_code = :generated_code LIMIT 1');
    $selectStmt->bindValue(':generated_code', $premadeCode, PDO::PARAM_STR);
    $selectStmt->execute();
    $existingId = $selectStmt->fetchColumn();

    if ($existingId !== false) {
        return (int) $existingId;
    }

    $defaultPurposeOptions = get_default_visitor_purpose_options();
    $defaultPurpose = isset($defaultPurposeOptions[0]) ? (string) $defaultPurposeOptions[0] : 'Clinic';

    $insertStmt = $conn->prepare('INSERT INTO tbl_visitor (visitor_name, purpose, contact_no, generated_code, qr_used) VALUES (:visitor_name, :purpose, :contact_no, :generated_code, 0)');
    $insertStmt->bindValue(':visitor_name', '', PDO::PARAM_STR);
    $insertStmt->bindValue(':purpose', $defaultPurpose, PDO::PARAM_STR);
    $insertStmt->bindValue(':contact_no', '', PDO::PARAM_STR);
    $insertStmt->bindValue(':generated_code', $premadeCode, PDO::PARAM_STR);
    $insertStmt->execute();

    return (int) $conn->lastInsertId();
}
?>
