<?php
include('./conn/bootstrap.php');
include('./conn/conn.php');
include('./conn/visitor_table.php');
require_once('./conn/system_settings.php');
enable_kiosk_mode();
ensure_visitor_table($conn);
ensure_system_settings_table($conn);
$scanSoundEnabled = get_system_setting_int($conn, 'scan_sound_enabled', 1) === 1;
$visitorPurposeOptions = get_visitor_purpose_options($conn);
$premadeVisitorQrCode = get_premade_visitor_qr_code($conn);
ensure_premade_visitor_profile($conn);
$premadeVisitorQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode($premadeVisitorQrCode);
$premadeNotice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_premade_visitor_profile'])) {
    verify_csrf_or_reject();
    $isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $profileName = '';
    $profileContact = '';
    $profilePurpose = trim((string) ($_POST['premade_visitor_purpose'] ?? ''));

    if (!in_array($profilePurpose, $visitorPurposeOptions, true)) {
        $_SESSION['premade_visitor_notice'] = 'Please select a valid purpose option.';
        if ($isAjaxRequest) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => (string) $_SESSION['premade_visitor_notice']]);
            exit();
        }
    } else {
        $updateStmt = $conn->prepare('UPDATE tbl_visitor SET visitor_name = :visitor_name, purpose = :purpose, contact_no = :contact_no, qr_used = 0 WHERE generated_code = :generated_code');
        $updateStmt->bindValue(':visitor_name', $profileName, PDO::PARAM_STR);
        $updateStmt->bindValue(':purpose', $profilePurpose, PDO::PARAM_STR);
        $updateStmt->bindValue(':contact_no', $profileContact, PDO::PARAM_STR);
        $updateStmt->bindValue(':generated_code', $premadeVisitorQrCode, PDO::PARAM_STR);
        $updateStmt->execute();
        $_SESSION['premade_visitor_notice'] = 'Premade visitor purpose updated. Name and contact stay blank.';
        if ($isAjaxRequest) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit();
        }
    }

    redirect_to('user.php');
}

$premadeVisitorName = '';
$premadeVisitorContact = '';
$premadeVisitorPurpose = isset($visitorPurposeOptions[0]) ? (string) $visitorPurposeOptions[0] : 'Clinic';
$profileStmt = $conn->prepare('SELECT visitor_name, purpose, contact_no FROM tbl_visitor WHERE generated_code = :generated_code LIMIT 1');
$profileStmt->bindValue(':generated_code', $premadeVisitorQrCode, PDO::PARAM_STR);
$profileStmt->execute();
$profileRow = $profileStmt->fetch();
if (is_array($profileRow)) {
    $premadeVisitorName = trim((string) ($profileRow['visitor_name'] ?? $premadeVisitorName));
    $premadeVisitorContact = trim((string) ($profileRow['contact_no'] ?? $premadeVisitorContact));
    $premadeVisitorPurpose = trim((string) ($profileRow['purpose'] ?? $premadeVisitorPurpose));
}

