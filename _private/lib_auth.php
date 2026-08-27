<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function auth_session_start(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;

  session_name('RSUDVCLAIMSESS');
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  session_start();
}

function auth_user(): ?array {
  auth_session_start();
  return $_SESSION['user'] ?? null;
}

function require_login(): void {
  if (!auth_user()) {
    header('Location: login.php');
    exit;
  }
}

function attempt_login(string $username, string $password): array {
  $username = trim($username);
  if ($username === '' || $password === '') return ['ok'=>false,'error'=>'Username/password wajib diisi'];

  $st = pdo()->prepare("SELECT username, fullname, password FROM mlite_users WHERE username = :u LIMIT 1");
  $st->execute([':u' => $username]);
  $u = $st->fetch();

  if (!$u) return ['ok'=>false,'error'=>'Username tidak ditemukan'];

  if (!password_verify($password, (string)$u['password'])) {
    return ['ok'=>false,'error'=>'Password salah'];
  }

  auth_session_start();
  session_regenerate_id(true); // security [web:119]

  $_SESSION['user'] = [
    'username' => $u['username'],
    'fullname' => $u['fullname'],
  ];

  return ['ok'=>true];
}

function logout(): void {
  auth_session_start();
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
  }
  session_destroy();
}
