<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';
require_once __DIR__ . '/../_private/lib_csrf.php';

function mkr_e(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function mkr_date(?string $value, bool $withTime = false): string {
  if (!$value || substr($value, 0, 10) === '0000-00-00') return '-';
  $time = strtotime($value);
  return $time ? date($withTime ? 'd-m-Y H:i' : 'd-m-Y', $time) : mkr_e($value);
}

function mkr_sep_class(?string $value): string {
  return ['1'=>'Kelas 1', '2'=>'Kelas 2', '3'=>'Kelas 3'][(string)$value] ?? '-';
}

$today = date('Y-m-d');
$tglAwal = (string)($_GET['tgl_awal'] ?? $today);
$tglAkhir = (string)($_GET['tgl_akhir'] ?? $today);
$hasil = (string)($_GET['hasil'] ?? 'berbeda');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAwal)) $tglAwal = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAkhir)) $tglAkhir = $today;
if ($tglAwal > $tglAkhir) [$tglAwal, $tglAkhir] = [$tglAkhir, $tglAwal];
if (!in_array($hasil, ['berbeda', 'sesuai', 'semua'], true)) $hasil = 'berbeda';

$sql = "
  SELECT
    bs.no_sep, bs.no_rawat, bs.nomr, bs.nama_pasien, bs.tglsep, bs.klsrawat,
    ki.kd_kamar, ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar,
    ki.stts_pulang, k.kelas AS kelas_kamar
  FROM bridging_sep bs
  INNER JOIN kamar_inap ki ON ki.no_rawat = bs.no_rawat
  INNER JOIN kamar k ON k.kd_kamar = ki.kd_kamar
  WHERE bs.jnspelayanan = '1'
    AND bs.tglsep BETWEEN :tgl_awal AND :tgl_akhir
    AND k.kd_bangsal NOT IN ('B0063','B0002','B0003','B0082')
  ORDER BY bs.no_sep, ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar
";
$stmt = pdo()->prepare($sql);
$stmt->execute(['tgl_awal'=>$tglAwal, 'tgl_akhir'=>$tglAkhir]);
$allRows = $stmt->fetchAll();

$totalSesuai = 0;
$totalBerbeda = 0;
foreach ($allRows as &$row) {
  $row['kelas_sep'] = mkr_sep_class($row['klsrawat']);
  $row['is_match'] = strcasecmp(trim($row['kelas_sep']), trim((string)$row['kelas_kamar'])) === 0;
  $row['is_match'] ? $totalSesuai++ : $totalBerbeda++;
}
unset($row);

