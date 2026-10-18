<?php
include('./conn/bootstrap.php');
require_login();
include('./conn/conn.php');
include('./conn/visitor_table.php');
include('./conn/attendance_tables.php');
require_once('./conn/system_settings.php');

ensure_visitor_table($conn);
ensure_attendance_tables($conn);
ensure_system_settings_table($conn);
$visitorPurposeOptions = get_visitor_purpose_options($conn);

$startDate = trim((string) ($_GET['start_date'] ?? ''));
$endDate = trim((string) ($_GET['end_date'] ?? ''));
$startTimeRaw = trim((string) ($_GET['start_time'] ?? ''));
$endTimeRaw = trim((string) ($_GET['end_time'] ?? ''));
$nameFilter = trim((string) ($_GET['name'] ?? ''));
$purposeFilter = trim((string) ($_GET['purpose'] ?? ''));
$export = $_GET['export'] ?? '';

$hasStartDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) === 1;
$hasEndDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) === 1;
$hasStartTime = preg_match('/^\d{2}:\d{2}$/', $startTimeRaw) === 1;
$hasEndTime = preg_match('/^\d{2}:\d{2}$/', $endTimeRaw) === 1;
$startTime = $hasStartTime ? $startTimeRaw : '';
$endTime = $hasEndTime ? $endTimeRaw : '';

if ($hasStartDate && $hasEndDate && $startDate > $endDate) {
    $tmp = $startDate;
    $startDate = $endDate;
    $endDate = $tmp;
}

if ($hasStartTime && $hasEndTime && $startTime > $endTime) {
    $tmp = $startTime;
    $startTime = $endTime;
    $endTime = $tmp;
}

$sql = 'SELECT
    tbl_visitor_attendance_id,
    visitor_name,
    purpose,
    contact_no,
    time_in,
    time_out
    FROM tbl_visitor_attendance
    INNER JOIN tbl_visitor ON tbl_visitor.tbl_visitor_id = tbl_visitor_attendance.tbl_visitor_id';

$conditions = [];
$params = [];

if ($hasStartDate) {
    $conditions[] = 'time_in >= :start_dt';
    $params[':start_dt'] = $startDate . ' 00:00:00';
}

if ($hasEndDate) {
    $conditions[] = 'time_in <= :end_dt';
    $params[':end_dt'] = $endDate . ' 23:59:59';
}

if ($hasStartTime) {
    $conditions[] = 'TIME(time_in) >= :start_time';
    $params[':start_time'] = $startTime;
}

if ($hasEndTime) {
    $conditions[] = 'TIME(time_in) <= :end_time';
    $params[':end_time'] = $endTime;
}

if ($nameFilter !== '') {
    $conditions[] = "visitor_name LIKE :name ESCAPE '\\\\'";
    $params[':name'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $nameFilter) . '%';
}

if ($purposeFilter !== '') {
    $conditions[] = 'purpose = :purpose';
    $params[':purpose'] = $purposeFilter;
}

if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY tbl_visitor_attendance_id DESC';

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll();

$totalCount = count($rows);
$inCount = 0;
$outCount = 0;

foreach ($rows as $row) {
    if (!empty($row['time_out'])) {
        $outCount++;
    } else {
        $inCount++;
    }
}

if ($export === 'pdf') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Attendance Report PDF</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
        h2 { margin: 0 0 6px 0; }
        .meta { margin-bottom: 12px; font-size: 14px; }
        .summary { margin: 10px 0 14px 0; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #444; padding: 6px; text-align: center; }
        th { background: #e9ecef; }
        td.left { text-align: left; }
        @media print {
            body { margin: 10mm; }
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
    <h2>QRYPT - Visitor Attendance Report</h2>
    <div class="meta">
        Date Filter:
        <?= h($hasStartDate ? $startDate : 'All') ?>
        to
        <?= h($hasEndDate ? $endDate : 'All') ?>
        <br>
        Time Filter:
        <?= h($hasStartTime ? $startTime : 'All') ?>
        to
        <?= h($hasEndTime ? $endTime : 'All') ?>
        <br>
        Name Filter: <?= h($nameFilter !== '' ? $nameFilter : 'All') ?> |
        Purpose Filter: <?= h($purposeFilter !== '' ? $purposeFilter : 'All') ?>
        <br>
        Generated: <?= h(format_datetime_ph(date('Y-m-d H:i:s'))) ?>
    </div>

    <div class="summary">
        Total Logs: <strong><?= (int) $totalCount ?></strong> |
        IN: <strong><?= (int) $inCount ?></strong> |
        OUT: <strong><?= (int) $outCount ?></strong>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Purpose</th>
                <th>Contact</th>
                <th>Time In (PH)</th>
                <th>Time Out (PH)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($rows) === 0): ?>
                <tr><td colspan="7">No visitor records found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $idx => $row): ?>
                    <?php
                        $timeIn = format_datetime_ph((string) ($row['time_in'] ?? ''));
                        $timeOutRaw = (string) ($row['time_out'] ?? '');
                        $timeOut = format_datetime_ph($timeOutRaw);
                        $status = $timeOutRaw !== '' ? 'OUT' : 'IN';
                    ?>
                    <tr>
                        <td><?= (int) ($idx + 1) ?></td>
                        <td class="left"><?= h((string) ($row['visitor_name'] ?? '')) ?></td>
                        <td class="left"><?= h((string) ($row['purpose'] ?? '')) ?></td>
                        <td><?= h((string) ($row['contact_no'] ?? '')) ?></td>
                        <td><?= h($timeIn) ?></td>
                        <td><?= h($timeOut !== '' ? $timeOut : '-') ?></td>
                        <td><?= h($status) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        window.onload = function () {
            window.print();
        };
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
<?php
    exit();
}

