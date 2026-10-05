<?php
declare(strict_types=1);

require_once __DIR__ . '/lib_auth.php';
require_once __DIR__ . '/lib_csrf.php';

header('Content-Type: application/json; charset=utf-8');

function stb_json(array $data, int $status = 200): void {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (!auth_user()) stb_json(['ok'=>false, 'message'=>'Sesi login telah berakhir.'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') stb_json(['ok'=>false, 'message'=>'Method tidak diizinkan.'], 405);
if (!csrf_validate((string)($_POST['_csrf'] ?? ''), 'sep_tidak_terbit_bulk', false)) {
  stb_json(['ok'=>false, 'message'=>'Token keamanan tidak valid.'], 419);
}

$tglAwal = trim((string)($_POST['tgl_awal'] ?? ''));
$tglAkhir = trim((string)($_POST['tgl_akhir'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAwal) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAkhir)) {
  stb_json(['ok'=>false, 'message'=>'Rentang tanggal tidak valid.'], 422);
}
if ($tglAwal > $tglAkhir) [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
$today = date('Y-m-d');
if ($tglAwal >= $today || $tglAkhir >= $today) {
  stb_json(['ok'=>false, 'message'=>'Pembatalan hanya diperbolehkan sampai tanggal kemarin.'], 422);
}

$sql = "
  UPDATE reg_periksa rp
  INNER JOIN (SELECT DISTINCT kd_dokter FROM maping_dokter_dpjpvclaim) md ON md.kd_dokter=rp.kd_dokter
  LEFT JOIN bridging_sep bs ON bs.no_rawat=rp.no_rawat
  SET rp.stts='Batal'
  WHERE rp.kd_pj='BPJ'
    AND rp.stts<>'Batal'
    AND rp.status_lanjut='Ralan'
    AND rp.kd_poli NOT IN ('U0033','U0041','U0046','U0039','U0015','U0016','U0035')
    AND rp.status_lanjut='Ralan'
    AND rp.kd_poli NOT IN ('U0033','U0041','U0046','U0039','U0015','U0016','U0035')
    AND rp.tgl_registrasi BETWEEN :tgl_awal AND :tgl_akhir
    AND bs.no_sep IS NULL
";
try {
  $stmt = pdo()->prepare($sql);
  $stmt->execute(['tgl_awal'=>$tglAwal, 'tgl_akhir'=>$tglAkhir]);
  $updated = $stmt->rowCount();
  stb_json(['ok'=>true, 'updated'=>$updated, 'message'=>$updated.' kunjungan tanpa SEP berhasil diubah menjadi Batal.']);
} catch (Throwable $e) {
  error_log('Bulk SEP tidak terbit: '.$e->getMessage());
  stb_json(['ok'=>false, 'message'=>'Pembatalan bulk gagal. Tidak ada data yang diubah.'], 500);
}
