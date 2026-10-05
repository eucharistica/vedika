<?php
declare(strict_types=1);

require_once __DIR__ . '/lib_auth.php';
require_once __DIR__ . '/lib_csrf.php';
require_once __DIR__ . '/lib_eklaim.php';

header('Content-Type: application/json; charset=utf-8');

function spa_json(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function spa_code(?string $value): string {
  $value = strtolower(trim((string)$value));
  $value = (string)preg_replace('/\s+/', ' ', $value);
  if (in_array($value, ['rujuk', 'dirujuk', 'pindah rumah sakit', 'pindah rs'], true)) return '2';
  if (in_array($value, ['aps', 'pulang paksa', 'atas permintaan sendiri', 'pulang atas permintaan sendiri'], true)) return '3';
  if (in_array($value, ['meninggal', '+'], true)) return '4';
  if (in_array($value, ['lain-lain', 'lain lain', 'lainnya'], true)) return '5';
  return '1';
}

if (!auth_user()) spa_json(['ok' => false, 'message' => 'Sesi login telah berakhir.'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') spa_json(['ok' => false, 'message' => 'Method tidak diizinkan.'], 405);
if (!csrf_validate((string)($_POST['_csrf'] ?? ''), 'status_pulang_eklaim_audit', false)) {
  spa_json(['ok' => false, 'message' => 'Token keamanan tidak valid.'], 419);
}

$noRawat = trim((string)($_POST['no_rawat'] ?? ''));
$noSep = strtoupper(trim((string)($_POST['nosep'] ?? '')));
$jenis = (string)($_POST['jenis'] ?? '');
if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}\/[0-9]{6}$/', $noRawat)
    || !preg_match('/^[A-Z0-9]{10,40}$/', $noSep)
    || !in_array($jenis, ['Ralan', 'Ranap'], true)) {
  spa_json(['ok' => false, 'message' => 'Parameter audit tidak valid.'], 422);
}

if ($jenis === 'Ralan') {
  $stmt = pdo()->prepare("SELECT rp.tgl_registrasi tanggal, rp.stts status_simrs
    FROM reg_periksa rp INNER JOIN bridging_sep bs ON bs.no_rawat=rp.no_rawat AND bs.no_sep=:nosep
    WHERE rp.no_rawat=:no_rawat AND rp.status_lanjut='Ralan' LIMIT 1");
} else {
  $stmt = pdo()->prepare("SELECT ki.tgl_keluar tanggal, ki.stts_pulang status_simrs
    FROM reg_periksa rp INNER JOIN bridging_sep bs ON bs.no_rawat=rp.no_rawat AND bs.no_sep=:nosep
    INNER JOIN kamar_inap ki ON ki.no_rawat=rp.no_rawat
    WHERE rp.no_rawat=:no_rawat AND rp.status_lanjut='Ranap'
      AND COALESCE(ki.stts_pulang,'') <> 'Pindah Kamar'
      AND ki.tgl_keluar IS NOT NULL AND ki.tgl_keluar <> '' AND ki.tgl_keluar <> '0000-00-00'
    ORDER BY ki.tgl_keluar DESC, ki.jam_keluar DESC LIMIT 1");
}
$stmt->execute(['nosep' => $noSep, 'no_rawat' => $noRawat]);
$local = $stmt->fetch();
if (!$local) spa_json(['ok' => false, 'message' => 'SEP dan kunjungan SIMRS tidak ditemukan.'], 404);

$expected = spa_code($local['status_simrs']);
$user = auth_user();
$response = null;
$error = null;
$rawDischarge = null;
$discharge = null;
$kemenkes = null;
$bpjs = null;
$claim = null;

try {
  $response = eklaim_get_claim_data($noSep);
  $envelope = isset($response['response']) && is_array($response['response']) ? $response['response'] : $response;
  $data = isset($envelope['data']) && is_array($envelope['data']) ? $envelope['data'] : [];
  $rawDischarge = isset($data['discharge_status']) ? (string)$data['discharge_status'] : null;
  $discharge = $rawDischarge !== null && $rawDischarge !== '' ? $rawDischarge : null;
  $kemenkes = isset($data['kemenkes_dc_status_cd']) ? (string)$data['kemenkes_dc_status_cd'] : null;
  $bpjs = isset($data['bpjs_dc_status_cd']) ? (string)$data['bpjs_dc_status_cd'] : null;
  $claim = isset($data['klaim_status_cd']) ? (string)$data['klaim_status_cd'] : null;
  if ($discharge === null) $error = 'Belum kirim klaim';
} catch (Throwable $e) {
  $error = $e->getMessage();
}

$sentStmt = pdo()->prepare("SELECT
  EXISTS(SELECT 1 FROM inacbg_data_terkirim WHERE no_sep=:sep1)
  OR EXISTS(SELECT 1 FROM mlite_vedika_grouping_recap WHERE nosep=:sep2 AND LOWER(kemenkes_dc_status)='sent' AND LOWER(bpjs_dc_status)='sent')");
$sentStmt->execute(['sep1' => $noSep, 'sep2' => $noSep]);
$localDc = $sentStmt->fetchColumn() ? 'Terkirim DC' : 'Belum Terkirim DC';
$isMatch = $error === null && $discharge !== null && $discharge === $expected ? 1 : 0;
$responseJson = $response === null ? null : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$insert = pdo()->prepare("INSERT INTO mlite_vedika_dc_status_audit
  (no_rawat,nosep,jenis_rawat,tanggal_pelayanan,local_dc_status,eklaim_discharge_status_raw,
   eklaim_discharge_status,expected_discharge_status,is_match,kemenkes_dc_status,bpjs_dc_status,
   claim_status,response_json,error_message,checked_by,checked_at)
  VALUES (:no_rawat,:nosep,:jenis,:tanggal,:local_dc,:raw_status,:status,:expected,:is_match,
   :kemenkes,:bpjs,:claim,:response_json,:error,:checked_by,NOW())");
$insert->execute([
  'no_rawat'=>$noRawat, 'nosep'=>$noSep, 'jenis'=>$jenis, 'tanggal'=>$local['tanggal'],
  'local_dc'=>$localDc, 'raw_status'=>$rawDischarge, 'status'=>$discharge, 'expected'=>$expected,
  'is_match'=>$isMatch, 'kemenkes'=>$kemenkes, 'bpjs'=>$bpjs, 'claim'=>$claim,
  'response_json'=>$responseJson, 'error'=>$error, 'checked_by'=>(string)($user['username'] ?? ''),
]);

$isNoClaim = $error === 'Belum kirim klaim';
spa_json([
  'ok' => $error === null || $isNoClaim,
  'saved' => true,
  'match' => (bool)$isMatch,
  'no_claim' => $isNoClaim,
  'expected' => $expected,
  'eklaim' => $discharge,
  'message' => $error ?? ($isMatch ? 'Status sesuai.' : 'Status E-Klaim berbeda dengan SIMRS.'),
], $error === null || $isNoClaim ? 200 : 502);
