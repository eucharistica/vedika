<?php declare(strict_types=1);

/**
 * lib_log.php
 * Logging universal untuk VEDIKA
 * Support: login, logout, create, add, update, delete, error, download, info
 */

if (!function_exists('pdo')) {
    require_once __DIR__ . '/config.php';
}

if (!function_exists('auth_user')) {
    require_once __DIR__ . '/lib_auth.php';
}

function vedika_set_log_error(?string $message): void
{
    $GLOBALS['VEDIKA_LOG_LAST_ERROR'] = $message;
}

function vedika_log_last_error(): ?string
{
    return $GLOBALS['VEDIKA_LOG_LAST_ERROR'] ?? null;
}

function vedika_client_ip(): ?string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

    if (!$ip) {
        return null;
    }

    if (strpos($ip, ',') !== false) {
        $parts = explode(',', $ip);
        $ip = trim($parts[0]);
    }

    return substr(trim($ip), 0, 45);
}

function vedika_user_agent(): ?string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if (!$ua) {
        return null;
    }

    return substr(trim($ua), 0, 255);
}

function write_log(
    string $action,
    ?string $module = null,
    ?string $description = null,
    ?string $ref_id = null,
    string $status = 'success',
    ?string $username_override = null,
    ?string $fullname_override = null
): bool {
    vedika_set_log_error(null);

    try {
        if (!function_exists('pdo')) {
            throw new RuntimeException('Fungsi pdo() tidak ditemukan. Pastikan config.php termuat.');
        }

        $user = function_exists('auth_user') ? auth_user() : null;

        $username = $username_override ?? ($user['username'] ?? 'guest');
        $fullname = $fullname_override ?? ($user['fullname'] ?? null);

        $username = trim((string) $username);
        if ($username === '') {
            $username = 'guest';
        }

        $status = in_array($status, ['success', 'failed', 'info'], true) ? $status : 'info';

        $sql = "INSERT INTO vedika_log
                (username, fullname, action, module, description, ref_id, ip_address, user_agent, status)
                VALUES
                (:username, :fullname, :action, :module, :description, :ref_id, :ip_address, :user_agent, :status)";

        $st = pdo()->prepare($sql);

        return $st->execute([
            ':username'   => substr($username, 0, 100),
            ':fullname'   => $fullname !== null ? substr((string) $fullname, 0, 150) : null,
            ':action'     => substr(trim($action), 0, 50),
            ':module'     => $module !== null ? substr(trim($module), 0, 100) : null,
            ':description'=> $description,
            ':ref_id'     => $ref_id !== null ? substr((string) $ref_id, 0, 100) : null,
            ':ip_address' => vedika_client_ip(),
            ':user_agent' => vedika_user_agent(),
            ':status'     => $status,
        ]);
    } catch (\Throwable $e) {
        vedika_set_log_error($e->getMessage());
        error_log('vedika_log gagal insert: ' . $e->getMessage());
        return false;
    }
}

/* ---------- Helper universal ---------- */

function log_login(string $username, bool $success = true, ?string $desc = null, ?string $fullname = null): bool
{
    return write_log(
        'login',
        'auth',
        $desc ?? ($success ? 'User login berhasil' : 'User login gagal'),
        null,
        $success ? 'success' : 'failed',
        $username,
        $fullname
    );
}

function log_logout(?string $desc = 'User logout'): bool
{
    return write_log('logout', 'auth', $desc, null, 'success');
}

function log_create(string $module, ?string $ref_id = null, ?string $desc = null): bool
{
    return write_log('create', $module, $desc ?? 'Data dibuat', $ref_id, 'success');
}

function log_add(string $module, ?string $ref_id = null, ?string $desc = null): bool
{
    return write_log('add', $module, $desc ?? 'Data ditambahkan', $ref_id, 'success');
}

function log_update(string $module, ?string $ref_id = null, ?string $desc = null): bool
{
    return write_log('update', $module, $desc ?? 'Data diperbarui', $ref_id, 'success');
}

function log_delete(string $module, ?string $ref_id = null, ?string $desc = null): bool
{
    return write_log('delete', $module, $desc ?? 'Data dihapus', $ref_id, 'success');
}

function log_error(string $module, ?string $desc = null, ?string $ref_id = null): bool
{
    return write_log('error', $module, $desc ?? 'Terjadi kesalahan sistem', $ref_id, 'failed');
}

function log_download(string $module, ?string $ref_id = null, ?string $desc = null): bool
{
    return write_log('download', $module, $desc ?? 'File diunduh', $ref_id, 'success');
}

function log_info(string $module, ?string $desc = null, ?string $ref_id = null): bool
{
    return write_log('info', $module, $desc ?? 'Informasi sistem', $ref_id, 'info');
}