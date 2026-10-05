<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';
require_once __DIR__ . '/../_private/lib_csrf.php';

function msp_e(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function msp_date(?string $value, bool $withTime = false): string {
  if (!$value || substr($value, 0, 10) === '0000-00-00') return '-';
  $time = strtotime($value);
  return $time ? date($withTime ? 'd-m-Y H:i' : 'd-m-Y', $time) : msp_e($value);
}

function msp_normalize(?string $value): string {
  $value = strtolower(trim((string)$value));
  $value = preg_replace('/\s+/', ' ', $value);
  return (string)$value;
}

function msp_code(?string $value): string {
  $value = msp_normalize($value);
  if (in_array($value, ['rujuk', 'dirujuk', 'pindah rumah sakit', 'pindah rs'], true)) return '2';
  if (in_array($value, ['aps', 'pulang paksa', 'atas permintaan sendiri', 'pulang atas permintaan sendiri'], true)) return '3';
  if (in_array($value, ['meninggal', '+'], true)) return '4';
  if (in_array($value, ['lain-lain', 'lain lain', 'lainnya'], true)) return '5';
  return '1';
}

function msp_code_label(string $code): string {
  return [
    '1' => 'Atas persetujuan dokter',
    '2' => 'Dirujuk',
    '3' => 'Atas permintaan sendiri',
    '4' => 'Meninggal',
    '5' => 'Lain-lain',
  ][$code] ?? '-';
}

function msp_status_badge(string $code): string {
  return [
    '1' => 'success', '2' => 'primary', '3' => 'warning', '4' => 'dark', '5' => 'secondary'
  ][$code] ?? 'secondary';
}

$today = date('Y-m-d');
$tglAwal = (string)($_GET['tgl_awal'] ?? $today);
$tglAkhir = (string)($_GET['tgl_akhir'] ?? $today);
$filter = (string)($_GET['hasil'] ?? 'masalah');
$jenis = (string)($_GET['jenis'] ?? 'ranap');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAwal)) $tglAwal = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAkhir)) $tglAkhir = $today;
if ($tglAwal > $tglAkhir) [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
if (!in_array($filter, ['masalah', 'sesuai', 'semua'], true)) $filter = 'masalah';
if (!in_array($jenis, ['rajal', 'ranap'], true)) $jenis = 'ranap';

$evidenceSelect = "
    EXISTS(SELECT 1 FROM berkas_digital_perawatan bdp WHERE bdp.no_rawat = rp.no_rawat AND bdp.kode = '013') AS ada_berkas_aps,
    EXISTS(SELECT 1 FROM berkas_digital_perawatan bdp WHERE bdp.no_rawat = rp.no_rawat AND bdp.kode = '025') AS ada_berkas_rujukan,
    EXISTS(SELECT 1 FROM rujuk rj WHERE rj.no_rawat = rp.no_rawat) AS ada_rujuk_poli,
    EXISTS(SELECT 1 FROM rujuk_igd ri WHERE ri.no_rawat = rp.no_rawat) AS ada_rujuk_igd,
    EXISTS(SELECT 1 FROM rujuk_rawat_inap rri WHERE rri.no_rawat = rp.no_rawat) AS ada_rujuk_ranap
";

if ($jenis === 'ranap') {
  $sql = "
  SELECT
    rp.no_rawat,
    rp.no_rkm_medis,
    rp.tgl_registrasi,
    rp.stts AS reg_periksa_stts,
    ki.tgl_masuk,
    ki.jam_masuk,
    ki.tgl_keluar,
    ki.jam_keluar,
    ki.stts_pulang AS kamar_inap_stts_pulang,
    rpr.cara_keluar AS resume_cara_keluar,
    (SELECT bs.no_sep FROM bridging_sep bs WHERE bs.no_rawat = rp.no_rawat ORDER BY bs.tglsep DESC, bs.no_sep DESC LIMIT 1) AS no_sep,
    $evidenceSelect
  FROM reg_periksa rp
  INNER JOIN (
    SELECT ki1.*
    FROM kamar_inap ki1
    INNER JOIN (
      SELECT no_rawat, MAX(CONCAT(tgl_keluar, ' ', jam_keluar)) AS waktu_keluar_terakhir
      FROM kamar_inap
      WHERE COALESCE(stts_pulang, '') <> 'Pindah Kamar'
        AND tgl_keluar IS NOT NULL AND tgl_keluar <> '' AND tgl_keluar <> '0000-00-00'
      GROUP BY no_rawat
    ) terakhir
      ON terakhir.no_rawat = ki1.no_rawat
     AND terakhir.waktu_keluar_terakhir = CONCAT(ki1.tgl_keluar, ' ', ki1.jam_keluar)
    WHERE COALESCE(ki1.stts_pulang, '') <> 'Pindah Kamar'
  ) ki ON ki.no_rawat = rp.no_rawat
  LEFT JOIN resume_pasien_ranap rpr ON rpr.no_rawat = rp.no_rawat
  WHERE rp.status_lanjut = 'Ranap'
    AND ki.tgl_keluar BETWEEN :tgl_awal AND :tgl_akhir
  ORDER BY ki.tgl_keluar DESC, ki.jam_keluar DESC, rp.no_rawat DESC
";
} else {
  $sql = "
    SELECT
      rp.no_rawat, rp.no_rkm_medis, rp.tgl_registrasi,
      rp.stts AS reg_periksa_stts,
      NULL AS tgl_masuk, NULL AS jam_masuk,
      rp.tgl_registrasi AS tgl_keluar, rp.jam_reg AS jam_keluar,
      NULL AS kamar_inap_stts_pulang, NULL AS resume_cara_keluar,
      (SELECT bs.no_sep FROM bridging_sep bs WHERE bs.no_rawat = rp.no_rawat ORDER BY bs.tglsep DESC, bs.no_sep DESC LIMIT 1) AS no_sep,
      $evidenceSelect
    FROM reg_periksa rp
    WHERE rp.status_lanjut = 'Ralan'
      AND rp.tgl_registrasi BETWEEN :tgl_awal AND :tgl_akhir
    ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC, rp.no_rawat DESC
  ";
}

$stmt = pdo()->prepare($sql);
$stmt->execute(['tgl_awal' => $tglAwal, 'tgl_akhir' => $tglAkhir]);
$allRows = $stmt->fetchAll();

$totalSesuai = 0;
$totalMasalah = 0;
foreach ($allRows as &$row) {
  $row['kode_reg'] = msp_code($row['reg_periksa_stts']);
  $row['kode_payload'] = $jenis === 'ranap' ? msp_code($row['kamar_inap_stts_pulang']) : $row['kode_reg'];
  $row['kode_resume'] = $jenis === 'ranap' && $row['resume_cara_keluar'] !== null && trim((string)$row['resume_cara_keluar']) !== ''
    ? msp_code($row['resume_cara_keluar']) : null;

  $issues = [];
  if ($jenis === 'ranap') {
    if ($row['kode_resume'] === null) {
      $issues[] = 'Resume belum ada';
    } elseif ($row['kode_payload'] !== $row['kode_resume']) {
      $issues[] = 'Kamar dan resume berbeda';
    }
  }

  $hasReferral = (bool)$row['ada_berkas_rujukan'] || (bool)$row['ada_rujuk_poli']
    || (bool)$row['ada_rujuk_igd'] || (bool)$row['ada_rujuk_ranap'];
  if ($hasReferral && $row['kode_payload'] !== '2') $issues[] = 'Ada bukti rujuk, status bukan rujuk';
  if ((bool)$row['ada_berkas_aps'] && $row['kode_payload'] !== '3') $issues[] = 'Ada Laporan APS, status bukan APS';

  $row['issues'] = array_values(array_unique($issues));
  $row['bermasalah'] = count($row['issues']) > 0;
  $row['bermasalah'] ? $totalMasalah++ : $totalSesuai++;
}
unset($row);

$rows = array_values(array_filter($allRows, static function (array $row) use ($filter): bool {
  if ($filter === 'masalah') return $row['bermasalah'];
  if ($filter === 'sesuai') return !$row['bermasalah'];
  return true;
}));
$syncToken = csrf_token('status_pulang_rajal_sync');
$auditToken = csrf_token('status_pulang_eklaim_audit');
$claimPrintToken = csrf_token('status_pulang_claim_print');

$latestAudits = [];
$auditStmt = pdo()->prepare("SELECT a.* FROM mlite_vedika_dc_status_audit a
  INNER JOIN (
    SELECT no_rawat, nosep, MAX(id) id FROM mlite_vedika_dc_status_audit
    WHERE jenis_rawat=:jenis AND tanggal_pelayanan BETWEEN :tgl_awal AND :tgl_akhir
    GROUP BY no_rawat, nosep
  ) latest ON latest.id=a.id");
$auditStmt->execute([
  'jenis' => $jenis === 'ranap' ? 'Ranap' : 'Ralan',
  'tgl_awal' => $tglAwal,
  'tgl_akhir' => $tglAkhir,
]);
foreach ($auditStmt->fetchAll() as $audit) $latestAudits[$audit['no_rawat'].'|'.$audit['nosep']] = $audit;

$auditJobs = [];
foreach ($allRows as &$row) {
  $key = $row['no_rawat'].'|'.($row['no_sep'] ?? '');
  $row['audit'] = $latestAudits[$key] ?? null;
  if ($row['audit']) {
    if (!empty($row['audit']['error_message'])) {
      $mspAuditError = (string)$row['audit']['error_message'];
      $row['issues'][] = stripos($mspAuditError, 'discharge_status tidak ditemukan') !== false || strcasecmp(trim($mspAuditError), 'Belum kirim klaim') === 0
        ? 'Belum kirim klaim' : 'Pemeriksaan E-Klaim gagal';
    } elseif ((int)$row['audit']['is_match'] !== 1) {
      $row['issues'][] = 'E-Klaim dan SIMRS berbeda';
    }
    $row['issues'] = array_values(array_unique($row['issues']));
    $row['bermasalah'] = count($row['issues']) > 0;
  }
  if (!empty($row['no_sep'])) $auditJobs[] = ['no_rawat'=>$row['no_rawat'], 'nosep'=>$row['no_sep']];
}
unset($row);
$totalMasalah = 0;
$totalSesuai = 0;
foreach ($allRows as $row) $row['bermasalah'] ? $totalMasalah++ : $totalSesuai++;
$rows = array_values(array_filter($allRows, static function (array $row) use ($filter): bool {
  if ($filter === 'masalah') return $row['bermasalah'];
  if ($filter === 'sesuai') return !$row['bermasalah'];
  return true;
}));
?>

<style>
  .msp-id { font-family: Consolas, "Courier New", monospace; white-space: nowrap; }
  .msp-status { min-width: 155px; }
  .msp-evidence { min-width: 205px; }
  .msp-result { min-width: 235px; white-space: normal; }
  .msp-sub { color: #6c757d; font-size: .78rem; margin-top: 2px; }
  .msp-card-number { font-size: 1.55rem; font-weight: 700; line-height: 1; }
  #dtStatusPulang tbody tr:hover { background: #f8fbff; }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-1">Monitoring Status Pulang</h4>
    <div class="text-muted small">Rawat jalan memakai status registrasi; rawat inap memakai status kamar terakhir.</div>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $jenis === 'rajal' ? 'active' : '' ?>" href="?page=monitoring_status_pulang&jenis=rajal&tgl_awal=<?= msp_e($tglAwal) ?>&tgl_akhir=<?= msp_e($tglAkhir) ?>&hasil=<?= msp_e($filter) ?>"><i class="fas fa-stethoscope mr-1"></i> Rawat Jalan</a></li>
  <li class="nav-item"><a class="nav-link <?= $jenis === 'ranap' ? 'active' : '' ?>" href="?page=monitoring_status_pulang&jenis=ranap&tgl_awal=<?= msp_e($tglAwal) ?>&tgl_akhir=<?= msp_e($tglAkhir) ?>&hasil=<?= msp_e($filter) ?>"><i class="fas fa-bed mr-1"></i> Rawat Inap</a></li>
</ul>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" class="form-row align-items-end">
      <input type="hidden" name="page" value="monitoring_status_pulang">
      <input type="hidden" name="jenis" value="<?= msp_e($jenis) ?>">
      <div class="form-group col-sm-4 col-md-3 mb-2">
        <label for="tgl_awal" class="small mb-1"><?= $jenis === 'ranap' ? 'Tanggal Pulang Mulai' : 'Tanggal Registrasi Mulai' ?></label>
        <input type="date" class="form-control" id="tgl_awal" name="tgl_awal" value="<?= msp_e($tglAwal) ?>">
      </div>
      <div class="form-group col-sm-4 col-md-3 mb-2">
        <label for="tgl_akhir" class="small mb-1"><?= $jenis === 'ranap' ? 'Tanggal Pulang Akhir' : 'Tanggal Registrasi Akhir' ?></label>
        <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" value="<?= msp_e($tglAkhir) ?>">
      </div>
      <div class="form-group col-sm-4 col-md-3 mb-2">
        <label for="hasil" class="small mb-1">Tampilkan</label>
        <select class="form-control" id="hasil" name="hasil">
          <option value="masalah" <?= $filter === 'masalah' ? 'selected' : '' ?>>Perlu ditinjau</option>
          <option value="sesuai" <?= $filter === 'sesuai' ? 'selected' : '' ?>>Sesuai</option>
          <option value="semua" <?= $filter === 'semua' ? 'selected' : '' ?>>Semua data</option>
        </select>
      </div>
      <div class="form-group col-md-3 mb-2">
        <button class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i> Tampilkan</button>
      </div>
    </form>
  </div>
</div>

<div class="row mb-3">
  <div class="col-sm-4 mb-2 mb-sm-0"><div class="card h-100"><div class="card-body py-3"><div class="text-muted small"><?= $jenis === 'ranap' ? 'Total pasien pulang' : 'Total kunjungan rawat jalan' ?></div><div class="msp-card-number"><?= count($allRows) ?></div></div></div></div>
  <div class="col-sm-4 mb-2 mb-sm-0"><div class="card h-100 border-danger"><div class="card-body py-3"><div class="text-danger small">Perlu ditinjau</div><div class="msp-card-number text-danger"><?= $totalMasalah ?></div></div></div></div>
  <div class="col-sm-4"><div class="card h-100 border-success"><div class="card-body py-3"><div class="text-success small">Sesuai</div><div class="msp-card-number text-success"><?= $totalSesuai ?></div></div></div></div>
</div>

<?php if ($jenis === 'rajal'): ?>
<div class="d-flex justify-content-end mb-3">
  <button type="button" id="btnBulkSyncRajal" class="btn btn-warning">
    <i class="fas fa-sync-alt mr-1"></i> Sinkron Semua Rawat Jalan
  </button>
</div>
<?php endif; ?>

<div class="card border-info mb-3">
  <div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between">
    <div class="mr-3">
      <div class="font-weight-bold">Bandingkan dengan E-Klaim</div>
      <div class="text-muted small">Memanggil get_claim_data untuk <?= count($auditJobs) ?> SEP dan menyimpan hasilnya ke riwayat audit.</div>
      <div id="auditProgress" class="small text-info mt-1"></div>
    </div>
    <button type="button" id="btnAuditEklaim" class="btn btn-info mt-2 mt-md-0" <?= count($auditJobs) === 0 ? 'disabled' : '' ?>>
      <i class="fas fa-cloud-download-alt mr-1"></i> Cek Semua ke E-Klaim
    </button>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table id="dtStatusPulang" class="table table-bordered table-striped table-sm" style="width:100%">
        <thead class="thead-light"><tr>
          <th>Pasien</th><th><?= $jenis === 'ranap' ? 'Waktu Rawat' : 'Tanggal Kunjungan' ?></th><th>Status SIMRS</th><th>Status E-Klaim</th><?php if ($jenis === 'ranap'): ?><th>Resume Ranap</th><?php endif; ?><th>Bukti APS / Rujuk</th><th>Hasil</th><?php if ($jenis === 'rajal'): ?><th>Aksi</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td class="msp-id">
              <div><?= msp_e($row['no_rawat']) ?></div>
              <div class="msp-sub">RM: <?= msp_e($row['no_rkm_medis']) ?></div>
              <div class="msp-sub">SEP: <?= msp_e($row['no_sep'] ?: '-') ?></div>
            </td>
            <td data-order="<?= msp_e($row['tgl_keluar'].' '.$row['jam_keluar']) ?>">
              <?php if ($jenis === 'ranap'): ?>
                <div><b>Pulang:</b> <?= msp_date($row['tgl_keluar'].' '.$row['jam_keluar'], true) ?></div>
                <div class="msp-sub">Masuk: <?= msp_date($row['tgl_masuk'].' '.$row['jam_masuk'], true) ?></div>
              <?php else: ?>
                <div><?= msp_date($row['tgl_registrasi'].' '.$row['jam_keluar'], true) ?></div>
              <?php endif; ?>
            </td>
            <td class="msp-status">
              <span class="badge badge-<?= msp_status_badge($row['kode_payload']) ?>">Kode <?= msp_e($row['kode_payload']) ?></span>
              <div><?= msp_e(($jenis === 'ranap' ? $row['kamar_inap_stts_pulang'] : $row['reg_periksa_stts']) ?: '-') ?></div>
              <div class="msp-sub"><?= msp_e(msp_code_label($row['kode_payload'])) ?></div>
            </td>
            <td class="msp-status">
              <?php $audit = $row['audit']; ?>
              <?php if (!$audit): ?>
                <span class="badge badge-secondary">Belum dicek</span>
              <?php elseif ($audit['error_message']): ?>
                <?php $mspBelumKirim = stripos((string)$audit['error_message'], 'discharge_status tidak ditemukan') !== false || strcasecmp(trim((string)$audit['error_message']), 'Belum kirim klaim') === 0; ?>
                <span class="badge badge-<?= $mspBelumKirim ? 'warning' : 'danger' ?>"><?= $mspBelumKirim ? 'Belum kirim klaim' : 'Gagal' ?></span>
                <?php if (!$mspBelumKirim): ?><div class="msp-sub"><?= msp_e($audit['error_message']) ?></div><?php endif; ?>
              <?php else: ?>
                <span class="badge <?= (int)$audit['is_match'] === 1 ? 'badge-success' : 'badge-danger' ?>">Kode <?= msp_e($audit['eklaim_discharge_status'] ?: '-') ?></span>
                <div><?= (int)$audit['is_match'] === 1 ? 'Sesuai SIMRS' : 'Berbeda dengan SIMRS' ?></div>
                <div class="msp-sub">DC Kemenkes: <?= msp_e($audit['kemenkes_dc_status'] ?: '-') ?> · BPJS: <?= msp_e($audit['bpjs_dc_status'] ?: '-') ?></div>
                <div class="msp-sub"><?= msp_date($audit['checked_at'], true) ?></div>
              <?php endif; ?>
            </td>
            <?php if ($jenis === 'ranap'): ?>
            <td class="msp-status">
              <?php if ($row['kode_resume'] === null): ?>
                <span class="badge badge-danger">Belum ada</span>
              <?php else: ?>
                <span class="badge badge-<?= msp_status_badge($row['kode_resume']) ?>">Kode <?= msp_e($row['kode_resume']) ?></span>
                <div><?= msp_e($row['resume_cara_keluar']) ?></div>
              <?php endif; ?>
            </td>
            <?php endif; ?>
            <td class="msp-evidence">
              <?php if ($row['ada_berkas_aps']): ?><span class="badge badge-warning mr-1 mb-1">013 Laporan APS</span><?php endif; ?>
              <?php if ($row['ada_berkas_rujukan']): ?><span class="badge badge-primary mr-1 mb-1">025 Lembar Rujukan</span><?php endif; ?>
              <?php if ($row['ada_rujuk_ranap']): ?><span class="badge badge-primary mr-1 mb-1">Rujuk Rawat Inap</span><?php endif; ?>
              <?php if ($row['ada_rujuk_igd']): ?><span class="badge badge-info mr-1 mb-1">Rujuk IGD</span><?php endif; ?>
              <?php if ($row['ada_rujuk_poli']): ?><span class="badge badge-info mr-1 mb-1">Rujuk Poli</span><?php endif; ?>
              <?php if (!$row['ada_berkas_aps'] && !$row['ada_berkas_rujukan'] && !$row['ada_rujuk_ranap'] && !$row['ada_rujuk_igd'] && !$row['ada_rujuk_poli']): ?>
                <span class="text-muted">Tidak ditemukan</span>
              <?php endif; ?>
            </td>
            <td class="msp-result">
              <?php if ($row['bermasalah']): ?>
                <span class="badge badge-danger mb-1">Perlu ditinjau</span>
                <?php foreach ($row['issues'] as $issue): ?><div><i class="fas fa-exclamation-circle text-danger mr-1"></i><?= msp_e($issue) ?></div><?php endforeach; ?>
                <?php if (!empty($row['no_sep']) && !empty($row['audit']) && empty($row['audit']['error_message']) && !empty($row['audit']['eklaim_discharge_status'])): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-claim-print mt-2" data-no-rawat="<?= msp_e($row['no_rawat']) ?>" data-nosep="<?= msp_e($row['no_sep']) ?>">
                    <i class="fas fa-file-pdf mr-1"></i> Claim Print
                  </button>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-success">Sesuai</span>
              <?php endif; ?>
            </td>
            <?php if ($jenis === 'rajal'): ?>
            <td class="text-nowrap">
              <?php
                $mspHasAps = (bool)$row['ada_berkas_aps'];
                $mspHasReferral = (bool)$row['ada_berkas_rujukan'] || (bool)$row['ada_rujuk_poli'] || (bool)$row['ada_rujuk_igd'];
                $mspConflict = $mspHasAps && $mspHasReferral;
                $mspTarget = $mspHasReferral && !$mspHasAps ? 'Dirujuk' : ($mspHasAps && !$mspHasReferral ? 'Pulang Paksa' : '');
              ?>
              <?php if ($mspConflict): ?>
                <span class="badge badge-danger">Bukti konflik</span>
              <?php elseif ($mspTarget !== '' && $row['reg_periksa_stts'] !== $mspTarget): ?>
                <button type="button" class="btn btn-sm btn-primary btn-sync-rajal" data-no-rawat="<?= msp_e($row['no_rawat']) ?>" data-target="<?= msp_e($mspTarget) ?>">
                  <i class="fas fa-sync-alt mr-1"></i> Ubah ke <?= msp_e($mspTarget) ?>
                </button>
              <?php elseif ($mspTarget !== ''): ?>
                <span class="badge badge-success">Sudah sinkron</span>
              <?php else: ?>
                <span class="text-muted">Tidak ada bukti</span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="alert alert-light border mt-3 small mb-0">
  <b>Catatan:</b> Menu ini belum memanggil <code>get_claim_data</code> E-Klaim. Rawat jalan menggunakan <code>reg_periksa.stts</code>; rawat inap menggunakan <code>kamar_inap.stts_pulang</code> pada kamar keluar terakhir dan tidak membandingkannya dengan status registrasi.
</div>

<script>
(function () {
  function waitForDataTables(callback, tries) {
    if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) { callback(jQuery); return; }
    if (tries > 0) setTimeout(function () { waitForDataTables(callback, tries - 1); }, 50);
  }
  waitForDataTables(function ($) {
    if ($.fn.dataTable.isDataTable('#dtStatusPulang')) return;
    $('#dtStatusPulang').DataTable({
      responsive: true, pageLength: 25, lengthMenu: [10, 25, 50, 100], order: [], autoWidth: false,
      dom: 'lBfrtip',
      buttons: [
        { extend: 'copyHtml5', text: '<i class="fas fa-copy"></i> Copy', title: null, header: true },
        { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', title: 'Monitoring Status Pulang <?= $jenis === 'ranap' ? 'Rawat Inap' : 'Rawat Jalan' ?> <?= msp_e($tglAwal) ?> s.d. <?= msp_e($tglAkhir) ?>' }
      ],
      language: {
        emptyTable: 'Tidak ada data pada tanggal dan filter ini.', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ data', infoEmpty: 'Tidak ada data', zeroRecords: 'Data tidak ditemukan',
        paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
      }
    });

    $('#dtStatusPulang').on('click', '.btn-sync-rajal', async function () {
      var button = this;
      var noRawat = button.getAttribute('data-no-rawat');
      var target = button.getAttribute('data-target');
      if (!window.confirm('Ubah status ' + noRawat + ' menjadi "' + target + '" sesuai bukti yang ditemukan?')) return;

      var oldHtml = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan';
      try {
        var body = new URLSearchParams();
        body.set('_csrf', <?= json_encode($syncToken) ?>);
        body.set('no_rawat', noRawat);
        var response = await fetch('_private/api_status_pulang_rajal_sync.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
          body: body.toString()
        });
        var result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'Status gagal diperbarui.');
        window.alert(result.message);
        window.location.reload();
      } catch (error) {
        window.alert(error.message || 'Status gagal diperbarui.');
        button.disabled = false;
        button.innerHTML = oldHtml;
      }
    });

    $('#btnBulkSyncRajal').on('click', async function () {
      var button = this;
      var message = 'Sinkronkan seluruh status Rawat Jalan tanggal <?= msp_e(msp_date($tglAwal)) ?> sampai <?= msp_e(msp_date($tglAkhir)) ?>?\n\nStatus akan diubah menjadi Dirujuk atau Pulang Paksa berdasarkan bukti. Data konflik akan dilewati.';
      if (!window.confirm(message)) return;

      var oldHtml = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menyinkronkan';
      try {
        var body = new URLSearchParams();
        body.set('_csrf', <?= json_encode($syncToken) ?>);
        body.set('tgl_awal', <?= json_encode($tglAwal) ?>);
        body.set('tgl_akhir', <?= json_encode($tglAkhir) ?>);
        var response = await fetch('_private/api_status_pulang_rajal_bulk.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
          body: body.toString()
        });
        var result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.message || 'Sinkronisasi bulk gagal.');
        window.alert(result.message);
        window.location.reload();
      } catch (error) {
        window.alert(error.message || 'Sinkronisasi bulk gagal.');
        button.disabled = false;
        button.innerHTML = oldHtml;
      }
    });

    $('#btnAuditEklaim').on('click', async function () {
      var button = this;
      var jobs = <?= json_encode($auditJobs, JSON_UNESCAPED_SLASHES) ?>;
      var jenis = <?= json_encode($jenis === 'ranap' ? 'Ranap' : 'Ralan') ?>;
      if (!window.confirm('Cek ' + jobs.length + ' SEP ke E-Klaim dan simpan hasil audit? Proses dapat memerlukan beberapa menit.')) return;

      button.disabled = true;
      var progress = document.getElementById('auditProgress');
      var success = 0, failed = 0, match = 0, different = 0, noClaim = 0;
      for (var i = 0; i < jobs.length; i++) {
        progress.textContent = 'Memeriksa ' + (i + 1) + ' dari ' + jobs.length + ' SEP…';
        try {
          var body = new URLSearchParams();
          body.set('_csrf', <?= json_encode($auditToken) ?>);
          body.set('no_rawat', jobs[i].no_rawat);
          body.set('nosep', jobs[i].nosep);
          body.set('jenis', jenis);
          var response = await fetch('_private/api_status_pulang_eklaim_audit.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: body.toString()
          });
          var result = await response.json();
          if (!response.ok || !result.ok) throw new Error(result.message || 'Gagal');
          success++;
          if (result.no_claim) noClaim++;
          else if (result.match) match++;
          else different++;
        } catch (error) { failed++; }
      }
      progress.textContent = 'Selesai: ' + match + ' sesuai, ' + different + ' berbeda, ' + noClaim + ' belum kirim klaim, dan ' + failed + ' gagal.';
      window.alert(progress.textContent);
      window.location.reload();
    });

    $('#dtStatusPulang').on('click', '.btn-claim-print', async function () {
      var button = this;
      var oldHtml = button.innerHTML;
      var opened = window.open('', '_blank');
      if (!opened) {
        window.alert('Popup diblokir browser. Izinkan popup untuk membuka Claim Print.');
        return;
      }
      opened.document.write('<p style="font-family:sans-serif;padding:20px">Menyiapkan Claim Print…</p>');
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Membuka';
      try {
        var body = new URLSearchParams();
        body.set('_csrf', <?= json_encode($claimPrintToken) ?>);
        body.set('no_rawat', button.getAttribute('data-no-rawat'));
        body.set('nosep', button.getAttribute('data-nosep'));
        var response = await fetch('_private/api_status_pulang_claim_print.php', {
          method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: body.toString()
        });
        if (!response.ok) {
          var result = await response.json().catch(function(){ return {}; });
          throw new Error(result.message || 'Claim Print gagal dibuat.');
        }
        var blob = await response.blob();
        var url = URL.createObjectURL(blob);
        opened.location.href = url;
        setTimeout(function(){ URL.revokeObjectURL(url); }, 60000);
      } catch (error) {
        opened.close();
        window.alert(error.message || 'Claim Print gagal dibuat.');
      } finally {
        button.disabled = false;
        button.innerHTML = oldHtml;
      }
    });
  }, 100);
})();
</script>
