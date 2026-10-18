<?php
include('./conn/bootstrap.php');
require_login();
include('./conn/conn.php');
require_once('./conn/student_time_rules.php');
include('./conn/audit_log.php');

ensure_student_time_rules_table($conn);
ensure_audit_log_table($conn);

$error = '';
$success = '';

if (isset($_SESSION['schedules_flash_error'])) {
    $error = (string) $_SESSION['schedules_flash_error'];
    unset($_SESSION['schedules_flash_error']);
}
if (isset($_SESSION['schedules_flash_success'])) {
    $success = (string) $_SESSION['schedules_flash_success'];
    unset($_SESSION['schedules_flash_success']);
}

$studentDepartments = get_student_departments($conn);
$studentYearLevels = get_student_year_levels($conn);
$defaultDepartment = $studentDepartments[0] ?? 'BSIS';
$defaultYearLevel = $studentYearLevels[0] ?? 1;

$ruleDepartmentInput = $defaultDepartment;
$ruleYearInput = $defaultYearLevel;
$ruleDayKeyInput = 0;
$ruleStartInput = '08:00';
$ruleEndInput = '17:00';
$ruleRoomInput = '';
$ruleRoomRaw = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_reject();

    if (isset($_POST['save_student_time_rule_override'])) {
        $ruleDepartmentInput = normalize_student_department($_POST['rule_department'] ?? '');
        $ruleYearInput = normalize_student_year_level($_POST['rule_year_level'] ?? null);
        $ruleDayKeyInput = normalize_student_rule_day_key($_POST['rule_day_key'] ?? 0);
        $ruleStartInput = normalize_hhmm_time($_POST['rule_class_start_time'] ?? '', '');
        $ruleEndInput = normalize_hhmm_time($_POST['rule_class_end_time'] ?? '', '');
        $ruleRoomRaw = trim((string) ($_POST['rule_room'] ?? ''));
        $ruleRoomInput = normalize_student_room($ruleRoomRaw);

        $departmentAllowed = $ruleDepartmentInput !== '' && is_student_department_allowed($ruleDepartmentInput, $conn);
        $yearAllowed = $ruleYearInput !== null && is_student_year_level_allowed($ruleYearInput, $conn);

        if ($ruleDepartmentInput === '' || $ruleYearInput === null || $ruleDayKeyInput === null || $ruleStartInput === '' || $ruleEndInput === '') {
            $error = 'Department, year level, day, class start time, and class end time are required.';
        } elseif (!$departmentAllowed) {
            $error = 'Department is not in the allowed list. Update it in Admin Settings.';
        } elseif (!$yearAllowed) {
            $error = 'Year level is not in the allowed list. Update it in Admin Settings.';
        } elseif ($ruleRoomRaw !== '' && $ruleRoomInput === '') {
            $error = 'Room contains invalid characters.';
        } elseif ($ruleStartInput >= $ruleEndInput) {
            $error = 'End time must be later than start time.';
        } elseif (!upsert_student_time_rule($conn, $ruleDepartmentInput, $ruleYearInput, $ruleStartInput, $ruleEndInput, $ruleDayKeyInput, $ruleRoomInput)) {
            $error = 'Failed to save schedule rule.';
        } else {
            $roomLabel = '';
            if ($ruleRoomInput !== '') {
                $roomLabel = $ruleRoomInput;
                if (preg_match('/^(room|lab|lecture)\\b/i', $roomLabel) !== 1) {
                    $roomLabel = 'Room ' . $roomLabel;
                }
            }
            $success = 'Schedule rule saved successfully.';
            record_audit_log(
                $conn,
                'UPDATE_SETTING',
                'student_time_rule',
                null,
                'Saved schedule rule: ' . $ruleDepartmentInput . ' Year ' . $ruleYearInput . ' ' . student_rule_day_label($ruleDayKeyInput) . ' (' . $ruleStartInput . '-' . $ruleEndInput . ')' . ($roomLabel !== '' ? ' ' . $roomLabel : '')
            );
        }
    } elseif (isset($_POST['delete_student_time_rule_override'])) {
        $deleteRuleId = filter_input(INPUT_POST, 'student_time_rule_id', FILTER_VALIDATE_INT);
        if (!$deleteRuleId) {
            $error = 'Invalid schedule rule id.';
        } elseif (!delete_student_time_rule($conn, $deleteRuleId)) {
            $error = 'Schedule rule not found or already deleted.';
        } else {
            $success = 'Schedule rule deleted successfully.';
            record_audit_log($conn, 'DELETE', 'student_time_rule', $deleteRuleId, 'Deleted schedule rule');
        }
    }

    $_SESSION['schedules_flash_error'] = $error;
    $_SESSION['schedules_flash_success'] = $success;
    redirect_to('schedules.php');
}

