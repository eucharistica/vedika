<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';

$tanggal_cari_raw  = $_POST['tanggal_cari']  ?? '';
$tanggal_cari2_raw = $_POST['tanggal_cari2'] ?? '';

$tgl_cari  = $tanggal_cari_raw  !== '' ? FormatTgl("Y-m-d", $tanggal_cari_raw)  : date('Y-m-d');
$tgl_cari2 = $tanggal_cari2_raw !== '' ? FormatTgl("Y-m-d", $tanggal_cari2_raw) : date('Y-m-d');

$sql_emr = query("
    SELECT
      reg_periksa.tgl_registrasi,
      kamar_inap.tgl_masuk,
      kamar_inap.tgl_keluar,
      kamar_inap.kd_kamar,
      reg_periksa.no_rawat,
      reg_periksa.no_rkm_medis,
      maping_poli_bpjs_real.nm_poli_bpjs,
      pasien.nm_pasien,
      pasien.no_peserta,
      IFNULL(bridging_sep.no_sep,'-') AS nosep,
      IF(bridging_sep.jnspelayanan = '1','RI','RJ') AS sep,
      IFNULL(mlite_vedika.`status`,'Index') AS status_vedika
    FROM
      reg_periksa
      INNER JOIN kamar_inap ON kamar_inap.no_rawat = reg_periksa.no_rawat
      INNER JOIN pasien ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis
      INNER JOIN maping_poli_bpjs_real ON maping_poli_bpjs_real.kd_poli_rs = reg_periksa.kd_poli
      LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
      LEFT JOIN mlite_vedika ON mlite_vedika.no_rawat = reg_periksa.no_rawat
    WHERE
      kamar_inap.tgl_keluar BETWEEN '$tgl_cari' AND '$tgl_cari2'
      AND reg_periksa.kd_pj = 'BPJ'
      AND reg_periksa.status_lanjut = 'Ranap'
      AND kamar_inap.stts_pulang != 'Pindah Kamar'
    GROUP BY reg_periksa.no_rawat
    ORDER BY kamar_inap.tgl_keluar
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
  <h4 class="mb-0">Monitoring Mlite - Kunjungan Ranap</h4>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form action="index.php?page=mlite_ranap" method="post">
      <div class="form-row">
        <div class="col-md-3">
          <label>Dari</label>
          <input type="date" class="form-control" name="tanggal_cari" value="<?= htmlspecialchars($tgl_cari) ?>">
        </div>
        <div class="col-md-3">
          <label>Sampai</label>
          <input type="date" class="form-control" name="tanggal_cari2" value="<?= htmlspecialchars($tgl_cari2) ?>">
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
    <table id="dtMliteRanap" class="table table-bordered table-striped table-sm" style="width:100%">
      <thead>
      <tr>
        <th>No</th>
        <th>Nama Pasien</th>
        <th>No RM</th>
        <th>No SEP</th>
        <th>No Peserta</th>
        <th>Tanggal Masuk</th>
        <th>Tanggal Pulang</th>
        <th>Kamar</th>
        <th>No Rawat</th>
        <th>Status Vedika</th>
      </tr>
      </thead>
      <tbody>
      <?php $no=0; foreach($data_emr as $row): $no++; ?>
        <tr>
          <td><?= $no ?></td>
          <td><?= htmlspecialchars($row['nm_pasien']); ?></td>
          <td><?= htmlspecialchars($row['no_rkm_medis']); ?></td>
          <td><?= htmlspecialchars($row['nosep']); ?></td>
          <td><?= htmlspecialchars($row['no_peserta']); ?></td>
          <td><?= htmlspecialchars($row['tgl_masuk']); ?></td>
          <td><?= htmlspecialchars($row['tgl_keluar']); ?></td>
          <td><?= htmlspecialchars($row['kd_kamar']); ?></td>
          <td><?= htmlspecialchars($row['no_rawat']); ?></td>
          <td><?= htmlspecialchars($row['status_vedika']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-3">
  <div class="card-body">
    <h5>Rekap Status Vedika (Ranap)</h5>
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

