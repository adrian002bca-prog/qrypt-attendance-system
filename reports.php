<?php
include('./conn/bootstrap.php');
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT Reports</title>
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
            min-height: calc(100vh - 56px);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .chooser {
            width: 100%;
            max-width: 760px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 16px;
            padding: 28px;
        }
        .card-link {
            text-decoration: none !important;
            color: inherit;
            display: block;
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
        <div class="chooser">
            <h4 class="mb-3">Attendance Reports</h4>
            <p class="text-muted mb-4">Choose which report you want to view.</p>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <a class="card-link" href="./student-reports.php">
                        <div class="card border-dark h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-2">Student Report</h5>
                                <p class="card-text mb-0">View and export student attendance logs only.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 mb-3">
                    <a class="card-link" href="./visitor-reports.php">
                        <div class="card border-dark h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-2">Visitor Report</h5>
                                <p class="card-text mb-0">View and export visitor attendance logs only.</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
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








