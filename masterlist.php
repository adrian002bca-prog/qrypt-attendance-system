<?php
include('./conn/bootstrap.php');
require_login();
include('./conn/conn.php');
include('./conn/student_table.php');

ensure_student_table($conn);
$studentDepartments = get_student_departments($conn);
$studentYearLevels = get_student_year_levels($conn);
$filterDepartments = [];
try {
    $deptStmt = $conn->prepare("SELECT DISTINCT department FROM tbl_student WHERE department IS NOT NULL AND TRIM(department) <> '' ORDER BY department ASC");
    $deptStmt->execute();
    foreach ($deptStmt->fetchAll(PDO::FETCH_COLUMN) as $deptValue) {
        $normalizedDept = normalize_student_department((string) $deptValue);
        if ($normalizedDept !== '' && !in_array($normalizedDept, $filterDepartments, true)) {
            $filterDepartments[] = $normalizedDept;
        }
    }
} catch (Throwable $e) {
    $filterDepartments = [];
}
if (count($filterDepartments) === 0) {
    $filterDepartments = array_values($studentDepartments);
}
$studentImportResult = null;
if (isset($_SESSION['student_import_result']) && is_array($_SESSION['student_import_result'])) {
    $studentImportResult = $_SESSION['student_import_result'];
    unset($_SESSION['student_import_result']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" />

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@500&display=swap');

        * {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

    body {
        background: linear-gradient(to bottom, rgba(255,255,255,0.15) 0%, rgba(0,0,0,0.15) 100%), radial-gradient(at top center, rgba(255,255,255,0.40) 0%, rgba(0,0,0,0.40) 120%) #989898;
        background-blend-mode: multiply, multiply;
        background-attachment: fixed;
        background-repeat: no-repeat;
        background-size: cover;
        background: linear-gradient(180deg, #fff4e6 0%, #ffe3c2 100%) !important;
    }
    @media print {
        .no-print, .navbar, .site-footer { display: none !important; }
        body { background: #fff !important; }
        body * { visibility: hidden; }
        .print-area, .print-area * { visibility: visible; }
        .print-area { position: absolute; left: 0; top: 0; width: 100%; }
        .dataTables_length,
        .dataTables_filter,
        .dataTables_info,
        .dataTables_paginate {
            display: none !important;
        }
        #studentTable th:last-child,
        #studentTable td:last-child {
            display: none !important;
        }
    }

        .main {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: calc(100vh - 56px);
            padding: 20px 0;
        }

        .student-container {
            width: 90%;
            border-radius: 20px;
            padding: 24px;
            background-color: rgba(255, 255, 255, 0.8);
        }

        .student-container > div {
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
            border-radius: 10px;
            padding: 30px;
            height: auto;
        }

        .student-list {
            max-height: calc(100vh - 170px);
            overflow-y: auto;
            overflow-x: hidden;
        }

        .title {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-filter-row {
            display: flex;
            gap: 10px;
            align-items: end;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .student-filter-row .form-group {
            margin-bottom: 0;
            min-width: 180px;
        }

        .action-button {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
        }

        .inline-form {
            display: inline;
        }

        .student-thumb {
            width: 38px;
            height: 38px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #ced4da;
        }
        .student-thumb.js-student-photo-preview {
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .student-thumb.js-student-photo-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        table.dataTable thead > tr > th.sorting,
        table.dataTable thead > tr > th.sorting_asc,
        table.dataTable thead > tr > th.sorting_desc,
        table.dataTable thead > tr > th.sorting_asc_disabled,
        table.dataTable thead > tr > th.sorting_desc_disabled,
        table.dataTable thead > tr > td.sorting,
        table.dataTable thead > tr > td.sorting_asc,
        table.dataTable thead > tr > td.sorting_desc,
        table.dataTable thead > tr > td.sorting_asc_disabled,
        table.dataTable thead > tr > td.sorting_desc_disabled {
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .main {
                padding: 12px 0;
            }

            .student-container {
                width: 95%;
                padding: 12px;
            }

            .student-list {
                max-height: none;
                overflow: visible;
            }
        }
    .site-footer {
    margin-top: 24px;
    padding: 12px 0 18px;
    background-color: #ffd8a8;
}

.site-footer .footer-inner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: #4a4a4a;
    font-size: 14px;
}</style>
<style>
.navbar.js-hide-on-scroll {
    position: sticky;
    top: 0;
    z-index: 1030;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.navbar.js-hide-on-scroll.is-hidden {
    transform: translateY(-110%);
}
.navbar.js-hide-on-scroll.is-shown {
    box-shadow: 0 6px 18px rgba(0,0,0,0.12);
}
.site-footer {
    margin-top: 24px;
    padding: 12px 0 18px;
    background-color: #ffd8a8;
}

.site-footer .footer-inner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: #4a4a4a;
    font-size: 14px;
}</style></head>
<body>
    <nav class="navbar navbar-expand-lg js-hide-on-scroll navbar-light" style="background-color: #ffd8a8;">
        <a class="navbar-brand ml-4 d-flex align-items-center" href="#"><img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 5em; width: auto; margin-right: 8px;"><span>QRYPT</span></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="./index.php">Home <span class="sr-only">(current)</span></a>
                </li>
                <li class="nav-item active">
                    <a class="nav-link" href="./masterlist.php">List of Students</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./visitorlist.php">List of Visitors</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./reports.php">Reports</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./audit-logs.php">Audit Logs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./schedules.php">Schedule & Rooms</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./visitor-destination.php">Visitor Destination</a>
                </li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item mr-3">
                    <a class="nav-link" href="./settings.php">Admin Settings</a>
                </li>
                <li class="nav-item mr-3">
                    <a class="nav-link" href="./logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="main">
        <div class="student-container">
            <div class="student-list">
                <div class="title">
                    <h4>List of Students</h4>
                    <div>
                        <button class="btn btn-outline-secondary mr-2 no-print" id="exportStudentsPdf">Export PDF</button>
                        <button class="btn btn-dark no-print" data-toggle="modal" data-target="#addStudentModal">Add Student</button>
                    </div>
                </div>
                <hr>
                <?php if (is_array($studentImportResult)): ?>
                    <?php
                        $totalRows = (int) ($studentImportResult['total_rows'] ?? 0);
                        $insertedRows = (int) ($studentImportResult['inserted'] ?? 0);
                        $failedRows = (int) ($studentImportResult['failed'] ?? 0);
                        $errors = isset($studentImportResult['errors']) && is_array($studentImportResult['errors']) ? $studentImportResult['errors'] : [];
                        $alertClass = $failedRows > 0 ? 'alert-warning' : 'alert-success';
                    ?>
                    <div class="alert <?= h($alertClass) ?>" role="alert">
                        <strong>Import Result:</strong>
                        Total Rows: <?= $totalRows ?>,
                        Inserted: <?= $insertedRows ?>,
                        Failed: <?= $failedRows ?>.
                        <?php if (count($errors) > 0): ?>
                            <hr class="my-2">
                            <div><strong>Validation details (max 30):</strong></div>
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= h((string) $err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="student-filter-row">
                    <div class="form-group">
                        <label for="yearFilter" class="mb-1">1. Choose Year Level</label>
                        <select id="yearFilter" class="form-control form-control-sm">
                            <option value="">All Years</option>
                            <?php foreach ($studentYearLevels as $year): ?>
                                <option value="<?= h((string) $year) ?>"><?= h(format_student_year_level_label($year)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="departmentFilter" class="mb-1">2. Choose Department</label>
                        <select id="departmentFilter" class="form-control form-control-sm" disabled>
                            <option value="">Select year first</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="button" id="resetStudentFilters" class="btn btn-outline-secondary btn-sm">Reset Filters</button>
                    </div>
                </div>
                <div class="table-container table-responsive print-area">
                    <table class="table text-center table-sm" id="studentTable">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Photo</th>
                                <th scope="col">Student ID No.</th>
                                <th scope="col">Name</th>
                                <th scope="col">Department</th>
                                <th scope="col">Year</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $conn->prepare('SELECT * FROM tbl_student ORDER BY tbl_student_id DESC');
                            $stmt->execute();
                            $result = $stmt->fetchAll();

                            foreach ($result as $row) {
                                $studentID = (int) $row['tbl_student_id'];
                                $studentName = $row['student_name'] ?? '';
                                $studentIdNumber = trim((string) ($row['student_id_number'] ?? ''));
                                $studentDepartment = trim((string) ($row['department'] ?? ''));
                                $studentYearLevel = (int) ($row['year_level'] ?? 0);
                                $studentCourse = $row['course_section'] ?? '';
                                if ($studentDepartment === '') {
                                    $studentDepartment = $studentCourse !== '' ? $studentCourse : '-';
                                }
                                $yearDisplay = format_student_year_level_label($studentYearLevel);
                                $qrCode = $row['generated_code'] ?? '';
                                $studentPhoto = trim((string) ($row['photo_url'] ?? ''));
                                if ($studentPhoto !== '') {
                                    $normalizedPhoto = str_replace('\\', '/', $studentPhoto);
                                    if (strpos($normalizedPhoto, './') === 0) {
                                        $normalizedPhoto = substr($normalizedPhoto, 2);
                                    }

                                    // Hide broken local student photos right away.
                                    if (strpos($normalizedPhoto, 'uploads/student_photos/') === 0) {
                                        $absolutePhotoPath = __DIR__ . '/' . $normalizedPhoto;
                                        if (!is_file($absolutePhotoPath)) {
                                            $studentPhoto = '';
                                        } else {
                                            $studentPhoto = $normalizedPhoto;
                                        }
                                    }
                                }
                                $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . rawurlencode($qrCode);
                            ?>
                                <tr>
                                    <th scope="row" id="studentID-<?= $studentID ?>" data-student-id="<?= $studentID ?>"></th>
                                    <td id="studentPhoto-<?= $studentID ?>" data-photo-url="<?= h($studentPhoto) ?>" data-student-name="<?= h($studentName) ?>">
                                        <?php if ($studentPhoto !== ''): ?>
                                            <img
                                                src="<?= h($studentPhoto) ?>"
                                                class="student-thumb js-student-photo-preview"
                                                alt="<?= h($studentName) ?> Photo"
                                                title="Click to preview"
                                                onerror="replaceBrokenStudentPhoto(this)"
                                            >
                                        <?php else: ?>
                                            <span class="text-muted small">No Photo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td id="studentIdNumber-<?= $studentID ?>"><?= h($studentIdNumber !== '' ? $studentIdNumber : '-') ?></td>
                                    <td id="studentName-<?= $studentID ?>"><?= h($studentName) ?></td>
                                    <td id="studentDepartment-<?= $studentID ?>"><?= h($studentDepartment) ?></td>
                                    <td id="studentYear-<?= $studentID ?>"><?= h($yearDisplay) ?></td>
                                    <td>
                                        <div class="action-button">
                                            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#qrCodeModal<?= $studentID ?>"><img src="https://cdn-icons-png.flaticon.com/512/1341/1341632.png" alt="QR" width="16"></button>

                                            <div class="modal fade" id="qrCodeModal<?= $studentID ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title"><?= h($studentName) ?>'s QR Code</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body text-center">
                                                            <img src="<?= h($qrCodeUrl) ?>" alt="QR Code" width="300">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <button class="btn btn-secondary btn-sm" onclick="updateStudent(<?= $studentID ?>)">&#128393;</button>
                                            <form class="inline-form" action="./endpoint/delete-student.php" method="POST" onsubmit="return confirm('Do you want to delete this student?');">
                                                <input type="hidden" name="student" value="<?= $studentID ?>">
                                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                <button class="btn btn-danger btn-sm" type="submit">&#10006;</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addStudentModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="addStudent" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStudent">Add Student</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="./endpoint/add-student.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <div class="form-group">
                            <label for="studentFirstName">First Name:</label>
                            <input type="text" class="form-control" id="studentFirstName" name="student_first_name" required maxlength="80">
                        </div>
                        <div class="form-group">
                            <label for="studentLastName">Last Name:</label>
                            <input type="text" class="form-control" id="studentLastName" name="student_last_name" required maxlength="80">
                        </div>
                        <div class="form-group">
                            <label for="studentIdNumber">Student ID Number:</label>
                            <input type="text" class="form-control" id="studentIdNumber" name="student_id_number" required maxlength="10" placeholder="T2022-0002" pattern="^T[0-9]{4}-[0-9]{4}$">
                            <small class="form-text text-muted">Format: TYYYY-NNNN (example: T2022-0041)</small>
                        </div>
                        <div class="form-group">
                            <label for="studentDepartment">Department:</label>
                            <select class="form-control" id="studentDepartment" name="department" required>
                                <option value="">Select department or strand</option>
                                <?php foreach ($studentDepartments as $dept): ?>
                                    <option value="<?= h($dept) ?>"><?= h($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="studentYearLevel">Year Level:</label>
                            <select class="form-control" id="studentYearLevel" name="year_level" required>
                                <option value="">Select year level</option>
                                <?php foreach ($studentYearLevels as $year): ?>
                                    <option value="<?= h((string) $year) ?>"><?= h(format_student_year_level_label($year)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="studentPhotoFile">Student Photo (Optional, max 2 MB)</label>
                            <input type="file" class="form-control-file" id="studentPhotoFile" name="photo_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        </div>
                        <div class="alert alert-light border small mb-2" role="alert">
                            <strong>Privacy Notice / Terms</strong><br>
                            By checking this box, you confirm that the information you entered is true and correct.<br>
                            You consent to the collection and processing of your personal data for attendance, visitor management, and campus security purposes.<br>
                            Your data will be used only by authorized school personnel and stored securely.<br>
                            Your data may be retained as required by school policy and applicable law.<br>
                            You may request correction of inaccurate personal data through the school administrator.<br>
                            If you do not agree, the form cannot be submitted.
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="studentTermsAgree" name="consent_terms" value="1" required>
                            <label class="form-check-label" for="studentTermsAgree">I agree to the Terms and Conditions and Privacy Notice.</label>
                        </div>
                        <button type="button" class="btn btn-secondary form-control qr-generator" onclick="generateQrCode()">Generate QR Code</button>

                        <div class="qr-con text-center" style="display: none;">
                            <input type="hidden" class="form-control" id="generatedCode" name="generated_code">
                            <p>Take a picture of your QR code.</p>
                            <img class="mb-4" src="" id="qrImg" alt="Generated QR Code">
                        </div>
                        <div class="modal-footer modal-close" style="display: none;">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-dark">Add List</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="updateStudentModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="updateStudent" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStudent">Update Student</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="./endpoint/update-student.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" class="form-control" id="updateStudentId" name="tbl_student_id">
                        <div class="form-group">
                            <label for="updateStudentFirstName">First Name:</label>
                            <input type="text" class="form-control" id="updateStudentFirstName" name="student_first_name" required maxlength="80">
                        </div>
                        <div class="form-group">
                            <label for="updateStudentLastName">Last Name:</label>
                            <input type="text" class="form-control" id="updateStudentLastName" name="student_last_name" required maxlength="80">
                        </div>
                        <div class="form-group">
                            <label for="updateStudentIdNumber">Student ID Number:</label>
                            <input type="text" class="form-control" id="updateStudentIdNumber" name="student_id_number" required maxlength="10" placeholder="T2022-0002" pattern="^T[0-9]{4}-[0-9]{4}$">
                            <small class="form-text text-muted">Format: TYYYY-NNNN (example: T2022-0041)</small>
                        </div>
                        <div class="form-group">
                            <label for="updateStudentDepartment">Department:</label>
                            <select class="form-control" id="updateStudentDepartment" name="department" required>
                                <option value="">Select department or strand</option>
                                <?php foreach ($studentDepartments as $dept): ?>
                                    <option value="<?= h($dept) ?>"><?= h($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="updateStudentYearLevel">Year Level:</label>
                            <select class="form-control" id="updateStudentYearLevel" name="year_level" required>
                                <option value="">Select year level</option>
                                <?php foreach ($studentYearLevels as $year): ?>
                                    <option value="<?= h((string) $year) ?>"><?= h(format_student_year_level_label($year)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="updateStudentPhotoFile">Replace Student Photo (Optional, max 2 MB)</label>
                            <input type="file" class="form-control-file" id="updateStudentPhotoFile" name="photo_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <small class="form-text text-muted">Leave empty to keep current photo.</small>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" value="1" id="updateRemovePhoto" name="remove_photo">
                            <label class="form-check-label" for="updateRemovePhoto">Remove current photo</label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-dark">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="studentPhotoPreviewModal" tabindex="-1" aria-labelledby="studentPhotoPreviewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="studentPhotoPreviewLabel">Student Photo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img
                        id="studentPhotoPreviewImage"
                        src=""
                        alt="Student photo preview"
                        style="max-width: 100%; max-height: 70vh; border-radius: 10px; border: 1px solid #dee2e6;"
                    >
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Back</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>

    <script>
        const MAX_PHOTO_BYTES = 2 * 1024 * 1024;

        function replaceBrokenStudentPhoto(imgEl) {
            if (!imgEl || !imgEl.classList || !imgEl.classList.contains('js-student-photo-preview')) {
                return;
            }

            const photoCell = imgEl.closest('td');
            if (!photoCell) {
                imgEl.remove();
                return;
            }

            const fallback = document.createElement('span');
            fallback.className = 'text-muted small';
            fallback.textContent = 'No Photo';
            photoCell.innerHTML = '';
            photoCell.appendChild(fallback);
        }

        document.addEventListener('error', function (event) {
            replaceBrokenStudentPhoto(event.target);
        }, true);

        $(document).ready(function () {
            const studentTable = $('#studentTable').DataTable({
                stateSave: true,
                stateDuration: -1,
                columnDefs: [
                    { targets: 0, orderable: false, searchable: false }
                ],
                lengthMenu: [
                    [10, 25, 50, 100, 500],
                    [10, 25, 50, 100, 500]
                ]
            });

            studentTable.on('order.dt search.dt draw.dt', function () {
                const info = studentTable.page.info();
                studentTable
                    .column(0, { search: 'applied', order: 'applied', page: 'current' })
                    .nodes()
                    .each(function (cell, i) {
                        cell.textContent = String(info.start + i + 1);
                    });
            }).draw();
            const yearFilterEl = document.getElementById('yearFilter');
            const departmentFilterEl = document.getElementById('departmentFilter');
            const resetFiltersEl = document.getElementById('resetStudentFilters');
            const fixedDepartments = <?= json_encode(array_values($filterDepartments)) ?>;

            function populateDepartmentByYear() {
                const yearValue = yearFilterEl.value;
                const currentDept = departmentFilterEl.value;
                departmentFilterEl.innerHTML = '';

                if (yearValue === '') {
                    departmentFilterEl.disabled = true;
                    departmentFilterEl.innerHTML = '<option value="">Select year first</option>';
                    return;
                }

                departmentFilterEl.disabled = false;
                departmentFilterEl.innerHTML = '<option value="">All Departments</option>';

                fixedDepartments.forEach(function (dept) {
                    const opt = document.createElement('option');
                    opt.value = dept;
                    opt.textContent = dept;
                    departmentFilterEl.appendChild(opt);
                });

                if (currentDept !== '' && fixedDepartments.indexOf(currentDept) >= 0) {
                    departmentFilterEl.value = currentDept;
                }
            }

            function normalizeYear(value) {
                const raw = (value || '').toString().toUpperCase().trim();
                if (raw.indexOf('GRADE 11') === 0) return '11';
                if (raw.indexOf('GRADE 12') === 0) return '12';
                const match = raw.match(/\d+/);
                return match ? match[0] : '';
            }

            $.fn.dataTable.ext.search.push(function (settings, data) {
                if (!settings.nTable || settings.nTable.id !== 'studentTable') {
                    return true;
                }

                const selectedYear = yearFilterEl.value;
                const selectedDepartment = (departmentFilterEl.value || '').trim().toUpperCase();
                const rowDepartment = (data[4] || '').trim().toUpperCase();
                const rowYearRaw = (data[5] || '').toString().trim().toUpperCase();
                const rowYear = normalizeYear(rowYearRaw);

                if (selectedYear !== '') {
                    const yearMatch = rowYear === selectedYear;
                    if (!yearMatch) {
                        return false;
                    }
                }

                if (selectedDepartment !== '') {
                    const deptMatch = rowDepartment === selectedDepartment;
                    if (!deptMatch) {
                    return false;
                }
                }

                return true;
            });

            yearFilterEl.addEventListener('change', function () {
                populateDepartmentByYear();
                studentTable.draw();
            });

            departmentFilterEl.addEventListener('change', function () {
                studentTable.draw();
            });

            resetFiltersEl.addEventListener('click', function () {
                yearFilterEl.value = '';
                departmentFilterEl.value = '';
                populateDepartmentByYear();
                studentTable.draw();
            });

            $('#studentPhotoFile, #updateStudentPhotoFile').on('change', function () {
                const file = this.files && this.files[0] ? this.files[0] : null;
                if (!file) {
                    return;
                }

                if (file.size > MAX_PHOTO_BYTES) {
                    alert('Photo must be 2 MB or less.');
                    this.value = '';
                }
            });

            $('#studentTable').on('click', '.js-student-photo-preview', function () {
                const $img = $(this);
                const photoSrc = $img.attr('src') || '';
                if (!photoSrc) {
                    return;
                }

                const $photoCell = $img.closest('td');
                const studentName = ($photoCell.attr('data-student-name') || 'Student').trim();
                $('#studentPhotoPreviewLabel').text(studentName + ' Photo');
                $('#studentPhotoPreviewImage').attr('src', photoSrc).attr('alt', studentName + ' photo preview');
                $('#studentPhotoPreviewModal').modal('show');
            });

            const exportBtn = document.getElementById('exportStudentsPdf');
            if (exportBtn) {
                exportBtn.addEventListener('click', function () {
                    window.print();
                });
            }

        });

        function splitPersonName(fullName) {
            const normalized = String(fullName || '').trim().replace(/\s+/g, ' ');
            if (normalized === '') {
                return { firstName: '', lastName: '' };
            }
            const parts = normalized.split(' ');
            const firstName = parts.shift() || '';
            const lastName = parts.join(' ');
            return { firstName, lastName };
        }

        function updateStudent(id) {
            $('#updateStudentModal').modal('show');

            let updateStudentId = String(id);
            let updateStudentName = $('#studentName-' + id).text();
            let updateStudentIdNumber = $('#studentIdNumber-' + id).text();
            let updateStudentDepartment = $('#studentDepartment-' + id).text();
            let updateStudentYear = $('#studentYear-' + id).text();
            let updateStudentPhoto = $('#studentPhoto-' + id).attr('data-photo-url') || '';
            const splitName = splitPersonName(updateStudentName);

            $('#updateStudentId').val(updateStudentId);
            $('#updateStudentFirstName').val(splitName.firstName);
            $('#updateStudentLastName').val(splitName.lastName);
            $('#updateStudentIdNumber').val(updateStudentIdNumber === '-' ? '' : updateStudentIdNumber);
            $('#updateStudentDepartment').val(updateStudentDepartment === '-' ? '' : updateStudentDepartment);
            $('#updateStudentYearLevel').val(updateStudentYear === '-' ? '' : updateStudentYear);
            $('#updateStudentPhotoFile').val('');
            $('#updateRemovePhoto').prop('checked', false);
        }

        function generateRandomCode(length) {
            const characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            const randomValues = new Uint32Array(length);
            window.crypto.getRandomValues(randomValues);
            let randomString = '';

            for (let i = 0; i < length; i++) {
                const randomIndex = randomValues[i] % characters.length;
                randomString += characters.charAt(randomIndex);
            }

            return randomString;
        }

        function generateQrCode() {
            const qrImg = document.getElementById('qrImg');
            const text = generateRandomCode(32);

            $('#generatedCode').val(text);

            const apiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(text)}`;
            qrImg.src = apiUrl;

            document.getElementById('studentFirstName').style.pointerEvents = 'none';
            document.getElementById('studentLastName').style.pointerEvents = 'none';
            document.getElementById('studentIdNumber').style.pointerEvents = 'none';
            document.getElementById('studentDepartment').style.pointerEvents = 'none';
            document.getElementById('studentYearLevel').style.pointerEvents = 'none';
            document.getElementById('studentTermsAgree').style.pointerEvents = 'none';
            document.querySelector('.modal-close').style.display = '';
            document.querySelector('.qr-con').style.display = '';
            document.querySelector('.qr-generator').style.display = 'none';
        }
    </script>
<script>
(() => {
    const nav = document.querySelector('.navbar.js-hide-on-scroll');
    if (!nav) return;

    nav.classList.add('is-shown');

    let lastY = window.scrollY;
    let ticking = false;
    const delta = 6;
    const revealAt = 120; // px from top before showing

    const onScroll = () => {
        const y = window.scrollY;
        if (Math.abs(y - lastY) < delta) {
            ticking = false;
            return;
        }

        if (y <= revealAt) {
            nav.classList.remove('is-hidden');
            nav.classList.add('is-shown');
        } else {
            nav.classList.add('is-hidden');
            nav.classList.remove('is-shown');
        }

        lastY = y;
        ticking = false;
    };

    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(onScroll);
            ticking = true;
        }
    }, { passive: true });
})();
</script><footer class="site-footer">
    <div class="footer-inner">
        <img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 2em; width: auto;">
        <span>Copyright 2026. TIF-TCI</span>
    </div>
</footer></body>
</html>

