$studentTimeRuleRows = get_student_time_rules($conn);

$weekdayMap = [
    1 => 'Mon',
    2 => 'Tue',
    3 => 'Wed',
    4 => 'Thu',
    5 => 'Fri',
    6 => 'Sat',
    7 => 'Sun',
];
$validYears = valid_student_year_levels($conn);
$groupedScheduleRows = [];
foreach ($studentTimeRuleRows as $rule) {
    $department = (string) ($rule['department'] ?? '');
    $yearLevel = (int) ($rule['year_level'] ?? 0);
    $dayKey = (int) ($rule['day_key'] ?? 0);
    $slot = trim((string) ($rule['class_start_time'] ?? '')) . '-' . trim((string) ($rule['class_end_time'] ?? ''));
    $room = (string) ($rule['room'] ?? '');
    if ($department === '' || !in_array($yearLevel, $validYears, true) || $slot === '-') {
        continue;
    }

    $groupKey = $department . '|' . $yearLevel;
    if (!isset($groupedScheduleRows[$groupKey])) {
        $groupedScheduleRows[$groupKey] = [
            'department' => $department,
            'year_level' => $yearLevel,
            'any_day' => ['slot' => '', 'room' => ''],
            'days' => [
                1 => ['slot' => '', 'room' => ''],
                2 => ['slot' => '', 'room' => ''],
                3 => ['slot' => '', 'room' => ''],
                4 => ['slot' => '', 'room' => ''],
                5 => ['slot' => '', 'room' => ''],
                6 => ['slot' => '', 'room' => ''],
                7 => ['slot' => '', 'room' => ''],
            ],
        ];
    }

    if ($dayKey === 0) {
        $groupedScheduleRows[$groupKey]['any_day'] = ['slot' => $slot, 'room' => $room];
    } elseif (isset($weekdayMap[$dayKey])) {
        $groupedScheduleRows[$groupKey]['days'][$dayKey] = ['slot' => $slot, 'room' => $room];
    }
}