if (isset($_SESSION['premade_visitor_notice'])) {
    $premadeNotice = (string) $_SESSION['premade_visitor_notice'];
    unset($_SESSION['premade_visitor_notice']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT | User Panel</title>
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
            background-color: rgba(255, 255, 255, 0.85);
        }

        .attendance-container > .qr-container {
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
            border-radius: 10px;
            padding: 30px;
            height: auto;
        }

        .scan-type-switch {
            display: grid;
            grid-template-columns: repeat(2, minmax(140px, 1fr));
            gap: 14px;
            margin: 10px 0 16px;
        }

        .scan-type-switch .kiosk-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 18px 10px;
            border-radius: 14px;
            border: 2px solid transparent;
            background: #fff6e6;
            color: #1f1f1f;
            font-weight: 600;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .scan-type-switch .kiosk-card.active {
            border-color: #1f7a5b;
            box-shadow: 0 10px 24px rgba(0,0,0,0.18);
            transform: translateY(-1px);
            background: #e9f8f1;
        }

        .scan-type-switch .kiosk-card .kiosk-sub {
            font-size: 12px;
            font-weight: 500;
            text-transform: none;
            color: #5c5c5c;
        }

        .tutorial-modal .modal-header {
            background: #fff4e6;
            border-bottom: 1px solid #e7caa5;
        }

        .tutorial-modal .modal-title {
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .tutorial-modal .tutorial-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            align-items: start;
        }

        .tutorial-modal .tutorial-card {
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 12px 14px;
            background: #fff;
        }

        .tutorial-modal .tutorial-card h6 {
            font-weight: 700;
            margin-bottom: 8px;
        }

        .tutorial-modal ol {
            padding-left: 18px;
            margin-bottom: 0;
        }

        .tutorial-modal .tip {
            font-size: 12px;
            color: #6c757d;
            margin-top: 8px;
        }

        @media (max-width: 991px) {
            .tutorial-modal .tutorial-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575px) {
            .tutorial-modal .tutorial-grid {
                grid-template-columns: 1fr;
            }
        }

        .site-footer {
            margin-top: 24px;
            padding: 12px 0 18px;
            background-color: #ffd8a8;
        }

        .site-footer .footer-inner {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #4a4a4a;
            font-size: 14px;
            width: min(1200px, 92%);
            margin: 0 auto;
            min-height: 32px;
        }

        .site-footer .footer-credit {
            position: absolute;
            left: -24px;
            top: 50%;
            transform: translateY(-50%);
            white-space: nowrap;
        }

        .site-footer .footer-meta {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 991px) {
            .site-footer .footer-inner {
                flex-direction: column;
                gap: 6px;
            }

            .site-footer .footer-credit {
                position: static;
                transform: none;
                white-space: normal;
                text-align: center;
            }
        }

        @media (max-width: 575px) {
            .scan-type-switch {
                grid-template-columns: 1fr;
            }
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

    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg js-hide-on-scroll navbar-light" style="background-color: #ffd8a8;">
        <a class="navbar-brand ml-4 d-flex align-items-center" href="./user.php">
            <img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 5em; width: auto; margin-right: 8px;">
            <span>QRYPT</span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarUser" aria-controls="navbarUser" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarUser">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item"><a class="nav-link" href="./user.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="./user-students.php">List of Students</a></li>
                <li class="nav-item"><a class="nav-link" href="./user-visitors.php">List of Visitors</a></li>
                <li class="nav-item"><a class="nav-link" href="#" onclick="openPremadeVisitorQr(event)">Premade Visitor QR</a></li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="openTutorial(event)">User Manual</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./login.php?prefill=1">Admin Login</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="main" id="cameraSection">
        <div class="attendance-container">
            <div class="modal fade tutorial-modal" id="tutorialModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="tutorialTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tutorialTitle">User Manual</h5>
                            <button type="button" class="close" aria-label="Close" onclick="dismissTutorial()">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="tutorial-grid">
                                <div class="tutorial-card">
                                    <h6>Student</h6>
                                    <ol>
                                        <li>Click <strong>Student</strong> in the choice cards.</li>
                                        <li>Click <strong>Open Camera</strong>.</li>
                                        <li>Hold the student QR code in front of the camera.</li>
                                        <li>When the details show, confirm the scan for Time In or Time Out.</li>
                                    </ol>
                                    <div class="tip">Tip: Keep the QR code steady for faster detection.</div>
                                </div>
                                <div class="tutorial-card">
                                    <h6>Visitor</h6>
                                    <ol>
                                        <li>Go to <strong>List of Visitors</strong> in the header.</li>
                                        <li>Click <strong>Add Visitor</strong>.</li>
                                        <li>Fill in <strong>Full Name</strong>, choose <strong>Purpose</strong>, and add <strong>Contact Number</strong>.</li>
                                        <li>Click <strong>Generate QR Code</strong>, then click <strong>Save Visitor</strong>.</li>
                                        <li>Go back to <strong>Home</strong>, choose <strong>Visitor</strong>, then scan the visitor QR code.</li>
                                    </ol>
                                    <div class="tip">Tip: Use the visitor QR only once per visit.</div>
                                </div>
                                <div class="tutorial-card">
                                    <h6>Premade Visitor QR</h6>
                                    <ol>
                                        <li>Click <strong>Premade Visitor QR</strong> in the header.</li>
                                        <li>Select the <strong>Purpose / Department</strong>, then click <strong>Save Purpose &amp; Print QR</strong>.</li>
                                        <li>The system prints a batch of <strong>10 unique</strong> QR slips for visitors without phones.</li>
                                        <li>Each unique QR is for one visitor record, so scanning one slip records one visitor.</li>
                                        <li>After time out, use <strong>List of Visitors</strong> to update the premade visitor's name and contact if needed.</li>
                                    </ol>
                                    <div class="tip">Tip: Do not photocopy one slip for many visitors. Use the generated unique slips.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="dismissTutorial()">I Understand</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="premadeVisitorQrModal" tabindex="-1" aria-labelledby="premadeVisitorQrTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="premadeVisitorQrTitle">Premade Visitor QR</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body text-center">
                            <?php if ($premadeNotice !== ''): ?>
                                <div class="alert alert-info text-left" role="alert"><?= h($premadeNotice) ?></div>
                            <?php endif; ?>
                            <form method="POST" action="./user.php" class="text-left mb-3">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="save_premade_visitor_profile" value="1">
                                <p class="small text-muted mb-3">Name and contact are intentionally blank for premade QR visitors without phones.</p>
                                <div class="form-group">
                                    <label for="premadeVisitorPurpose">Purpose / Department</label>
                                    <select class="form-control" id="premadeVisitorPurpose" name="premade_visitor_purpose" required>
                                        <?php foreach ($visitorPurposeOptions as $purposeOption): ?>
                                            <option value="<?= h($purposeOption) ?>" <?= $premadeVisitorPurpose === $purposeOption ? 'selected' : '' ?>><?= h($purposeOption) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                            <p class="text-muted small mb-0">Use this for visitors without phones. QR slips are generated as unique codes when you click print.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-dark btn-sm" onclick="printPremadeVisitorQr()">Save Purpose & Print QR</button>
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="qr-container col-lg-6 col-md-8 col-12">
                    <div class="scanner-con">
                        <h5 class="text-center mb-2">Scan QR Code</h5>
                        <p class="text-center text-muted mb-2">Type is detected automatically (Student or Visitor).</p>
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
                        <form action="./endpoint/add-attendance-user.php" method="POST">
                            <h4 class="text-center" id="detectedLabel">QR Detected (Auto Time In/Out)!</h4>
                            <input type="hidden" id="detected-qr-code" name="qr_code">
                            <input type="hidden" id="person-type" name="person_type" value="visitor">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <button type="submit" class="btn btn-dark form-control">Submit Scan</button>
                        </form>
                        <button type="button" class="btn btn-link form-control mt-2" onclick="resetScannerUI()">Scan Again</button>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="studentScanConfirmModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="studentScanConfirmTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="studentScanConfirmTitle">Confirm Student Scan</h5>
                            <button type="button" class="close" aria-label="Close" onclick="cancelStudentConfirmation()">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="d-flex align-items-center mb-3">
                                <img id="studentConfirmPhoto" src="" alt="Student Photo" style="width: 120px; height: 120px; object-fit: cover; border-radius: 6px; border: 1px solid #ced4da; display: none;">
                                <div id="studentConfirmNoPhoto" class="text-muted small">No photo available</div>
                            </div>
                            <p class="mb-1"><strong>Name:</strong> <span id="studentConfirmName">-</span></p>
                            <p class="mb-1"><strong>Student ID:</strong> <span id="studentConfirmIdNumber">-</span></p>
                            <p class="mb-3"><strong>Course & Section:</strong> <span id="studentConfirmCourse">-</span></p>

                            <form id="studentConfirmForm" action="./endpoint/add-attendance-user.php" method="POST">
                                <input type="hidden" id="studentConfirmQrCode" name="qr_code">
                                <input type="hidden" name="person_type" value="student">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <button type="submit" class="btn btn-dark btn-block" id="studentConfirmSubmitBtn">Confirm to Time-in</button>
                            </form>
                            <button type="button" class="btn btn-link btn-block mt-2" onclick="cancelStudentConfirmation()">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-meta">
                <img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 2em; width: auto;">
                <span>Copyright 2026. TIF-TCI</span>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const premadeVisitorPurpose = <?= json_encode($premadeVisitorPurpose) ?>;
        const premadeVisitorCsrfToken = <?= json_encode(csrf_token()) ?>;
        const premadeCopiesPerPrint = 10;

        async function generatePremadeBatchForPrint(purposeValue) {
            const payload = new URLSearchParams();
            payload.set('csrf_token', premadeVisitorCsrfToken);
            payload.set('purpose', purposeValue);
            payload.set('copies', String(premadeCopiesPerPrint));

            const response = await fetch('./endpoint/generate-premade-qr-batch.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: payload.toString()
            });

            if (!response.ok) {
                let message = 'Failed to generate unique premade QR batch.';
                try {
                    const data = await response.json();
                    if (data && typeof data.message === 'string' && data.message.trim() !== '') {
                        message = data.message;
                    }
                } catch (e) {}
                throw new Error(message);
            }

            const data = await response.json();
            if (!data || data.ok !== true || !Array.isArray(data.items) || data.items.length === 0) {
                throw new Error('Invalid response while generating QR batch.');
            }
            return data.items;
        }

        async function printPremadeVisitorQr() {
            const purposeSelect = document.getElementById('premadeVisitorPurpose');
            const selectedPurpose = purposeSelect ? String(purposeSelect.value || '').trim() : String(premadeVisitorPurpose || '').trim();
            if (selectedPurpose === '') {
                alert('Please select a purpose before printing.');
                return;
            }

            let batchItems = [];
            try {
                batchItems = await generatePremadeBatchForPrint(selectedPurpose);
            } catch (error) {
                alert(error && error.message ? error.message : 'Failed to generate unique premade QR batch.');
                return;
            }

            const popup = window.open('', '_blank', 'width=760,height=900');
            if (!popup) {
                alert('Please allow pop-ups to print the premade visitor QR.');
                return;
            }
            const safePurpose = String(selectedPurpose || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const slips = batchItems.map(function (item) {
                const qrUrl = String(item.qr_url || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
                return `<div class="slip">
<div class="slip-title">QRYPT Visitor QR</div>
<div class="line"><strong>Name:</strong> __________________</div>
<div class="line"><strong>Contact:</strong> _______________</div>
<div class="line"><strong>Purpose:</strong> ${safePurpose}</div>
<div class="consent-line"><span class="consent-box" aria-hidden="true"></span> I agree to the Terms and Conditions and Privacy Notice.</div>
<img src="${qrUrl}" alt="Premade Visitor QR">
</div>`;
            });
            const rowOne = slips.slice(0, 5).map(function (item) { return `<td>${item}</td>`; }).join('');
            const rowTwo = slips.slice(5, 10).map(function (item) { return `<td>${item}</td>`; }).join('');

            const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Premade Visitor QR</title>
<style>
* { box-sizing: border-box; }
html, body { width: 279.4mm; height: 215.9mm; }
body { font-family: Arial, sans-serif; margin: 0; padding: 0; color: #111; background: #fff; overflow: hidden; }
.sheet { width: 279.4mm; height: 215.9mm; border-collapse: collapse; table-layout: fixed; }
.sheet td { width: 20%; height: 50%; padding: 0; border: 1px dashed #b0b0b0; vertical-align: middle; }
.slip {
    width: 100%;
    height: 100%;
    padding: 2.2mm 2mm;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.slip-title {
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 1.2mm;
}
.line {
    text-align: left;
    font-size: 10px;
    margin: 0.8mm 0;
    width: 100%;
    max-width: 50mm;
}
.consent-line {
    width: 100%;
    max-width: 50mm;
    text-align: left;
    font-size: 8.2px;
    line-height: 1.2;
    margin-top: 1mm;
}
.consent-box {
    display: inline-block;
    width: 9px;
    height: 9px;
    border: 1px solid #111;
    vertical-align: -1px;
    margin-right: 4px;
}
img {
    width: 27mm;
    height: 27mm;
    object-fit: contain;
    margin-top: 1mm;
    border: 1px solid #eee;
}
@page { size: Letter landscape; margin: 0; }
</style>
</head>
<body>
<table class="sheet" aria-label="Premade visitor QR batch">
<tr>${rowOne}</tr>
<tr>${rowTwo}</tr>
</table>
</body>
</html>`;

            popup.document.open();
            popup.document.write(html);
            popup.document.close();
            popup.focus();
            popup.onload = function () {
                popup.print();
            };
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
            const revealAt = 120;

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
    </script>
    <script>
        function dismissTutorial() {
            try {
                localStorage.setItem('tutorialDismissed', '1');
            } catch (e) {}
            $('#tutorialModal').modal('hide');
        }

        function openTutorial(event) {
            if (event) {
                event.preventDefault();
            }
            $('#tutorialModal').modal('show');
        }

        function openPremadeVisitorQr(event) {
            if (event) {
                event.preventDefault();
            }
            $('#premadeVisitorQrModal').modal('show');
        }

        $(document).ready(function () {
            let dismissed = false;
            try {
                dismissed = localStorage.getItem('tutorialDismissed') === '1';
            } catch (e) {}
            if (!dismissed) {
                $('#tutorialModal').modal('show');
            }
        });
    </script>
    <script>
        let scanner = null;
        let scannerRunning = false;
        let cameraEnabled = true;
        const scanSoundEnabled = <?= $scanSoundEnabled ? 'true' : 'false' ?>;
        const cameraPreferenceKey = 'user_home_camera_enabled';
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

        function resetScannerUI() {
            document.querySelector('.qr-detected-container').style.display = 'none';
            document.querySelector('.scanner-con').style.display = '';
            lastScannedCode = '';
            lastScannedAt = 0;
            if (scanner && cameraEnabled) {
                startScanner(true);
            } else if (!cameraEnabled) {
                setCameraStatus('Closed', 'danger');
            }
        }

        function fetchStudentInfoByQr(qrCode) {
            const formData = new FormData();
            formData.append('qr_code', qrCode);
            formData.append('csrf_token', '<?= h(csrf_token()) ?>');

            return fetch('./endpoint/get-student-by-qr-user.php', {
                method: 'POST',
                body: formData
            }).then(resp => resp.json());
        }

        function loadStudentForConfirmation(qrCode, data) {
            setCameraStatus('Checking student info...', 'warning');

            return Promise.resolve(data).then(data => {
                if (!data || !data.ok) {
                    throw new Error(data && data.message ? data.message : 'Failed to load student info.');
                }

                const student = data.student || {};
                const nextAction = student.next_action || 'time_in';
                if (student.already_recorded || nextAction === 'already_recorded') {
                    throw new Error('This QR code is already recorded');
                }

                document.getElementById('studentConfirmName').textContent = student.name || '-';
                document.getElementById('studentConfirmIdNumber').textContent = student.student_id_number || '-';
                document.getElementById('studentConfirmCourse').textContent = student.course_section || '-';
                document.getElementById('studentConfirmQrCode').value = qrCode;
                const submitBtn = document.getElementById('studentConfirmSubmitBtn');
                if (submitBtn) {
                    submitBtn.textContent = nextAction === 'time_out'
                        ? 'Confirm to Time-out'
                        : 'Confirm to Time-in';
                }

                const photoEl = document.getElementById('studentConfirmPhoto');
                const noPhotoEl = document.getElementById('studentConfirmNoPhoto');
                if (student.photo_url) {
                    photoEl.src = student.photo_url;
                    photoEl.style.display = 'block';
                    noPhotoEl.style.display = 'none';
                } else {
                    photoEl.style.display = 'none';
                    noPhotoEl.style.display = 'block';
                }

                $('#studentScanConfirmModal').modal('show');
                setCameraStatus('Awaiting confirmation', 'warning');
            });
        }

        function cancelStudentConfirmation() {
            $('#studentScanConfirmModal').modal('hide');
            resetScannerUI();
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

            const config = getScanConfig();
            const cameraConfig = { facingMode: 'environment' };

            const beginStart = function () {
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

                    playScanTone('success');
                    showScanPopup('Scan successful');
                    $('#detected-qr-code').val(normalizedText);
                    stopScanner('Waiting for confirmation', 'warning');
                    document.querySelector('.scanner-con').style.display = 'none';
                    document.querySelector('.qr-detected-container').style.display = '';
                    document.getElementById('detectedLabel').textContent = 'Checking QR type...';
                    setCameraStatus('Detecting QR type...', 'warning');

                    fetchStudentInfoByQr(normalizedText).then(function (studentLookup) {
                        if (studentLookup && studentLookup.ok === true) {
                            document.getElementById('person-type').value = 'student';
                            document.getElementById('detectedLabel').textContent = 'Student QR Detected (Auto Time In/Out)!';
                            return loadStudentForConfirmation(normalizedText, studentLookup);
                        }

                        const lookupMessage = studentLookup && typeof studentLookup.message === 'string'
                            ? studentLookup.message.trim()
                            : '';

                        if (lookupMessage === 'Student not found for this QR code.') {
                            document.getElementById('person-type').value = 'visitor';
                            document.getElementById('detectedLabel').textContent = 'Visitor QR Detected (Auto Time In/Out)!';
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

            if (scannerRunning && forceRestart) {
                stopScanner().then(beginStart);
            } else if (!scannerRunning) {
                setCameraStatus('Starting...', 'warning');
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

        loadCameraPreference();
        updateCameraButtons();
        if (cameraEnabled) {
            openCamera();
        } else {
            setCameraStatus('Closed', 'danger');
        }
    </script>
</body>
</html>
