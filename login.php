<?php declare(strict_types=1);

require_once __DIR__ . '/_private/config.php';
require_once __DIR__ . '/_private/lib_auth.php';
require_once __DIR__ . '/_private/lib_log.php';
require_once __DIR__ . '/_private/lib_csrf.php';
require_once __DIR__ . '/_private/lib_flash.php';

auth_session_start();

if (auth_user()) {
    header('Location: index.php');
    exit;
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require('login_form');

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $r = attempt_login($username, $password);

    if ($r['ok']) {
        $user = auth_user();

        log_login(
            $user['username'] ?? $username,
            true,
            'Login berhasil ke sistem VEDIKA',
            $user['fullname'] ?? null
        );

        flash_login('Login berhasil', 'Selamat datang di sistem VEDIKA');
        header('Location: index.php');
        exit;
    }

    log_login(
        $username !== '' ? $username : 'guest',
        false,
        $r['error'] ?? 'Login gagal'
    );

    $error = $r['error'] ?? 'Login gagal';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - VEDIKA</title>
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/toast.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d6efd22, #6f42c122);
        }
        .card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,.12);
        }
        .app-logo {
            height: 64px;
            object-fit: contain;
        }
    </style>
</head>
<body class="d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="text-center mb-3">
                            <img src="logo.png" class="app-logo" alt="Logo">
                        </div>
                        <h4 class="mb-1 text-center">VEDIKA</h4>
                        <p class="text-muted mb-4 text-center">Silakan login untuk melanjutkan.</p>

                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2">
                                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" autocomplete="off">
                            <?= csrf_input('login_form') ?>

                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" required autofocus>
                            </div>

                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <button class="btn btn-primary btn-block">Login</button>
                        </form>

                        <small class="text-muted d-block mt-3">Akses terbatas untuk petugas.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/toast.js"></script>
    <script>
        <?php if ($error): ?>
        window.addEventListener('load', function () {
            vdToast.error('Login gagal', <?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>);
        });
        <?php endif; ?>

        <?php if (isset($_GET['toast']) && $_GET['toast'] === 'logout'): ?>
        window.addEventListener('load', function () {
            vdToast.logout('Logout', 'Anda telah keluar dari sistem');
        });
        <?php endif; ?>
    </script>
</body>
</html>