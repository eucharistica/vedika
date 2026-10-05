<?php
declare(strict_types=1);
require_once __DIR__ . '/_private/lib_auth.php';
require_login();

// Halaman monitoring berisi data dinamis; jangan simpan respons error/hasil lama di browser atau proxy.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$page = $_GET['page'] ?? 'mlite_rajal';

$allowed = [
  'mlite_rajal'      => __DIR__.'/pages/mlite_rajal.php',
  'mlite_ranap'      => __DIR__.'/pages/mlite_ranap.php',
  'vclaim_kunjungan' => __DIR__.'/pages/vclaim_kunjungan.php',
  'vclaim_klaim'     => __DIR__.'/pages/vclaim_klaim.php',
  'analisis_rujukan' => __DIR__.'/pages/analisis_rujukan.php',
  'klaim_ranap_kamar'=> __DIR__.'/pages/klaim_ranap_kamar.php',
  'grouping_ditolak' => __DIR__.'/pages/grouping_ditolak.php',
  'monitoring_dc'    => __DIR__.'/pages/monitoring_dc.php',
  'monitoring_status_pulang' => __DIR__.'/pages/monitoring_status_pulang.php',
  'monitoring_kelas_ranap' => __DIR__.'/pages/monitoring_kelas_ranap.php',
  'sep_tidak_terbit' => __DIR__.'/pages/sep_tidak_terbit.php',
  'monitoring_expertise_radiologi' => __DIR__.'/pages/monitoring_expertise_radiologi.php',
  'laporan_transfusi_ctg' => __DIR__.'/pages/laporan_transfusi_ctg.php',
];
if (!isset($allowed[$page])) $page = 'mlite_rajal';

$user = auth_user();

include __DIR__.'/layout/header.php';
include __DIR__.'/layout/sidebar.php';
include $allowed[$page];
include __DIR__.'/layout/footer.php';
