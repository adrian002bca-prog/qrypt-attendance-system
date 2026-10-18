<?php
include('./conn/bootstrap.php');
require_login();
include('./conn/conn.php');
include('./conn/visitor_table.php');
include('./conn/attendance_tables.php');
include('./conn/auto_close.php');

ensure_visitor_table($conn);
ensure_attendance_tables($conn);
ensure_daily_auto_close($conn);

$archivedVisitorDestinationsStmt = $conn->prepare('SELECT
    vd.tbl_visitor_destination_id,
    v.visitor_name,
    v.purpose,
    vd.destination_name,
    vd.recorded_at,
    vd.archived_at,
    va.time_in,
    va.time_out
    FROM tbl_visitor_destination vd
    INNER JOIN tbl_visitor_attendance va ON va.tbl_visitor_attendance_id = vd.tbl_visitor_attendance_id
    INNER JOIN tbl_visitor v ON v.tbl_visitor_id = vd.tbl_visitor_id
    WHERE vd.is_archived = 1
    ORDER BY vd.archived_at DESC, vd.tbl_visitor_destination_id DESC');
$archivedVisitorDestinationsStmt->execute();
$archivedVisitorDestinations = $archivedVisitorDestinationsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT | Visitor Destination Archive</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@500&display=swap');

        * {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(180deg, #fff4e6 0%, #ffe3c2 100%) !important;
        }

        .main {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: calc(100vh - 56px);
            padding: 20px 0;
        }

        .page-shell {
            width: 92%;
            border-radius: 20px;
            padding: 24px;
            background-color: rgba(255, 255, 255, 0.84);
        }

        .records-card {
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
            border-radius: 12px;
            background: #fff;
            padding: 24px;
        }

        .records-card .table-responsive {
            max-height: calc(100vh - 220px);
            overflow: auto;
        }

        .status-pill {
            white-space: nowrap;
        }

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

        @media (max-width: 991.98px) {
            .page-shell {
                width: 96%;
                padding: 14px;
            }

            .records-card .table-responsive {
                max-height: none;
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
            width: min(1200px, 92%);
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg js-hide-on-scroll navbar-light" style="background-color: #ffd8a8;">
        <a class="navbar-brand ml-4 d-flex align-items-center" href="#"><img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 5em; width: auto; margin-right: 8px;"><span>QRYPT</span></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="./index.php">Home</a>
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
                <li class="nav-item active">
                    <a class="nav-link" href="./visitor-destination-trash.php">Archive <span class="sr-only">(current)</span></a>
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
        <div class="page-shell">
            <div class="records-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                    <h4 class="mb-2">Visitor Destination Archive</h4>
                    <small class="text-muted mb-2">Archived records stay here for reference.</small>
                </div>
                <div class="table-responsive">
	                    <table class="table table-sm table-hover text-center">
	                        <thead class="thead-dark">
	                            <tr>
	                                <th>Visitor</th>
	                                <th>Purpose</th>
	                                <th>Destination</th>
	                                <th>Time In</th>
	                                <th>Recorded At</th>
	                                <th>Archived At</th>
	                                <th>Status</th>
	                                <th>Action</th>
	                            </tr>
	                        </thead>
	                        <tbody>
	                            <?php if (count($archivedVisitorDestinations) === 0): ?>
	                                <tr>
	                                    <td colspan="8" class="text-muted">No archived visitor destination records yet.</td>
	                                </tr>
	                            <?php else: ?>
	                                <?php foreach ($archivedVisitorDestinations as $row): ?>
                                    <?php
                                    $status = !empty($row['time_out']) ? 'OUT' : 'IN';
                                    $statusBadge = $status === 'IN' ? 'success' : 'secondary';
                                    ?>
                                    <tr>
                                        <td><?= h((string) ($row['visitor_name'] ?? '')) ?></td>
                                        <td><?= h((string) ($row['purpose'] ?? '')) ?></td>
                                        <td><?= h((string) ($row['destination_name'] ?? '')) ?></td>
                                        <td><?= h(format_datetime_ph((string) ($row['time_in'] ?? ''))) ?></td>
	                                        <td><?= h(format_datetime_ph((string) ($row['recorded_at'] ?? ''))) ?></td>
	                                        <td><?= h(format_datetime_ph((string) ($row['archived_at'] ?? ''))) ?></td>
	                                        <td><span class="badge badge-<?= h($statusBadge) ?> status-pill"><?= h($status) ?></span></td>
	                                        <td>
	                                            <div class="d-flex justify-content-center align-items-center" style="gap: 6px;">
	                                                <form action="./endpoint/restore-visitor-destination.php" method="POST" class="m-0">
	                                                    <input type="hidden" name="visitor_destination_id" value="<?= h((string) $row['tbl_visitor_destination_id']) ?>">
	                                                    <input type="hidden" name="redirect_path" value="visitor-destination-trash.php">
	                                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
	                                                    <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
	                                                </form>
	                                                <form action="./endpoint/delete-visitor-destination.php" method="POST" class="m-0" onsubmit="return confirm('Delete this archived visitor destination record permanently?');">
	                                                    <input type="hidden" name="visitor_destination_id" value="<?= h((string) $row['tbl_visitor_destination_id']) ?>">
	                                                    <input type="hidden" name="redirect_path" value="visitor-destination-trash.php">
	                                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
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

    <footer class="site-footer">
        <div class="footer-inner">
            <img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 2em; width: auto;">
            <span>Copyright 2026. TIF-TCI</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>

