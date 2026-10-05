<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';

function ltc_e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function ltc_date(?string $value): string {
  if (!$value || substr($value,0,10)==='0000-00-00') return '-';
  $time=strtotime($value); return $time ? date('d-m-Y',$time) : ltc_e($value);
}

function ltc_code_list(?string $value, string $kind): string {
  $items = preg_split('/\s*,\s*/', (string)$value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $html = [];
  foreach ($items as $item) {
    $item = trim($item);
    $highlight = $kind === 'diag'
      ? ($item === 'D64.9' || $item === 'Z37.0' || $item === 'O82.9' || $item === 'O80.9' || preg_match('/^A15(?:\.|$)/i', $item))
      : in_array($item, ['96.05','96.71','96.70'], true);
    $safe = ltc_e($item);
    $html[] = $highlight ? '<strong>'.$safe.'</strong>' : $safe;
  }
  return implode(', ', $html);
}

$today=date('Y-m-d');
$tglAwal=(string)($_GET['tgl_awal'] ?? $today);
$tglAkhir=(string)($_GET['tgl_akhir'] ?? $today);
$filter=(string)($_GET['filter'] ?? 'butuh');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tglAwal))$tglAwal=$today;
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tglAkhir))$tglAkhir=$today;
if($tglAwal>$tglAkhir)[$tglAwal,$tglAkhir]=[$tglAkhir,$tglAwal];
if(!in_array($filter,['butuh','lengkap','semua'],true))$filter='butuh';

$sql="
 SELECT rp.no_rawat,rp.no_rkm_medis,ps.nm_pasien,rp.tgl_registrasi,p.nm_poli,
   GROUP_CONCAT(DISTINCT dp.kd_penyakit ORDER BY dp.prioritas SEPARATOR ', ') AS diagnosa,
   GROUP_CONCAT(DISTINCT pp.kode ORDER BY pp.prioritas SEPARATOR ', ') AS prosedur,
   EXISTS(SELECT 1 FROM berkas_digital_perawatan b WHERE b.no_rawat=rp.no_rawat AND b.kode='021') AS ada_021,
   EXISTS(SELECT 1 FROM berkas_digital_perawatan b WHERE b.no_rawat=rp.no_rawat AND b.kode='041') AS ada_041,
   EXISTS(SELECT 1 FROM berkas_digital_perawatan b WHERE b.no_rawat=rp.no_rawat AND b.kode='022') AS ada_022,
   EXISTS(SELECT 1 FROM berkas_digital_perawatan b WHERE b.no_rawat=rp.no_rawat AND b.kode='007') AS ada_007,
   EXISTS(SELECT 1 FROM berkas_digital_perawatan b WHERE b.no_rawat=rp.no_rawat AND b.kode='023') AS ada_023,
   EXISTS(SELECT 1 FROM sitb_pasien_norm s WHERE s.no_rkm_medis=rp.no_rkm_medis AND COALESCE(s.no_sitb,'')<>'') AS ada_sitb
 FROM reg_periksa rp
 INNER JOIN pasien ps ON ps.no_rkm_medis=rp.no_rkm_medis
 LEFT JOIN poliklinik p ON p.kd_poli=rp.kd_poli
 LEFT JOIN diagnosa_pasien dp ON dp.no_rawat=rp.no_rawat AND dp.status='Ranap'
 LEFT JOIN prosedur_pasien pp ON pp.no_rawat=rp.no_rawat AND pp.status='Ranap'
 WHERE rp.status_lanjut='Ranap' AND rp.tgl_registrasi BETWEEN :tgl_awal AND :tgl_akhir
 GROUP BY rp.no_rawat,rp.no_rkm_medis,ps.nm_pasien,rp.tgl_registrasi,p.nm_poli
 ORDER BY rp.tgl_registrasi DESC,rp.no_rawat DESC
