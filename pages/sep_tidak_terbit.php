<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';
require_once __DIR__ . '/../_private/lib_csrf.php';

function stb_e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function stb_date(?string $value, bool $withTime=false): string {
  if (!$value || substr($value,0,10)==='0000-00-00') return '-';
  $time=strtotime($value); return $time ? date($withTime?'d-m-Y H:i':'d-m-Y',$time) : stb_e($value);
}

$today=date('Y-m-d');
$yesterday=date('Y-m-d', strtotime('-1 day'));
$tglAwal=(string)($_GET['tgl_awal'] ?? $yesterday);
$tglAkhir=(string)($_GET['tgl_akhir'] ?? $yesterday);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tglAwal)) $tglAwal=$yesterday;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tglAkhir)) $tglAkhir=$yesterday;
if ($tglAwal>$tglAkhir) [$tglAwal,$tglAkhir]=[$tglAkhir,$tglAwal];
if ($tglAkhir>$yesterday) $tglAkhir=$yesterday;
if ($tglAwal>$yesterday) $tglAwal=$yesterday;

$sql="
  SELECT rp.no_rawat,rp.no_rkm_medis,rp.tgl_registrasi,rp.jam_reg,rp.stts,rp.status_lanjut,
    rp.status_bayar,d.nm_dokter,ps.nm_pasien,p.nm_poli
  FROM reg_periksa rp
  INNER JOIN (SELECT DISTINCT kd_dokter FROM maping_dokter_dpjpvclaim) md ON md.kd_dokter=rp.kd_dokter
  INNER JOIN dokter d ON d.kd_dokter=rp.kd_dokter
  INNER JOIN pasien ps ON ps.no_rkm_medis=rp.no_rkm_medis
  LEFT JOIN poliklinik p ON p.kd_poli=rp.kd_poli
  LEFT JOIN bridging_sep bs ON bs.no_rawat=rp.no_rawat
  WHERE rp.kd_pj='BPJ' AND rp.stts<>'Batal'
    AND rp.status_lanjut='Ralan'
    AND rp.kd_poli NOT IN ('U0033','U0041','U0046','U0039','U0015','U0016','U0035')
    AND rp.status_lanjut='Ralan'
    AND rp.kd_poli NOT IN ('U0033','U0041','U0046','U0039','U0015','U0016','U0035')
    AND rp.tgl_registrasi BETWEEN :tgl_awal AND :tgl_akhir
    AND bs.no_sep IS NULL
  ORDER BY rp.tgl_registrasi DESC,rp.jam_reg DESC,rp.no_rawat DESC
";
$stmt=pdo()->prepare($sql);
$stmt->execute(['tgl_awal'=>$tglAwal,'tgl_akhir'=>$tglAkhir]);
$rows=$stmt->fetchAll();
$token=csrf_token('sep_tidak_terbit_bulk');
?>

