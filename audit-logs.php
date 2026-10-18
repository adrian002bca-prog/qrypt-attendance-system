<?php
include('./conn/bootstrap.php');
require_login();
include('./conn/conn.php');
include('./conn/audit_log.php');

ensure_audit_log_table($conn);

$startDate = trim((string) ($_GET['start_date'] ?? ''));
$endDate = trim((string) ($_GET['end_date'] ?? ''));
$usernameFilter = trim((string) ($_GET['username'] ?? ''));
$actionFilter = trim((string) ($_GET['action'] ?? ''));
$entityFilter = trim((string) ($_GET['entity_type'] ?? ''));
$detailsFilter = trim((string) ($_GET['details'] ?? ''));
$export = '';

$hasStartDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) === 1;
$hasEndDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) === 1;

if ($hasStartDate && $hasEndDate && $startDate > $endDate) {
    $tmp = $startDate;
    $startDate = $endDate;
    $endDate = $tmp;
}

$sql = 'SELECT tbl_audit_log_id, username, action, entity_type, details, ip_address, created_at FROM tbl_audit_log';
$conditions = [];
$params = [];

if ($hasStartDate) {
    $conditions[] = 'created_at >= :start_dt';
    $params[':start_dt'] = $startDate . ' 00:00:00';
}
if ($hasEndDate) {
    $conditions[] = 'created_at <= :end_dt';
    $params[':end_dt'] = $endDate . ' 23:59:59';
}
if ($usernameFilter !== '') {
    $conditions[] = "username LIKE :username ESCAPE '\\\\'";
    $params[':username'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $usernameFilter) . '%';
}
if ($actionFilter !== '') {
    $conditions[] = "action LIKE :action ESCAPE '\\\\'";
    $params[':action'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $actionFilter) . '%';
}
if ($entityFilter !== '') {
    $conditions[] = "entity_type LIKE :entity_type ESCAPE '\\\\'";
    $params[':entity_type'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $entityFilter) . '%';
}
if ($detailsFilter !== '') {
    $conditions[] = "details LIKE :details ESCAPE '\\\\'";
    $params[':details'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $detailsFilter) . '%';
}

if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY tbl_audit_log_id DESC LIMIT 1000';

$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT Audit Logs</title>
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
    @media print {
        .no-print, .navbar, .site-footer { display: none !important; }
        body { background: #fff !important; }
        body * { visibility: hidden; }
        .print-area, .print-area * { visibility: visible; }
        .print-area { position: absolute; left: 0; top: 0; width: 100%; }
    }
        .main { min-height: calc(100vh - 56px); padding: 20px 0; display: flex; justify-content: center; }
        .wrap { width: 94%; background: rgba(255,255,255,0.9); border-radius: 16px; padding: 18px; }
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
                <li class="nav-item"><a class="nav-link" href="./reports.php">Reports</a></li>
                <li class="nav-item active"><a class="nav-link" href="./audit-logs.php">Audit Logs <span class="sr-only">(current)</span></a></li>
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
        <div class="wrap">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Audit Logs</h4>
                <button class="btn btn-outline-secondary btn-sm no-print" id="exportAuditPdf">Export PDF</button>
            </div>
            <form method="GET" action="./audit-logs.php" class="mb-3">
                <div class="form-row align-items-end">
                    <div class="col-md-2 mb-2">
                        <label>Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?= h($hasStartDate ? $startDate : '') ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label>End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?= h($hasEndDate ? $endDate : '') ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label>Username</label>
                        <input type="text" class="form-control" name="username" value="<?= h($usernameFilter) ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label>Action</label>
                        <input type="text" class="form-control" name="action" value="<?= h($actionFilter) ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label>Entity Type</label>
                        <input type="text" class="form-control" name="entity_type" value="<?= h($entityFilter) ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label>Details</label>
                        <input type="text" class="form-control" name="details" value="<?= h($detailsFilter) ?>">
                    </div>
                    <div class="col-12 mb-2 d-flex">
                        <button type="submit" class="btn btn-dark">Apply</button>
                    </div>
                </div>
            </form>

            <div class="table-wrap table-responsive print-area">
                <table class="table table-sm table-bordered text-center">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>Date/Time (PH)</th>
                            <th>Username</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) === 0): ?>
                            <tr><td colspan="7" class="text-muted">No audit logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= (int) ($row['tbl_audit_log_id'] ?? 0) ?></td>
                                    <td><?= h(format_datetime_ph((string) ($row['created_at'] ?? ''))) ?></td>
                                    <td><?= h((string) ($row['username'] ?? '')) ?></td>
                                    <td><?= h((string) ($row['action'] ?? '')) ?></td>
                                    <td><?= h((string) ($row['entity_type'] ?? '')) ?></td>
                                    <td class="text-left"><?= h((string) ($row['details'] ?? '')) ?></td>
                                    <td><?= h((string) ($row['ip_address'] ?? '')) ?></td>
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

(function () {
    const btn = document.getElementById('exportAuditPdf');
    if (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    }
})();
</script><footer class="site-footer">
    <div class="footer-inner">
        <img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 2em; width: auto;">
        <span>Copyright 2026. TIF-TCI</span>
    </div>
</footer></body>
</html>








