<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/student_table.php');

require_login();
verify_csrf_or_reject();

ensure_student_table($conn);

$qrCode = trim($_POST['qr_code'] ?? '');
if ($qrCode === '' || strlen($qrCode) > 255) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Invalid QR code.']);
    exit();
}

try {
    $stmt = $conn->prepare('SELECT tbl_student_id, student_name, student_id_number, course_section, photo_url FROM tbl_student WHERE generated_code = :generated_code LIMIT 1');
    $stmt->bindValue(':generated_code', $qrCode, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch();

    header('Content-Type: application/json; charset=utf-8');

    if ($row === false) {
        echo json_encode(['ok' => false, 'message' => 'Student not found for this QR code.']);
        exit();
    }

    echo json_encode([
        'ok' => true,
        'student' => [
            'id' => (int) ($row['tbl_student_id'] ?? 0),
            'name' => (string) ($row['student_name'] ?? ''),
            'student_id_number' => (string) ($row['student_id_number'] ?? ''),
            'course_section' => (string) ($row['course_section'] ?? ''),
            'photo_url' => (string) ($row['photo_url'] ?? ''),
        ],
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Failed to fetch student.']);
}
?>
