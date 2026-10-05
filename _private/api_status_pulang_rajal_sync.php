<?php
declare(strict_types=1);

require_once __DIR__ . '/lib_auth.php';
require_once __DIR__ . '/lib_csrf.php';

header('Content-Type: application/json; charset=utf-8');

function msp_sync_json(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (!auth_user()) msp_sync_json(['ok' => false, 'message' => 'Sesi login telah berakhir.'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  msp_sync_json(['ok' => false, 'message' => 'Method tidak diizinkan.'], 405);
}
if (!csrf_validate((string)($_POST['_csrf'] ?? ''), 'status_pulang_rajal_sync', false)) {
  msp_sync_json(['ok' => false, 'message' => 'Token keamanan tidak valid.'], 419);
}

$noRawat = trim((string)($_POST['no_rawat'] ?? ''));
if (!preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}\/[0-9]{6}$/', $noRawat)) {
  msp_sync_json(['ok' => false, 'message' => 'Nomor rawat tidak valid.'], 422);
}

$sql = "
  SELECT rp.stts,
    EXISTS(SELECT 1 FROM berkas_digital_perawatan b WHERE b.no_rawat=rp.no_rawat AND b.kode='013') AS aps,
    EXISTS(SELECT 1 FROM berkas_digital_perawatan b WHERE b.no_rawat=rp.no_rawat AND b.kode='025') AS berkas_rujuk,
    EXISTS(SELECT 1 FROM rujuk r WHERE r.no_rawat=rp.no_rawat) AS rujuk_poli,
    EXISTS(SELECT 1 FROM rujuk_igd r WHERE r.no_rawat=rp.no_rawat) AS rujuk_igd
  FROM reg_periksa rp
  WHERE rp.no_rawat=:no_rawat AND rp.status_lanjut='Ralan'
  LIMIT 1
";
$stmt = pdo()->prepare($sql);
$stmt->execute(['no_rawat' => $noRawat]);
$row = $stmt->fetch();
if (!$row) msp_sync_json(['ok' => false, 'message' => 'Kunjungan rawat jalan tidak ditemukan.'], 404);

$hasAps = (bool)$row['aps'];
$hasReferral = (bool)$row['berkas_rujuk'] || (bool)$row['rujuk_poli'] || (bool)$row['rujuk_igd'];
if ($hasAps && $hasReferral) {
  msp_sync_json(['ok' => false, 'message' => 'Bukti APS dan rujukan ditemukan bersamaan. Periksa manual sebelum mengubah status.'], 409);
}
if (!$hasAps && !$hasReferral) {
  msp_sync_json(['ok' => false, 'message' => 'Tidak ditemukan bukti APS atau rujukan untuk kunjungan ini.'], 422);
}

$target = $hasReferral ? 'Dirujuk' : 'Pulang Paksa';
if ((string)$row['stts'] === $target) {
  msp_sync_json(['ok' => true, 'changed' => false, 'status' => $target, 'message' => 'Status sudah sesuai dengan bukti.']);
}

$update = pdo()->prepare("UPDATE reg_periksa SET stts=:status WHERE no_rawat=:no_rawat AND status_lanjut='Ralan'");
$update->execute(['status' => $target, 'no_rawat' => $noRawat]);
if ($update->rowCount() !== 1) {
  msp_sync_json(['ok' => false, 'message' => 'Status tidak berhasil diperbarui.'], 500);
}

msp_sync_json([
  'ok' => true,
  'changed' => true,
  'status' => $target,
  'message' => 'Status berhasil diubah dari ' . $row['stts'] . ' menjadi ' . $target . '.',
]);
