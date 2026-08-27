<?php
declare(strict_types=1);
require_once __DIR__ . '/_private/lib_auth.php';
require_login();

$page = $_GET['page'] ?? 'mlite_rajal';

$allowed = [
  'mlite_rajal'      => __DIR__.'/pages/mlite_rajal.php',
  'mlite_ranap'      => __DIR__.'/pages/mlite_ranap.php',
  'vclaim_kunjungan' => __DIR__.'/pages/vclaim_kunjungan.php',
  'vclaim_klaim'     => __DIR__.'/pages/vclaim_klaim.php',
  'analisis_rujukan' => __DIR__.'/pages/analisis_rujukan.php',
  'klaim_ranap_kamar'=> __DIR__.'/pages/klaim_ranap_kamar.php',
];
if (!isset($allowed[$page])) $page = 'mlite_rajal';

$user = auth_user();

include __DIR__.'/layout/header.php';
include __DIR__.'/layout/sidebar.php';
include $allowed[$page];
include __DIR__.'/layout/footer.php';
