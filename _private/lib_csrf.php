<?php declare(strict_types=1);

/**
 * lib_csrf.php
 * Helper CSRF universal untuk VEDIKA
 */

if (!function_exists('auth_session_start') && is_file(__DIR__ . '/lib_auth.php')) {
    require_once __DIR__ . '/lib_auth.php';
}

function csrf_session_start(): void
{
    if (function_exists('auth_session_start')) {
        auth_session_start();
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function csrf_generate(string $key = 'default', bool $force = false): string
{
    csrf_session_start();

    if (
        $force ||
        empty($_SESSION['_csrf'][$key]) ||
        !is_string($_SESSION['_csrf'][$key])
    ) {
        $_SESSION['_csrf'][$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'][$key];
}

function csrf_token(string $key = 'default'): string
{
    return csrf_generate($key);
}

function csrf_regenerate(string $key = 'default'): string
{
    return csrf_generate($key, true);
}

function csrf_input(string $key = 'default', string $field = '_csrf'): string
{
    $token = htmlspecialchars(csrf_token($key), ENT_QUOTES, 'UTF-8');
    $field = htmlspecialchars($field, ENT_QUOTES, 'UTF-8');

    return '<input type="hidden" name="' . $field . '" value="' . $token . '">';
}

function csrf_meta(string $key = 'default'): string
{
    $token = htmlspecialchars(csrf_token($key), ENT_QUOTES, 'UTF-8');
    return '<meta name="csrf-token" content="' . $token . '">';
}

function csrf_validate(?string $submittedToken, string $key = 'default', bool $rotate = true): bool
{
    csrf_session_start();

    $sessionToken = $_SESSION['_csrf'][$key] ?? null;

    if (
        !is_string($submittedToken) || $submittedToken === '' ||
        !is_string($sessionToken) || $sessionToken === ''
    ) {
        return false;
    }

    $valid = hash_equals($sessionToken, $submittedToken);

    if ($valid && $rotate) {
        csrf_regenerate($key);
    }

    return $valid;
}

function csrf_require(string $key = 'default', string $field = '_csrf', bool $rotate = true): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    $submittedToken = $_POST[$field] ?? null;

    if (!csrf_validate(is_string($submittedToken) ? $submittedToken : null, $key, $rotate)) {
        http_response_code(419);
        exit('CSRF token tidak valid atau sudah kedaluwarsa.');
    }
}