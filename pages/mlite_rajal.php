<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';

$tanggal_cari_raw  = $_POST['tanggal_cari']  ?? '';
$tanggal_cari2_raw = $_POST['tanggal_cari2'] ?? '';

$tgl_cari  = $tanggal_cari_raw  !== '' ? FormatTgl("Y-m-d", $tanggal_cari_raw)  : date('Y-m-d');
$tgl_cari2 = $tanggal_cari2_raw !== '' ? FormatTgl("Y-m-d", $tanggal_cari2_raw) : date('Y-m-d');
$filter_poli = trim((string)($_POST['filter_poli'] ?? ''));
$filter_dokter = trim((string)($_POST['filter_dokter'] ?? ''));
$filter_status = trim((string)($_POST['filter_status'] ?? ''));
if (!in_array($filter_status, ['', 'Index', 'Lengkap', 'Pengajuan', 'Perbaiki'], true)) $filter_status = '';
$safe_filter_poli = addslashes($filter_poli);
$safe_filter_dokter = addslashes($filter_dokter);

$filter_base = "
  FROM reg_periksa
  INNER JOIN pasien ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis
  INNER JOIN maping_poli_bpjs_real ON maping_poli_bpjs_real.kd_poli_rs = reg_periksa.kd_poli
  INNER JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    AND bridging_sep.jnspelayanan = '2'
    AND bridging_sep.no_sep <> ''
  WHERE reg_periksa.tgl_registrasi BETWEEN '$tgl_cari' AND '$tgl_cari2'
    AND reg_periksa.kd_pj = 'BPJ'
    AND reg_periksa.status_lanjut = 'Ralan'
    AND reg_periksa.stts NOT IN ('Batal')
    AND maping_poli_bpjs_real.kd_poli_bpjs NOT IN ('ANT','NEO')
";

