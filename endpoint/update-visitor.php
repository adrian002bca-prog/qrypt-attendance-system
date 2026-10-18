<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/visitor_table.php');
include('../conn/audit_log.php');
require_once('../conn/system_settings.php');

require_kiosk_or_login();
ensure_visitor_table($conn);
ensure_audit_log_table($conn);
ensure_system_settings_table($conn);
verify_csrf_or_reject();

$visitorId = filter_input(INPUT_POST, 'tbl_visitor_id', FILTER_VALIDATE_INT);
$visitorFirstName = trim($_POST['visitor_first_name'] ?? '');
$visitorLastName = trim($_POST['visitor_last_name'] ?? '');
$visitorName = trim($_POST['visitor_name'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');
$contactNo = trim($_POST['contact_no'] ?? '');
$redirectPage = trim((string) ($_POST['redirect_page'] ?? 'visitorlist.php'));
if ($redirectPage !== 'visitorlist.php' && $redirectPage !== 'user-visitors.php') {
    $redirectPage = 'visitorlist.php';
}

if ($visitorFirstName !== '' || $visitorLastName !== '') {
    if ($visitorFirstName === '' || $visitorLastName === '') {
        exit('Please provide both first name and last name.');
    }
    $visitorName = trim($visitorFirstName . ' ' . $visitorLastName);
}

if (!$visitorId || $visitorName === '' || $purpose === '' || $contactNo === '') {
    exit('Invalid request data.');
}

try {
    $premadeCode = get_premade_visitor_qr_code($conn);
    $eligibilityStmt = $conn->prepare('SELECT
        v.generated_code,
        va.time_in AS latest_time_in,
        va.time_out AS latest_time_out
        FROM tbl_visitor v
        LEFT JOIN (
            SELECT va1.tbl_visitor_id, va1.time_in, va1.time_out
            FROM tbl_visitor_attendance va1
            INNER JOIN (
                SELECT tbl_visitor_id, MAX(tbl_visitor_attendance_id) AS latest_attendance_id
                FROM tbl_visitor_attendance
                GROUP BY tbl_visitor_id
            ) latest ON latest.latest_attendance_id = va1.tbl_visitor_attendance_id
        ) va ON va.tbl_visitor_id = v.tbl_visitor_id
        WHERE v.tbl_visitor_id = :tbl_visitor_id
        LIMIT 1');
    $eligibilityStmt->bindValue(':tbl_visitor_id', $visitorId, PDO::PARAM_INT);
    $eligibilityStmt->execute();
    $eligibilityRow = $eligibilityStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($eligibilityRow)) {
        exit('Visitor not found.');
    }

    $generatedCode = (string) ($eligibilityRow['generated_code'] ?? '');
    $isPremadeVisitor = hash_equals($premadeCode, $generatedCode)
        || strpos($generatedCode, 'QRYPT_PRE_') === 0;
    $latestTimeIn = trim((string) ($eligibilityRow['latest_time_in'] ?? ''));
    $latestTimeOut = trim((string) ($eligibilityRow['latest_time_out'] ?? ''));
    $isOut = $latestTimeIn !== '' && $latestTimeOut !== '';

    if (!$isPremadeVisitor || !$isOut) {
        exit('Only premade visitors with OUT status can be edited.');
    }

    $stmt = $conn->prepare('UPDATE tbl_visitor SET visitor_name = :visitor_name, purpose = :purpose, contact_no = :contact_no WHERE tbl_visitor_id = :tbl_visitor_id');
    $stmt->bindValue(':tbl_visitor_id', $visitorId, PDO::PARAM_INT);
    $stmt->bindValue(':visitor_name', $visitorName, PDO::PARAM_STR);
    $stmt->bindValue(':purpose', $purpose, PDO::PARAM_STR);
    $stmt->bindValue(':contact_no', $contactNo, PDO::PARAM_STR);
    $stmt->execute();
    record_audit_log(
        $conn,
        'UPDATE',
        'visitor',
        $visitorId,
        'Updated visitor: ' . $visitorName . ' | ' . $purpose
    );

    redirect_to($redirectPage);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Failed to update visitor.');
}
?>


