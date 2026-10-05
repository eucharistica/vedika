<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';

function mer_e(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function mer_datetime(?string $date, ?string $time): string {
  if (!$date || $date === '0000-00-00') return '-';
  $timestamp = strtotime($date.' '.($time ?: '00:00:00'));
  return $timestamp ? date('d-m-Y H:i', $timestamp) : mer_e($date.' '.$time);
}

$today = date('Y-m-d');
$tglAwal = (string)($_GET['tgl_awal'] ?? $today);
$tglAkhir = (string)($_GET['tgl_akhir'] ?? $today);
$filter = (string)($_GET['hasil'] ?? 'belum');
$jenisRawat = (string)($_GET['jenis_rawat'] ?? 'semua');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAwal)) $tglAwal = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAkhir)) $tglAkhir = $today;
if ($tglAwal > $tglAkhir) [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
if (!in_array($filter, ['belum','sudah','semua'], true)) $filter = 'belum';
if (!in_array($jenisRawat, ['semua','Ralan','Ranap'], true)) $jenisRawat = 'semua';

$sql = "
  SELECT
    pr.no_rawat, rp.no_rkm_medis, ps.nm_pasien,
    COALESCE(pl.nm_poli, '-') AS nm_poli,
    pr.status AS jenis_rawat, pr.kd_jenis_prw, jpr.nm_perawatan,
    pr.tgl_periksa, pr.jam, rp.tgl_registrasi,
    CASE WHEN rp.kd_pj = 'BPJ' AND bs.tglsep IS NOT NULL THEN bs.tglsep ELSE rp.tgl_registrasi END AS tanggal_filter,
    hr.hasil, hr.tgl_hasil, hr.jam_hasil
  FROM periksa_radiologi pr
  INNER JOIN reg_periksa rp ON rp.no_rawat = pr.no_rawat
  INNER JOIN pasien ps ON ps.no_rkm_medis = rp.no_rkm_medis
  LEFT JOIN poliklinik pl ON pl.kd_poli = rp.kd_poli
  LEFT JOIN (
    SELECT no_rawat, MAX(tglsep) AS tglsep
    FROM bridging_sep
    GROUP BY no_rawat
  ) bs ON bs.no_rawat = rp.no_rawat
  INNER JOIN jns_perawatan_radiologi jpr ON jpr.kd_jenis_prw = pr.kd_jenis_prw
  LEFT JOIN (
    SELECT no_rawat, tgl_periksa, jam,
      MAX(NULLIF(TRIM(hasil), '')) AS hasil,
      MAX(tgl_hasil) AS tgl_hasil,
      MAX(jam_hasil) AS jam_hasil
    FROM hasil_radiologi
    GROUP BY no_rawat, tgl_periksa, jam
  ) hr ON hr.no_rawat = pr.no_rawat
      AND hr.tgl_periksa = pr.tgl_periksa
      AND hr.jam = pr.jam
  WHERE (CASE WHEN rp.kd_pj = 'BPJ' AND bs.tglsep IS NOT NULL THEN bs.tglsep ELSE rp.tgl_registrasi END)
    BETWEEN :tgl_awal AND :tgl_akhir
    AND pr.kd_jenis_prw NOT IN ('RAD-11101','RAD-111042')
    AND pr.kd_jenis_prw NOT IN ('J000120','J000145')
    AND (:jenis_rawat = 'semua' OR pr.status = :jenis_rawat_status)
  ORDER BY pr.no_rawat, pr.tgl_periksa, pr.jam, jpr.nm_perawatan
";
$stmt = pdo()->prepare($sql);
$stmt->execute([
  'tgl_awal'=>$tglAwal,
  'tgl_akhir'=>$tglAkhir,
  'jenis_rawat'=>$jenisRawat,
  'jenis_rawat_status'=>$jenisRawat,
]);
$allRows = $stmt->fetchAll();

$totalSudah = 0;
$totalBelum = 0;
foreach ($allRows as &$row) {
  $row['sudah_expertise'] = trim((string)$row['hasil']) !== '';
  $row['sudah_expertise'] ? $totalSudah++ : $totalBelum++;
}
unset($row);

$rows = array_values(array_filter($allRows, static function(array $row) use ($filter): bool {
  if ($filter === 'belum') return !$row['sudah_expertise'];
  if ($filter === 'sudah') return $row['sudah_expertise'];
  return true;
}));
?>

<style>
  .mer-id { font-family:Consolas,"Courier New",monospace; white-space:nowrap; }
  .mer-sub { color:#6c757d; font-size:.78rem; }
  .mer-action { min-width:250px; white-space:normal; }
  .mer-result { min-width:220px; white-space:normal; max-width:420px; }
  .mer-number { font-size:1.55rem; font-weight:700; line-height:1; }
  #dtExpertiseRadiologi tbody tr:hover { background:#f8fbff; }
</style>

<div class="mb-3">
  <h4 class="mb-1">Monitoring Expertise Radiologi</h4>
  <div class="text-muted small">Filter tanggal memakai tanggal SEP untuk pasien BPJS, atau tanggal daftar untuk pasien lainnya. Pencocokan expertise tetap berdasarkan tanggal dan jam pemeriksaan radiologi.</div>
</div>

<div class="card mb-3"><div class="card-body py-3">
  <form method="get" class="form-row align-items-end">
    <input type="hidden" name="page" value="monitoring_expertise_radiologi">
    <div class="form-group col-sm-6 col-lg-2 mb-2"><label class="small mb-1" for="tgl_awal">Tanggal SEP/Daftar Mulai</label><input class="form-control" type="date" id="tgl_awal" name="tgl_awal" value="<?= mer_e($tglAwal) ?>"></div>
    <div class="form-group col-sm-6 col-lg-2 mb-2"><label class="small mb-1" for="tgl_akhir">Tanggal SEP/Daftar Akhir</label><input class="form-control" type="date" id="tgl_akhir" name="tgl_akhir" value="<?= mer_e($tglAkhir) ?>"></div>
    <div class="form-group col-sm-6 col-lg-3 mb-2"><label class="small mb-1" for="jenis_rawat">Jenis Rawat</label><select class="form-control" id="jenis_rawat" name="jenis_rawat"><option value="semua" <?= $jenisRawat==='semua'?'selected':'' ?>>Semua</option><option value="Ralan" <?= $jenisRawat==='Ralan'?'selected':'' ?>>Rawat Jalan</option><option value="Ranap" <?= $jenisRawat==='Ranap'?'selected':'' ?>>Rawat Inap</option></select></div>
    <div class="form-group col-sm-6 col-lg-3 mb-2"><label class="small mb-1" for="hasil">Status Expertise</label><select class="form-control" id="hasil" name="hasil"><option value="belum" <?= $filter==='belum'?'selected':'' ?>>Belum ada Expertise</option><option value="sudah" <?= $filter==='sudah'?'selected':'' ?>>Sudah ada Expertise</option><option value="semua" <?= $filter==='semua'?'selected':'' ?>>Semua tindakan</option></select></div>
    <div class="form-group col-lg-2 mb-2"><button class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i> Tampilkan</button></div>
  </form>
</div></div>

<div class="row mb-3">
  <div class="col-sm-4 mb-2 mb-sm-0"><div class="card h-100"><div class="card-body py-3"><div class="text-muted small">Total tindakan</div><div class="mer-number"><?= count($allRows) ?></div></div></div></div>
  <div class="col-sm-4 mb-2 mb-sm-0"><div class="card h-100 border-danger"><div class="card-body py-3"><div class="text-danger small">Belum ada Expertise</div><div class="mer-number text-danger"><?= $totalBelum ?></div></div></div></div>
  <div class="col-sm-4"><div class="card h-100 border-success"><div class="card-body py-3"><div class="text-success small">Sudah ada Expertise</div><div class="mer-number text-success"><?= $totalSudah ?></div></div></div></div>
</div>

<div class="card"><div class="card-body"><div class="table-responsive">
  <table id="dtExpertiseRadiologi" class="table table-bordered table-striped table-sm" style="width:100%">
    <thead class="thead-light"><tr><th>No Rawat</th><th>No RM</th><th>Pasien</th><th>Poli</th><th>Tanggal SEP/Daftar</th><th>Waktu Periksa Radiologi</th><th>Pemeriksaan Radiologi</th><th>Status Expertise</th><th>Hasil</th></tr></thead>
    <tbody><?php foreach ($rows as $row): ?><tr>
      <td class="mer-id"><?= mer_e($row['no_rawat']) ?></td>
      <td class="mer-id"><?= mer_e($row['no_rkm_medis']) ?></td>
      <td><?= mer_e($row['nm_pasien']) ?><div class="mer-sub"><?= mer_e($row['jenis_rawat']) ?></div></td>
      <td><?= mer_e($row['nm_poli']) ?></td>
      <td data-order="<?= mer_e($row['tanggal_filter']) ?>"><?= mer_e($row['tanggal_filter']) ?></td>
      <td data-order="<?= mer_e($row['tgl_periksa'].' '.$row['jam']) ?>"><?= mer_datetime($row['tgl_periksa'],$row['jam']) ?></td>
      <td class="mer-action"><div><?= mer_e($row['nm_perawatan']) ?></div><div class="mer-sub"><?= mer_e($row['kd_jenis_prw']) ?></div></td>
      <td><?= $row['sudah_expertise'] ? '<span class="badge badge-success">Sudah ada Expertise</span>' : '<span class="badge badge-danger">Belum ada Expertise</span>' ?></td>
      <td class="mer-result">
        <?php if ($row['sudah_expertise']): ?>
          <div><?= nl2br(mer_e(mb_strimwidth((string)$row['hasil'],0,220,'…','UTF-8'))) ?></div>
          <div class="mer-sub">Hasil: <?= mer_datetime($row['tgl_hasil'],$row['jam_hasil']) ?></div>
        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
      </td>
    </tr><?php endforeach; ?></tbody>
  </table>
</div></div></div>

<script>
(function(){
  function ready(cb,n){if(window.jQuery&&jQuery.fn&&jQuery.fn.DataTable){cb(jQuery);return}if(n>0)setTimeout(function(){ready(cb,n-1)},50)}
  ready(function($){
    if($.fn.dataTable.isDataTable('#dtExpertiseRadiologi'))return;
    $('#dtExpertiseRadiologi').DataTable({responsive:true,pageLength:25,lengthMenu:[10,25,50,100],order:[],autoWidth:false,dom:'lBfrtip',buttons:[{extend:'copyHtml5',text:'<i class="fas fa-copy"></i> Copy',title:null,header:true},{extend:'excelHtml5',text:'<i class="fas fa-file-excel"></i> Excel',title:'Monitoring Expertise Radiologi <?= mer_e($jenisRawat === 'Ralan' ? 'Rawat Jalan' : ($jenisRawat === 'Ranap' ? 'Rawat Inap' : 'Semua Jenis')) ?> <?= mer_e($tglAwal) ?> s.d. <?= mer_e($tglAkhir) ?>'}],language:{emptyTable:'Tidak ada tindakan radiologi pada tanggal dan filter ini.',search:'Cari:',lengthMenu:'Tampilkan _MENU_ data',info:'Menampilkan _START_–_END_ dari _TOTAL_ data',infoEmpty:'Tidak ada data',zeroRecords:'Data tidak ditemukan',paginate:{previous:'Sebelumnya',next:'Berikutnya'}}});
  },100);
})();
</script>