";
$stmt=pdo()->prepare($sql);$stmt->execute(['tgl_awal'=>$tglAwal,'tgl_akhir'=>$tglAkhir]);$allRows=$stmt->fetchAll();
foreach($allRows as &$row){
  $diag=preg_split('/\s*,\s*/',(string)$row['diagnosa'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $procs=preg_split('/\s*,\s*/',(string)$row['prosedur'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $hasD649=in_array('D64.9',$diag,true);
  $hasTb=(bool)array_filter($diag,static fn($d)=>preg_match('/^A15(?:\.|$)/i',(string)$d));
  $hasZ370=in_array('Z37.0',$diag,true);
  $hasO829=in_array('O82.9',$diag,true);
  $hasO809=in_array('O80.9',$diag,true);
  $hasVent=(bool)array_intersect(['96.05','96.71','96.70'],$procs);
  $needs=[];
  if($hasD649&&!$row['ada_021'])$needs[]='Lembar Transfusi Darah';
  if($hasVent&&!$row['ada_041'])$needs[]='Cardex';
  if($hasTb&&!$row['ada_sitb'])$needs[]='Input No SITB Pasien';
  if($hasZ370&&!$row['ada_022'])$needs[]='SHK';
  if($hasO829&&!$row['ada_007'])$needs[]='CTG';
  if($hasO809){if(!$row['ada_007'])$needs[]='CTG';if(!$row['ada_023'])$needs[]='Partograf';}
  $row['needs']=array_values(array_unique($needs));$row['is_complete']=count($row['needs'])===0;
  $row['has_d649']=$hasD649;$row['has_tb']=$hasTb;$row['has_z370']=$hasZ370;$row['has_o829']=$hasO829;$row['has_o809']=$hasO809;$row['has_vent']=$hasVent;
}
unset($row);
$rows=array_values(array_filter($allRows,static function(array $r)use($filter):bool{if($filter==='butuh')return !$r['is_complete']&&count($r['needs'])>0;if($filter==='lengkap')return $r['is_complete'];return true;}));
$totalButuh=count(array_filter($allRows,static fn($r)=>count($r['needs'])>0));
$totalLengkap=count($allRows)-$totalButuh;
?>
<style>.ltc-id{font-family:Consolas,"Courier New",monospace;white-space:nowrap}.ltc-sub{color:#6c757d;font-size:.78rem}.ltc-needs{min-width:230px;white-space:normal}.ltc-number{font-size:1.55rem;font-weight:700;line-height:1}#dtLaporanTindakan tbody tr:hover{background:#fffaf2}</style>
<div class="mb-3"><h4 class="mb-1">Laporan Transfusi, CTG, Partograf</h4><div class="text-muted small">Pemeriksaan kelengkapan berkas pendukung untuk pasien Rawat Inap berdasarkan diagnosis dan prosedur.</div></div>
<div class="card mb-3"><div class="card-body py-3"><form method="get" class="form-row align-items-end"><input type="hidden" name="page" value="laporan_transfusi_ctg"><div class="form-group col-sm-4 col-md-3 mb-2"><label class="small mb-1">Tanggal Mulai</label><input class="form-control" type="date" name="tgl_awal" value="<?= ltc_e($tglAwal) ?>"></div><div class="form-group col-sm-4 col-md-3 mb-2"><label class="small mb-1">Tanggal Akhir</label><input class="form-control" type="date" name="tgl_akhir" value="<?= ltc_e($tglAkhir) ?>"></div><div class="form-group col-sm-4 col-md-3 mb-2"><label class="small mb-1">Tampilkan</label><select class="form-control" name="filter"><option value="butuh" <?= $filter==='butuh'?'selected':'' ?>>Perlu dilengkapi</option><option value="lengkap" <?= $filter==='lengkap'?'selected':'' ?>>Lengkap</option><option value="semua" <?= $filter==='semua'?'selected':'' ?>>Semua rawat inap</option></select></div><div class="form-group col-md-3 mb-2"><button class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i> Tampilkan</button></div></form></div></div>
<div class="row mb-3"><div class="col-sm-4 mb-2 mb-sm-0"><div class="card h-100"><div class="card-body py-3"><div class="text-muted small">Total Rawat Inap</div><div class="ltc-number"><?= count($allRows) ?></div></div></div></div><div class="col-sm-4 mb-2 mb-sm-0"><div class="card border-danger h-100"><div class="card-body py-3"><div class="text-danger small">Perlu dilengkapi</div><div class="ltc-number text-danger"><?= $totalButuh ?></div></div></div></div><div class="col-sm-4"><div class="card border-success h-100"><div class="card-body py-3"><div class="text-success small">Lengkap / tidak ada kriteria khusus</div><div class="ltc-number text-success"><?= $totalLengkap ?></div></div></div></div></div>
<div class="card border-info mb-3"><div class="card-body py-2"><div class="font-weight-bold mb-1">Kamus kode berkas</div><div class="small"><span class="badge badge-secondary">021</span> Lembar Transfusi Darah &nbsp; <span class="badge badge-secondary">041</span> Cardex &nbsp; <span class="badge badge-secondary">022</span> SHK &nbsp; <span class="badge badge-secondary">007</span> CTG &nbsp; <span class="badge badge-secondary">023</span> Partograf &nbsp; <span class="badge badge-secondary">SITB</span> Nomor SITB Pasien</div><div class="small text-muted mt-1"><span class="badge badge-success">Hijau</span> tersedia &nbsp; <span class="badge badge-secondary">Abu-abu</span> belum tersedia</div></div></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table id="dtLaporanTindakan" class="table table-bordered table-striped table-sm" style="width:100%"><thead class="thead-light"><tr><th>No Rawat</th><th>No RM</th><th>Pasien</th><th>Tanggal</th><th>Poli</th><th>Diagnosa</th><th>Prosedur</th><th>Bukti Berkas</th><th>Status</th><th>Yang Dibutuhkan</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td class="ltc-id"><?= ltc_e($row['no_rawat']) ?></td><td class="ltc-id"><?= ltc_e($row['no_rkm_medis']) ?></td><td><?= ltc_e($row['nm_pasien']) ?></td><td data-order="<?= ltc_e($row['tgl_registrasi']) ?>"><?= ltc_date($row['tgl_registrasi']) ?></td><td><?= ltc_e($row['nm_poli']?:'-') ?></td><td class="ltc-sub"><?= $row['diagnosa'] ? ltc_code_list($row['diagnosa'],'diag') : '-' ?></td><td class="ltc-sub"><?= $row['prosedur'] ? ltc_code_list($row['prosedur'],'proc') : '-' ?></td><td>
<?php if($row['has_d649']): ?><span class="badge <?= $row['ada_021']?'badge-success':'badge-secondary' ?>" title="Lembar Transfusi Darah">021</span><?php endif; ?>
<?php if($row['has_vent']): ?><span class="badge <?= $row['ada_041']?'badge-success':'badge-secondary' ?>" title="Cardex">041</span><?php endif; ?>
<?php if($row['has_z370']): ?><span class="badge <?= $row['ada_022']?'badge-success':'badge-secondary' ?>">022</span><?php endif; ?>
<?php if($row['has_o829']||$row['has_o809']): ?><span class="badge <?= $row['ada_007']?'badge-success':'badge-secondary' ?>">007</span><?php endif; ?>
<?php if($row['has_o809']): ?><span class="badge <?= $row['ada_023']?'badge-success':'badge-secondary' ?>">023</span><?php endif; ?>
<?php if($row['has_tb']): ?><span class="badge <?= $row['ada_sitb']?'badge-success':'badge-secondary' ?>" title="Nomor SITB Pasien">SITB</span><?php endif; ?>
</td><td><?= count($row['needs']) ? '<span class="badge badge-danger">Perlu dilengkapi</span>' : '<span class="badge badge-success">Lengkap</span>' ?></td><td class="ltc-needs"><?php if(count($row['needs'])): foreach($row['needs'] as $need): ?><div><i class="fas fa-exclamation-circle text-danger mr-1"></i><?= ltc_e($need) ?></div><?php endforeach; else: ?><span class="text-muted">Tidak ada</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>

<script>(function(){function r(cb,n){if(window.jQuery&&jQuery.fn&&jQuery.fn.DataTable){cb(jQuery);return}if(n>0)setTimeout(function(){r(cb,n-1)},50)}r(function($){$('#dtLaporanTindakan').DataTable({responsive:true,pageLength:25,lengthMenu:[10,25,50,100],order:[],autoWidth:false,dom:'lBfrtip',buttons:[{extend:'copyHtml5',text:'<i class="fas fa-copy"></i> Copy',title:null,header:true},{extend:'excelHtml5',text:'<i class="fas fa-file-excel"></i> Excel',title:'Laporan Transfusi CTG Partograf <?= ltc_e($tglAwal) ?> s.d. <?= ltc_e($tglAkhir) ?>'}],language:{emptyTable:'Tidak ada data pada tanggal dan filter ini.',search:'Cari:',lengthMenu:'Tampilkan _MENU_ data',info:'Menampilkan _START_–_END_ dari _TOTAL_ data',infoEmpty:'Tidak ada data',zeroRecords:'Data tidak ditemukan',paginate:{previous:'Sebelumnya',next:'Berikutnya'}}})},100)})();</script>
