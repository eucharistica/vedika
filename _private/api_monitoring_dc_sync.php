<?php
declare(strict_types=1);

require_once __DIR__ . '/lib_auth.php';
require_once __DIR__ . '/lib_csrf.php';
require_once __DIR__ . '/lib_eklaim.php';

header('Content-Type: application/json; charset=utf-8');

function dc_sync_json(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (!auth_user()) dc_sync_json(['ok' => false, 'message' => 'Sesi login telah berakhir.'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  dc_sync_json(['ok' => false, 'message' => 'Method tidak diizinkan.'], 405);
}

$token = isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : '';
if (!csrf_validate($token, 'monitoring_dc_sync', false)) {
  dc_sync_json(['ok' => false, 'message' => 'Token keamanan tidak valid.'], 419);
}

$noSep = strtoupper(trim((string)($_POST['no_sep'] ?? '')));
if (!preg_match('/^[A-Z0-9]{10,40}$/', $noSep)) {
  dc_sync_json(['ok' => false, 'message' => 'Nomor SEP tidak valid.'], 422);
}

$stmt = pdo()->prepare('SELECT 1 FROM bridging_sep WHERE no_sep = :no_sep LIMIT 1');
$stmt->execute(['no_sep' => $noSep]);
if (!$stmt->fetchColumn()) dc_sync_json(['ok' => false, 'message' => 'SEP tidak ditemukan.'], 404);

$stmt = pdo()->prepare('SELECT 1 FROM inacbg_data_terkirim WHERE no_sep = :no_sep LIMIT 1');
$stmt->execute(['no_sep' => $noSep]);
if ($stmt->fetchColumn()) {
  dc_sync_json(['ok' => true, 'sent' => true, 'saved' => false, 'message' => 'Sudah tercatat sebelumnya.']);
}

try {
  $status = eklaim_dc_status(eklaim_get_claim_data($noSep));
  if (!$status['sent']) {
    dc_sync_json([
      'ok' => true,
      'sent' => false,
      'saved' => false,
      'message' => 'Belum terkirim di E-Klaim.',
      'status' => $status,
    ]);
  }

  $insert = pdo()->prepare("
    INSERT INTO inacbg_data_terkirim (no_sep, nik)
    SELECT :insert_no_sep, 'Terkirim DC'
    WHERE NOT EXISTS (
      SELECT 1 FROM inacbg_data_terkirim WHERE no_sep = :check_no_sep
    )
  ");
  $insert->execute([
    'insert_no_sep' => $noSep,
    'check_no_sep' => $noSep,
  ]);

  dc_sync_json([
    'ok' => true,
    'sent' => true,
    'saved' => $insert->rowCount() > 0,
    'message' => $insert->rowCount() > 0
      ? 'Terkirim di E-Klaim dan berhasil dicatat.'
      : 'Terkirim di E-Klaim dan sudah tercatat.',
  ]);
} catch (Throwable $e) {
  error_log('Monitoring DC sync [' . $noSep . ']: ' . $e->getMessage());
  dc_sync_json(['ok' => false, 'message' => $e->getMessage()], 502);
}
