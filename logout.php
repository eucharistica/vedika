<?php declare(strict_types=1);

require_once __DIR__ . '/_private/config.php';
require_once __DIR__ . '/_private/lib_auth.php';
require_once __DIR__ . '/_private/lib_log.php';
require_once __DIR__ . '/_private/lib_flash.php';

log_logout('User logout dari sistem VEDIKA');
flash_logout('Logout', 'Anda telah keluar dari sistem');
logout();

header('Location: login.php?toast=logout');
exit;