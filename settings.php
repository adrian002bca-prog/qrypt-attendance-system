<?php
include('./conn/bootstrap.php');
require_login();
include('./conn/conn.php');
include('./conn/audit_log.php');
include('./conn/db_backup.php');
require_once('./conn/system_settings.php');
include('./conn/auto_close.php');
include('./conn/student_table.php');
require_once('./conn/student_time_rules.php');

ensure_audit_log_table($conn);
ensure_system_settings_table($conn);
$dailyBackupStatus = ensure_daily_backup($conn) !== false;
$autoCloseResult = ensure_daily_auto_close($conn);
$recentBackups = [];
$scanCooldownSeconds = get_system_setting_int($conn, 'scan_cooldown_seconds', 10);
$autoCloseCutoffTime = get_auto_close_cutoff_time($conn);
$scanSoundEnabled = get_system_setting_int($conn, 'scan_sound_enabled', 1) === 1;
$studentClassStartTime = get_student_class_start_time($conn);
$studentClassEndTime = get_student_class_end_time($conn);
$studentLateGraceMinutes = get_student_late_grace_minutes($conn);
$studentEarlyOutGraceMinutes = get_student_early_out_grace_minutes($conn);
ensure_student_time_rules_table($conn);
$studentDepartments = get_student_departments($conn);
$studentYearLevels = get_student_year_levels($conn);
$studentDepartmentsText = implode(', ', $studentDepartments);
$studentYearLevelsText = implode(', ', $studentYearLevels);
$visitorPurposeOptions = get_visitor_purpose_options($conn);
$visitorPurposeOptionsText = implode(PHP_EOL, $visitorPurposeOptions);

