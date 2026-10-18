<?php
include('./conn/bootstrap.php');
require_login();
include('./conn/conn.php');
include('./conn/visitor_table.php');
include('./conn/attendance_tables.php');
include('./conn/auto_close.php');
require_once('./conn/system_settings.php');

ensure_visitor_table($conn);
ensure_attendance_tables($conn);
ensure_daily_auto_close($conn);
ensure_system_settings_table($conn);
$scanSoundEnabled = get_system_setting_int($conn, 'scan_sound_enabled', 1) === 1;
$visitorPurposeOptions = get_visitor_purpose_options($conn);
$hasPurposeSnapshot = column_exists($conn, 'tbl_visitor_destination', 'purpose_snapshot');
$premadeVisitorQrCode = get_premade_visitor_qr_code($conn);
if ($hasPurposeSnapshot) {
    $repairPremadeSnapshotStmt = $conn->prepare('UPDATE tbl_visitor_destination vd
        INNER JOIN tbl_visitor v ON v.tbl_visitor_id = vd.tbl_visitor_id
        SET vd.purpose_snapshot = vd.destination_name
        WHERE v.generated_code = :premade_code
        AND TRIM(COALESCE(vd.destination_name, "")) <> ""
        AND (
            TRIM(COALESCE(vd.purpose_snapshot, "")) = ""
            OR LOWER(TRIM(vd.purpose_snapshot)) <> LOWER(TRIM(vd.destination_name))
        )');
    $repairPremadeSnapshotStmt->bindValue(':premade_code', $premadeVisitorQrCode, PDO::PARAM_STR);
    $repairPremadeSnapshotStmt->execute();
}
$purposeSelectExpr = $hasPurposeSnapshot
    ? 'CASE WHEN TRIM(COALESCE(vd.purpose_snapshot, "")) = "" THEN "(No purpose saved)" ELSE vd.purpose_snapshot END'
    : 'v.purpose';

$recentVisitorDestinationsStmt = $conn->prepare('SELECT
    vd.tbl_visitor_destination_id,
    CASE
        WHEN TRIM(COALESCE(v.visitor_name, "")) = "" THEN "Premade QR Visitor"
        ELSE v.visitor_name
    END AS visitor_name_display,
    ' . $purposeSelectExpr . ' AS purpose,
    vd.destination_name,
    vd.recorded_at,
    va.time_in,
    va.time_out
    FROM tbl_visitor_destination vd
    INNER JOIN tbl_visitor_attendance va ON va.tbl_visitor_attendance_id = vd.tbl_visitor_attendance_id
    INNER JOIN tbl_visitor v ON v.tbl_visitor_id = vd.tbl_visitor_id
    WHERE vd.is_archived = 0
    ORDER BY vd.recorded_at DESC, vd.tbl_visitor_destination_id DESC');
$recentVisitorDestinationsStmt->execute();
$recentVisitorDestinations = $recentVisitorDestinationsStmt->fetchAll();

$archivedVisitorDestinationsStmt = $conn->prepare('SELECT
    vd.tbl_visitor_destination_id,
    CASE
        WHEN TRIM(COALESCE(v.visitor_name, "")) = "" THEN "Premade QR Visitor"
        ELSE v.visitor_name
    END AS visitor_name_display,
    ' . $purposeSelectExpr . ' AS purpose,
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

$currentRecordView = trim((string) ($_GET['view'] ?? 'records'));
if ($currentRecordView !== 'archive') {
    $currentRecordView = 'records';
}

$lastRecordedQr = trim((string) ($_GET['last_qr'] ?? ''));
if (strlen($lastRecordedQr) > 255) {
    $lastRecordedQr = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT | Visitor Destination</title>
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
        @media print {
            .no-print, .navbar, .site-footer { display: none !important; }
            body { background: #fff !important; }
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area { position: absolute; left: 0; top: 0; width: 100%; }
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

        .scanner-card,
        .records-card {
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
            border-radius: 12px;
            background: #fff;
            padding: 24px;
            height: 100%;
        }

        .records-card {
            overflow: hidden;
        }

        .records-card .table-responsive {
            max-height: calc(100vh - 250px);
            overflow: auto;
        }

        .hint-box {
            background: #fff7ec;
            border: 1px solid #f1d1a2;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }

        .hint-box p:last-child {
            margin-bottom: 0;
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

        .qr-detected-container {
            display: none;
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

        .records-view-switch {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .records-view-switch a {
            text-decoration: none;
            color: #495057;
            border: 1px solid #ced4da;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 14px;
        }

        .records-view-switch a.active {
            background: #343a40;
            border-color: #343a40;
            color: #fff;
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
                <li class="nav-item active">
                    <a class="nav-link" href="./visitor-destination.php">Visitor Destination <span class="sr-only">(current)</span></a>
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
        <div class="page-shell print-area">
            <div class="row">
                <div class="col-lg-4 mb-3">
                    <div class="scanner-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0">Visitor Destination</h4>
                        </div>
                        <div class="hint-box">
                            <p class="mb-1"><strong>How it works</strong></p>
                            <p class="mb-1">1. Type the exact destination or office.</p>
                            <p class="mb-1">2. Scan the visitor QR code.</p>
                            <p>3. The destination record is saved automatically.</p>
                        </div>

                        <div class="scanner-con">
                            <div class="form-group">
                                <label for="destinationName"><strong>Destination / Section</strong></label>
                                <select class="form-control" id="destinationName" required>
                                    <option value="">Select destination...</option>
                                    <?php foreach ($visitorPurposeOptions as $purposeOption): ?>
                                        <option value="<?= h($purposeOption) ?>"><?= h($purposeOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Choose the destination, then scan the visitor QR code.</small>
                            </div>

                            <p class="mb-2"><small id="cameraStatus" class="text-muted">Camera status: Idle</small></p>
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

                        <div class="qr-detected-container">
                            <form id="destinationScanForm" action="./endpoint/add-attendance.php" method="POST">
                                <h5 class="text-center mb-3" id="detectedLabel">Saving visitor destination...</h5>
                                <input type="hidden" id="detected-qr-code" name="qr_code">
                                <input type="hidden" name="person_type" value="visitor">
                                <input type="hidden" id="destination-name-hidden" name="destination_name" value="">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <button type="submit" class="btn btn-dark form-control">Submit</button>
                            </form>
                            <button type="button" class="btn btn-link form-control mt-2" onclick="resetScannerUI()">Scan Again</button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 mb-3">
                    <div class="records-card">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                            <h4 class="mb-2">Visitor Destination</h4>
                            <small class="text-muted mb-2">
                                <?= $currentRecordView === 'archive' ? 'Archived records stay here for reference.' : 'Newest records appear at the top.' ?>
                            </small>
                        </div>
                        <div class="records-view-switch no-print">
                            <a href="./visitor-destination.php" class="<?= $currentRecordView === 'records' ? 'active' : '' ?>">Records</a>
                            <a href="./visitor-destination.php?view=archive" class="<?= $currentRecordView === 'archive' ? 'active' : '' ?>">Archive</a>
                        </div>
                        <div class="table-responsive">
                            <?php if ($currentRecordView === 'archive'): ?>
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
                                            <th class="no-print">Action</th>
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
                                                $archivedStatus = !empty($row['time_out']) ? 'OUT' : 'IN';
                                                $archivedStatusBadge = $archivedStatus === 'IN' ? 'success' : 'secondary';
                                                ?>
                                                <tr>
                                                    <td><?= h((string) ($row['visitor_name_display'] ?? '')) ?></td>
                                                    <td><?= h((string) ($row['purpose'] ?? '')) ?></td>
                                                    <td><?= h((string) ($row['destination_name'] ?? '')) ?></td>
                                                    <td><?= h(format_datetime_ph((string) ($row['time_in'] ?? ''))) ?></td>
                                                    <td><?= h(format_datetime_ph((string) ($row['recorded_at'] ?? ''))) ?></td>
                                                    <td><?= h(format_datetime_ph((string) ($row['archived_at'] ?? ''))) ?></td>
                                                    <td><span class="badge badge-<?= h($archivedStatusBadge) ?> status-pill"><?= h($archivedStatus) ?></span></td>
                                                    <td class="no-print">
                                                        <div class="d-flex justify-content-center align-items-center" style="gap: 6px;">
                                                            <form action="./endpoint/restore-visitor-destination.php" method="POST" class="m-0">
                                                                <input type="hidden" name="visitor_destination_id" value="<?= h((string) $row['tbl_visitor_destination_id']) ?>">
                                                                <input type="hidden" name="redirect_path" value="visitor-destination.php?view=archive">
                                                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                                            </form>
                                                            <form action="./endpoint/delete-visitor-destination.php" method="POST" class="m-0" onsubmit="return confirm('Delete this archived visitor destination record permanently?');">
                                                                <input type="hidden" name="visitor_destination_id" value="<?= h((string) $row['tbl_visitor_destination_id']) ?>">
                                                                <input type="hidden" name="redirect_path" value="visitor-destination.php?view=archive">
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
                            <?php else: ?>
                                <table class="table table-sm table-hover text-center">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Visitor</th>
                                            <th>Purpose</th>
                                            <th>Destination</th>
                                            <th>Time In</th>
                                            <th>Recorded At</th>
                                            <th>Status</th>
                                            <th class="no-print">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($recentVisitorDestinations) === 0): ?>
                                            <tr>
                                                <td colspan="7" class="text-muted">No visitor destination records yet.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentVisitorDestinations as $row): ?>
                                                <?php
                                                $status = !empty($row['time_out']) ? 'OUT' : 'IN';
                                                $statusBadge = $status === 'IN' ? 'success' : 'secondary';
                                                ?>
                                                <tr>
                                                    <td><?= h((string) ($row['visitor_name_display'] ?? '')) ?></td>
                                                    <td><?= h((string) ($row['purpose'] ?? '')) ?></td>
                                                    <td><?= h((string) ($row['destination_name'] ?? '')) ?></td>
                                                    <td><?= h(format_datetime_ph((string) ($row['time_in'] ?? ''))) ?></td>
                                                    <td><?= h(format_datetime_ph((string) ($row['recorded_at'] ?? ''))) ?></td>
                                                    <td><span class="badge badge-<?= h($statusBadge) ?> status-pill"><?= h($status) ?></span></td>
                                                    <td class="no-print">
                                                        <form action="./endpoint/archive-visitor-destination.php" method="POST" class="m-0" onsubmit="return confirm('Archive this visitor destination record?');">
                                                            <input type="hidden" name="visitor_destination_id" value="<?= h((string) $row['tbl_visitor_destination_id']) ?>">
                                                            <input type="hidden" name="redirect_page" value="visitor-destination.php">
                                                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Archive</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
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
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const initialLastRecordedQr = <?= json_encode($lastRecordedQr) ?>;
        const scanSoundEnabled = <?= $scanSoundEnabled ? 'true' : 'false' ?>;
        let scanner = null;
        let scannerRunning = false;
        let cameraEnabled = true;
        let missingDestinationShown = false;
        const lastRecordedQrKey = 'visitor_destination_last_recorded_qr';
        const cameraPreferenceKey = 'visitor_destination_camera_enabled';
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

        function getLastRecordedQr() {
            try {
                return sessionStorage.getItem(lastRecordedQrKey) || '';
            } catch (e) {
                return '';
            }
        }

        function setLastRecordedQr(qrCode) {
            try {
                if (typeof qrCode === 'string' && qrCode.trim() !== '') {
                    sessionStorage.setItem(lastRecordedQrKey, qrCode.trim());
                }
            } catch (e) {}
        }

        function setCameraStatus(message, tone = 'muted') {
            const statusEl = document.getElementById('cameraStatus');
            if (!statusEl) {
                return;
            }
            statusEl.className = 'text-' + tone;
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

        function stopScanner(statusMessage = 'Stopped', statusTone = 'warning') {
            if (!scanner || !scannerRunning) {
                setCameraStatus(statusMessage, statusTone);
                return Promise.resolve();
            }

            return scanner.stop().then(function () {
                scannerRunning = false;
                setCameraStatus(statusMessage, statusTone);
            }).catch(function () {
                scannerRunning = false;
                setCameraStatus(statusMessage + ' (with warning)', statusTone);
            });
        }

        function resetScannerUI() {
            document.querySelector('.qr-detected-container').style.display = 'none';
            document.querySelector('.scanner-con').style.display = '';
            lastScannedCode = '';
            lastScannedAt = 0;
            document.getElementById('destination-name-hidden').value = '';
            if (scanner && cameraEnabled) {
                startScanner(true);
            } else if (!cameraEnabled) {
                setCameraStatus('Closed', 'danger');
            }
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

                    const destinationValue = document.getElementById('destinationName').value.trim();
                    if (destinationValue === '') {
                        const lastRecordedQr = getLastRecordedQr();
                        if (lastRecordedQr !== '' && lastRecordedQr === normalizedText) {
                            setCameraStatus('This QR code is already recorded. Please scan another QR code', 'warning');
                            missingDestinationShown = true;
                            return;
                        }

                        if (!missingDestinationShown) {
                            setCameraStatus('Enter the destination first before scanning', 'warning');
                            missingDestinationShown = true;
                        }
                        return;
                    }
                    missingDestinationShown = false;

                    playScanTone('success');
                    showScanPopup('Scan successful');
                    document.getElementById('detected-qr-code').value = normalizedText;
                    document.getElementById('destination-name-hidden').value = destinationValue;
                    setLastRecordedQr(normalizedText);

                    stopScanner('Saving destination...', 'warning');
                    document.querySelector('.scanner-con').style.display = 'none';
                    document.querySelector('.qr-detected-container').style.display = '';
                    setTimeout(function () {
                        document.getElementById('destinationScanForm').submit();
                    }, 350);
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
                }).catch(function () {
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
            stopScanner('Closed', 'danger');
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
            if (typeof initialLastRecordedQr === 'string' && initialLastRecordedQr.trim() !== '') {
                setLastRecordedQr(initialLastRecordedQr);
            }
            loadCameraPreference();
            const destinationInput = document.getElementById('destinationName');
            if (destinationInput) {
                const onDestinationSelected = function () {
                    if (destinationInput.value.trim() !== '') {
                        missingDestinationShown = false;
                        if (cameraEnabled) {
                            setCameraStatus(scannerRunning ? 'Running' : 'Ready', 'success');
                        }
                    }
                };
                destinationInput.addEventListener('change', onDestinationSelected);
                destinationInput.addEventListener('input', onDestinationSelected);
            }
            updateCameraButtons();
            if (cameraEnabled) {
                openCamera();
            } else {
                setCameraStatus('Closed', 'danger');
            }

        });

        window.addEventListener('beforeunload', function () {
            if (scannerRunning) {
                scanner.stop();
            }
        });
    </script>
</body>
</html>

