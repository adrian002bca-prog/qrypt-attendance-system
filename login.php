<?php
include('./conn/bootstrap.php');

if (is_logged_in()) {
    redirect_to('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_reject();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $status = login_rate_limit_status($username);

    if (isset($status['storage_ok']) && $status['storage_ok'] === false) {
        $error = 'Login is temporarily unavailable. Please try again in a minute.';
    } elseif (!$status['allowed']) {
        $wait = max(1, (int) $status['retry_after']);
        $error = 'Too many login attempts. Try again in ' . $wait . ' seconds.';
    } elseif (hash_equals(auth_username(), $username) && password_verify($password, auth_password_hash())) {
        clear_login_failures($username);
        login_user($username);
        redirect_to('index.php');
    } else {
        record_login_failure($username);
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | QRYPT</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&display=swap');

        :root {
            --bg-cream: #fff4e6;
            --bg-peach: #ffe3c2;
            --accent: #ffd8a8;
            --ink: #2e2e2e;
            --muted: #6c757d;
        }

        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, var(--bg-cream) 0%, var(--bg-peach) 100%);
        }

        .login-shell {
            width: min(920px, 92vw);
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(0,0,0,0.18);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .login-shell:hover {
            transform: translateY(-4px);
            box-shadow: 0 26px 55px rgba(0,0,0,0.22);
        }

        .login-left {
            padding: 36px 40px;
        }

        .login-right {
            background: linear-gradient(135deg, #f7cfa0 0%, #f2b87b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
            position: relative;
        }

        .login-right::before,
        .login-right::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0.35;
        }

        .login-right::before {
            width: 220px;
            height: 220px;
            background: #fff;
            top: -60px;
            right: -80px;
        }

        .login-right::after {
            width: 160px;
            height: 160px;
            background: #ffd8a8;
            bottom: -50px;
            left: -40px;
        }

        .brand-card {
            background: rgba(255,255,255,0.9);
            border-radius: 14px;
            padding: 22px 24px;
            text-align: center;
            box-shadow: 0 10px 22px rgba(0,0,0,0.12);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .brand-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 28px rgba(0,0,0,0.18);
        }

        .brand-logo {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            background: #fff6ea;
            display: grid;
            place-items: center;
            margin: 0 auto 12px;
            overflow: hidden;
            border: 2px solid #f3cfa6;
        }

        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .brand-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 4px;
        }

        .brand-sub {
            font-size: 12px;
            color: var(--muted);
        }

        .login-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--ink);
        }

        .login-sub {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 18px;
        }

        .btn-login {
            background: #2f2f2f;
            border-color: #2f2f2f;
        }

        @media (max-width: 768px) {
            .login-shell {
                grid-template-columns: 1fr;
            }
            .login-right {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <div class="login-left">
            <div class="login-title">QRYPT Login</div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="./login.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" value="" required>
                </div>
                <button type="submit" class="btn btn-dark btn-block btn-login">Login</button>
            </form>
        </div>
        <div class="login-right">
            <div class="brand-card">
                <div class="brand-logo">
                    <img src="img/images__2_-removebg-preview.png" alt="TIFTCI Logo">
                </div>
                <div class="brand-title">QRYPT Admin</div>
                <div class="brand-sub">Attendance & Visitors</div>
            </div>
        </div>
    </div>
</body>
</html>