if ($export === 'csv') {
    $dateLabel = ($hasStartDate || $hasEndDate)
        ? (($hasStartDate ? $startDate : 'start') . '_to_' . ($hasEndDate ? $endDate : 'end'))
        : 'all_dates';
    $filename = 'visitor_attendance_report_' . $dateLabel . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Purpose', 'Contact', 'Time In (PH)', 'Time Out (PH)', 'Status']);

    foreach ($rows as $row) {
        $timeIn = format_datetime_ph((string) ($row['time_in'] ?? ''));
        $timeOut = format_datetime_ph((string) ($row['time_out'] ?? ''));
        $status = !empty($row['time_out']) ? 'OUT' : 'IN';

        fputcsv($out, [
            (string) ($row['visitor_name'] ?? ''),
            (string) ($row['purpose'] ?? ''),
            (string) ($row['contact_no'] ?? ''),
            $timeIn,
            $timeOut !== '' ? $timeOut : '-',
            $status,
        ]);
    }

    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT Visitor Reports</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@500&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        body {
            background: linear-gradient(to bottom, rgba(255,255,255,0.15) 0%, rgba(0,0,0,0.15) 100%), radial-gradient(at top center, rgba(255,255,255,0.40) 0%, rgba(0,0,0,0.40) 120%) #989898;
            background-blend-mode: multiply, multiply;
            background-attachment: fixed;
            background-repeat: no-repeat;
            background-size: cover;
            background: linear-gradient(180deg, #fff4e6 0%, #ffe3c2 100%) !important;
        }
        .main { min-height: calc(100vh - 56px); padding: 20px 0; display: flex; justify-content: center; }
        .report-container { width: 92%; background: rgba(255,255,255,0.86); border-radius: 18px; padding: 20px; }
        .summary-card { border-radius: 10px; padding: 14px; color: #fff; margin-bottom: 12px; }
        .sum-total { background: #2c3e50; }
        .sum-in { background: #218838; }
        .sum-out { background: #dc3545; }
        .table-wrap { background: #fff; border-radius: 10px; padding: 12px; }
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
                <li class="nav-item"><a class="nav-link" href="./index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="./masterlist.php">List of Students</a></li>
                <li class="nav-item"><a class="nav-link" href="./visitorlist.php">List of Visitors</a></li>
                <li class="nav-item active"><a class="nav-link" href="./reports.php">Reports <span class="sr-only">(current)</span></a></li>
                <li class="nav-item"><a class="nav-link" href="./audit-logs.php">Audit Logs</a></li>
                <li class="nav-item"><a class="nav-link" href="./schedules.php">Schedule & Rooms</a></li>
                <li class="nav-item"><a class="nav-link" href="./visitor-destination.php">Visitor Destination</a></li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item mr-3"><a class="nav-link" href="./settings.php">Admin Settings</a></li>
                <li class="nav-item mr-3"><a class="nav-link" href="./logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="main">
        <div class="report-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Visitor Attendance Report</h4>
                <a class="btn btn-outline-dark btn-sm" href="./student-reports.php">Go to Student Report</a>
            </div>

            <form method="GET" action="./visitor-reports.php" class="mb-3">
                <div class="form-row align-items-end">
                    <div class="col-md-2 mb-2">
                        <label for="startDate">Start Date</label>
                        <input type="date" class="form-control" id="startDate" name="start_date" value="<?= h($hasStartDate ? $startDate : '') ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label for="endDate">End Date</label>
                        <input type="date" class="form-control" id="endDate" name="end_date" value="<?= h($hasEndDate ? $endDate : '') ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label for="startTime">Start Time</label>
                        <input type="time" class="form-control" id="startTime" name="start_time" value="<?= h($hasStartTime ? $startTime : '') ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label for="endTime">End Time</label>
                        <input type="time" class="form-control" id="endTime" name="end_time" value="<?= h($hasEndTime ? $endTime : '') ?>">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="name">Visitor Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= h($nameFilter) ?>" placeholder="e.g. Visitor One">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="purpose">Purpose</label>
                        <select class="form-control" id="purpose" name="purpose">
                            <option value="">All</option>
                            <?php foreach ($visitorPurposeOptions as $purposeOption): ?>
                                <option value="<?= h((string) $purposeOption) ?>" <?= $purposeFilter === (string) $purposeOption ? 'selected' : '' ?>>
                                    <?= h((string) $purposeOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2 d-flex">
                        <button type="submit" class="btn btn-dark mr-2">Apply</button>
                        <button type="button" class="btn btn-info ml-2" id="exportVisitorPdf">PDF</button>
                    </div>
                </div>
            </form>

            <div class="row mb-2">
                <div class="col-md-4"><div class="summary-card sum-total"><div>Total Logs</div><h5 class="mb-0"><?= (int) $totalCount ?></h5></div></div>
                <div class="col-md-4"><div class="summary-card sum-in"><div>Currently IN</div><h5 class="mb-0"><?= (int) $inCount ?></h5></div></div>
                <div class="col-md-4"><div class="summary-card sum-out"><div>Timed OUT</div><h5 class="mb-0"><?= (int) $outCount ?></h5></div></div>
            </div>

            <div class="table-wrap table-responsive">
                <table class="table table-sm table-bordered text-center">
                    <thead class="thead-dark">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Contact</th>
                            <th>Time In (PH)</th>
                            <th>Time Out (PH)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) === 0): ?>
                            <tr><td colspan="7" class="text-muted">No visitor records found for selected dates.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $idx => $row): ?>
                                <?php
                                    $timeIn = format_datetime_ph((string) ($row['time_in'] ?? ''));
                                    $timeOutRaw = (string) ($row['time_out'] ?? '');
                                    $timeOut = format_datetime_ph($timeOutRaw);
                                    $status = $timeOutRaw !== '' ? 'OUT' : 'IN';
                                ?>
                                <tr>
                                    <td><?= (int) ($idx + 1) ?></td>
                                    <td><?= h((string) ($row['visitor_name'] ?? '')) ?></td>
                                    <td><?= h((string) ($row['purpose'] ?? '')) ?></td>
                                    <td><?= h((string) ($row['contact_no'] ?? '')) ?></td>
                                    <td><?= h($timeIn) ?></td>
                                    <td><?= h($timeOut !== '' ? $timeOut : '-') ?></td>
                                    <td><?= $status === 'OUT' ? '<span class="badge badge-danger">OUT</span>' : '<span class="badge badge-success">IN</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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
<script>
(() => {
    const exportBtn = document.getElementById('exportVisitorPdf');
    if (!exportBtn) return;

    exportBtn.addEventListener('click', function () {
        const table = document.querySelector('.table-wrap table');
        if (!table) {
            alert('No visitor report table found to export.');
            return;
        }

        const popup = window.open('', '_blank', 'width=1100,height=800');
        if (!popup) {
            alert('Please allow pop-ups to export the PDF.');
            return;
        }

        const dateLabel = `${<?= json_encode($hasStartDate ? $startDate : 'All') ?>} to ${<?= json_encode($hasEndDate ? $endDate : 'All') ?>}`;
        const timeLabel = `${<?= json_encode($hasStartTime ? $startTime : 'All') ?>} to ${<?= json_encode($hasEndTime ? $endTime : 'All') ?>}`;
        const nameLabel = <?= json_encode($nameFilter !== '' ? $nameFilter : 'All') ?>;
        const purposeLabel = <?= json_encode($purposeFilter !== '' ? $purposeFilter : 'All') ?>;
        const generatedLabel = <?= json_encode(format_datetime_ph(date('Y-m-d H:i:s'))) ?>;
        const totalCount = <?= (int) $totalCount ?>;
        const inCount = <?= (int) $inCount ?>;
        const outCount = <?= (int) $outCount ?>;

        const html = `<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Visitor Attendance Report PDF</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
        h2 { margin: 0 0 6px 0; }
        .meta { margin-bottom: 12px; font-size: 14px; }
        .summary { margin: 10px 0 14px 0; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #444; padding: 6px; text-align: center; }
        th { background: #e9ecef; }
        td:nth-child(2), td:nth-child(3) { text-align: left; }
        .site-footer { margin-top: 24px; }
        .site-footer .footer-inner { display: flex; align-items: center; justify-content: center; gap: 10px; color: #4a4a4a; font-size: 14px; }
        @page { size: auto; margin: 10mm; }
    </style>
</head>
<body>
    <h2>QRYPT - Visitor Attendance Report</h2>
    <div class="meta">
        Date Filter: ${dateLabel}<br>
        Time Filter: ${timeLabel}<br>
        Name Filter: ${nameLabel} | Purpose Filter: ${purposeLabel}<br>
        Generated: ${generatedLabel}
    </div>
    <div class="summary">
        Total Logs: <strong>${totalCount}</strong> |
        IN: <strong>${inCount}</strong> |
        OUT: <strong>${outCount}</strong>
    </div>
    ${table.outerHTML}
    <footer class="site-footer">
        <div class="footer-inner">
            <img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 2em; width: auto;">
            <span>Copyright 2026. TIF-TCI</span>
        </div>
    </footer>
</body>
</html>`;

        popup.document.open();
        popup.document.write(html);
        popup.document.close();
        popup.focus();
        popup.onload = function () {
            popup.print();
        };
    });
})();
</script>
<footer class="site-footer">
    <div class="footer-inner">
        <img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 2em; width: auto;">
        <span>Copyright 2026. TIF-TCI</span>
    </div>
</footer></body>
</html>








