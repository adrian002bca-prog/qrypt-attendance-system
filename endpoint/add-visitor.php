<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/visitor_table.php');
include('../conn/audit_log.php');

require_login();
ensure_visitor_table($conn);
ensure_audit_log_table($conn);
verify_csrf_or_reject();

$visitorFirstName = trim($_POST['visitor_first_name'] ?? '');
$visitorLastName = trim($_POST['visitor_last_name'] ?? '');
$visitorName = trim($_POST['visitor_name'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');
$contactNo = trim($_POST['contact_no'] ?? '');
$generatedCode = trim($_POST['generated_code'] ?? '');
$consentTerms = trim((string) ($_POST['consent_terms'] ?? ''));

if ($visitorFirstName !== '' || $visitorLastName !== '') {
    if ($visitorFirstName === '' || $visitorLastName === '') {
        exit('Please provide both first name and last name.');
    }
    $visitorName = trim($visitorFirstName . ' ' . $visitorLastName);
}

if ($visitorName === '' || $purpose === '' || $contactNo === '' || $generatedCode === '') {
    exit('Please fill in all fields.');
}

if (!preg_match('/^[A-Za-z0-9]{10,64}$/', $generatedCode)) {
    exit('Invalid QR code format.');
}

if ($consentTerms !== '1') {
    exit('You must agree to the Terms and Conditions before saving.');
}

try {
    $checkStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_visitor WHERE generated_code = :generated_code');
    $checkStmt->bindValue(':generated_code', $generatedCode, PDO::PARAM_STR);
    $checkStmt->execute();

    if ((int) $checkStmt->fetchColumn() > 0) {
        exit('QR code already exists. Generate a new one.');
    }

    $stmt = $conn->prepare('INSERT INTO tbl_visitor (visitor_name, purpose, contact_no, generated_code) VALUES (:visitor_name, :purpose, :contact_no, :generated_code)');
    $stmt->bindValue(':visitor_name', $visitorName, PDO::PARAM_STR);
    $stmt->bindValue(':purpose', $purpose, PDO::PARAM_STR);
    $stmt->bindValue(':contact_no', $contactNo, PDO::PARAM_STR);
    $stmt->bindValue(':generated_code', $generatedCode, PDO::PARAM_STR);
    $stmt->execute();
    $newVisitorId = (int) $conn->lastInsertId();
    record_audit_log(
        $conn,
        'CREATE',
        'visitor',
        $newVisitorId,
        'Added visitor: ' . $visitorName . ' | ' . $purpose
    );

    redirect_to('visitorlist.php');
} catch (PDOException $e) {
    http_response_code(500);
    exit('Failed to add visitor.');
}
?>


