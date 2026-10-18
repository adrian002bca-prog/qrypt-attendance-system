<?php
include('./conn/bootstrap.php');
require_login();
include('./conn/conn.php');
include('./conn/student_table.php');
include('./conn/visitor_table.php');
include('./conn/attendance_tables.php');
include('./conn/auto_close.php');
require_once('./conn/system_settings.php');
require_once('./conn/student_time_rules.php');

ensure_student_table($conn);
ensure_visitor_table($conn);
ensure_attendance_tables($conn);
ensure_daily_auto_close($conn);
ensure_system_settings_table($conn);
$studentDepartments = get_student_departments($conn);
$scanSoundEnabled = get_system_setting_int($conn, 'scan_sound_enabled', 1) === 1;
$studentClassStartTime = get_student_class_start_time($conn);
$studentClassEndTime = get_student_class_end_time($conn);
$studentLateGraceMinutes = get_student_late_grace_minutes($conn);
$studentEarlyOutGraceMinutes = get_student_early_out_grace_minutes($conn);
ensure_student_time_rules_table($conn);
$studentTimeRuleMap = build_student_time_rule_map(get_student_time_rules($conn));

$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');
$yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
$yesterdayEnd = date('Y-m-d 23:59:59', strtotime('-1 day'));

$studentInTodayStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_attendance WHERE time_in BETWEEN :today_start AND :today_end AND time_out IS NULL');
$studentInTodayStmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
$studentInTodayStmt->bindValue(':today_end', $todayEnd, PDO::PARAM_STR);
$studentInTodayStmt->execute();
$studentInToday = (int) $studentInTodayStmt->fetchColumn();

$studentOutTodayStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_attendance WHERE time_in BETWEEN :today_start AND :today_end AND time_out IS NOT NULL');
$studentOutTodayStmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
$studentOutTodayStmt->bindValue(':today_end', $todayEnd, PDO::PARAM_STR);
$studentOutTodayStmt->execute();
$studentOutToday = (int) $studentOutTodayStmt->fetchColumn();

$visitorInTodayStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_visitor_attendance WHERE time_in BETWEEN :today_start AND :today_end AND time_out IS NULL');
$visitorInTodayStmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
$visitorInTodayStmt->bindValue(':today_end', $todayEnd, PDO::PARAM_STR);
$visitorInTodayStmt->execute();
$visitorInToday = (int) $visitorInTodayStmt->fetchColumn();

$visitorOutTodayStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_visitor_attendance WHERE time_in BETWEEN :today_start AND :today_end AND time_out IS NOT NULL');
$visitorOutTodayStmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
$visitorOutTodayStmt->bindValue(':today_end', $todayEnd, PDO::PARAM_STR);
$visitorOutTodayStmt->execute();
$visitorOutToday = (int) $visitorOutTodayStmt->fetchColumn();

$studentInYesterdayStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_attendance WHERE time_in BETWEEN :yesterday_start AND :yesterday_end AND time_out IS NULL');
$studentInYesterdayStmt->bindValue(':yesterday_start', $yesterdayStart, PDO::PARAM_STR);
$studentInYesterdayStmt->bindValue(':yesterday_end', $yesterdayEnd, PDO::PARAM_STR);
$studentInYesterdayStmt->execute();
$studentInYesterday = (int) $studentInYesterdayStmt->fetchColumn();

$studentOutYesterdayStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_attendance WHERE time_in BETWEEN :yesterday_start AND :yesterday_end AND time_out IS NOT NULL');
$studentOutYesterdayStmt->bindValue(':yesterday_start', $yesterdayStart, PDO::PARAM_STR);
$studentOutYesterdayStmt->bindValue(':yesterday_end', $yesterdayEnd, PDO::PARAM_STR);
$studentOutYesterdayStmt->execute();
$studentOutYesterday = (int) $studentOutYesterdayStmt->fetchColumn();

$visitorInYesterdayStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_visitor_attendance WHERE time_in BETWEEN :yesterday_start AND :yesterday_end AND time_out IS NULL');
$visitorInYesterdayStmt->bindValue(':yesterday_start', $yesterdayStart, PDO::PARAM_STR);
$visitorInYesterdayStmt->bindValue(':yesterday_end', $yesterdayEnd, PDO::PARAM_STR);
$visitorInYesterdayStmt->execute();
$visitorInYesterday = (int) $visitorInYesterdayStmt->fetchColumn();

$visitorOutYesterdayStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_visitor_attendance WHERE time_in BETWEEN :yesterday_start AND :yesterday_end AND time_out IS NOT NULL');
$visitorOutYesterdayStmt->bindValue(':yesterday_start', $yesterdayStart, PDO::PARAM_STR);
$visitorOutYesterdayStmt->bindValue(':yesterday_end', $yesterdayEnd, PDO::PARAM_STR);
$visitorOutYesterdayStmt->execute();
$visitorOutYesterday = (int) $visitorOutYesterdayStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

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

        .main {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: calc(100vh - 56px);
            padding: 20px 0;
        }

        .attendance-container {
            width: 90%;
            border-radius: 20px;
            padding: 24px;
            background-color: rgba(255, 255, 255, 0.8);
        }

        .attendance-container > .qr-container,
        .attendance-container > .attendance-list {
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
            border-radius: 10px;
            padding: 30px;
            height: auto;
        }

        .attendance-container > .attendance-list {
            width: 64%;
            margin-left: auto;
        }

        .daily-summary-card {
            border-radius: 10px;
            color: #fff;
            padding: 12px;
            margin-bottom: 10px;
        }

        .daily-student-in { background: #218838; }
        .daily-student-out { background: #dc3545; }
        .daily-visitor-in { background: #17a2b8; }
        .daily-visitor-out { background: #6c757d; }

        .attendance-list {
            max-height: calc(100vh - 170px);
            overflow-y: auto;
            overflow-x: hidden;
        }

        .attendance-list table {
            table-layout: auto;
        }

        .attendance-list th,
        .attendance-list td {
            padding: 0.5rem 0.6rem;
            vertical-align: middle;
        }

        .student-table th:nth-child(2),
        .student-table td:nth-child(2),
        .visitor-table th:nth-child(2),
        .visitor-table td:nth-child(2) {
            text-align: left;
            min-width: 170px;
            padding-left: 14px;
        }

        .student-table th:nth-child(4),
        .student-table td:nth-child(4),
        .student-table th:nth-child(5),
        .student-table td:nth-child(5),
        .visitor-table th:nth-child(5),
        .visitor-table td:nth-child(5),
        .visitor-table th:nth-child(6),
        .visitor-table td:nth-child(6) {
            white-space: nowrap;
            min-width: 170px;
            font-variant-numeric: tabular-nums;
        }

        .inline-form {
            display: inline;
        }
        
        .action-button {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
        }

        .scan-type-switch {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .scan-type-switch .btn {
            min-width: 120px;
        }

        .scan-help {
            background: #fff7ec;
            border: 1px solid #f1d1a2;
            border-radius: 8px;
            padding: 7px 10px;
            margin-bottom: 10px;
        }

        .scanner-frame {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #d9dee3;
            background: #000;
        }

        .scanner-frame #interactive {
            min-height: 300px;
        }

        .scan-guide-corners {
            position: absolute;
            inset: 12%;
            border: 2px dashed rgba(255, 255, 255, 0.75);
            border-radius: 12px;
            pointer-events: none;
            box-shadow: inset 0 0 0 9999px rgba(0, 0, 0, 0.18);
        }

        .camera-zoom-control {
            display: none;
            align-items: center;
            gap: 8px;
            background: #fff9f0;
            border: 1px solid #ecd6b1;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 10px;
        }

        .camera-zoom-control input[type="range"] {
            width: 100%;
        }

        .attendance-panel {
            display: none;
            margin-top: 16px;
        }

        .attendance-panel.active {
            display: block;
        }

        .student-group-filter {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 12px 0 14px;
        }

        .student-group-filter .btn {
            min-width: 110px;
        }

        @media (max-width: 991.98px) {
            .main {
                padding: 12px 0;
            }

            .attendance-container {
                width: 95%;
                padding: 12px;
            }

            .attendance-container > .attendance-list {
                width: 100%;
                margin-left: 0;
                margin-top: 12px;
            }

            .attendance-list {
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
                <li class="nav-item active">
                    <a class="nav-link" href="./index.php">Home <span class="sr-only">(current)</span></a>
                </li>
                <li class="nav-item">
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
        <div class="attendance-container row">
            <div class="col-12 mb-2">
                <div class="row">
                    <div class="col-md-3 col-6">
                        <div class="daily-summary-card daily-student-in">
                            <small>Students IN Today</small>
                            <h5 class="mb-0"><?= $studentInToday ?></h5>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="daily-summary-card daily-student-out">
                            <small>Students OUT Today</small>
                            <h5 class="mb-0"><?= $studentOutToday ?></h5>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="daily-summary-card daily-visitor-in">
                            <small>Visitors IN Today</small>
                            <h5 class="mb-0"><?= $visitorInToday ?></h5>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="daily-summary-card daily-visitor-out">
                            <small>Visitors OUT Today</small>
                            <h5 class="mb-0"><?= $visitorOutToday ?></h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="qr-container col-4">
                <div class="scanner-con">
                    <h5 class="text-center mb-2">Scan QR Code</h5>
                    <div class="scan-type-switch">
                        <button type="button" class="btn btn-primary btn-sm" id="viewStudentsBtn" onclick="setAttendancePanelByMode('student')">View Students</button>
                        <button type="button" class="btn btn-outline-success btn-sm" id="viewVisitorsBtn" onclick="setAttendancePanelByMode('visitor')">View Visitors</button>
                    </div>
                    <p class="text-center text-muted mb-2">QR type is detected automatically (Student or Visitor).</p>
                    <p class="text-center mb-2"><small id="cameraStatus" class="text-muted">Camera status: Idle</small></p>
                    <div class="d-flex justify-content-center mb-2">
                        <button type="button" class="btn btn-success btn-sm mr-2" id="openCameraBtn" onclick="openCamera()">Open Camera</button>
                        <button type="button" class="btn btn-danger btn-sm" id="closeCameraBtn" onclick="closeCamera()">Close Camera</button>
                    </div>
                    <div class="scan-help text-center small text-muted">
                        Place the QR code inside the box, hold steady, and keep it 10-20 cm from the camera.
                    </div>
                    <div class="camera-zoom-control" id="cameraZoomWrap">
                        <label for="cameraZoomRange" class="mb-0 small"><strong>Zoom</strong></label>
                        <input type="range" id="cameraZoomRange" min="1" max="1" step="0.1" value="1">
                    </div>
                    <div class="scanner-frame">
                        <div id="interactive" class="viewport" style="width: 100%;"></div>
                        <div class="scan-guide-corners" aria-hidden="true"></div>
                    </div>
                </div>

                <div class="qr-detected-container" style="display: none;">
                    <form action="./endpoint/add-attendance.php" method="POST">
                        <h4 class="text-center" id="detectedLabel">QR Detected (Auto Time In/Out)!</h4>
                        <input type="hidden" id="detected-qr-code" name="qr_code">
                        <input type="hidden" id="person-type" name="person_type" value="visitor">
                        <input type="hidden" id="destination-name-hidden" name="destination_name" value="">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <button type="submit" class="btn btn-dark form-control">Submit Scan</button>
                    </form>
                    <button type="button" class="btn btn-link form-control mt-2" onclick="resetScannerUI()">Scan Again</button>
                </div>
            </div>

            <div class="attendance-list">
                <div id="studentAttendancePanel" class="attendance-panel active">
                    <h4>List of Course & Strand of Present Students</h4>
                    <div class="student-group-filter" id="studentGroupFilter">
                        <button type="button" class="btn btn-dark btn-sm active" data-group="ALL">All Students</button>
                        <?php foreach ($studentDepartments as $dept): ?>
                            <button type="button" class="btn btn-outline-dark btn-sm" data-group="<?= h($dept) ?>"><?= h($dept) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="table-container table-responsive">
                        <table class="table text-center table-sm student-table">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Course & Section</th>
                                    <th scope="col">Time In</th>
                                    <th scope="col">Time Out</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Arrival</th>
                                    <th scope="col">Remarks</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $studentStmt = $conn->prepare('SELECT tbl_attendance_id, student_name, student_id_number, course_section, department, year_level, time_in, time_out FROM tbl_attendance INNER JOIN tbl_student ON tbl_student.tbl_student_id = tbl_attendance.tbl_student_id WHERE tbl_attendance.time_in BETWEEN :today_start AND :today_end ORDER BY tbl_attendance.tbl_attendance_id DESC');
                                $studentStmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
                                $studentStmt->bindValue(':today_end', $todayEnd, PDO::PARAM_STR);
                                $studentStmt->execute();
                                $studentRows = $studentStmt->fetchAll();

                                foreach ($studentRows as $index => $row) {
                                    $displayNumber = $index + 1;
                                    $attendanceID = (int) $row['tbl_attendance_id'];
                                    $studentName = $row['student_name'] ?? '';
                                    $studentIdNumber = trim((string) ($row['student_id_number'] ?? ''));
                                    $studentCourse = $row['course_section'] ?? '';
                                    $studentDepartment = $row['department'] ?? '';
                                    $studentYearLevel = $row['year_level'] ?? null;
                                    $studentGroup = strtoupper(trim((string) $studentDepartment));
                                    $timeInRaw = $row['time_in'] ?? '';
                                    $timeOutRaw = $row['time_out'] ?? null;
                                    $timeIn = format_datetime_ph($timeInRaw);
                                    $timeOut = format_datetime_ph($timeOutRaw ?? '');
                                    $schedule = resolve_student_schedule_from_map($studentTimeRuleMap, $studentDepartment, $studentYearLevel, $studentClassStartTime, $studentClassEndTime, $timeInRaw);
                                    $timeRule = evaluate_student_time_rule(
                                        $timeInRaw,
                                        (string) ($timeOutRaw ?? ''),
                                        $schedule['start'],
                                        $schedule['end'],
                                        $studentLateGraceMinutes,
                                        $studentEarlyOutGraceMinutes
                                    );
                                ?>
                                    <tr data-student-group="<?= h($studentGroup !== '' ? $studentGroup : 'OTHER') ?>">
                                        <th scope="row"><?= $displayNumber ?></th>
                                        <td><?= h($studentName) ?><?= $studentIdNumber !== '' ? ' <small class="text-muted">(' . h($studentIdNumber) . ')</small>' : '' ?></td>
                                        <td><?= h($studentCourse) ?></td>
                                        <td><?= h($timeIn) ?></td>
                                        <td><?= h($timeOut ?: '-') ?></td>
                                        <td><?= $timeOut ? '<span class="badge badge-danger">OUT</span>' : '<span class="badge badge-success">IN</span>' ?></td>
                                        <td><span class="badge badge-<?= h($timeRule['arrival_badge']) ?>"><?= h($timeRule['arrival_label']) ?></span></td>
                                        <td><span class="badge badge-<?= h($timeRule['departure_badge']) ?>"><?= h($timeRule['departure_label']) ?></span></td>
                                        <td>
                                            <div class="action-button">
                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-sm"
                                                    onclick="openAttendanceEdit(this)"
                                                    data-attendance-id="<?= $attendanceID ?>"
                                                    data-person-type="student"
                                                    data-time-in="<?= h((string) $timeInRaw) ?>"
                                                    data-time-out="<?= h((string) ($timeOutRaw ?? '')) ?>"
                                                >&#128393;</button>
                                                <form class="inline-form" action="./endpoint/delete-attendance.php" method="POST" onsubmit="return confirm('Do you want to remove this attendance?');">
                                                    <input type="hidden" name="attendance" value="<?= $attendanceID ?>">
                                                    <input type="hidden" name="person_type" value="student">
                                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                    <button class="btn btn-danger delete-button" type="submit">X</button>
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

                <div id="visitorAttendancePanel" class="attendance-panel">
                    <h4>List of Present Visitors</h4>
                    <div class="table-container table-responsive">
                        <table class="table text-center table-sm visitor-table">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Purpose</th>
                                    <th scope="col">Contact</th>
                                    <th scope="col">Time In</th>
                                    <th scope="col">Time Out</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $visitorStmt = $conn->prepare('SELECT tbl_visitor_attendance_id, visitor_name, purpose, contact_no, time_in, time_out FROM tbl_visitor_attendance INNER JOIN tbl_visitor ON tbl_visitor.tbl_visitor_id = tbl_visitor_attendance.tbl_visitor_id ORDER BY tbl_visitor_attendance.tbl_visitor_attendance_id DESC');
                                $visitorStmt->execute();
                                $visitorRows = $visitorStmt->fetchAll();

                                foreach ($visitorRows as $index => $row) {
                                    $displayNumber = $index + 1;
                                    $attendanceID = (int) $row['tbl_visitor_attendance_id'];
                                    $visitorName = $row['visitor_name'] ?? '';
                                    $purpose = $row['purpose'] ?? '';
                                    $contactNo = $row['contact_no'] ?? '';
                                    $timeInRaw = $row['time_in'] ?? '';
                                    $timeOutRaw = $row['time_out'] ?? null;
                                    $timeIn = format_datetime_ph($timeInRaw);
                                    $timeOut = format_datetime_ph($timeOutRaw ?? '');
                                ?>
                                    <tr>
                                        <th scope="row"><?= $displayNumber ?></th>
                                        <td><?= h($visitorName) ?></td>
                                        <td><?= h($purpose) ?></td>
                                        <td><?= h($contactNo) ?></td>
                                        <td><?= h($timeIn) ?></td>
                                        <td><?= h($timeOut ?: '-') ?></td>
                                        <td><?= $timeOut ? '<span class="badge badge-danger">OUT</span>' : '<span class="badge badge-success">IN</span>' ?></td>
                                        <td>
                                            <div class="action-button">
                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-sm"
                                                    onclick="openAttendanceEdit(this)"
                                                    data-attendance-id="<?= $attendanceID ?>"
                                                    data-person-type="visitor"
                                                    data-time-in="<?= h((string) $timeInRaw) ?>"
                                                    data-time-out="<?= h((string) ($timeOutRaw ?? '')) ?>"
                                                >&#128393;</button>
                                                <form class="inline-form" action="./endpoint/delete-attendance.php" method="POST" onsubmit="return confirm('Do you want to remove this attendance?');">
                                                    <input type="hidden" name="attendance" value="<?= $attendanceID ?>">
                                                    <input type="hidden" name="person_type" value="visitor">
                                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                    <button class="btn btn-danger delete-button" type="submit">X</button>
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
    </div>

    <div class="modal fade" id="updateAttendanceModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="updateAttendance" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateAttendance">Update Attendance</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="./endpoint/update-attendance.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" id="updateAttendanceId" name="attendance">
                        <input type="hidden" id="updatePersonType" name="person_type">
                        <div class="form-group">
                            <label for="updateTimeIn">Time In (PH Time)</label>
                            <input type="datetime-local" class="form-control" id="updateTimeIn" name="time_in" required>
                            <small id="updateTimeInPreview" class="form-text text-muted">Preview: -</small>
                        </div>
                        <div class="form-group">
                            <label for="updateTimeOut">Time Out (PH Time)</label>
                            <input type="datetime-local" class="form-control" id="updateTimeOut" name="time_out">
                            <small id="updateTimeOutPreview" class="form-text text-muted">Preview: -</small>
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

    <div class="modal fade" id="studentScanConfirmModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="studentScanConfirmTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="studentScanConfirmTitle">Confirm Student Scan</h5>
                    <button type="button" class="close" aria-label="Close" onclick="cancelStudentConfirmation()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <img id="studentConfirmPhoto" src="" alt="Student Photo" style="width: 120px; height: 120px; object-fit: cover; border-radius: 6px; border: 1px solid #ced4da; display: none;">
                        <div id="studentConfirmNoPhoto" class="text-muted small">No photo available</div>
                    </div>
                    <p class="mb-1"><strong>Name:</strong> <span id="studentConfirmName">-</span></p>
                    <p class="mb-1"><strong>Student ID:</strong> <span id="studentConfirmIdNumber">-</span></p>
                    <p class="mb-3"><strong>Course & Section:</strong> <span id="studentConfirmCourse">-</span></p>

                    <form id="studentConfirmForm" action="./endpoint/add-attendance.php" method="POST">
                        <input type="hidden" id="studentConfirmQrCode" name="qr_code">
                        <input type="hidden" name="person_type" value="student">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <button type="submit" class="btn btn-dark btn-block">Confirm and Save Attendance</button>
                    </form>
                    <button type="button" class="btn btn-link btn-block mt-2" onclick="cancelStudentConfirmation()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script>
        let scanner = null;
        let scannerRunning = false;
        let cameraEnabled = true;
        const csrfToken = <?= json_encode(csrf_token()) ?>;
        const scanSoundEnabled = <?= $scanSoundEnabled ? 'true' : 'false' ?>;
        const cameraPreferenceKey = 'admin_home_camera_enabled';
        let lastScannedCode = '';
        let lastScannedAt = 0;
        let secureContextHintShown = false;

        function isCameraContextAllowed() {
            const host = (window.location.hostname || '').toLowerCase();
            const isLocalHost = host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
            return window.isSecureContext || isLocalHost;
        }

        function showCameraContextHint() {
            setCameraStatus('Blocked: use HTTPS or localhost', 'danger');
            if (secureContextHintShown) {
                return;
            }
            secureContextHintShown = true;
            alert('Camera access is blocked on HTTP IP addresses. Open this system using HTTPS (recommended) or localhost on the same PC.');
        }

        function getScanConfig() {
            const dynamicBox = Math.max(220, Math.min(320, Math.floor(window.innerWidth * 0.42)));
            const config = {
                fps: 14,
                qrbox: { width: dynamicBox, height: dynamicBox },
                aspectRatio: 1.333,
                disableFlip: false
            };

            if (typeof Html5QrcodeSupportedFormats !== 'undefined') {
                config.formatsToSupport = [Html5QrcodeSupportedFormats.QR_CODE];
            }

            return config;
        }

        function setupZoomControl() {
            const zoomWrap = document.getElementById('cameraZoomWrap');
            const zoomRange = document.getElementById('cameraZoomRange');
            if (!zoomWrap || !zoomRange || !scannerRunning || !scanner) {
                return;
            }

            if (typeof scanner.getRunningTrackCapabilities !== 'function') {
                zoomWrap.style.display = 'none';
                return;
            }

            const caps = scanner.getRunningTrackCapabilities() || {};
            if (!caps.zoom || typeof scanner.applyVideoConstraints !== 'function') {
                zoomWrap.style.display = 'none';
                return;
            }

            const min = Number(caps.zoom.min ?? 1);
            const max = Number(caps.zoom.max ?? min);
            const step = Number(caps.zoom.step ?? 0.1);

            if (!Number.isFinite(min) || !Number.isFinite(max) || max <= min) {
                zoomWrap.style.display = 'none';
                return;
            }

            zoomRange.min = String(min);
            zoomRange.max = String(max);
            zoomRange.step = String(step > 0 ? step : 0.1);
            zoomRange.value = String(min);

            if (zoomRange.dataset.bound !== '1') {
                zoomRange.addEventListener('input', function () {
                    const zoomValue = Number(zoomRange.value);
                    if (!Number.isFinite(zoomValue)) {
                        return;
                    }
                    scanner.applyVideoConstraints({ advanced: [{ zoom: zoomValue }] }).catch(function () {});
                });
                zoomRange.dataset.bound = '1';
            }

            zoomWrap.style.display = 'flex';
        }

        function applyCameraEnhancements() {
            if (!scanner || !scannerRunning || typeof scanner.applyVideoConstraints !== 'function') {
                return;
            }

            const attempts = [
                { advanced: [{ focusMode: 'continuous' }] },
                { advanced: [{ autoFocus: true }] },
                { advanced: [{ exposureMode: 'continuous' }] }
            ];

            attempts.reduce(function (chain, constraints) {
                return chain.then(function () {
                    return scanner.applyVideoConstraints(constraints).catch(function () {});
                });
            }, Promise.resolve()).then(function () {
                setupZoomControl();
            });
        }

        function loadCameraPreference() {
            try {
                const savedValue = localStorage.getItem(cameraPreferenceKey);
                if (savedValue === '0') {
                    cameraEnabled = false;
                } else if (savedValue === '1') {
                    cameraEnabled = true;
                }
            } catch (e) {}
        }

        function saveCameraPreference() {
            try {
                localStorage.setItem(cameraPreferenceKey, cameraEnabled ? '1' : '0');
            } catch (e) {}
        }
        
        function clearStuckModalBackdrop() {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');

            document.querySelectorAll('.modal-backdrop').forEach(function (el) {
                el.parentNode.removeChild(el);
            });
        }

        function toDatetimeLocal(dateTimeValue) {
            if (!dateTimeValue) {
                return '';
            }
            return dateTimeValue.replace(' ', 'T').slice(0, 16);
        }

        function formatDatetimePreview(value) {
            if (!value) {
                return '-';
            }

            const dt = new Date(value);
            if (Number.isNaN(dt.getTime())) {
                return '-';
            }

            const year = dt.getFullYear();
            const month = String(dt.getMonth() + 1).padStart(2, '0');
            const day = String(dt.getDate()).padStart(2, '0');
            let hour = dt.getHours();
            const minute = String(dt.getMinutes()).padStart(2, '0');
            const second = String(dt.getSeconds()).padStart(2, '0');
            const period = hour >= 12 ? 'PM' : 'AM';
            hour = hour % 12;
            hour = hour === 0 ? 12 : hour;

            return `${year}-${month}-${day} ${String(hour).padStart(2, '0')}:${minute}:${second} ${period}`;
        }

        function updateAttendanceTimePreview() {
            const timeInVal = document.getElementById('updateTimeIn').value;
            const timeOutVal = document.getElementById('updateTimeOut').value;

            document.getElementById('updateTimeInPreview').textContent = 'Preview: ' + formatDatetimePreview(timeInVal);
            document.getElementById('updateTimeOutPreview').textContent = 'Preview: ' + formatDatetimePreview(timeOutVal);
        }

        function setCameraStatus(message, tone = 'muted') {
            const statusEl = document.getElementById('cameraStatus');
            if (!statusEl) {
                return;
            }

            statusEl.classList.remove('text-muted', 'text-success', 'text-danger', 'text-warning');
            statusEl.classList.add('text-' + tone);
            statusEl.textContent = 'Camera status: ' + message;
        }

        function playScanTone(kind) {
            if (!scanSoundEnabled) {
                return;
            }
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) {
                    return;
                }

                const ctx = new AudioCtx();
                const now = ctx.currentTime;
                const gain = ctx.createGain();
                gain.connect(ctx.destination);

                if (kind === 'error') {
                    const osc1 = ctx.createOscillator();
                    osc1.type = 'square';
                    osc1.frequency.setValueAtTime(220, now);
                    osc1.connect(gain);
                    gain.gain.setValueAtTime(0.0001, now);
                    gain.gain.exponentialRampToValueAtTime(0.20, now + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.18);
                    osc1.start(now);
                    osc1.stop(now + 0.18);

                    const osc2 = ctx.createOscillator();
                    osc2.type = 'square';
                    osc2.frequency.setValueAtTime(170, now + 0.22);
                    osc2.connect(gain);
                    gain.gain.setValueAtTime(0.0001, now + 0.22);
                    gain.gain.exponentialRampToValueAtTime(0.20, now + 0.23);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.40);
                    osc2.start(now + 0.22);
                    osc2.stop(now + 0.40);

                    setTimeout(function () { ctx.close(); }, 550);
                } else {
                    const osc = ctx.createOscillator();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, now);
                    osc.connect(gain);
                    gain.gain.setValueAtTime(0.0001, now);
                    gain.gain.exponentialRampToValueAtTime(0.16, now + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.16);
                    osc.start(now);
                    osc.stop(now + 0.16);

                    setTimeout(function () { ctx.close(); }, 260);
                }
            } catch (e) {}
        }

        function showScanPopup(message) {
            const popupId = 'scan-success-popup';
            const existing = document.getElementById(popupId);
            if (existing) {
                existing.parentNode.removeChild(existing);
            }

            const popup = document.createElement('div');
            popup.id = popupId;
            popup.className = 'alert alert-success';
            popup.setAttribute('role', 'status');
            popup.textContent = message || 'Scan successful';
            popup.style.position = 'fixed';
            popup.style.top = '16px';
            popup.style.left = '50%';
            popup.style.transform = 'translateX(-50%)';
            popup.style.zIndex = '2000';
            popup.style.minWidth = '260px';
            popup.style.textAlign = 'center';
            popup.style.boxShadow = '0 8px 18px rgba(0,0,0,0.2)';
            popup.style.transition = 'opacity 0.2s ease';
            document.body.appendChild(popup);

            setTimeout(function () {
                popup.style.opacity = '0';
            }, 900);
            setTimeout(function () {
                if (popup.parentNode) {
                    popup.parentNode.removeChild(popup);
                }
            }, 1200);
        }

        function updateCameraButtons() {
            const openBtn = document.getElementById('openCameraBtn');
            const closeBtn = document.getElementById('closeCameraBtn');

            if (!openBtn || !closeBtn) {
                return;
            }

            openBtn.disabled = cameraEnabled;
            closeBtn.disabled = !cameraEnabled;
        }

        function openAttendanceEdit(buttonEl) {
            const attendanceId = buttonEl.getAttribute('data-attendance-id') || '';
            const personType = buttonEl.getAttribute('data-person-type') || 'student';
            const timeIn = buttonEl.getAttribute('data-time-in') || '';
            const timeOut = buttonEl.getAttribute('data-time-out') || '';

            document.getElementById('updateAttendanceId').value = attendanceId;
            document.getElementById('updatePersonType').value = personType;
            document.getElementById('updateTimeIn').value = toDatetimeLocal(timeIn);
            document.getElementById('updateTimeOut').value = toDatetimeLocal(timeOut);
            updateAttendanceTimePreview();

            $('#updateAttendanceModal').modal('show');
        }

        function setAttendancePanelByMode(mode) {
            const isVisitor = mode === 'visitor';
            const studentPanel = document.getElementById('studentAttendancePanel');
            const visitorPanel = document.getElementById('visitorAttendancePanel');
            const viewStudentsBtn = document.getElementById('viewStudentsBtn');
            const viewVisitorsBtn = document.getElementById('viewVisitorsBtn');

            if (isVisitor) {
                studentPanel.classList.remove('active');
                visitorPanel.classList.add('active');
                if (viewStudentsBtn) {
                    viewStudentsBtn.className = 'btn btn-outline-primary btn-sm';
                }
                if (viewVisitorsBtn) {
                    viewVisitorsBtn.className = 'btn btn-success btn-sm';
                }
            } else {
                visitorPanel.classList.remove('active');
                studentPanel.classList.add('active');
                if (viewStudentsBtn) {
                    viewStudentsBtn.className = 'btn btn-primary btn-sm';
                }
                if (viewVisitorsBtn) {
                    viewVisitorsBtn.className = 'btn btn-outline-success btn-sm';
                }
            }
        }

        function resetScannerUI() {
            document.querySelector('.qr-detected-container').style.display = 'none';
            document.querySelector('.scanner-con').style.display = '';
            lastScannedCode = '';
            lastScannedAt = 0;
            const destinationHidden = document.getElementById('destination-name-hidden');
            if (destinationHidden) {
                destinationHidden.value = '';
            }
            if (scanner && cameraEnabled) {
                startScanner(true);
            } else if (!cameraEnabled) {
                setCameraStatus('Closed', 'danger');
            }
        }

        function fetchStudentInfoByQr(qrCode) {
            const formData = new FormData();
            formData.append('qr_code', qrCode);
            formData.append('csrf_token', csrfToken);

            return fetch('./endpoint/get-student-by-qr.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            });
        }

        function loadStudentForConfirmation(qrCode, data) {
            setCameraStatus('Checking student info...', 'warning');

            return Promise.resolve(data).then(function (data) {
                if (!data || data.ok !== true || !data.student) {
                    throw new Error((data && data.message) ? data.message : 'Unable to fetch student data.');
                }

                const student = data.student;
                document.getElementById('studentConfirmName').textContent = student.name || '-';
                document.getElementById('studentConfirmIdNumber').textContent = student.student_id_number || '-';
                document.getElementById('studentConfirmCourse').textContent = student.course_section || '-';
                document.getElementById('studentConfirmQrCode').value = qrCode;
                document.getElementById('person-type').value = 'student';

                const photoEl = document.getElementById('studentConfirmPhoto');
                const noPhotoEl = document.getElementById('studentConfirmNoPhoto');
                if (student.photo_url) {
                    photoEl.src = student.photo_url;
                    photoEl.style.display = '';
                    noPhotoEl.style.display = 'none';
                } else {
                    photoEl.src = '';
                    photoEl.style.display = 'none';
                    noPhotoEl.style.display = '';
                }

                $('#studentScanConfirmModal').modal('show');
                setCameraStatus('Awaiting staff confirmation', 'warning');
            });
        }

        function cancelStudentConfirmation() {
            $('#studentScanConfirmModal').modal('hide');
            resetScannerUI();
        }

        function stopScanner(statusMessage = 'Stopped', statusTone = 'warning') {
            if (!scanner || !scannerRunning) {
                return Promise.resolve();
            }

            return scanner.stop()
                .then(function () {
                    scannerRunning = false;
                    setCameraStatus(statusMessage, statusTone);
                })
                .catch(function () {
                    scannerRunning = false;
                    setCameraStatus(statusMessage + ' (with warning)', statusTone);
                });
        }

        function startScanner(forceRestart = false) {
            if (!isCameraContextAllowed()) {
                showCameraContextHint();
                updateCameraButtons();
                return;
            }

            if (!cameraEnabled) {
                setCameraStatus('Closed', 'danger');
                updateCameraButtons();
                return;
            }

            if (!scanner) {
                scanner = new Html5Qrcode('interactive');
            }

            if (scannerRunning && !forceRestart) {
                setCameraStatus('Running', 'success');
                return;
            }

            const beginStart = function () {
                const config = getScanConfig();
                const cameraConfig = { facingMode: 'environment' };
                const onScanSuccess = function (decodedText) {
                    const normalizedText = (decodedText || '').trim();
                    if (normalizedText === '') {
                        return;
                    }

                    const nowTs = Date.now();
                    if (normalizedText === lastScannedCode && (nowTs - lastScannedAt) < 1800) {
                        return;
                    }
                    lastScannedCode = normalizedText;
                    lastScannedAt = nowTs;

                    const destinationHidden = document.getElementById('destination-name-hidden');
                    if (destinationHidden) {
                        destinationHidden.value = '';
                    }

                    playScanTone('success');
                    showScanPopup('Scan successful');
                    $('#detected-qr-code').val(normalizedText);
                    stopScanner('Waiting for confirmation', 'warning');
                    document.querySelector('.qr-detected-container').style.display = '';
                    document.querySelector('.scanner-con').style.display = 'none';
                    document.getElementById('detectedLabel').textContent = 'Checking QR type...';
                    setCameraStatus('Detecting QR type...', 'warning');

                    fetchStudentInfoByQr(normalizedText).then(function (studentLookup) {
                        if (studentLookup && studentLookup.ok === true) {
                            setAttendancePanelByMode('student');
                            document.getElementById('detectedLabel').textContent = 'Student QR Detected (Auto Time In/Out)!';
                            return loadStudentForConfirmation(normalizedText, studentLookup);
                        }

                        const lookupMessage = studentLookup && typeof studentLookup.message === 'string'
                            ? studentLookup.message.trim()
                            : '';
                        if (lookupMessage === 'Student not found for this QR code.') {
                            document.getElementById('person-type').value = 'visitor';
                            document.getElementById('detectedLabel').textContent = 'Visitor QR Detected (Auto Time In/Out)!';
                            setAttendancePanelByMode('visitor');
                            setCameraStatus('Visitor detected. Ready to submit.', 'warning');
                            return;
                        }

                        throw new Error(lookupMessage !== '' ? lookupMessage : 'Failed to detect QR type.');
                    }).catch(function (err) {
                        playScanTone('error');
                        alert(err.message || 'Failed to detect QR type.');
                        resetScannerUI();
                    });
                };

                const onScanFailure = function () {};

                const startByDeviceId = function () {
                    setCameraStatus('Searching camera devices...', 'warning');
                    Html5Qrcode.getCameras()
                        .then(function (devices) {
                            if (!Array.isArray(devices) || devices.length === 0) {
                                setCameraStatus('No cameras found', 'danger');
                                playScanTone('error');
                                alert('No cameras found.');
                                return;
                            }

                            const preferred = devices.find(function (d) {
                                const label = (d.label || '').toLowerCase();
                                return label.indexOf('back') >= 0 || label.indexOf('rear') >= 0;
                            }) || devices[0];

                            scanner.start(
                                { deviceId: { exact: preferred.id } },
                                config,
                                onScanSuccess,
                                onScanFailure
                            ).then(function () {
                                scannerRunning = true;
                                setCameraStatus('Running', 'success');
                                updateCameraButtons();
                                applyCameraEnhancements();
                            }).catch(function (deviceErr) {
                                setCameraStatus('Error - ' + deviceErr, 'danger');
                                playScanTone('error');
                                alert('Camera access error: ' + deviceErr);
                            });
                        })
                        .catch(function (cameraErr) {
                            setCameraStatus('Error - ' + cameraErr, 'danger');
                            playScanTone('error');
                            alert('Camera access error: ' + cameraErr);
                        });
                };

                setCameraStatus('Starting...', 'warning');
                scanner.start(
                    cameraConfig,
                    config,
                    onScanSuccess,
                    onScanFailure
                ).then(function () {
                    scannerRunning = true;
                    setCameraStatus('Running', 'success');
                    updateCameraButtons();
                    applyCameraEnhancements();
                }).catch(function (err) {
                    setCameraStatus('Fallback to available camera...', 'warning');
                    startByDeviceId();
                });
            };

            if (forceRestart) {
                stopScanner().then(beginStart);
            } else {
                beginStart();
            }
        }

        function closeCamera() {
            cameraEnabled = false;
            saveCameraPreference();
            updateCameraButtons();
            const zoomWrap = document.getElementById('cameraZoomWrap');
            if (zoomWrap) {
                zoomWrap.style.display = 'none';
            }
            stopScanner('Closed', 'danger').then(function () {
                setCameraStatus('Closed', 'danger');
            });
        }

        function openCamera() {
            cameraEnabled = true;
            saveCameraPreference();
            updateCameraButtons();
            if (!isCameraContextAllowed()) {
                showCameraContextHint();
                return;
            }
            startScanner(true);
        }

        document.addEventListener('DOMContentLoaded', function () {
            clearStuckModalBackdrop();
            setAttendancePanelByMode('student');
            loadCameraPreference();
            updateCameraButtons();
            if (cameraEnabled) {
                openCamera();
            } else {
                setCameraStatus('Closed', 'danger');
            }
            const studentGroupButtons = document.querySelectorAll('#studentGroupFilter [data-group]');
            const studentRows = document.querySelectorAll('#studentAttendancePanel tbody tr[data-student-group]');
            studentGroupButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const selectedGroup = button.getAttribute('data-group') || 'ALL';
                    studentGroupButtons.forEach(function (btn) {
                        btn.className = btn === button ? 'btn btn-dark btn-sm active' : 'btn btn-outline-dark btn-sm';
                    });

                    studentRows.forEach(function (row) {
                        const rowGroup = row.getAttribute('data-student-group') || '';
                        row.style.display = selectedGroup === 'ALL' || rowGroup === selectedGroup ? '' : 'none';
                    });
                });
            });

            $('#updateAttendanceModal').on('hidden.bs.modal', function () {
                clearStuckModalBackdrop();
            });

            $('#studentScanConfirmModal').on('hidden.bs.modal', function () {
                clearStuckModalBackdrop();
            });

            document.getElementById('updateTimeIn').addEventListener('input', updateAttendanceTimePreview);
            document.getElementById('updateTimeOut').addEventListener('input', updateAttendanceTimePreview);
        });

        window.addEventListener('beforeunload', function () {
            if (scannerRunning) {
                scanner.stop();
            }
        });
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














