<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/student_table.php');
include('../conn/audit_log.php');

require_login();

verify_csrf_or_reject();

$studentId = filter_input(INPUT_POST, 'tbl_student_id', FILTER_VALIDATE_INT);
$studentFirstName = trim($_POST['student_first_name'] ?? '');
$studentLastName = trim($_POST['student_last_name'] ?? '');
$studentName = trim($_POST['student_name'] ?? '');
$studentIdNumber = normalize_student_id_number($_POST['student_id_number'] ?? '');
$studentDepartment = normalize_student_department($_POST['department'] ?? '');
$studentYearLevel = normalize_student_year_level($_POST['year_level'] ?? null);
$studentCourse = build_student_course_section($studentDepartment, $studentYearLevel);
$removePhoto = trim($_POST['remove_photo'] ?? '') === '1';
$maxPhotoBytes = 2 * 1024 * 1024;

ensure_student_table($conn);
ensure_audit_log_table($conn);

if ($studentFirstName !== '' || $studentLastName !== '') {
    if ($studentFirstName === '' || $studentLastName === '') {
        exit('Please provide both first name and last name.');
    }
    $studentName = trim($studentFirstName . ' ' . $studentLastName);
}

if (!$studentId || $studentName === '' || $studentIdNumber === '' || $studentDepartment === '' || $studentYearLevel === null || $studentCourse === '') {
    exit('Invalid request data.');
}

if (!preg_match('/^T\d{4}-\d{4}$/', $studentIdNumber)) {
    exit('Student ID Number format must be TYYYY-NNNN (e.g., T2022-0002).');
}

if (!is_student_department_allowed($studentDepartment, $conn)) {
    exit('Department is not in the allowed list. Update it in Admin Settings.');
}

if (!is_student_year_level_allowed($studentYearLevel, $conn)) {
    exit('Year level is not in the allowed list. Update it in Admin Settings.');
}

if (isset($_FILES['photo_file']) && (int) ($_FILES['photo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $uploadErr = (int) $_FILES['photo_file']['error'];
    if ($uploadErr !== UPLOAD_ERR_OK) {
        exit('Failed to upload student photo.');
    }

    $photoSize = (int) ($_FILES['photo_file']['size'] ?? 0);
    if ($photoSize <= 0 || $photoSize > $maxPhotoBytes) {
        exit('Student photo must be 2 MB or less.');
    }
}

try {
    $studentStmt = $conn->prepare('SELECT photo_url FROM tbl_student WHERE tbl_student_id = :tbl_student_id LIMIT 1');
    $studentStmt->bindValue(':tbl_student_id', $studentId, PDO::PARAM_INT);
    $studentStmt->execute();
    $studentRow = $studentStmt->fetch();
    if ($studentRow === false) {
        exit('Student not found.');
    }

    $photoUrl = trim((string) ($studentRow['photo_url'] ?? ''));
    $photoUrl = $photoUrl !== '' ? $photoUrl : null;

    $idCheckStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_student WHERE student_id_number = :student_id_number AND tbl_student_id <> :tbl_student_id');
    $idCheckStmt->bindValue(':student_id_number', $studentIdNumber, PDO::PARAM_STR);
    $idCheckStmt->bindValue(':tbl_student_id', $studentId, PDO::PARAM_INT);
    $idCheckStmt->execute();
    if ((int) $idCheckStmt->fetchColumn() > 0) {
        exit('Student ID Number already exists. Please use a unique ID number.');
    }

    if ($removePhoto) {
        if (is_string($photoUrl) && strpos($photoUrl, 'uploads/student_photos/') === 0) {
            $existingPath = dirname(__DIR__) . '/' . $photoUrl;
            if (is_file($existingPath)) {
                @unlink($existingPath);
            }
        }
        $photoUrl = null;
    }

    if (isset($_FILES['photo_file']) && (int) ($_FILES['photo_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmpPath = (string) ($_FILES['photo_file']['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpPath)) {
            exit('Invalid photo upload.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpPath);
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedTypes[$mimeType])) {
            exit('Only JPG, PNG, and WEBP images are allowed.');
        }

        $uploadDir = dirname(__DIR__) . '/uploads/student_photos';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            exit('Failed to create upload directory.');
        }

        $fileName = 'student_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
        $destPath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            exit('Failed to save uploaded photo.');
        }

        if (is_string($photoUrl) && strpos($photoUrl, 'uploads/student_photos/') === 0) {
            $oldPath = dirname(__DIR__) . '/' . $photoUrl;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $photoUrl = 'uploads/student_photos/' . $fileName;
    }

    $stmt = $conn->prepare('UPDATE tbl_student SET student_name = :student_name, student_id_number = :student_id_number, department = :department, year_level = :year_level, course_section = :course_section, photo_url = :photo_url WHERE tbl_student_id = :tbl_student_id');
    $stmt->bindValue(':tbl_student_id', $studentId, PDO::PARAM_INT);
    $stmt->bindValue(':student_name', $studentName, PDO::PARAM_STR);
    $stmt->bindValue(':student_id_number', $studentIdNumber, PDO::PARAM_STR);
    $stmt->bindValue(':department', $studentDepartment, PDO::PARAM_STR);
    $stmt->bindValue(':year_level', $studentYearLevel, PDO::PARAM_INT);
    $stmt->bindValue(':course_section', $studentCourse, PDO::PARAM_STR);
    if ($photoUrl === null || $photoUrl === '') {
        $stmt->bindValue(':photo_url', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':photo_url', $photoUrl, PDO::PARAM_STR);
    }
    $stmt->execute();
    record_audit_log(
        $conn,
        'UPDATE',
        'student',
        $studentId,
        'Updated student: ' . $studentName . ' (' . $studentIdNumber . ') | ' . $studentCourse
    );

    redirect_to('masterlist.php');
} catch (PDOException $e) {
    http_response_code(500);
    exit('Failed to update student.');
}
?>