if (!empty($groupedScheduleRows)) {
    uasort($groupedScheduleRows, static function (array $a, array $b): int {
        $cmpDept = strcmp($a['department'], $b['department']);
        if ($cmpDept !== 0) {
            return $cmpDept;
        }
        return $a['year_level'] <=> $b['year_level'];
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT | Schedules & Rooms</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@500&display=swap');

        * {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(180deg, #fff4e6 0%, #ffe3c2 100%);
        }
    @media print {
        .no-print, .navbar, .site-footer { display: none !important; }
        body { background: #fff !important; margin: 0; overflow: visible; }
        @page { size: A4 landscape; margin: 6mm; }
        * { visibility: hidden; }
        .print-target, .print-target * { visibility: visible !important; }
        .print-target {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
            box-shadow: none;
            border: 0;
            zoom: 0.75;
        }
        .table-responsive { overflow: visible !important; }
        table { width: 100% !important; font-size: 9px; }
        th, td { padding: 2px 4px !important; }
    }
        .wrap {
            width: 98%;
            max-width: 1600px;
            margin: 18px auto 30px auto;
            padding: 0 8px;
        }
        .panel {
            background: #fff;
            border: 1px solid #d9dee6;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05);
            overflow: visible;
        }
        .rule-filters {
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }
        .view-switch {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }
        .view-pane {
            display: none;
        }
        .view-pane.active {
            display: block;
        }
        .schedule-cell {
            white-space: nowrap;
            font-size: 12px;
        }
        .schedule-cell.empty {
            color: #9ca3af;
        }
        .coverage-note {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 0;
        }
        .rule-inline-actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .rule-key-preview {
            font-size: 12px;
            color: #6b7280;
            margin-top: 6px;
        }
        @media (max-width: 991.98px) {
            .rule-filters { grid-template-columns: 1fr; }
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
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item"><a class="nav-link" href="./index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="./masterlist.php">List of Students</a></li>
                <li class="nav-item"><a class="nav-link" href="./visitorlist.php">List of Visitors</a></li>
                <li class="nav-item"><a class="nav-link" href="./reports.php">Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="./audit-logs.php">Audit Logs</a></li>
                <li class="nav-item active"><a class="nav-link" href="./schedules.php">Schedule & Rooms <span class="sr-only">(current)</span></a></li>
                <li class="nav-item"><a class="nav-link" href="./visitor-destination.php">Visitor Destination</a></li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item mr-3"><a class="nav-link" href="./settings.php">Admin Settings</a></li>
                <li class="nav-item mr-3"><a class="nav-link" href="./logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="wrap">
        <div class="panel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h4 class="mb-1">Schedules & Rooms</h4>
                <button class="btn btn-outline-secondary btn-sm no-print" id="exportSchedulesPdf">Export PDF</button>
            </div>
            <p class="text-muted mb-3">Manage Department + Year + Day schedule rules.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="alert"><?= h($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="./schedules.php" class="mb-3" id="specificRuleForm">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="save_student_time_rule_override" value="1">
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label for="rule_department">Department</label>
                        <select class="form-control" id="rule_department" name="rule_department" required>
                            <?php foreach ($studentDepartments as $dept): ?>
                                <option value="<?= h($dept) ?>"><?= h($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="rule_year_level">Year</label>
                        <select class="form-control" id="rule_year_level" name="rule_year_level" required>
                            <?php foreach ($studentYearLevels as $year): ?>
                                <option value="<?= h((string) $year) ?>"><?= h(format_student_year_level_label($year)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="rule_day_key">Day</label>
                        <select class="form-control" id="rule_day_key" name="rule_day_key" required>
                            <option value="0">Any Day (Use for Dept+Year default)</option>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="rule_class_start_time">Start</label>
                        <input type="time" class="form-control" id="rule_class_start_time" name="rule_class_start_time" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="rule_class_end_time">End</label>
                        <input type="time" class="form-control" id="rule_class_end_time" name="rule_class_end_time" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="rule_room">Room (optional)</label>
                        <input type="text" class="form-control" id="rule_room" name="rule_room" maxlength="50" placeholder="e.g. Room 101">
                    </div>
                    <div class="form-group col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-dark btn-block">Save</button>
                    </div>
                </div>
                <div class="rule-inline-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearRuleFormBtn">Clear</button>
                    <small class="rule-key-preview" id="ruleKeyPreview">You are editing: -</small>
                </div>
            </form>

            <div class="view-switch">
                <button type="button" class="btn btn-dark btn-sm" id="showGroupedBtn">Grouped (Recommended)</button>
                <button type="button" class="btn btn-outline-dark btn-sm" id="showDetailedBtn">Detailed (Edit/Delete)</button>
            </div>

            <div class="view-pane active print-target" id="groupedView">
                <?php if (count($groupedScheduleRows) === 0): ?>
                    <p class="text-muted mb-0">No schedule rules yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-2">
                            <thead class="thead-light">
                                <tr>
                                    <th>Department</th>
                                    <th>Year</th>
                                    <th>Mon</th>
                                    <th>Tue</th>
                                    <th>Wed</th>
                                    <th>Thu</th>
                                    <th>Fri</th>
                                    <th>Sat</th>
                                    <th>Sun</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedScheduleRows as $group): ?>
                                    <?php
                                        $anyDaySlot = (string) ($group['any_day']['slot'] ?? '');
                                        $anyDayRoom = (string) ($group['any_day']['room'] ?? '');
                                    ?>
                                    <tr>
                                        <td><?= h((string) $group['department']) ?></td>
                                        <td><?= h((string) $group['year_level']) ?></td>
                                        <?php for ($d = 1; $d <= 7; $d++): ?>
                                            <?php
                                                $slotValue = (string) ($group['days'][$d]['slot'] ?? '');
                                                $roomValue = (string) ($group['days'][$d]['room'] ?? '');
                                                if ($slotValue === '' && $anyDaySlot !== '') {
                                                    $slotValue = $anyDaySlot;
                                                    $roomValue = $anyDayRoom;
                                                    if ($slotValue !== '') {
                                                        $slotValue .= ' (Default)';
                                                    }
                                                }
                                                if ($roomValue !== '') {
                                                    $roomLabel = $roomValue;
                                                    if (preg_match('/^room\\b/i', $roomLabel) === 1) {
                                                        // keep as-is (already prefixed)
                                                    } elseif (preg_match('/^\\d/', $roomLabel) === 1) {
                                                        $roomLabel = 'Room ' . $roomLabel;
                                                    }
                                                    $slotValue = trim($slotValue . ' | ' . $roomLabel);
                                                }
                                            ?>
                                            <td class="schedule-cell <?= $slotValue === '' ? 'empty' : '' ?>"><?= $slotValue === '' ? '-' : h($slotValue) ?></td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="coverage-note">Tip: use Detailed view to edit or delete individual rows.</p>
                <?php endif; ?>
            </div>

            <div class="view-pane" id="detailedView">
                <div class="rule-filters">
                    <div class="form-group mb-0">
                        <label for="ruleFilterDepartment">Department</label>
                        <select id="ruleFilterDepartment" class="form-control form-control-sm">
                            <option value="">All</option>
                            <?php foreach ($studentDepartments as $dept): ?>
                                <option value="<?= h($dept) ?>"><?= h($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label for="ruleFilterYear">Year</label>
                        <select id="ruleFilterYear" class="form-control form-control-sm">
                            <option value="">All</option>
                            <?php foreach ($studentYearLevels as $year): ?>
                                <option value="<?= h((string) $year) ?>"><?= h(format_student_year_level_label($year)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label for="ruleFilterDay">Day</label>
                        <select id="ruleFilterDay" class="form-control form-control-sm">
                            <option value="">All</option>
                            <option value="Any Day">Any Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label for="ruleFilterSearch">Search</label>
                        <input id="ruleFilterSearch" class="form-control form-control-sm" type="text" placeholder="Type keyword">
                    </div>
                </div>

                <div class="table-responsive print-table">
                    <table class="table table-sm table-bordered" id="rulesTable">
                        <thead class="thead-light">
                            <tr>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Day</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Room</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($studentTimeRuleRows) === 0): ?>
                                <tr><td colspan="7" class="text-muted">No schedule rules yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($studentTimeRuleRows as $rule): ?>
                                    <tr
                                        data-rule-department="<?= h((string) ($rule['department'] ?? '')) ?>"
                                        data-rule-year="<?= h((string) ($rule['year_level'] ?? '')) ?>"
                                        data-rule-day-key="<?= h((string) ($rule['day_key'] ?? '0')) ?>"
                                        data-rule-day-label="<?= h(student_rule_day_label($rule['day_key'] ?? 0)) ?>"
                                        data-rule-start="<?= h((string) ($rule['class_start_time'] ?? '')) ?>"
                                        data-rule-end="<?= h((string) ($rule['class_end_time'] ?? '')) ?>"
                                        data-rule-room="<?= h((string) ($rule['room'] ?? '')) ?>"
                                    >
                                        <td><?= h((string) ($rule['department'] ?? '')) ?></td>
                                        <td><?= h((string) ($rule['year_level'] ?? '')) ?></td>
                                        <td><?= h(student_rule_day_label($rule['day_key'] ?? 0)) ?></td>
                                        <td><?= h((string) ($rule['class_start_time'] ?? '')) ?></td>
                                        <td><?= h((string) ($rule['class_end_time'] ?? '')) ?></td>
                                        <td><?= h((string) ($rule['room'] ?? '')) ?></td>
                                        <td>
                                            <div class="rule-inline-actions">
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-rule-btn">Edit</button>
                                                <form method="POST" action="./schedules.php" onsubmit="return confirm('Delete this schedule rule?');">
                                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="delete_student_time_rule_override" value="1">
                                                    <input type="hidden" name="student_time_rule_id" value="<?= (int) ($rule['tbl_student_time_rule_id'] ?? 0) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const ruleForm = document.getElementById('specificRuleForm');
            const clearBtn = document.getElementById('clearRuleFormBtn');
            const previewEl = document.getElementById('ruleKeyPreview');
            const deptEl = document.getElementById('rule_department');
            const yearEl = document.getElementById('rule_year_level');
            const dayEl = document.getElementById('rule_day_key');
            const startEl = document.getElementById('rule_class_start_time');
            const endEl = document.getElementById('rule_class_end_time');
            const roomEl = document.getElementById('rule_room');
            const filterDeptEl = document.getElementById('ruleFilterDepartment');
            const filterYearEl = document.getElementById('ruleFilterYear');
            const filterDayEl = document.getElementById('ruleFilterDay');
            const filterSearchEl = document.getElementById('ruleFilterSearch');
            const showGroupedBtn = document.getElementById('showGroupedBtn');
            const showDetailedBtn = document.getElementById('showDetailedBtn');
            const groupedView = document.getElementById('groupedView');
            const detailedView = document.getElementById('detailedView');
            const ruleRows = Array.from(document.querySelectorAll('#rulesTable tbody tr')).filter(function (row) {
                return row.hasAttribute('data-rule-department');
            });

            function activateView(mode) {
                const groupedMode = mode === 'grouped';
                groupedView.classList.toggle('active', groupedMode);
                detailedView.classList.toggle('active', !groupedMode);
                showGroupedBtn.classList.toggle('btn-dark', groupedMode);
                showGroupedBtn.classList.toggle('btn-outline-dark', !groupedMode);
                showDetailedBtn.classList.toggle('btn-dark', !groupedMode);
                showDetailedBtn.classList.toggle('btn-outline-dark', groupedMode);
            }

            function dayLabelFromSelect() {
                return dayEl.options[dayEl.selectedIndex] ? dayEl.options[dayEl.selectedIndex].text : '-';
            }

            function updateRuleKeyPreview() {
                const dept = (deptEl.value || '-').trim();
                const year = (yearEl.value || '-').trim();
                const day = dayLabelFromSelect();
                previewEl.textContent = 'You are editing: ' + dept + ' | Year ' + year + ' | ' + day;
            }

            function applyRuleFilters() {
                const fd = (filterDeptEl.value || '').trim().toUpperCase();
                const fy = (filterYearEl.value || '').trim();
                const fday = (filterDayEl.value || '').trim().toUpperCase();
                const q = (filterSearchEl.value || '').trim().toUpperCase();

                ruleRows.forEach(function (row) {
                    const dept = (row.getAttribute('data-rule-department') || '').toUpperCase();
                    const year = (row.getAttribute('data-rule-year') || '');
                    const dayLabel = (row.getAttribute('data-rule-day-label') || '').toUpperCase();
                    const text = (row.textContent || '').toUpperCase();

                    let visible = true;
                    if (fd !== '' && dept !== fd) visible = false;
                    if (fy !== '' && year !== fy) visible = false;
                    if (fday !== '' && dayLabel !== fday) visible = false;
                    if (q !== '' && text.indexOf(q) === -1) visible = false;

                    row.style.display = visible ? '' : 'none';
                });
            }

            const defaultDepartment = <?= json_encode((string) $defaultDepartment) ?>;
            const defaultYear = <?= json_encode((string) $defaultYearLevel) ?>;

            function fillRuleFormFromRow(row) {
                deptEl.value = row.getAttribute('data-rule-department') || defaultDepartment;
                yearEl.value = row.getAttribute('data-rule-year') || defaultYear;
                dayEl.value = row.getAttribute('data-rule-day-key') || '0';
                startEl.value = row.getAttribute('data-rule-start') || '';
                endEl.value = row.getAttribute('data-rule-end') || '';
                if (roomEl) {
                    roomEl.value = row.getAttribute('data-rule-room') || '';
                }
                updateRuleKeyPreview();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function resetRuleForm() {
                deptEl.value = defaultDepartment;
                yearEl.value = defaultYear;
                dayEl.value = '0';
                startEl.value = '';
                endEl.value = '';
                if (roomEl) {
                    roomEl.value = '';
                }
                updateRuleKeyPreview();
            }

            [deptEl, yearEl, dayEl].forEach(function (el) {
                el.addEventListener('change', updateRuleKeyPreview);
            });
            [filterDeptEl, filterYearEl, filterDayEl].forEach(function (el) {
                if (el) el.addEventListener('change', applyRuleFilters);
            });
            if (filterSearchEl) filterSearchEl.addEventListener('input', applyRuleFilters);
            if (showGroupedBtn) {
                showGroupedBtn.addEventListener('click', function () { activateView('grouped'); });
            }
            if (showDetailedBtn) {
                showDetailedBtn.addEventListener('click', function () { activateView('detailed'); });
            }
            document.querySelectorAll('.edit-rule-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const row = btn.closest('tr');
                    if (row) {
                        fillRuleFormFromRow(row);
                        activateView('detailed');
                    }
                });
            });
            clearBtn.addEventListener('click', resetRuleForm);

            ruleForm.addEventListener('submit', function (ev) {
                const keyDept = (deptEl.value || '').trim().toUpperCase();
                const keyYear = (yearEl.value || '').trim();
                const keyDay = (dayEl.value || '').trim();

                const exists = ruleRows.some(function (row) {
                    return (row.getAttribute('data-rule-department') || '').toUpperCase() === keyDept
                        && (row.getAttribute('data-rule-year') || '') === keyYear
                        && (row.getAttribute('data-rule-day-key') || '') === keyDay;
                });

                if (exists) {
                    const ok = confirm('A rule with this Department + Year + Day already exists. Save will update it. Continue?');
                    if (!ok) ev.preventDefault();
                }
            });

            updateRuleKeyPreview();
            applyRuleFilters();
            activateView('grouped');
        })();
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

(function () {
    const btn = document.getElementById('exportSchedulesPdf');
    if (!btn) return;

    function buildPrintHtml(tableHtml) {
        return `<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Schedules & Rooms</title>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        body { margin: 0; font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        th, td { border: 1px solid #999; padding: 2px 4px; text-align: center; }
        thead th { background: #f0f0f0; }
    </style>
</head>
<body>
    ${tableHtml}
</body>
</html>`;
    }

    btn.addEventListener('click', function () {
        const activeView = document.querySelector('.view-pane.active');
        const table = activeView ? activeView.querySelector('table') : null;
        if (!table) {
            alert('No schedule table found to export.');
            return;
        }

        const popup = window.open('', '_blank');
        if (!popup) {
            alert('Please allow pop-ups to export the PDF.');
            return;
        }

        popup.document.open();
        popup.document.write(buildPrintHtml(table.outerHTML));
        popup.document.close();
        popup.focus();
        popup.onload = function () {
            popup.print();
            popup.close();
        };
    });
})();

</script><footer class="site-footer">
    <div class="footer-inner">
        <img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 2em; width: auto;">
        <span>Copyright 2026. TIF-TCI</span>
    </div>
</footer></body>
</html>








