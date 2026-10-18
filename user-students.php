<?php
include('./conn/bootstrap.php');
include('./conn/conn.php');
include('./conn/student_table.php');
include('./conn/attendance_tables.php');
require_once('./conn/system_settings.php');
require_once('./conn/student_time_rules.php');

enable_kiosk_mode();
ensure_student_table($conn);
ensure_attendance_tables($conn);
ensure_system_settings_table($conn);
ensure_student_time_rules_table($conn);
$studentDepartments = get_student_departments($conn);
$studentClassStartTime = get_student_class_start_time($conn);
$studentClassEndTime = get_student_class_end_time($conn);
$studentLateGraceMinutes = get_student_late_grace_minutes($conn);
$studentEarlyOutGraceMinutes = get_student_early_out_grace_minutes($conn);
$studentTimeRuleMap = build_student_time_rule_map(get_student_time_rules($conn));
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT | Students</title>
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

        .student-thumb {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .student-thumb.js-student-photo-preview {
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .student-thumb.js-student-photo-preview:hover {
            transform: scale(1.06);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .student-group-filter {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 10px 0 14px;
        }

        .student-group-filter .btn {
            min-width: 110px;
        }

        .student-filter-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            margin: 10px 0 14px;
        }

        .student-search-box {
            display: flex;
            gap: 8px;
            align-items: center;
            min-width: 260px;
        }

        .student-search-box .form-control {
            min-width: 220px;
        }

        @media (max-width: 767px) {
            .student-filter-toolbar,
            .student-search-box {
                flex-direction: column;
                align-items: stretch;
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
                                <li>Fill in <strong>Full Name</strong>, choose <strong>Purpose</strong>, and add <strong>Contact Number</strong>.</li>
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

    <div class="container content">
        <div class="table-container">
            <h4 class="mb-3">List of Course & Strand of Present Students</h4>
            <div class="student-filter-toolbar">
                <div class="student-group-filter" id="studentGroupFilter">
                    <button type="button" class="btn btn-dark btn-sm active" data-group="ALL">All Students</button>
                    <?php foreach ($studentDepartments as $dept): ?>
                        <button type="button" class="btn btn-outline-dark btn-sm" data-group="<?= h($dept) ?>"><?= h($dept) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="student-search-box">
                    <input type="text" class="form-control form-control-sm" id="studentSearchInput" placeholder="Search student or course">
                    <button type="button" class="btn btn-dark btn-sm" id="studentSearchButton">Search</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Student ID No.</th>
                            <th>Course & Section</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                            <th>Arrival</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare('SELECT
                            s.tbl_student_id,
                            s.student_name,
                            s.student_id_number,
                            s.photo_url,
                            s.department,
                            s.year_level,
                            s.course_section,
                            a.time_in,
                            a.time_out
                            FROM tbl_attendance a
                            INNER JOIN tbl_student s ON s.tbl_student_id = a.tbl_student_id
                            WHERE a.time_in BETWEEN :today_start AND :today_end
                            ORDER BY a.tbl_attendance_id DESC');
                        $stmt->bindValue(':today_start', $todayStart, PDO::PARAM_STR);
                        $stmt->bindValue(':today_end', $todayEnd, PDO::PARAM_STR);
                        $stmt->execute();
                        while ($row = $stmt->fetch()) {
                            $studentID = (int) ($row['tbl_student_id'] ?? 0);
                            $studentName = $row['student_name'] ?? '';
                            $studentIdNumber = trim((string) ($row['student_id_number'] ?? ''));
                            $studentPhoto = trim((string) ($row['photo_url'] ?? ''));
                            $studentDepartment = trim((string) ($row['department'] ?? ''));
                            $studentYearLevel = $row['year_level'] ?? null;
                            $studentCourse = $row['course_section'] ?? '';
                            $studentGroup = strtoupper($studentDepartment !== '' ? $studentDepartment : 'OTHER');
                            $timeInRaw = (string) ($row['time_in'] ?? '');
                            $timeOutRaw = (string) ($row['time_out'] ?? '');
                            $timeIn = format_datetime_ph($timeInRaw);
                            $timeOut = format_datetime_ph($timeOutRaw);
                            $schedule = resolve_student_schedule_from_map(
                                $studentTimeRuleMap,
                                $studentDepartment,
                                $studentYearLevel,
                                $studentClassStartTime,
                                $studentClassEndTime,
                                $timeInRaw
                            );
                            $timeRule = evaluate_student_time_rule(
                                $timeInRaw,
                                $timeOutRaw,
                                $schedule['start'],
                                $schedule['end'],
                                $studentLateGraceMinutes,
                                $studentEarlyOutGraceMinutes
                            );
                            ?>
                            <tr data-student-group="<?= h($studentGroup) ?>">
                                <td data-student-name="<?= h($studentName) ?>">
                                    <?php if ($studentPhoto !== ''): ?>
                                        <img
                                            src="<?= h($studentPhoto) ?>"
                                            class="student-thumb js-student-photo-preview"
                                            alt="<?= h($studentName) ?> Photo"
                                            title="Click to preview"
                                            data-student-name="<?= h($studentName) ?>"
                                            data-student-id="<?= $studentID ?>"
                                        >
                                    <?php else: ?>
                                        <span class="text-muted small">No Photo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($studentName) ?></td>
                                <td><?= h($studentIdNumber !== '' ? $studentIdNumber : '-') ?></td>
                                <td><?= h($studentCourse !== '' ? $studentCourse : '-') ?></td>
                                <td><?= h($timeIn !== '' ? $timeIn : '-') ?></td>
                                <td><?= h($timeOut !== '' ? $timeOut : '-') ?></td>
                                <td><?= $timeOut !== '' ? '<span class="badge badge-danger">OUT</span>' : '<span class="badge badge-success">IN</span>' ?></td>
                                <td><span class="badge badge-<?= h($timeRule['arrival_badge']) ?>"><?= h($timeRule['arrival_label']) ?></span></td>
                                <td><span class="badge badge-<?= h($timeRule['departure_badge']) ?>"><?= h($timeRule['departure_label']) ?></span></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
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
        (function () {
            const refreshMs = 10000;
            const tableSelector = 'table tbody';

            function reapplySelectedFilter() {
                if (window.applyStudentFilters) {
                    window.applyStudentFilters();
                }
            }

            async function refreshTable() {
                try {
                    const resp = await fetch(window.location.href, { cache: 'no-store' });
                    const html = await resp.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newBody = doc.querySelector(tableSelector);
                    const currBody = document.querySelector(tableSelector);
                    if (newBody && currBody) {
                        currBody.innerHTML = newBody.innerHTML;
                        reapplySelectedFilter();
                    }
                } catch (e) {
                    // ignore transient errors
                }
            }

            setInterval(refreshTable, refreshMs);
        })();
    </script>
    <script>
        (function () {
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

            document.addEventListener('click', function (event) {
                const photo = event.target.closest('.js-student-photo-preview');
                if (!photo) {
                    return;
                }

                const src = photo.getAttribute('src') || '';
                if (src === '') {
                    return;
                }

                const studentName = (photo.getAttribute('data-student-name') || 'Student').trim();
                const titleEl = document.getElementById('studentPhotoPreviewLabel');
                const imageEl = document.getElementById('studentPhotoPreviewImage');
                if (titleEl) {
                    titleEl.textContent = studentName + ' Photo';
                }
                if (imageEl) {
                    imageEl.setAttribute('src', src);
                    imageEl.setAttribute('alt', studentName + ' photo preview');
                }
                $('#studentPhotoPreviewModal').modal('show');
            });

            const filterButtons = document.querySelectorAll('#studentGroupFilter [data-group]');
            const searchInput = document.getElementById('studentSearchInput');
            const searchButton = document.getElementById('studentSearchButton');

            function getSelectedGroup() {
                const activeButton = document.querySelector('#studentGroupFilter [data-group].active');
                return activeButton ? (activeButton.getAttribute('data-group') || 'ALL') : 'ALL';
            }

            function getSearchTerm() {
                return searchInput ? searchInput.value.trim().toLowerCase() : '';
            }

            function applyStudentFilters() {
                const selectedGroup = getSelectedGroup();
                const searchTerm = getSearchTerm();
                const rows = document.querySelectorAll('table tbody tr[data-student-group]');

                rows.forEach(function (row) {
                    const rowGroup = row.getAttribute('data-student-group') || '';
                    const rowText = row.textContent.toLowerCase();
                    const matchesGroup = selectedGroup === 'ALL' || rowGroup === selectedGroup;
                    const matchesSearch = searchTerm === '' || rowText.indexOf(searchTerm) !== -1;
                    row.style.display = matchesGroup && matchesSearch ? '' : 'none';
                });
            }

            window.applyStudentFilters = applyStudentFilters;

            filterButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    filterButtons.forEach(function (btn) {
                        btn.className = btn === button ? 'btn btn-dark btn-sm active' : 'btn btn-outline-dark btn-sm';
                    });
                    applyStudentFilters();
                });
            });

            if (searchButton) {
                searchButton.addEventListener('click', applyStudentFilters);
            }

            if (searchInput) {
                searchInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        applyStudentFilters();
                    }
                });

                searchInput.addEventListener('input', function () {
                    if (searchInput.value.trim() === '') {
                        applyStudentFilters();
                    }
                });
            }
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

