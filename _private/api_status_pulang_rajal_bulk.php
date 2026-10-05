<?php
declare(strict_types=1);

require_once __DIR__ . '/lib_auth.php';
require_once __DIR__ . '/lib_csrf.php';

header('Content-Type: application/json; charset=utf-8');

function msp_bulk_json(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (!auth_user()) msp_bulk_json(['ok' => false, 'message' => 'Sesi login telah berakhir.'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  msp_bulk_json(['ok' => false, 'message' => 'Method tidak diizinkan.'], 405);
}
if (!csrf_validate((string)($_POST['_csrf'] ?? ''), 'status_pulang_rajal_sync', false)) {
  msp_bulk_json(['ok' => false, 'message' => 'Token keamanan tidak valid.'], 419);
}

$tglAwal = trim((string)($_POST['tgl_awal'] ?? ''));
$tglAkhir = trim((string)($_POST['tgl_akhir'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAwal) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAkhir)) {
  msp_bulk_json(['ok' => false, 'message' => 'Rentang tanggal tidak valid.'], 422);
}
if ($tglAwal > $tglAkhir) [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];

$sql = "
  SELECT rp.no_rawat, rp.stts,
    EXISTS(SELECT 1 FROM berkas_digital_perawatan b WHERE b.no_rawat=rp.no_rawat AND b.kode='013') AS aps,
    EXISTS(SELECT 1 FROM berkas_digital_perawatan b WHERE b.no_rawat=rp.no_rawat AND b.kode='025') AS berkas_rujuk,
    EXISTS(SELECT 1 FROM rujuk r WHERE r.no_rawat=rp.no_rawat) AS rujuk_poli,
    EXISTS(SELECT 1 FROM rujuk_igd r WHERE r.no_rawat=rp.no_rawat) AS rujuk_igd
  FROM reg_periksa rp
  WHERE rp.status_lanjut='Ralan' AND rp.tgl_registrasi BETWEEN :tgl_awal AND :tgl_akhir
";
$stmt = pdo()->prepare($sql);
$stmt->execute(['tgl_awal' => $tglAwal, 'tgl_akhir' => $tglAkhir]);
$rows = $stmt->fetchAll();

$updated = 0;
$alreadyCorrect = 0;
$withoutEvidence = 0;
$conflicts = 0;
$db = pdo();
$update = $db->prepare("UPDATE reg_periksa SET stts=:status WHERE no_rawat=:no_rawat AND status_lanjut='Ralan'");

try {
  $db->beginTransaction();
  foreach ($rows as $row) {
    $hasAps = (bool)$row['aps'];
    $hasReferral = (bool)$row['berkas_rujuk'] || (bool)$row['rujuk_poli'] || (bool)$row['rujuk_igd'];
    if ($hasAps && $hasReferral) { $conflicts++; continue; }
    if (!$hasAps && !$hasReferral) { $withoutEvidence++; continue; }

    $target = $hasReferral ? 'Dirujuk' : 'Pulang Paksa';
    if ((string)$row['stts'] === $target) { $alreadyCorrect++; continue; }
    $update->execute(['status' => $target, 'no_rawat' => $row['no_rawat']]);
    $updated += $update->rowCount();
  }
  $db->commit();
} catch (Throwable $e) {
  if ($db->inTransaction()) $db->rollBack();
  error_log('Bulk status pulang rajal: ' . $e->getMessage());
  msp_bulk_json(['ok' => false, 'message' => 'Sinkronisasi bulk gagal dan seluruh perubahan dibatalkan.'], 500);
}

msp_bulk_json([
  'ok' => true,
  'updated' => $updated,
  'already_correct' => $alreadyCorrect,
  'without_evidence' => $withoutEvidence,
  'conflicts' => $conflicts,
  'message' => $updated . ' status berhasil diperbarui. ' . $alreadyCorrect . ' sudah sesuai, ' .
    $withoutEvidence . ' tanpa bukti, dan ' . $conflicts . ' konflik dilewati.',
]);
