<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/visitor_table.php');
include('../conn/audit_log.php');
require_once('../conn/system_settings.php');

require_kiosk_or_login();
verify_csrf_or_reject();

ensure_visitor_table($conn);
ensure_audit_log_table($conn);
ensure_system_settings_table($conn);

$purpose = trim((string) ($_POST['purpose'] ?? ''));
$copies = (int) ($_POST['copies'] ?? 10);

$validPurposes = get_visitor_purpose_options($conn);
if (!in_array($purpose, $validPurposes, true)) {
    http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Please select a valid purpose option.']);
    exit();
}

if ($copies < 1 || $copies > 20) {
    $copies = 10;
}

function generate_unique_premade_code(PDO $conn) {
    for ($attempt = 0; $attempt < 15; $attempt++) {
        $candidate = 'QRYPT_PRE_' . strtoupper(bin2hex(random_bytes(6)));
        $checkStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_visitor WHERE generated_code = :generated_code');
        $checkStmt->bindValue(':generated_code', $candidate, PDO::PARAM_STR);
        $checkStmt->execute();
        if ((int) $checkStmt->fetchColumn() === 0) {
            return $candidate;
        }
    }

    throw new RuntimeException('Could not generate a unique QR code.');
}

try {
    $conn->beginTransaction();

    $codes = [];
    $insertStmt = $conn->prepare('INSERT INTO tbl_visitor (visitor_name, purpose, contact_no, generated_code, qr_used) VALUES (:visitor_name, :purpose, :contact_no, :generated_code, 0)');
    for ($i = 0; $i < $copies; $i++) {
        $generatedCode = generate_unique_premade_code($conn);
        $insertStmt->bindValue(':visitor_name', '', PDO::PARAM_STR);
        $insertStmt->bindValue(':purpose', $purpose, PDO::PARAM_STR);
        $insertStmt->bindValue(':contact_no', '', PDO::PARAM_STR);
        $insertStmt->bindValue(':generated_code', $generatedCode, PDO::PARAM_STR);
        $insertStmt->execute();
        $codes[] = $generatedCode;
    }

    record_audit_log(
        $conn,
        'CREATE',
        'visitor',
        null,
        'Generated premade visitor QR batch: ' . $copies . ' code(s) for purpose "' . $purpose . '"'
    );

    $conn->commit();

    $items = [];
    foreach ($codes as $code) {
        $items[] = [
            'code' => $code,
            'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode($code),
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'purpose' => $purpose,
        'copies' => $copies,
        'items' => $items,
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Failed to generate premade QR batch.']);
}
?>