if (ensure_backup_directory()) {
    $files = glob(backup_dir_path() . '/*.sql');
    if (is_array($files)) {
        usort($files, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $files = array_slice($files, 0, 10);
        foreach ($files as $path) {
            $size = @filesize($path);
            $mtime = @filemtime($path);
            $recentBackups[] = [
                'name' => basename($path),
                'size' => is_int($size) && $size >= 0 ? $size : 0,
                'mtime' => is_int($mtime) ? date('Y-m-d H:i:s', $mtime) : '',
            ];
        }
    }
}

$error = '';
$success = '';
$currentAuthUsername = auth_username();

if (isset($_SESSION['settings_flash_error'])) {
    $error = (string) $_SESSION['settings_flash_error'];
    unset($_SESSION['settings_flash_error']);
}

if (isset($_SESSION['settings_flash_success'])) {
    $success = (string) $_SESSION['settings_flash_success'];
    unset($_SESSION['settings_flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_reject();

    if (isset($_POST['save_scan_cooldown'])) {
        $cooldownInput = trim((string) ($_POST['scan_cooldown_seconds'] ?? ''));
        if (!preg_match('/^\d+$/', $cooldownInput)) {
            $error = 'Cooldown must be a whole number.';
        } else {
            $cooldown = (int) $cooldownInput;
            if ($cooldown < 1 || $cooldown > 120) {
                $error = 'Cooldown must be between 1 and 120 seconds.';
            } else {
                set_system_setting($conn, 'scan_cooldown_seconds', (string) $cooldown);
                $scanCooldownSeconds = $cooldown;
                $success = 'Scan cooldown updated successfully.';
                record_audit_log($conn, 'UPDATE_SETTING', 'system_setting', null, 'Updated scan cooldown to ' . $cooldown . ' seconds');
            }
        }
    } elseif (isset($_POST['save_scan_sound'])) {
        $soundInput = trim((string) ($_POST['scan_sound_enabled'] ?? '1'));
        if (!in_array($soundInput, ['0', '1'], true)) {
            $error = 'Invalid sound setting.';
        } else {
            set_system_setting($conn, 'scan_sound_enabled', $soundInput);
            $scanSoundEnabled = $soundInput === '1';
            $success = 'Scan sound setting updated successfully.';
            record_audit_log($conn, 'UPDATE_SETTING', 'system_setting', null, 'Updated scan sound to ' . ($scanSoundEnabled ? 'ON' : 'OFF'));
        }
    } elseif (isset($_POST['save_auto_close_cutoff'])) {
        $cutoffInput = trim((string) ($_POST['auto_close_cutoff_time'] ?? ''));
        $normalizedCutoff = normalize_auto_close_cutoff($cutoffInput);
        if ($normalizedCutoff !== $cutoffInput) {
            $error = 'Cutoff time format must be HH:MM (24-hour).';
        } else {
            set_system_setting($conn, 'auto_close_cutoff_time', $normalizedCutoff);
            $autoCloseCutoffTime = $normalizedCutoff;
            $success = 'Auto-close cutoff time updated successfully.';
            record_audit_log($conn, 'UPDATE_SETTING', 'system_setting', null, 'Updated auto-close cutoff to ' . $normalizedCutoff);
        }
    } elseif (isset($_POST['save_visitor_purpose_options'])) {
        $purposeRaw = trim((string) ($_POST['visitor_purpose_options'] ?? ''));
        $parsedPurposeOptions = parse_visitor_purpose_options_text($purposeRaw);
        if (count($parsedPurposeOptions) === 0) {
            $error = 'Please provide at least one visitor purpose option.';
        } else {
            set_system_setting($conn, 'visitor_purpose_options', implode(PHP_EOL, $parsedPurposeOptions));
            $visitorPurposeOptions = $parsedPurposeOptions;
            $visitorPurposeOptionsText = implode(PHP_EOL, $parsedPurposeOptions);
            $success = 'Visitor purpose options updated successfully.';
            record_audit_log($conn, 'UPDATE_SETTING', 'system_setting', null, 'Updated visitor purpose options list');
        }
    } elseif (isset($_POST['save_student_time_rules'])) {
        $classStartInput = trim((string) ($_POST['student_class_start_time'] ?? ''));
        $classEndInput = trim((string) ($_POST['student_class_end_time'] ?? ''));
        $lateGraceInput = trim((string) ($_POST['student_late_grace_minutes'] ?? '0'));
        $earlyGraceInput = trim((string) ($_POST['student_early_out_grace_minutes'] ?? '0'));
        $normalizedStart = normalize_hhmm_time($classStartInput, '08:00');
        $normalizedEnd = normalize_hhmm_time($classEndInput, '17:00');
        $lateGraceRaw = preg_match('/^\d+$/', $lateGraceInput) ? (int) $lateGraceInput : null;
        $earlyGraceRaw = preg_match('/^\d+$/', $earlyGraceInput) ? (int) $earlyGraceInput : null;
        $lateGrace = $lateGraceRaw === null ? 0 : normalize_grace_minutes($lateGraceRaw, 0);
        $earlyGrace = $earlyGraceRaw === null ? 0 : normalize_grace_minutes($earlyGraceRaw, 0);

        if ($normalizedStart !== $classStartInput || $normalizedEnd !== $classEndInput) {
            $error = 'Class start and end time must use HH:MM (24-hour).';
        } elseif ($lateGraceRaw === null || $earlyGraceRaw === null) {
            $error = 'Grace minutes must be whole numbers.';
        } elseif ($lateGraceRaw < 0 || $lateGraceRaw > 180 || $earlyGraceRaw < 0 || $earlyGraceRaw > 180) {
            $error = 'Grace minutes must be between 0 and 180.';
        } elseif ($normalizedStart >= $normalizedEnd) {
            $error = 'Class end time must be later than class start time.';
        } else {
            set_system_setting($conn, 'student_class_start_time', $normalizedStart);
            set_system_setting($conn, 'student_class_end_time', $normalizedEnd);
            set_system_setting($conn, 'student_late_grace_minutes', (string) $lateGrace);
            set_system_setting($conn, 'student_early_out_grace_minutes', (string) $earlyGrace);
            $studentClassStartTime = $normalizedStart;
            $studentClassEndTime = $normalizedEnd;
            $studentLateGraceMinutes = $lateGrace;
            $studentEarlyOutGraceMinutes = $earlyGrace;
            $success = 'Student late/early rule updated successfully.';
            record_audit_log($conn, 'UPDATE_SETTING', 'system_setting', null, 'Updated student time rules: start ' . $normalizedStart . ', end ' . $normalizedEnd . ', late grace ' . $lateGrace . 'm, early-out grace ' . $earlyGrace . 'm');
        }
    } elseif (isset($_POST['save_student_lists'])) {
        $deptRaw = trim((string) ($_POST['student_departments_list'] ?? ''));
        $yearRaw = trim((string) ($_POST['student_year_levels_list'] ?? ''));
        $deptList = parse_student_departments($deptRaw);
        $yearList = parse_student_year_levels($yearRaw);

        if (count($deptList) === 0) {
            $error = 'Please provide at least one department.';
        } elseif (count($yearList) === 0) {
            $error = 'Please provide at least one year level.';
        } else {
            set_system_setting($conn, 'student_departments', implode(',', $deptList));
            set_system_setting($conn, 'student_year_levels', implode(',', $yearList));
            $studentDepartments = $deptList;
            $studentYearLevels = $yearList;
            $studentDepartmentsText = implode(', ', $deptList);
            $studentYearLevelsText = implode(', ', $yearList);
            $success = 'Student lists updated successfully.';
            record_audit_log(
                $conn,
                'UPDATE_SETTING',
                'system_setting',
                null,
                'Updated student departments and year levels'
            );
        }
    } elseif (isset($_POST['save_username'])) {
        $currentPasswordForUsername = $_POST['current_password_for_username'] ?? '';
        $newUsername = trim((string) ($_POST['new_username'] ?? ''));
        $confirmUsername = trim((string) ($_POST['confirm_username'] ?? ''));

        if (!password_verify($currentPasswordForUsername, auth_password_hash())) {
            $error = 'Current password is incorrect.';
        } elseif ($newUsername === '') {
            $error = 'New username is required.';
        } elseif (strlen($newUsername) < 3 || strlen($newUsername) > 50) {
            $error = 'New username must be between 3 and 50 characters.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $newUsername)) {
            $error = 'New username can only use letters, numbers, underscore, dot, or hyphen.';
        } elseif (!hash_equals($newUsername, $confirmUsername)) {
            $error = 'New username and confirm username do not match.';
        } elseif (hash_equals(auth_username(), $newUsername)) {
            $error = 'New username must be different from current username.';
        } else {
            $oldUsername = auth_username();

            if (save_auth_username($newUsername)) {
                $_SESSION['username'] = $newUsername;
                clear_login_failures($oldUsername);
                clear_login_failures($newUsername);
                $currentAuthUsername = $newUsername;
                $success = 'Username updated successfully.';
                record_audit_log($conn, 'UPDATE_USERNAME', 'admin_account', null, 'Admin username changed from ' . $oldUsername . ' to ' . $newUsername);
            } else {
                $error = 'Failed to save new username. Check file permissions.';
            }
        }
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, auth_password_hash())) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            $error = 'New password and confirm password do not match.';
        } elseif (password_verify($newPassword, auth_password_hash())) {
            $error = 'New password must be different from current password.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            if (save_auth_password_hash($newHash)) {
                $success = 'Password updated successfully.';
                record_audit_log($conn, 'UPDATE_PASSWORD', 'admin_account', null, 'Admin password updated');
            } else {
                $error = 'Failed to save new password. Check file permissions.';
            }
        }
    }

    $_SESSION['settings_flash_error'] = $error;
    $_SESSION['settings_flash_success'] = $success;
    redirect_to('settings.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRYPT Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@500&display=swap');

        * {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        :root {
            --bg: #fff1df;
            --card: #ffffff;
            --border: #d9dee6;
            --muted: #6b7280;
            --title: #1f2937;
        }
        body {
            background: var(--bg);
        }
        .settings-wrap {
            max-width: 1220px;
            margin: 26px auto 40px auto;
            padding: 0 16px;
        }
        .settings-header {
            margin-bottom: 16px;
        }
        .settings-header h4 {
            color: var(--title);
            margin-bottom: 4px;
            font-weight: 600;
        }
        .settings-header p {
            color: var(--muted);
            margin: 0;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .settings-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05);
        }
        .settings-card h5 {
            margin-bottom: 6px;
            color: var(--title);
            font-weight: 600;
        }
        .settings-card .desc {
            color: var(--muted);
            margin-bottom: 12px;
            font-size: 13px;
        }
        .settings-card-wide {
            grid-column: 1 / -1;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.05);
        }
        .card-body {
            padding: 18px;
        }
        .settings-panels {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .settings-panel {
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            padding: 14px;
        }
        .settings-panel-wide {
            grid-column: 1 / -1;
        }
        .settings-panel h6 {
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--title);
        }
        .rule-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .rule-tab-btn {
            border: 1px solid var(--border);
            background: #fff;
            color: #111827;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 13px;
        }
        .rule-tab-btn.active {
            background: #111827;
            border-color: #111827;
            color: #fff;
        }
        .rule-tab-panel {
            display: none;
        }
        .rule-tab-panel.active {
            display: block;
        }
        .rule-filters {
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }
        .rule-key-preview {
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
        }
        .rule-priority-note {
            background: #eef6ff;
            border: 1px solid #cfe3ff;
            border-radius: 8px;
            color: #1e3a5f;
            padding: 8px 10px;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .rule-inline-actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .sub-block {
            border-top: 1px dashed var(--border);
            padding-top: 12px;
            margin-top: 12px;
        }
        .small-note {
            color: var(--muted);
            font-size: 12px;
        }
        .status-pill {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 6px;
        }
        .status-ok {
            background: #dcfce7;
            color: #166534;
        }
        .status-bad {
            background: #fee2e2;
            color: #991b1b;
        }
        .table th {
            white-space: nowrap;
        }
        .btn-toolbar-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        @media (max-width: 991.98px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            .settings-panels {
                grid-template-columns: 1fr;
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

    <div class="settings-wrap">
        <div class="card">
            <div class="card-body">
                <div class="settings-header">
                    <h4>Admin Settings</h4>
                    <p>Manage security, schedules, and backups from one page.</p>
                </div>
                
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
                <?php endif; ?>

                <?php if ($success !== ''): ?>
                    <div class="alert alert-success" role="alert"><?= h($success) ?></div>
                <?php endif; ?>

                <div class="settings-panels">
                <section class="settings-panel">
                <h6 class="mb-2">Account Security</h6>
                <p class="small-note mb-2">Change admin password for this system.</p>
                <form method="POST" action="./settings.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-dark btn-sm">Save Password</button>
                </form>

                <div class="sub-block">
                    <h6 class="mb-2">Change Username</h6>
                    <p class="small-note mb-2">Current username: <strong><?= h($currentAuthUsername) ?></strong></p>
                    <form method="POST" action="./settings.php">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="save_username" value="1">

                        <div class="form-group">
                            <label for="current_password_for_username">Current Password</label>
                            <input type="password" class="form-control" id="current_password_for_username" name="current_password_for_username" required>
                        </div>

                        <div class="form-group">
                            <label for="new_username">New Username</label>
                            <input type="text" class="form-control" id="new_username" name="new_username" maxlength="50" pattern="[A-Za-z0-9_.-]+" required>
                            <small class="small-note">Allowed: letters, numbers, underscore, dot, hyphen.</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_username">Confirm New Username</label>
                            <input type="text" class="form-control" id="confirm_username" name="confirm_username" maxlength="50" pattern="[A-Za-z0-9_.-]+" required>
                        </div>

                        <button type="submit" class="btn btn-dark btn-sm">Save Username</button>
                    </form>
                </div>
                </section>

                <section class="settings-panel">

                <h6 class="mb-2">Database Backup</h6>
                <p class="text-muted mb-2">
                    Automatic daily backup is
                    <span class="status-pill <?= $dailyBackupStatus ? 'status-ok' : 'status-bad' ?>">
                        <?= $dailyBackupStatus ? 'active' : 'failed' ?>
                    </span>
                </p>
                <form method="POST" action="./endpoint/backup-db.php" class="mb-3">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <button type="submit" class="btn btn-outline-dark btn-sm">Download Backup (.sql)</button>
                </form>
                <div class="mb-3">
                    <h6 class="mb-2">Recent Backups</h6>
                    <?php if (count($recentBackups) === 0): ?>
                        <p class="text-muted mb-0">No backup files found yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>File Name</th>
                                        <th>Size</th>
                                        <th>Modified (PH)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBackups as $backup): ?>
                                        <tr>
                                            <td><?= h((string) $backup['name']) ?></td>
                                            <td><?= h(number_format(((int) $backup['size']) / 1024, 1)) ?> KB</td>
                                            <td><?= h(format_datetime_ph((string) $backup['mtime'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <h6 class="mb-2">Database Restore</h6>
                <p class="text-danger mb-2">Warning: Restore replaces current data. A pre-restore backup will be created automatically.</p>
                <form method="POST" action="./endpoint/restore-db.php" enctype="multipart/form-data" onsubmit="return confirm('This will restore database from backup. Continue?');">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <div class="form-group">
                        <label for="backup_file">Backup File (.sql, max 20 MB)</label>
                        <input type="file" class="form-control-file" id="backup_file" name="backup_file" accept=".sql" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_restore">Type CONFIRM to restore</label>
                        <input type="text" class="form-control" id="confirm_restore" name="confirm_restore" required>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm">Restore Database</button>
                </form>

                </section>

                <section class="settings-panel">

                <h6 class="mb-2">Scan Cooldown</h6>
                <p class="text-muted mb-2">Set how many seconds before the same QR can be scanned again.</p>
                <form method="POST" action="./settings.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="save_scan_cooldown" value="1">
                    <div class="form-group">
                        <label for="scan_cooldown_seconds">Cooldown (seconds, 1-120)</label>
                        <input type="number" min="1" max="120" class="form-control" id="scan_cooldown_seconds" name="scan_cooldown_seconds" value="<?= (int) $scanCooldownSeconds ?>" required>
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm">Save Cooldown</button>
                </form>

                <hr>

                <h6 class="mb-2">Scan Sounds</h6>
                <p class="text-muted mb-2">Enable or disable success/error beep sounds during scanning.</p>
                <form method="POST" action="./settings.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="save_scan_sound" value="1">
                    <div class="form-group">
                        <label for="scan_sound_enabled">Sound</label>
                        <select class="form-control" id="scan_sound_enabled" name="scan_sound_enabled">
                            <option value="1" <?= $scanSoundEnabled ? 'selected' : '' ?>>ON</option>
                            <option value="0" <?= !$scanSoundEnabled ? 'selected' : '' ?>>OFF</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm">Save Sound Setting</button>
                </form>

                <hr>

                <h6 class="mb-2">Visitor Purpose Options</h6>
                <p class="text-muted mb-2">One shared list for Visitor Purpose and Visitor Destination dropdowns. Add one option per line.</p>
                <form method="POST" action="./settings.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="save_visitor_purpose_options" value="1">
                    <div class="form-group">
                        <label for="visitor_purpose_options">Options</label>
                        <textarea class="form-control" id="visitor_purpose_options" name="visitor_purpose_options" rows="7" placeholder="Example: Gymnasium"><?= h($visitorPurposeOptionsText) ?></textarea>
                        <small class="small-note">Changes here automatically sync to List of Visitors and Visitor Destination.</small>
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm">Save Visitor Options</button>
                </form>

                <hr>

                <h6 class="mb-2">Daily Auto-Close</h6>
                <p class="text-muted mb-2">Auto-close unresolved IN records from previous days using this cutoff time.</p>
                <p class="text-muted mb-2">
                    Last run today:
                    <?= !empty($autoCloseResult['ran']) ? '<strong class="text-success">Yes</strong>' : '<strong class="text-secondary">No</strong>' ?>,
                    Closed count:
                    <strong><?= (int) ($autoCloseResult['closed_count'] ?? 0) ?></strong>
                </p>
                <form method="POST" action="./settings.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="save_auto_close_cutoff" value="1">
                    <div class="form-group">
                        <label for="auto_close_cutoff_time">Cutoff Time (24-hour)</label>
                        <input type="time" class="form-control" id="auto_close_cutoff_time" name="auto_close_cutoff_time" value="<?= h($autoCloseCutoffTime) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm">Save Auto-Close Time</button>
                </form>

                </section>

                <section class="settings-panel settings-panel-wide">

                <h6 class="mb-2">Student Lists</h6>
                <p class="text-muted mb-2">Manage the departments and year levels used across the system.</p>
                <form method="POST" action="./settings.php" class="mb-3">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="save_student_lists" value="1">
                    <div class="form-group">
                        <label for="student_departments_list">Departments (comma or new line)</label>
                        <textarea class="form-control" id="student_departments_list" name="student_departments_list" rows="2" placeholder="e.g. BSIS, BSENTREP, BSTM, ICT, HE, ABM"><?= h($studentDepartmentsText) ?></textarea>
                        <small class="small-note">Use short course or strand names. Example: BSIS, COMSCI.</small>
                    </div>
                    <div class="form-group">
                        <label for="student_year_levels_list">Year Levels (comma separated)</label>
                        <input type="text" class="form-control" id="student_year_levels_list" name="student_year_levels_list" value="<?= h($studentYearLevelsText) ?>" placeholder="e.g. 11, 12, 1, 2, 3, 4, 5">
                        <small class="small-note">You can add new levels like 5 for 5th Year.</small>
                    </div>
                    <div class="rule-inline-actions">
                        <button type="submit" class="btn btn-dark btn-sm">Save Student Lists</button>
                    </div>
                </form>

                <h6 class="mb-2">Student Late/Early Rules</h6>
                <div class="rule-priority-note">
                    Rule priority: Specific schedules from Schedules page.
                </div>
                <h6 class="mb-2">Grace Rules</h6>
                <p class="text-muted mb-2">All students use Department + Year + Day schedules. Set only grace minutes here.</p>
                    <form method="POST" action="./settings.php" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="save_student_time_rules" value="1">
                        <input type="hidden" name="student_class_start_time" value="<?= h($studentClassStartTime) ?>">
                        <input type="hidden" name="student_class_end_time" value="<?= h($studentClassEndTime) ?>">
                        <div class="form-group">
                            <label for="student_late_grace_minutes">Late Grace (minutes)</label>
                            <input type="number" min="0" max="180" class="form-control" id="student_late_grace_minutes" name="student_late_grace_minutes" value="<?= (int) $studentLateGraceMinutes ?>" required>
                            <small class="small-note">Example: Start 06:00, grace 15 => late starts at 06:16.</small>
                        </div>
                        <div class="form-group">
                            <label for="student_early_out_grace_minutes">Early-Out Grace (minutes)</label>
                            <input type="number" min="0" max="180" class="form-control" id="student_early_out_grace_minutes" name="student_early_out_grace_minutes" value="<?= (int) $studentEarlyOutGraceMinutes ?>" required>
                            <small class="small-note">Example: End 19:00, grace 15 => early out only before 18:45.</small>
                        </div>
                        <div class="rule-inline-actions">
                            <button type="submit" class="btn btn-dark btn-sm">Save Grace Rules</button>
                        </div>
                    </form>
                </section>
                </div>
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
    <div class="footer-inner">
        <img src="img/images__2_-removebg-preview.png" alt="QRYPT logo" style="height: 2em; width: auto;">
        <span>Copyright 2026. TIF-TCI</span>
    </div>
</footer></body>
</html>
















