<?php declare(strict_types=1);

/**
 * lib_flash.php
 * Flash message / toast session helper universal untuk VEDIKA
 */

if (!function_exists('auth_session_start') && is_file(__DIR__ . '/lib_auth.php')) {
    require_once __DIR__ . '/lib_auth.php';
}

function flash_session_start(): void
{
    if (function_exists('auth_session_start')) {
        auth_session_start();
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function flash_set(string $key, $value): void
{
    flash_session_start();
    $_SESSION['_flash'][$key] = $value;
}

function flash_has(string $key): bool
{
    flash_session_start();
    return array_key_exists($key, $_SESSION['_flash'] ?? []);
}

function flash_get(string $key, $default = null)
{
    flash_session_start();
    return $_SESSION['_flash'][$key] ?? $default;
}

function flash_pull(string $key, $default = null)
{
    flash_session_start();

    $value = $_SESSION['_flash'][$key] ?? $default;

    if (isset($_SESSION['_flash'][$key])) {
        unset($_SESSION['_flash'][$key]);
    }

    if (isset($_SESSION['_flash']) && empty($_SESSION['_flash'])) {
        unset($_SESSION['_flash']);
    }

    return $value;
}

function flash_forget(string $key): void
{
    flash_session_start();

    if (isset($_SESSION['_flash'][$key])) {
        unset($_SESSION['_flash'][$key]);
    }

    if (isset($_SESSION['_flash']) && empty($_SESSION['_flash'])) {
        unset($_SESSION['_flash']);
    }
}

function flash_clear(): void
{
    flash_session_start();
    unset($_SESSION['_flash']);
}

function flash_toast(string $type, string $title, string $message = '', int $duration = 3500): void
{
    flash_set('toast', [
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'duration' => $duration,
    ]);
}

function flash_success(string $title = 'Berhasil', string $message = '', int $duration = 3500): void
{
    flash_toast('success', $title, $message, $duration);
}

function flash_error(string $title = 'Terjadi kesalahan', string $message = '', int $duration = 4500): void
{
    flash_toast('error', $title, $message, $duration);
}

function flash_info(string $title = 'Informasi', string $message = '', int $duration = 3500): void
{
    flash_toast('info', $title, $message, $duration);
}

function flash_warning(string $title = 'Perhatian', string $message = '', int $duration = 4000): void
{
    flash_toast('warning', $title, $message, $duration);
}

function flash_login(string $title = 'Login berhasil', string $message = 'Selamat datang'): void
{
    flash_toast('login', $title, $message, 3000);
}

function flash_logout(string $title = 'Logout', string $message = 'Anda telah keluar dari sistem'): void
{
    flash_toast('logout', $title, $message, 3000);
}