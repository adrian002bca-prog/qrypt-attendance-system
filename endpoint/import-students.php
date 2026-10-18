<?php
include('../conn/bootstrap.php');
include('../conn/conn.php');
include('../conn/student_table.php');
include('../conn/audit_log.php');

require_login();
verify_csrf_or_reject();

ensure_student_table($conn);
ensure_audit_log_table($conn);
$allowedDepartments = get_student_departments($conn);
$allowedYearLevels = get_student_year_levels($conn);
$allowedYearLabels = array_map('format_student_year_level_label', $allowedYearLevels);

$result = [
    'total_rows' => 0,
    'inserted' => 0,
    'failed' => 0,
    'errors' => [],
];

function import_error_push(&$errors, $line, $message) {
    if (count($errors) < 30) {
        $errors[] = 'Line ' . $line . ': ' . $message;
    }
}

if (!isset($_FILES['csv_file']) || (int) ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $result['errors'][] = 'Please upload a valid CSV file.';
    $_SESSION['student_import_result'] = $result;
    redirect_to('masterlist.php');
}

$tmpPath = (string) ($_FILES['csv_file']['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    $result['errors'][] = 'Invalid uploaded CSV file.';
    $_SESSION['student_import_result'] = $result;
    redirect_to('masterlist.php');
}

$originalName = strtolower((string) ($_FILES['csv_file']['name'] ?? ''));
if ($originalName !== '' && substr($originalName, -4) !== '.csv') {
    $result['errors'][] = 'File must be a .csv.';
    $_SESSION['student_import_result'] = $result;
    redirect_to('masterlist.php');
}

$handle = fopen($tmpPath, 'r');
if ($handle === false) {
    $result['errors'][] = 'Failed to read CSV file.';
    $_SESSION['student_import_result'] = $result;
    redirect_to('masterlist.php');
}

$headerRow = fgetcsv($handle);
if (!is_array($headerRow) || count($headerRow) === 0) {
    fclose($handle);
    $result['errors'][] = 'CSV is empty.';
    $_SESSION['student_import_result'] = $result;
    redirect_to('masterlist.php');
}

$headerMap = [];
foreach ($headerRow as $idx => $name) {
    $key = strtolower(trim((string) $name));
    if ($key !== '') {
        $headerMap[$key] = $idx;
    }
}

$requiredCols = ['student_name', 'department', 'year_level', 'generated_code'];
$missing = [];
foreach ($requiredCols as $required) {
    if (!array_key_exists($required, $headerMap)) {
        $missing[] = $required;
    }
}

if (count($missing) > 0) {
    fclose($handle);
    $result['errors'][] = 'Missing required column(s): ' . implode(', ', $missing) . '. Required header: student_name, department, year_level, generated_code';
    $_SESSION['student_import_result'] = $result;
    redirect_to('masterlist.php');
}

$existingCodes = [];
$existingStudentIdNumbers = [];
$codeStmt = $conn->prepare('SELECT generated_code FROM tbl_student');
$codeStmt->execute();
foreach ($codeStmt->fetchAll() as $row) {
    $code = (string) ($row['generated_code'] ?? '');
    if ($code !== '') {
        $existingCodes[$code] = true;
    }
}
$studentIdStmt = $conn->prepare('SELECT student_id_number FROM tbl_student WHERE student_id_number IS NOT NULL AND TRIM(student_id_number) <> ""');
$studentIdStmt->execute();
foreach ($studentIdStmt->fetchAll() as $row) {
    $studentIdNumber = normalize_student_id_number($row['student_id_number'] ?? '');
    if ($studentIdNumber !== '') {
        $existingStudentIdNumbers[$studentIdNumber] = true;
    }
}

$seenInFile = [];
$seenStudentIdInFile = [];
$insertStmt = $conn->prepare('INSERT INTO tbl_student (student_name, student_id_number, department, year_level, course_section, generated_code, photo_url) VALUES (:student_name, :student_id_number, :department, :year_level, :course_section, :generated_code, :photo_url)');

$lineNo = 1;
while (($row = fgetcsv($handle)) !== false) {
    $lineNo++;

    $isEmptyRow = true;
    foreach ($row as $cell) {
        if (trim((string) $cell) !== '') {
            $isEmptyRow = false;
            break;
        }
    }
    if ($isEmptyRow) {
        continue;
    }

    $result['total_rows']++;

    $studentName = trim((string) ($row[$headerMap['student_name']] ?? ''));
    $department = normalize_student_department($row[$headerMap['department']] ?? '');
    $yearLevel = normalize_student_year_level($row[$headerMap['year_level']] ?? null);
    $generatedCode = trim((string) ($row[$headerMap['generated_code']] ?? ''));
    $studentIdNumber = '';
    if (array_key_exists('student_id_number', $headerMap)) {
        $studentIdNumber = normalize_student_id_number($row[$headerMap['student_id_number']] ?? '');
    }
    $photoUrl = '';
    if (array_key_exists('photo_url', $headerMap)) {
        $photoUrl = trim((string) ($row[$headerMap['photo_url']] ?? ''));
    }

    if ($studentName === '') {
        $result['failed']++;
        import_error_push($result['errors'], $lineNo, 'student_name is required.');
        continue;
    }

    if ($department === '') {
        $result['failed']++;
        import_error_push($result['errors'], $lineNo, 'department is invalid.');
        continue;
    }

    if (!is_student_department_allowed($department, $conn)) {
        $result['failed']++;
        import_error_push($result['errors'], $lineNo, 'department must be one of: ' . implode(', ', $allowedDepartments) . '.');
        continue;
    }

    if ($yearLevel === null || !is_student_year_level_allowed($yearLevel, $conn)) {
        $result['failed']++;
        import_error_push($result['errors'], $lineNo, 'year_level must be one of: ' . implode(', ', $allowedYearLabels) . '.');
        continue;
    }

    if ($generatedCode === '' || preg_match('/^[A-Za-z0-9]{10,64}$/', $generatedCode) !== 1) {
        $result['failed']++;
        import_error_push($result['errors'], $lineNo, 'generated_code must be alphanumeric (10-64 chars).');
        continue;
    }

    if (array_key_exists('student_id_number', $headerMap)) {
        $rawStudentId = trim((string) ($row[$headerMap['student_id_number']] ?? ''));
        if ($rawStudentId === '') {
            $result['failed']++;
            import_error_push($result['errors'], $lineNo, 'student_id_number is required when the column is provided.');
            continue;
        }
        if ($studentIdNumber === '') {
            $result['failed']++;
            import_error_push($result['errors'], $lineNo, 'student_id_number format must be TYYYY-NNNN.');
            continue;
        }
        if (isset($seenStudentIdInFile[$studentIdNumber])) {
            $result['failed']++;
            import_error_push($result['errors'], $lineNo, 'student_id_number is duplicated in CSV.');
            continue;
        }
        if (isset($existingStudentIdNumbers[$studentIdNumber])) {
            $result['failed']++;
            import_error_push($result['errors'], $lineNo, 'student_id_number already exists in database.');
            continue;
        }
        $seenStudentIdInFile[$studentIdNumber] = true;
    }

    if (isset($seenInFile[$generatedCode])) {
        $result['failed']++;
        import_error_push($result['errors'], $lineNo, 'generated_code is duplicated in CSV.');
        continue;
    }
    $seenInFile[$generatedCode] = true;

    if (isset($existingCodes[$generatedCode])) {
        $result['failed']++;
        import_error_push($result['errors'], $lineNo, 'generated_code already exists in database.');
        continue;
    }

    $courseSection = build_student_course_section($department, $yearLevel);
    if ($courseSection === '') {
        $result['failed']++;
        import_error_push($result['errors'], $lineNo, 'Failed to build course section from department/year.');
        continue;
    }

    try {
        $insertStmt->bindValue(':student_name', $studentName, PDO::PARAM_STR);
        if ($studentIdNumber === '') {
            $insertStmt->bindValue(':student_id_number', null, PDO::PARAM_NULL);
        } else {
            $insertStmt->bindValue(':student_id_number', $studentIdNumber, PDO::PARAM_STR);
        }
        $insertStmt->bindValue(':department', $department, PDO::PARAM_STR);
        $insertStmt->bindValue(':year_level', $yearLevel, PDO::PARAM_INT);
        $insertStmt->bindValue(':course_section', $courseSection, PDO::PARAM_STR);
        $insertStmt->bindValue(':generated_code', $generatedCode, PDO::PARAM_STR);
        if ($photoUrl === '') {
            $insertStmt->bindValue(':photo_url', null, PDO::PARAM_NULL);
        } else {
            $insertStmt->bindValue(':photo_url', $photoUrl, PDO::PARAM_STR);
        }
        $insertStmt->execute();
        $result['inserted']++;
        $existingCodes[$generatedCode] = true;
        if ($studentIdNumber !== '') {
            $existingStudentIdNumbers[$studentIdNumber] = true;
        }
    } catch (PDOException $e) {
        $result['failed']++;
        import_error_push($result['errors'], $lineNo, 'Database insert failed.');
    }
}

fclose($handle);

record_audit_log(
    $conn,
    'IMPORT',
    'student',
    null,
    'Student CSV import: total=' . $result['total_rows'] . ', inserted=' . $result['inserted'] . ', failed=' . $result['failed']
);

$_SESSION['student_import_result'] = $result;
redirect_to('masterlist.php');
?>
