<?php
include('./conn/bootstrap.php');
require_login();
include('./conn/conn.php');
include('./conn/visitor_table.php');
require_once('./conn/system_settings.php');

ensure_visitor_table($conn);
ensure_system_settings_table($conn);
$visitorPurposeOptions = get_visitor_purpose_options($conn);
$premadeVisitorQrCode = get_premade_visitor_qr_code($conn);
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
        #visitorTable th:last-child,
        #visitorTable td:last-child {
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

        .visitor-container {
            width: 90%;
            border-radius: 20px;
            padding: 24px;
            background-color: rgba(255, 255, 255, 0.8);
        }

        .visitor-container > div {
            box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
            border-radius: 10px;
            padding: 30px;
            height: auto;
        }

        .visitor-list {
            max-height: calc(100vh - 170px);
            overflow-y: auto;
            overflow-x: hidden;
        }

        .title {
            display: flex;
            justify-content: space-between;
            align-items: center;
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

            .visitor-container {
                width: 95%;
                padding: 12px;
            }

            .visitor-list {
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
                    <a class="nav-link" href="./index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./masterlist.php">List of Students</a>
                </li>
                <li class="nav-item active">
                    <a class="nav-link" href="./visitorlist.php">List of Visitors <span class="sr-only">(current)</span></a>
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
        <div class="visitor-container">
            <div class="visitor-list">
                <div class="title">
                    <h4>List of Visitors</h4>
                    <div>
                        <button class="btn btn-outline-secondary mr-2 no-print" id="exportVisitorsPdf">Export PDF</button>
                        <button class="btn btn-dark no-print" data-toggle="modal" data-target="#addVisitorModal">Add Visitor</button>
                    </div>
                </div>
                <hr>
                <div class="table-container table-responsive print-area">
                    <table class="table text-center table-sm" id="visitorTable">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Name</th>
                                <th scope="col">Purpose</th>
                                <th scope="col">Contact</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $conn->prepare('SELECT v.*
                                FROM tbl_visitor v
                                WHERE
                                    TRIM(COALESCE(v.visitor_name, "")) <> ""
                                    OR TRIM(COALESCE(v.contact_no, "")) <> ""
                                    OR EXISTS (
                                        SELECT 1
                                        FROM tbl_visitor_attendance va
                                        WHERE va.tbl_visitor_id = v.tbl_visitor_id
                                    )
                                ORDER BY v.tbl_visitor_id DESC');
                            $stmt->execute();
                            $result = $stmt->fetchAll();

                            foreach ($result as $row) {
                                $visitorID = (int) $row['tbl_visitor_id'];
                                $visitorName = $row['visitor_name'] ?? '';
                                $visitorPurpose = $row['purpose'] ?? '';
                                $visitorContact = $row['contact_no'] ?? '';
                                $qrCode = $row['generated_code'] ?? '';
                                $isPremadeVisitor = hash_equals($premadeVisitorQrCode, (string) $qrCode)
                                    || strpos((string) $qrCode, 'QRYPT_PRE_') === 0;
                                $displayVisitorName = trim((string) $visitorName) !== ''
                                    ? (string) $visitorName
                                    : 'Premade QR Visitor';
                                $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . rawurlencode($qrCode);
                            ?>
                                <tr>
                                    <th scope="row" id="visitorID-<?= $visitorID ?>" data-visitor-id="<?= $visitorID ?>"></th>
                                    <td id="visitorName-<?= $visitorID ?>" data-raw-name="<?= h((string) $visitorName) ?>"><?= h($displayVisitorName) ?><?php if ($isPremadeVisitor): ?> <span class="badge badge-info ml-1">Premade</span><?php endif; ?></td>
                                    <td id="visitorPurpose-<?= $visitorID ?>"><?= h($visitorPurpose) ?></td>
                                    <td id="visitorContact-<?= $visitorID ?>"><?= h($visitorContact) ?></td>
                                    <td>
                                        <div class="action-button">
                                            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#qrCodeModal<?= $visitorID ?>"><img src="https://cdn-icons-png.flaticon.com/512/1341/1341632.png" alt="QR" width="16"></button>

                                            <div class="modal fade" id="qrCodeModal<?= $visitorID ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title"><?= h($displayVisitorName) ?>'s QR Code</h5>
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

                                            <button class="btn btn-secondary btn-sm" onclick="updateVisitor(<?= $visitorID ?>)">&#128393;</button>
                                            <form class="inline-form" action="./endpoint/delete-visitor.php" method="POST" onsubmit="return confirm('Do you want to delete this visitor?');">
                                                <input type="hidden" name="visitor" value="<?= $visitorID ?>">
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

    <div class="modal fade" id="addVisitorModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="addVisitor" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addVisitor">Add Visitor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="./endpoint/add-visitor.php" method="POST">
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
                            <p>Take a picture of your QR code.</p>
                            <img class="mb-4" src="" id="qrImg" alt="Generated QR Code">
                        </div>
                        <div class="modal-footer modal-close" style="display: none;">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-dark">Add Visitor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="updateVisitorModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="updateVisitor" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateVisitor">Update Visitor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="./endpoint/update-visitor.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
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
                            <input type="text" class="form-control" id="updateVisitorContact" name="contact_no" required>
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.js"></script>

    <script>
        $(document).ready(function () {
            const visitorTable = $('#visitorTable').DataTable({
                stateSave: true,
                stateDuration: -1,
                columnDefs: [
                    { targets: 0, orderable: false, searchable: false }
                ]
            });

            visitorTable.on('order.dt search.dt draw.dt', function () {
                const info = visitorTable.page.info();
                visitorTable
                    .column(0, { search: 'applied', order: 'applied', page: 'current' })
                    .nodes()
                    .each(function (cell, i) {
                        cell.textContent = String(info.start + i + 1);
                    });
            }).draw();

            const exportBtn = document.getElementById('exportVisitorsPdf');
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

        function updateVisitor(id) {
            $('#updateVisitorModal').modal('show');

            let updateVisitorId = String(id);
            let updateVisitorName = String($('#visitorName-' + id).data('raw-name') || '').trim();
            let updateVisitorPurpose = $('#visitorPurpose-' + id).text();
            let updateVisitorContact = $('#visitorContact-' + id).text();
            const splitName = splitPersonName(updateVisitorName);

            $('#updateVisitorId').val(updateVisitorId);
            $('#updateVisitorFirstName').val(splitName.firstName);
            $('#updateVisitorLastName').val(splitName.lastName);
            $('#updateVisitorPurpose').val(updateVisitorPurpose);
            $('#updateVisitorContact').val(updateVisitorContact);
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

            document.getElementById('visitorFirstName').style.pointerEvents = 'none';
            document.getElementById('visitorLastName').style.pointerEvents = 'none';
            document.getElementById('visitorPurpose').style.pointerEvents = 'none';
            document.getElementById('visitorContact').style.pointerEvents = 'none';
            document.getElementById('visitorTermsAgree').style.pointerEvents = 'none';
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