$auditToken = csrf_token('status_pulang_eklaim_audit');
$latestAudits = [];
$auditStmt = pdo()->prepare("SELECT a.* FROM mlite_vedika_dc_status_audit a
  INNER JOIN (
    SELECT no_rawat,nosep,MAX(id) id FROM mlite_vedika_dc_status_audit
    WHERE jenis_rawat='Ranap' AND tanggal_pelayanan BETWEEN :tgl_awal AND :tgl_akhir
    GROUP BY no_rawat,nosep
  ) latest ON latest.id=a.id");
$auditStmt->execute(['tgl_awal'=>$tglAwal,'tgl_akhir'=>$tglAkhir]);
foreach ($auditStmt->fetchAll() as $audit) $latestAudits[$audit['no_rawat'].'|'.$audit['nosep']] = $audit;

$auditJobsByKey = [];
foreach ($allRows as &$row) {
  $key = $row['no_rawat'].'|'.$row['no_sep'];
  $audit = $latestAudits[$key] ?? null;
  $kelasEklaim = null;
  if ($audit && empty($audit['error_message']) && !empty($audit['response_json'])) {
    $decoded = json_decode((string)$audit['response_json'], true);
    $envelope = is_array($decoded) && isset($decoded['response']) && is_array($decoded['response']) ? $decoded['response'] : $decoded;
    $data = is_array($envelope) && isset($envelope['data']) && is_array($envelope['data']) ? $envelope['data'] : [];
    if (isset($data['kelas_rawat']) && (string)$data['kelas_rawat'] !== '') $kelasEklaim = (string)$data['kelas_rawat'];
  }
  $row['audit'] = $audit;
  $row['kelas_eklaim'] = $kelasEklaim;
  $row['eklaim_match'] = $kelasEklaim === null ? null : $kelasEklaim === (string)$row['klsrawat'];
  $auditJobsByKey[$key] = ['no_rawat'=>$row['no_rawat'],'nosep'=>$row['no_sep']];
}
unset($row);
$auditJobs = array_values($auditJobsByKey);

$rows = array_values(array_filter($allRows, static function(array $row) use ($hasil): bool {
  if ($hasil === 'sesuai') return $row['is_match'];
  if ($hasil === 'berbeda') return !$row['is_match'];
  return true;
}));
?>

<style>
  .mkr-id { font-family: Consolas, "Courier New", monospace; white-space: nowrap; }
  .mkr-sub { color:#6c757d; font-size:.78rem; }
  .mkr-number { font-size:1.55rem; font-weight:700; line-height:1; }
  #dtKelasRanap tbody tr:hover { background:#f8fbff; }
</style>

<div class="mb-3">
  <h4 class="mb-1">Monitoring Kelas Rawat Inap</h4>
  <div class="text-muted small">Membandingkan kelas SEP rawat inap dengan kelas kamar pada setiap riwayat perawatan.</div>
  <div class="text-muted small">PICU, ICU, HCU, dan NICU dikecualikan karena pelayanan intensive care tetap dibayarkan sebagai Kelas 3.</div>
</div>

<div class="card mb-3"><div class="card-body py-3">
  <form method="get" class="form-row align-items-end">
    <input type="hidden" name="page" value="monitoring_kelas_ranap">
    <div class="form-group col-sm-4 col-md-3 mb-2">
      <label class="small mb-1" for="tgl_awal">Tanggal SEP Mulai</label>
      <input class="form-control" type="date" id="tgl_awal" name="tgl_awal" value="<?= mkr_e($tglAwal) ?>">
    </div>
    <div class="form-group col-sm-4 col-md-3 mb-2">
      <label class="small mb-1" for="tgl_akhir">Tanggal SEP Akhir</label>
      <input class="form-control" type="date" id="tgl_akhir" name="tgl_akhir" value="<?= mkr_e($tglAkhir) ?>">
    </div>
    <div class="form-group col-sm-4 col-md-3 mb-2">
      <label class="small mb-1" for="hasil">Tampilkan</label>
      <select class="form-control" id="hasil" name="hasil">
        <option value="berbeda" <?= $hasil==='berbeda'?'selected':'' ?>>Kelas berbeda</option>
        <option value="sesuai" <?= $hasil==='sesuai'?'selected':'' ?>>Kelas sesuai</option>
        <option value="semua" <?= $hasil==='semua'?'selected':'' ?>>Semua data</option>
      </select>
    </div>
    <div class="form-group col-md-3 mb-2"><button class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i> Tampilkan</button></div>
  </form>
</div></div>

<div class="row mb-3">
  <div class="col-sm-4 mb-2 mb-sm-0"><div class="card h-100"><div class="card-body py-3"><div class="text-muted small">Riwayat kamar</div><div class="mkr-number"><?= count($allRows) ?></div></div></div></div>
  <div class="col-sm-4 mb-2 mb-sm-0"><div class="card h-100 border-danger"><div class="card-body py-3"><div class="text-danger small">Kelas berbeda</div><div class="mkr-number text-danger"><?= $totalBerbeda ?></div></div></div></div>
  <div class="col-sm-4"><div class="card h-100 border-success"><div class="card-body py-3"><div class="text-success small">Kelas sesuai</div><div class="mkr-number text-success"><?= $totalSesuai ?></div></div></div></div>
</div>

<div class="card border-info mb-3"><div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between">
  <div class="mr-3"><div class="font-weight-bold">Bandingkan Kelas dengan E-Klaim</div><div class="text-muted small">Memeriksa <code>get_claim_data</code> untuk <?= count($auditJobs) ?> SEP unik dan membandingkan <code>kelas_rawat</code> dengan kelas SEP.</div><div id="mkrAuditProgress" class="small text-info mt-1"></div></div>
  <button type="button" id="btnMkrAudit" class="btn btn-info mt-2 mt-md-0" <?= count($auditJobs)===0?'disabled':'' ?>><i class="fas fa-cloud-download-alt mr-1"></i> Cek Semua ke E-Klaim</button>
</div></div>

<div class="card"><div class="card-body"><div class="table-responsive">
  <table id="dtKelasRanap" class="table table-bordered table-striped table-sm" style="width:100%">
    <thead class="thead-light"><tr>
      <th>No SEP</th><th>Pasien</th><th>Tanggal SEP</th><th>Kamar</th><th>Periode Kamar</th><th>Kelas SEP</th><th>Kelas Kamar</th><th>Hasil Kamar</th><th>Kelas E-Klaim</th><th>Hasil E-Klaim</th>
    </tr></thead>
    <tbody><?php foreach ($rows as $row): ?><tr>
      <td class="mkr-id"><div><?= mkr_e($row['no_sep']) ?></div><div class="mkr-sub"><?= mkr_e($row['no_rawat']) ?></div></td>
      <td><div><?= mkr_e($row['nama_pasien'] ?: '-') ?></div><div class="mkr-sub">RM: <?= mkr_e($row['nomr'] ?: '-') ?></div></td>
      <td data-order="<?= mkr_e($row['tglsep']) ?>"><?= mkr_date($row['tglsep']) ?></td>
      <td class="mkr-id"><?= mkr_e($row['kd_kamar']) ?></td>
      <td data-order="<?= mkr_e($row['tgl_masuk'].' '.$row['jam_masuk']) ?>">
        <div><?= mkr_date($row['tgl_masuk'].' '.$row['jam_masuk'], true) ?></div>
        <div class="mkr-sub">s.d. <?= mkr_date($row['tgl_keluar'].' '.$row['jam_keluar'], true) ?></div>
      </td>
      <td><span class="badge badge-primary"><?= mkr_e($row['kelas_sep']) ?></span></td>
      <td><span class="badge badge-<?= $row['is_match']?'success':'warning' ?>"><?= mkr_e($row['kelas_kamar'] ?: '-') ?></span></td>
      <td><?= $row['is_match'] ? '<span class="badge badge-success">Sesuai</span>' : '<span class="badge badge-danger">Berbeda</span>' ?></td>
      <td>
        <?php if (!$row['audit']): ?><span class="badge badge-secondary">Belum dicek</span>
        <?php elseif (!empty($row['audit']['error_message'])): ?>
          <?php $mkrNoClaim = stripos((string)$row['audit']['error_message'],'discharge_status tidak ditemukan')!==false || strcasecmp(trim((string)$row['audit']['error_message']),'Belum kirim klaim')===0; ?>
          <span class="badge badge-<?= $mkrNoClaim?'warning':'danger' ?>"><?= $mkrNoClaim?'Belum kirim klaim':'Gagal' ?></span>
        <?php elseif ($row['kelas_eklaim'] === null): ?><span class="badge badge-warning">Kelas tidak ditemukan</span>
        <?php else: ?><span class="badge badge-primary"><?= mkr_e(mkr_sep_class($row['kelas_eklaim'])) ?></span><div class="mkr-sub"><?= mkr_date($row['audit']['checked_at'],true) ?></div><?php endif; ?>
      </td>
      <td>
        <?php if ($row['eklaim_match'] === null): ?><span class="text-muted">-</span>
        <?php elseif ($row['eklaim_match']): ?><span class="badge badge-success">Sesuai SEP</span>
        <?php else: ?><span class="badge badge-danger">Berbeda dengan SEP</span><?php endif; ?>
      </td>
    </tr><?php endforeach; ?></tbody>
  </table>
</div></div></div>

<script>
(function(){
  function ready(cb,n){ if(window.jQuery&&jQuery.fn&&jQuery.fn.DataTable){cb(jQuery);return;} if(n>0)setTimeout(function(){ready(cb,n-1);},50); }
  ready(function($){
    if($.fn.dataTable.isDataTable('#dtKelasRanap'))return;
    $('#dtKelasRanap').DataTable({
      responsive:true,pageLength:25,lengthMenu:[10,25,50,100],order:[],autoWidth:false,dom:'lBfrtip',
      buttons:[
        {extend:'copyHtml5',text:'<i class="fas fa-copy"></i> Copy',title:null,header:true},
        {extend:'excelHtml5',text:'<i class="fas fa-file-excel"></i> Excel',title:'Monitoring Kelas Rawat Inap <?= mkr_e($tglAwal) ?> s.d. <?= mkr_e($tglAkhir) ?>'}
      ],
      language:{emptyTable:'Tidak ada data pada tanggal dan filter ini.',search:'Cari:',lengthMenu:'Tampilkan _MENU_ data',info:'Menampilkan _START_–_END_ dari _TOTAL_ data',infoEmpty:'Tidak ada data',zeroRecords:'Data tidak ditemukan',paginate:{previous:'Sebelumnya',next:'Berikutnya'}}
    });

    $('#btnMkrAudit').on('click',async function(){
      var button=this,jobs=<?= json_encode($auditJobs,JSON_UNESCAPED_SLASHES) ?>;
      if(!confirm('Cek kelas ' + jobs.length + ' SEP ke E-Klaim? Hasil pemeriksaan akan disimpan ke riwayat audit.'))return;
      button.disabled=true;
      var progress=document.getElementById('mkrAuditProgress');
      var success=0,failed=0;
      for(var i=0;i<jobs.length;i++){
        progress.textContent='Memeriksa '+(i+1)+' dari '+jobs.length+' SEP…';
        try{
          var body=new URLSearchParams();body.set('_csrf',<?= json_encode($auditToken) ?>);body.set('no_rawat',jobs[i].no_rawat);body.set('nosep',jobs[i].nosep);body.set('jenis','Ranap');
          var response=await fetch('_private/api_status_pulang_eklaim_audit.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString()});
          var result=await response.json();if(!response.ok||!result.ok)throw new Error(result.message||'Gagal');success++;
        }catch(error){failed++;}
      }
      progress.textContent='Selesai: '+success+' berhasil dan '+failed+' gagal.';
      alert(progress.textContent);location.reload();
    });
  },100);
})();
</script>