$poli_options = query("SELECT DISTINCT reg_periksa.kd_poli, maping_poli_bpjs_real.nm_poli_bpjs
  $filter_base ORDER BY maping_poli_bpjs_real.nm_poli_bpjs");
$dokter_options = query("SELECT DISTINCT reg_periksa.kd_dokter, dokter.nm_dokter
  FROM reg_periksa
  INNER JOIN dokter ON dokter.kd_dokter = reg_periksa.kd_dokter
  INNER JOIN maping_poli_bpjs_real ON maping_poli_bpjs_real.kd_poli_rs = reg_periksa.kd_poli
  INNER JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    AND bridging_sep.jnspelayanan = '2'
    AND bridging_sep.no_sep <> ''
  WHERE reg_periksa.tgl_registrasi BETWEEN '$tgl_cari' AND '$tgl_cari2'
    AND reg_periksa.kd_pj = 'BPJ' AND reg_periksa.status_lanjut = 'Ralan'
    AND reg_periksa.stts NOT IN ('Batal')
    AND maping_poli_bpjs_real.kd_poli_bpjs NOT IN ('ANT','NEO')
  ORDER BY dokter.nm_dokter");

$extra_filter = '';
if ($filter_poli !== '') $extra_filter .= " AND reg_periksa.kd_poli = '$safe_filter_poli'";
if ($filter_dokter !== '') $extra_filter .= " AND reg_periksa.kd_dokter = '$safe_filter_dokter'";
if ($filter_status !== '') {
  $safe_filter_status = addslashes($filter_status);
  $extra_filter .= " AND IFNULL(mlite_vedika.`status`, 'Index') = '$safe_filter_status'";
}

$sql_emr = query("
  SELECT
    reg_periksa.tgl_registrasi,
    reg_periksa.no_rawat,
    reg_periksa.no_rkm_medis,
    dokter.nm_dokter,
    maping_poli_bpjs_real.nm_poli_bpjs,
    pasien.nm_pasien,
    pasien.no_peserta,
    IFNULL(bridging_sep.no_sep,'-') AS nosep,
    IF(bridging_sep.jnspelayanan = '2','RJ','RI') AS sep,
    IFNULL(mlite_vedika.`status`,'Index') AS status_vedika
  FROM
    reg_periksa
    INNER JOIN pasien ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis
    INNER JOIN maping_poli_bpjs_real ON maping_poli_bpjs_real.kd_poli_rs = reg_periksa.kd_poli
    INNER JOIN dokter ON dokter.kd_dokter = reg_periksa.kd_dokter
    INNER JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
      AND bridging_sep.jnspelayanan = '2'
      AND bridging_sep.no_sep <> ''
    LEFT JOIN mlite_vedika ON mlite_vedika.no_rawat = reg_periksa.no_rawat
  WHERE
    reg_periksa.tgl_registrasi BETWEEN '$tgl_cari' AND '$tgl_cari2'
    AND reg_periksa.kd_pj = 'BPJ'
    AND reg_periksa.status_lanjut = 'Ralan'
    AND reg_periksa.stts NOT IN ('Batal')
    AND maping_poli_bpjs_real.kd_poli_bpjs NOT IN ('ANT','NEO')
    $extra_filter
  GROUP BY reg_periksa.no_rawat
  ORDER BY reg_periksa.tgl_registrasi
");

$data_emr = [];
$summary  = [];
$total_summary = 0;

while ($row = fetch_array($sql_emr)) {
  $data_emr[] = $row;
  $status = $row['status_vedika'] ?? 'Index';
  if ($status === '' || $status === null) $status = 'Index';
  $summary[$status] = ($summary[$status] ?? 0) + 1;
  $total_summary++;
}

$statuses_order = ['Index', 'Lengkap', 'Pengajuan', 'Perbaiki'];
?>

<div class="d-flex align-items-center justify-content-between mb-2">
  <h4 class="mb-0">Monitoring Mlite - Kunjungan Rajal</h4>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form action="index.php?page=mlite_rajal" method="post">
      <div class="form-row">
        <div class="col-md-3">
          <label>Dari</label>
          <input type="date" class="form-control" name="tanggal_cari" value="<?= htmlspecialchars($tgl_cari) ?>">
        </div>
        <div class="col-md-3">
          <label>Sampai</label>
          <input type="date" class="form-control" name="tanggal_cari2" value="<?= htmlspecialchars($tgl_cari2) ?>">
        </div>
        <div class="col-md-3">
          <label>Poli</label>
          <select class="form-control" name="filter_poli">
            <option value="">Semua Poli</option>
            <?php while ($opt = fetch_array($poli_options)): ?>
              <option value="<?= htmlspecialchars($opt['kd_poli']) ?>" <?= $filter_poli === $opt['kd_poli'] ? 'selected' : '' ?>><?= htmlspecialchars($opt['nm_poli_bpjs']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label>Dokter</label>
          <select class="form-control" name="filter_dokter">
            <option value="">Semua Dokter</option>
            <?php while ($opt = fetch_array($dokter_options)): ?>
              <option value="<?= htmlspecialchars($opt['kd_dokter']) ?>" <?= $filter_dokter === $opt['kd_dokter'] ? 'selected' : '' ?>><?= htmlspecialchars($opt['nm_dokter']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label>Status Vedika</label>
          <select class="form-control" name="filter_status">
            <option value="">Semua Status</option>
            <option value="Index" <?= $filter_status === 'Index' ? 'selected' : '' ?>>Index</option>
            <option value="Lengkap" <?= $filter_status === 'Lengkap' ? 'selected' : '' ?>>Lengkap</option>
            <option value="Pengajuan" <?= $filter_status === 'Pengajuan' ? 'selected' : '' ?>>Pengajuan</option>
            <option value="Perbaiki" <?= $filter_status === 'Perbaiki' ? 'selected' : '' ?>>Perbaiki</option>
          </select>
        </div>
        <div class="col-md-3 align-self-end">
          <button class="btn btn-info btn-block"><i class="fa fa-search"></i> Search</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <table id="dtMliteRajal" class="table table-bordered table-striped table-sm" style="width:100%">
      <thead>
      <tr>
        <th>No</th><th>Tanggal Registrasi</th><th>No Rawat</th><th>No RM</th><th>Nama Poli</th>
        <th>Dokter</th><th>Nama Pasien</th><th>No Peserta</th><th>No SEP</th><th>Status Vedika</th>
      </tr>
      </thead>
      <tbody>
      <?php $no=0; foreach($data_emr as $row): $no++; ?>
        <tr>
          <td><?= $no ?></td>
          <td><?= htmlspecialchars($row['tgl_registrasi']) ?></td>
          <td><?= htmlspecialchars($row['no_rawat']) ?></td>
          <td><?= htmlspecialchars($row['no_rkm_medis']) ?></td>
          <td><?= htmlspecialchars($row['nm_poli_bpjs']) ?></td>
          <td><?= htmlspecialchars($row['nm_dokter']) ?></td>
          <td><?= htmlspecialchars($row['nm_pasien']) ?></td>
          <td><?= htmlspecialchars($row['no_peserta']) ?></td>
          <td><?= htmlspecialchars($row['nosep']) ?></td>
          <td><?= htmlspecialchars($row['status_vedika']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-3">
  <div class="card-body">
    <h5>Rekap Status Vedika (Rajal)</h5>
    <table class="table table-bordered table-sm mb-0">
      <thead><tr><th>Status</th><th>Jumlah</th><th>Persentase</th></tr></thead>
      <tbody>
      <?php foreach ($statuses_order as $st):
        $jumlah = $summary[$st] ?? 0;
        $persen = $total_summary > 0 ? ($jumlah / $total_summary) * 100 : 0;
      ?>
        <tr>
          <td><?= htmlspecialchars($st) ?></td>
          <td><?= $jumlah ?></td>
          <td><?= number_format($persen, 2, ',', '.') ?> %</td>
        </tr>
      <?php endforeach; ?>
        <tr class="font-weight-bold">
          <td>Total</td>
          <td><?= $total_summary ?></td>
          <td><?= $total_summary > 0 ? '100,00 %' : '0,00 %' ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