<style>
.stb-id{font-family:Consolas,"Courier New",monospace;white-space:nowrap}.stb-sub{font-size:.78rem;color:#6c757d}.stb-count{font-size:1.6rem;font-weight:700;line-height:1}
</style>

<div class="mb-3"><h4 class="mb-1">SEP Tidak Terbit</h4><div class="text-muted small">Kunjungan BPJS dengan dokter yang terdaftar pada mapping DPJP VClaim, tetapi belum mempunyai SEP.</div></div>

<div class="card mb-3"><div class="card-body py-3">
  <form method="get" class="form-row align-items-end">
    <input type="hidden" name="page" value="sep_tidak_terbit">
    <div class="form-group col-sm-4 mb-2"><label class="small mb-1" for="tgl_awal">Tanggal Mulai</label><input class="form-control" type="date" id="tgl_awal" name="tgl_awal" value="<?= stb_e($tglAwal) ?>" max="<?= stb_e($yesterday) ?>"></div>
    <div class="form-group col-sm-4 mb-2"><label class="small mb-1" for="tgl_akhir">Tanggal Akhir (maks. kemarin)</label><input class="form-control" type="date" id="tgl_akhir" name="tgl_akhir" value="<?= stb_e($tglAkhir) ?>" max="<?= stb_e($yesterday) ?>"></div>
    <div class="form-group col-sm-4 mb-2"><button class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i> Tampilkan</button></div>
  </form>
</div></div>
<div class="alert alert-info py-2 small"><i class="fas fa-info-circle mr-1"></i> Kunjungan tanggal hari ini tidak ditampilkan dan tidak dapat dibatalkan secara bulk.</div>

<div class="row mb-3">
  <div class="col-md-6 mb-2 mb-md-0"><div class="card border-warning h-100"><div class="card-body py-3"><div class="text-warning small">Kunjungan tanpa SEP</div><div class="stb-count text-warning"><?= count($rows) ?></div></div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body py-3 d-flex align-items-center justify-content-between"><div><b>Batalkan seluruh hasil pencarian</b><div class="stb-sub">Syarat akan diperiksa ulang saat proses dijalankan.</div></div><button id="btnBulkBatal" class="btn btn-danger" <?= count($rows)===0?'disabled':'' ?>><i class="fas fa-ban mr-1"></i> Batal Semua</button></div></div></div>
</div>

<div class="card"><div class="card-body"><div class="table-responsive">
<table id="dtSepTidakTerbit" class="table table-bordered table-striped table-sm" style="width:100%">
  <thead class="thead-light"><tr><th>No Rawat</th><th>No RM</th><th>Tanggal</th><th>Nama Pasien</th><th>Dokter</th><th>Poli</th><th>Jenis</th><th>Status</th></tr></thead>
  <tbody><?php foreach($rows as $row): ?><tr>
    <td class="stb-id"><?= stb_e($row['no_rawat']) ?></td><td class="stb-id"><?= stb_e($row['no_rkm_medis']) ?></td>
    <td data-order="<?= stb_e($row['tgl_registrasi'].' '.$row['jam_reg']) ?>"><?= stb_date($row['tgl_registrasi'].' '.$row['jam_reg'],true) ?></td>
    <td><?= stb_e($row['nm_pasien']) ?></td><td><?= stb_e($row['nm_dokter']) ?></td>
    <td><?= stb_e($row['nm_poli'] ?: '-') ?></td><td><span class="badge badge-<?= $row['status_lanjut']==='Ranap'?'primary':'info' ?>"><?= stb_e($row['status_lanjut']) ?></span></td>
    <td><span class="badge badge-warning"><?= stb_e($row['stts']) ?></span><div class="stb-sub"><?= stb_e($row['status_bayar']) ?></div></td>
  </tr><?php endforeach; ?></tbody>
</table>
</div></div></div>

<script>
(function(){
 function ready(cb,n){if(window.jQuery&&jQuery.fn&&jQuery.fn.DataTable){cb(jQuery);return}if(n>0)setTimeout(function(){ready(cb,n-1)},50)}
 ready(function($){
  $('#dtSepTidakTerbit').DataTable({responsive:true,pageLength:25,lengthMenu:[10,25,50,100],order:[],autoWidth:false,dom:'lBfrtip',buttons:[{extend:'copyHtml5',text:'<i class="fas fa-copy"></i> Copy',title:null,header:true},{extend:'excelHtml5',text:'<i class="fas fa-file-excel"></i> Excel',title:'SEP Tidak Terbit <?= stb_e($tglAwal) ?> s.d. <?= stb_e($tglAkhir) ?>'}],language:{emptyTable:'Tidak ada kunjungan BPJS tanpa SEP.',search:'Cari:',lengthMenu:'Tampilkan _MENU_ data',info:'Menampilkan _START_–_END_ dari _TOTAL_ data',infoEmpty:'Tidak ada data',zeroRecords:'Data tidak ditemukan',paginate:{previous:'Sebelumnya',next:'Berikutnya'}}});
  $('#btnBulkBatal').on('click',async function(){
    if(!confirm('Ubah <?= count($rows) ?> kunjungan BPJS tanpa SEP tanggal <?= stb_e(stb_date($tglAwal)) ?> sampai <?= stb_e(stb_date($tglAkhir)) ?> menjadi Batal?'))return;
    var b=this,old=b.innerHTML;b.disabled=true;b.innerHTML='<i class="fas fa-spinner fa-spin mr-1"></i> Memproses';
    try{var body=new URLSearchParams();body.set('_csrf',<?= json_encode($token) ?>);body.set('tgl_awal',<?= json_encode($tglAwal) ?>);body.set('tgl_akhir',<?= json_encode($tglAkhir) ?>);var res=await fetch('_private/api_sep_tidak_terbit_bulk.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString()});var result=await res.json();if(!res.ok||!result.ok)throw new Error(result.message||'Proses gagal');alert(result.message);location.reload()}catch(e){alert(e.message||'Proses gagal');b.disabled=false;b.innerHTML=old}
  });
 },100);
})();
</script>
