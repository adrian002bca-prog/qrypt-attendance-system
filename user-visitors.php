<?php
include('./conn/bootstrap.php');
include('./conn/conn.php');
include('./conn/visitor_table.php');
include('./conn/attendance_tables.php');
require_once('./conn/system_settings.php');

enable_kiosk_mode();
ensure_visitor_table($conn);
ensure_attendance_tables($conn);
ensure_system_settings_table($conn);
$visitorPurposeOptions = get_visitor_purpose_options($conn);
$premadeVisitorQrCode = get_premade_visitor_qr_code($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT | Visitors</title>
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

        .content {
            padding: 20px 0 40px;
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
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
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

        .table-container {
            background: rgba(255,255,255,0.9);
            border-radius: 12px;
            padding: 20px;
            box-shadow: rgba(0,0,0,0.2) 0 4px 12px;
        }

        .visitor-clickable {
            cursor: pointer;
        }

        .action-button {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
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
}</style>
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
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="openTutorial(event)">User Manual</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./login.php">Admin Login</a>
                </li>
            </ul>
        </div>
    </nav>

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
                                <li>Fill in <strong>First Name</strong> and <strong>Last Name</strong>, choose <strong>Purpose</strong>, and add <strong>Contact Number</strong>.</li>
                                <li>Click <strong>Generate QR Code</strong>, then click <strong>Save Visitor</strong>.</li>
                                <li>Go back to <strong>Home</strong>, choose <strong>Visitor</strong>, then scan the visitor QR code.</li>
                            </ol>
                            <div class="tip">Tip: Use the visitor QR only once per visit.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="dismissTutorial()">I Understand</button>
                </div>
            </div>
        </div>
    </div>

    <div class="container content">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">List of Visitors</h4>
                <button class="btn btn-dark" data-toggle="modal" data-target="#addVisitorModal">Add Visitor</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Contact</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare('SELECT
                            v.*,
                            va.time_in AS latest_time_in,
                            va.time_out AS latest_time_out
                            FROM tbl_visitor v
                            LEFT JOIN (
                                SELECT va1.tbl_visitor_id, va1.time_in, va1.time_out
                                FROM tbl_visitor_attendance va1
                                INNER JOIN (
                                    SELECT tbl_visitor_id, MAX(tbl_visitor_attendance_id) AS latest_attendance_id
                                    FROM tbl_visitor_attendance
                                    GROUP BY tbl_visitor_id
                                ) latest ON latest.latest_attendance_id = va1.tbl_visitor_attendance_id
                            ) va ON va.tbl_visitor_id = v.tbl_visitor_id
                            WHERE
                                -- Keep normal visitor rows visible.
                                TRIM(COALESCE(v.visitor_name, "")) <> ""
                                OR TRIM(COALESCE(v.contact_no, "")) <> ""
                                -- For premade-generated rows (blank name/contact), show only if scanned.
                                OR va.time_in IS NOT NULL
                            ORDER BY v.tbl_visitor_id DESC');
                        $stmt->execute();
                        $displayNumber = 1;
                        while ($row = $stmt->fetch()) {
                            $visitorID = (int) $row['tbl_visitor_id'];
                            $visitorName = $row['visitor_name'] ?? '';
                            $visitorPurpose = $row['purpose'] ?? '';
                            $visitorContact = $row['contact_no'] ?? '';
                            $visitorQrCode = (string) ($row['generated_code'] ?? '');
                            $timeIn = format_datetime_ph((string) ($row['latest_time_in'] ?? ''));
                            $timeOut = format_datetime_ph((string) ($row['latest_time_out'] ?? ''));
                            $statusLabel = '-';
                            $statusBadge = 'secondary';
                            if ($timeIn !== '') {
                                if ($timeOut !== '') {
                                    $statusLabel = 'OUT';
                                    $statusBadge = 'danger';
                                } else {
                                    $statusLabel = 'IN';
                                    $statusBadge = 'success';
                                }
                            }
                            $isPremadeVisitor = hash_equals($premadeVisitorQrCode, $visitorQrCode)
                                || strpos($visitorQrCode, 'QRYPT_PRE_') === 0;
                            $canEditPremadeOut = $isPremadeVisitor && $statusLabel === 'OUT';
                            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($visitorQrCode);
                            ?>
                            <tr>
                                <td class="visitor-clickable" data-toggle="modal" data-target="#visitorQrModal<?= $visitorID ?>"><?= $displayNumber ?></td>
                                <td class="visitor-clickable" data-toggle="modal" data-target="#visitorQrModal<?= $visitorID ?>">
                                    <?= h($visitorName !== '' ? $visitorName : 'Premade QR Visitor') ?>
                                    <?php if ($isPremadeVisitor): ?>
                                        <span class="badge badge-info ml-1">Premade</span>
                                    <?php endif; ?>
                                </td>
                                <td class="visitor-clickable" data-toggle="modal" data-target="#visitorQrModal<?= $visitorID ?>"><?= h($visitorPurpose) ?></td>
                                <td class="visitor-clickable" data-toggle="modal" data-target="#visitorQrModal<?= $visitorID ?>"><?= h($visitorContact) ?></td>
                                <td><?= h($timeIn !== '' ? $timeIn : '-') ?></td>
                                <td><?= h($timeOut !== '' ? $timeOut : '-') ?></td>
                                <td><span class="badge badge-<?= h($statusBadge) ?>"><?= h($statusLabel) ?></span></td>
                                <td>
                                    <div class="action-button">
                                        <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#visitorQrModal<?= $visitorID ?>">
                                            <img src="https://cdn-icons-png.flaticon.com/512/1341/1341632.png" alt="QR" width="16">
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            onclick="openPremadeVisitorEditModal(this)"
                                            data-visitor-id="<?= $visitorID ?>"
                                            data-visitor-name="<?= h((string) $visitorName) ?>"
                                            data-visitor-purpose="<?= h((string) $visitorPurpose) ?>"
                                            data-visitor-contact="<?= h((string) $visitorContact) ?>"
                                            <?= $canEditPremadeOut ? '' : 'disabled' ?>
                                            title="<?= h($canEditPremadeOut ? 'Edit premade visitor details' : 'Only premade visitors with OUT status can be edited') ?>"
                                        >&#128393;</button>
                                    </div>
                                </td>
                            </tr>

                            <div class="modal fade" id="visitorQrModal<?= $visitorID ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?= h($visitorName) ?>'s QR Code</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img src="<?= h($qrCodeUrl) ?>" alt="Visitor QR Code" width="260">
                                            <p class="mt-3 mb-1"><strong>Status:</strong> <?= h($statusLabel) ?></p>
                                            <p class="mb-0"><strong>Purpose:</strong> <?= h($visitorPurpose) ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php $displayNumber++; } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addVisitorModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="addVisitor" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addVisitor">Add Visitor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="./endpoint/add-visitor-user.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <div class="form-group">
                            <label for="visitorFirstName">First Name:</label>
                            <input type="text" class="form-control" id="visitorFirstName" name="visitor_first_name" required maxlength="80">
                        </div>
                        <div class="form-group">
                            <label for="visitorLastName">Last Name:</label>
                            <input type="text" class="form-control" id="visitorLastName" name="visitor_last_name" required maxlength="80">
                        </div>
                        <div class="form-group">
                            <label for="visitorPurpose">Purpose:</label>
                            <select class="form-control" id="visitorPurpose" name="purpose" required>
                                <option value="" disabled selected>Select purpose</option>
                                <?php foreach ($visitorPurposeOptions as $purposeOption): ?>
                                    <option><?= h($purposeOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="visitorContact">Contact Number:</label>
                            <input type="text" class="form-control" id="visitorContact" name="contact_no" required>
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
                            <input class="form-check-input" type="checkbox" id="visitorTermsAgree" name="consent_terms" value="1" required>
                            <label class="form-check-label" for="visitorTermsAgree">I agree to the Terms and Conditions and Privacy Notice.</label>
                        </div>
                        <button type="button" class="btn btn-secondary form-control qr-generator" onclick="generateQrCode()">Generate QR Code</button>

                        <div class="qr-con text-center" style="display: none;">
                            <input type="hidden" class="form-control" id="generatedCode" name="generated_code">
                            <img class="mb-4" src="" id="qrImg" alt="Generated QR Code">
                        </div>
                        <button type="submit" class="btn btn-dark form-control mt-2 modal-close" style="display: none;">Save Visitor</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="updateVisitorModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="updateVisitor" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateVisitor">Update Premade Visitor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="./endpoint/update-visitor.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="redirect_page" value="user-visitors.php">
                        <input type="hidden" class="form-control" id="updateVisitorId" name="tbl_visitor_id">
                        <div class="form-group">
                            <label for="updateVisitorFirstName">First Name:</label>
                            <input type="text" class="form-control" id="updateVisitorFirstName" name="visitor_first_name" required maxlength="80">
                        </div>
                        <div class="form-group">
                            <label for="updateVisitorLastName">Last Name:</label>
                            <input type="text" class="form-control" id="updateVisitorLastName" name="visitor_last_name" required maxlength="80">
                        </div>
                        <div class="form-group">
                            <label for="updateVisitorPurpose">Purpose:</label>
                            <select class="form-control" id="updateVisitorPurpose" name="purpose" required>
                                <option value="" disabled>Select purpose</option>
                                <?php foreach ($visitorPurposeOptions as $purposeOption): ?>
                                    <option><?= h($purposeOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="updateVisitorContact">Contact Number:</label>
                            <input type="text" class="form-control" id="updateVisitorContact" name="contact_no" required maxlength="40">
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

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
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
        function generateRandomCode(length) {
            const characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            const randomValues = new Uint32Array(length);
            window.crypto.getRandomValues(randomValues);
            let randomString = '';
            for (let i = 0; i < length; i++) {
                randomString += characters[randomValues[i] % characters.length];
            }
            return randomString;
        }

        function generateQrCode() {
            const text = generateRandomCode(16);
            const qrImg = document.getElementById('qrImg');
            $('#generatedCode').val(text);
            const apiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(text)}`;
            qrImg.src = apiUrl;

            document.getElementById('visitorFirstName').style.pointerEvents = 'none';
            document.getElementById('visitorLastName').style.pointerEvents = 'none';
            document.getElementById('visitorPurpose').style.pointerEvents = 'none';
            document.getElementById('visitorContact').style.pointerEvents = 'none';
            document.getElementById('visitorTermsAgree').style.pointerEvents = 'none';
            document.querySelector('.modal-close').style.display = '';
            document.querySelector('.qr-con').style.display = '';
            document.querySelector('.qr-generator').style.display = 'none';
        }

        $('#addVisitorModal').on('hidden.bs.modal', function () {
            document.getElementById('visitorFirstName').style.pointerEvents = '';
            document.getElementById('visitorLastName').style.pointerEvents = '';
            document.getElementById('visitorPurpose').style.pointerEvents = '';
            document.getElementById('visitorContact').style.pointerEvents = '';
            document.getElementById('visitorTermsAgree').style.pointerEvents = '';
            document.querySelector('.modal-close').style.display = 'none';
            document.querySelector('.qr-con').style.display = 'none';
            document.querySelector('.qr-generator').style.display = '';
            document.getElementById('visitorFirstName').value = '';
            document.getElementById('visitorLastName').value = '';
            document.getElementById('visitorPurpose').value = '';
            document.getElementById('visitorContact').value = '';
            document.getElementById('visitorTermsAgree').checked = false;
            document.getElementById('generatedCode').value = '';
            document.getElementById('qrImg').src = '';
        });
    </script>
    <script>
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

        function openPremadeVisitorEditModal(buttonEl) {
            if (!buttonEl || buttonEl.disabled) {
                return;
            }

            const visitorId = String(buttonEl.getAttribute('data-visitor-id') || '').trim();
            const visitorName = String(buttonEl.getAttribute('data-visitor-name') || '').trim();
            const visitorPurpose = String(buttonEl.getAttribute('data-visitor-purpose') || '').trim();
            const visitorContact = String(buttonEl.getAttribute('data-visitor-contact') || '').trim();
            const splitName = splitPersonName(visitorName);

            $('#updateVisitorId').val(visitorId);
            $('#updateVisitorFirstName').val(splitName.firstName);
            $('#updateVisitorLastName').val(splitName.lastName);
            $('#updateVisitorPurpose').val(visitorPurpose);
            $('#updateVisitorContact').val(visitorContact);
            $('#updateVisitorModal').modal('show');
        }
    </script>
    <script>
        (function () {
            const refreshMs = 10000;
            const tableSelector = 'table tbody';

            async function refreshTable() {
                try {
                    const resp = await fetch(window.location.href, { cache: 'no-store' });
                    const html = await resp.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newBody = doc.querySelector(tableSelector);
                    const currBody = document.querySelector(tableSelector);
                    if (newBody && currBody) {
                        currBody.innerHTML = newBody.innerHTML;
                    }
                } catch (e) {
                    // ignore transient errors
                }
            }

            setInterval(refreshTable, refreshMs);
        })();
    </script>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-meta">
            <img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 2em; width: auto;">
            <span>Copyright 2026. TIF-TCI</span>
        </div>
    </div>
</footer></body>
</html>

