<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';

const MASA_RUJUKAN_HARI = 90;

function ar_e(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ar_tanggal(?string $value): string {
  if (!$value || $value === '0000-00-00') return '-';
  $time = strtotime($value);
  return $time ? date('d-m-Y', $time) : ar_e($value);
}

$noSep = strtoupper(trim((string)($_GET['no_sep'] ?? '')));
$error = '';
$sep = null;
$rujukanAktif = [];
$riwayat = [];

if ($noSep !== '') {
  if (!preg_match('/^[A-Z0-9]{10,30}$/', $noSep)) {
    $error = 'Format nomor SEP tidak valid.';
  } else {
    $stmt = pdo()->prepare("
      SELECT no_sep, no_rawat, DATE(tglsep) AS tglsep, DATE(tglrujukan) AS tglrujukan,
             no_rujukan, nomr, nama_pasien, no_kartu, kdpolitujuan, nmpolitujuan,
             nmdpjplayanan, nmdpdjp
      FROM bridging_sep
      WHERE UPPER(no_sep) = :no_sep
      LIMIT 1
    ");
    $stmt->execute(['no_sep' => $noSep]);
    $sep = $stmt->fetch();

    if (!$sep) {
      $error = 'Nomor SEP tidak ditemukan pada tabel bridging_sep.';
    } else {
      $stmt = pdo()->prepare("
        SELECT
          UPPER(TRIM(no_rujukan)) AS no_rujukan,
          MIN(DATE(tglrujukan)) AS tgl_rujukan,
          DATE_ADD(MIN(DATE(tglrujukan)), INTERVAL 3 MONTH) AS akhir_masa_rujukan,
          SUBSTRING_INDEX(GROUP_CONCAT(kdpolitujuan ORDER BY tglsep, no_sep SEPARATOR '||'), '||', 1) AS kd_poli_awal,
          SUBSTRING_INDEX(GROUP_CONCAT(nmpolitujuan ORDER BY tglsep, no_sep SEPARATOR '||'), '||', 1) AS poli_awal,
          COUNT(*) AS jumlah_sep,
          DATEDIFF(:tgl_target_umur, MIN(DATE(tglrujukan))) AS umur_hari
        FROM bridging_sep
        WHERE nomr = :nomr
          AND jnspelayanan = '2'
          AND no_rujukan IS NOT NULL
          AND TRIM(no_rujukan) <> ''
          AND DATE(tglrujukan) <= :tgl_target_awal
          AND DATE_ADD(DATE(tglrujukan), INTERVAL " . MASA_RUJUKAN_HARI . " DAY) >= :tgl_target_akhir
        GROUP BY UPPER(TRIM(no_rujukan))
        ORDER BY tgl_rujukan, no_rujukan
      ");
      $stmt->execute([
        'nomr' => $sep['nomr'],
        'tgl_target_umur' => $sep['tglsep'],
        'tgl_target_awal' => $sep['tglsep'],
        'tgl_target_akhir' => $sep['tglsep'],
      ]);
      $rujukanAktif = $stmt->fetchAll();

      if ($rujukanAktif) {
        $nomorRujukan = array_column($rujukanAktif, 'no_rujukan');
        $placeholders = implode(',', array_fill(0, count($nomorRujukan), '?'));
        $stmt = pdo()->prepare("
          SELECT no_sep, DATE(tglsep) AS tglsep, UPPER(TRIM(no_rujukan)) AS no_rujukan,
                 kdpolitujuan, nmpolitujuan, nmdpjplayanan, nmdpdjp,
                 CASE WHEN UPPER(no_sep) = ? THEN 1 ELSE 0 END AS sep_target
          FROM bridging_sep
          WHERE nomr = ?
            AND jnspelayanan = '2'
            AND UPPER(TRIM(no_rujukan)) IN ($placeholders)
            AND UPPER(TRIM(kdpolitujuan)) <> 'IRM'
            AND DATE(tglsep) BETWEEN
                (SELECT MIN(DATE(tglrujukan)) FROM bridging_sep
                 WHERE nomr = ? AND UPPER(TRIM(no_rujukan)) IN ($placeholders))
                AND
                (SELECT DATE_ADD(MAX(DATE(tglrujukan)), INTERVAL " . MASA_RUJUKAN_HARI . " DAY)
                 FROM bridging_sep
                 WHERE nomr = ? AND UPPER(TRIM(no_rujukan)) IN ($placeholders))
          ORDER BY tglsep, no_sep
        ");
        $params = array_merge(
          [$noSep, $sep['nomr']], $nomorRujukan,
          [$sep['nomr']], $nomorRujukan,
          [$sep['nomr']], $nomorRujukan
        );
        $stmt->execute($params);
        $riwayat = $stmt->fetchAll();
      }
    }
  }
}

$jumlahRujukan = count($rujukanAktif);
$punyaDuaRujukan = $jumlahRujukan >= 2;
?>

<style>
  .ar-summary { border-left: 5px solid #17a2b8; }
  .ar-summary.is-proven { border-left-color: #28a745; background: #f4fbf6; }
  .ar-summary.is-single { border-left-color: #ffc107; background: #fffaf0; }
  .ar-id { font-family: Consolas, "Courier New", monospace; font-size: .92rem; }
  .ar-target { background: #e8f4ff !important; font-weight: 600; }
  .ar-ref-card { height: 100%; border-top: 4px solid #17a2b8; }
  .ar-label { color: #6c757d; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; }
</style>

<div class="d-flex align-items-center justify-content-between mb-2">
  <h4 class="mb-0">Bukti Rujukan Aktif</h4>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form action="index.php" method="get">
      <input type="hidden" name="page" value="analisis_rujukan">
      <div class="form-row align-items-end">
        <div class="col-md-6">
          <label for="noSepAnalisis">Nomor SEP yang dipermasalahkan</label>
          <input type="text" class="form-control ar-id" id="noSepAnalisis" name="no_sep"
                 value="<?= ar_e($noSep) ?>" maxlength="30" autocomplete="off"
                 placeholder="Contoh: 0113R0730626V000060" required>
        </div>
        <div class="col-md-3 mt-2 mt-md-0">
          <button class="btn btn-info btn-block"><i class="fas fa-search"></i> Analisis SEP</button>
        </div>
      </div>
      <small class="text-muted d-block mt-2">Sistem memeriksa rujukan pasien yang masih berada dalam masa 90 hari pada tanggal SEP.</small>
    </form>
  </div>
</div>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= ar_e($error) ?></div>
<?php elseif ($sep): ?>
  <div class="card mb-3 ar-summary <?= $punyaDuaRujukan ? 'is-proven' : 'is-single' ?>">
    <div class="card-body">
      <div class="row">
        <div class="col-lg-8">
          <div class="ar-label">Kesimpulan administratif</div>
          <?php if ($punyaDuaRujukan): ?>
            <h5 class="text-success mt-1 mb-2"><i class="fas fa-check-circle"></i> Terbukti ada <?= $jumlahRujukan ?> rujukan aktif pada <?= ar_tanggal($sep['tglsep']) ?></h5>
            <p class="mb-0">SEP sasaran diterbitkan saat pasien memiliki lebih dari satu rujukan aktif. Data ini mendukung klarifikasi bahwa kunjungan berasal dari rujukan yang berbeda, bukan semata-mata perpindahan poli dalam satu rujukan/konsultasi internal.</p>
          <?php else: ?>
            <h5 class="text-warning mt-1 mb-2"><i class="fas fa-exclamation-triangle"></i> Hanya ditemukan <?= $jumlahRujukan ?> rujukan aktif</h5>
            <p class="mb-0">Belum ada bukti dua nomor rujukan berbeda yang sama-sama aktif pada tanggal SEP sasaran.</p>
          <?php endif; ?>
        </div>
        <div class="col-lg-4 mt-3 mt-lg-0">
          <div class="ar-label">SEP sasaran</div>
          <div class="ar-id font-weight-bold"><?= ar_e($sep['no_sep']) ?></div>
          <div><?= ar_tanggal($sep['tglsep']) ?> · <?= ar_e($sep['kdpolitujuan']) ?> / <?= ar_e($sep['nmpolitujuan']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header font-weight-bold">Identitas pasien pada SEP</div>
    <div class="card-body py-3">
      <div class="row">
        <div class="col-md-3"><div class="ar-label">No. RM</div><div class="ar-id"><?= ar_e($sep['nomr']) ?></div></div>
        <div class="col-md-3"><div class="ar-label">Nama</div><div><?= ar_e($sep['nama_pasien']) ?></div></div>
        <div class="col-md-3"><div class="ar-label">No. Kartu</div><div class="ar-id"><?= ar_e($sep['no_kartu']) ?></div></div>
        <div class="col-md-3"><div class="ar-label">DPJP</div><div><?= ar_e($sep['nmdpjplayanan'] ?: $sep['nmdpdjp']) ?></div></div>
      </div>
    </div>
  </div>

  <h5 class="mb-2">Rujukan aktif pada tanggal SEP</h5>
  <?php if (!$rujukanAktif): ?>
    <div class="alert alert-warning">Tidak ditemukan nomor rujukan yang aktif berdasarkan batas 90 hari.</div>
  <?php else: ?>
    <div class="row mb-3">
      <?php foreach ($rujukanAktif as $r): ?>
        <div class="col-lg-6 mb-3">
          <div class="card ar-ref-card">
            <div class="card-body">
              <div class="ar-label">Nomor rujukan</div>
              <div class="ar-id font-weight-bold mb-2"><?= ar_e($r['no_rujukan']) ?></div>
              <div class="row">
                <div class="col-6"><div class="ar-label">Poli awal</div><div><?= ar_e($r['kd_poli_awal']) ?> / <?= ar_e($r['poli_awal']) ?></div></div>
                <div class="col-6"><div class="ar-label">Terbit</div><div><?= ar_tanggal($r['tgl_rujukan']) ?></div></div>
                <div class="col-6 mt-2"><div class="ar-label">Akhir masa rujukan (3 bulan)</div><div><?= ar_tanggal($r['akhir_masa_rujukan']) ?></div></div>
                <div class="col-6 mt-2"><div class="ar-label">Umur saat SEP</div><div><?= (int)$r['umur_hari'] ?> hari</div></div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <span class="font-weight-bold">Riwayat berobat dengan rujukan aktif</span>
      <small class="text-muted d-block">Poli IRM tidak ditampilkan karena diperbolehkan untuk konsultasi medik.</small>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm mb-0">
          <thead class="thead-light">
            <tr><th>Tanggal</th><th>Poli</th><th>No. SEP</th><th>No. Rujukan</th><th>DPJP</th><th>Keterangan</th></tr>
          </thead>
          <tbody>
          <?php foreach ($riwayat as $row): ?>
            <tr class="<?= (int)$row['sep_target'] === 1 ? 'ar-target' : '' ?>">
              <td><?= ar_tanggal($row['tglsep']) ?></td>
              <td><?= ar_e($row['kdpolitujuan']) ?> / <?= ar_e($row['nmpolitujuan']) ?></td>
              <td class="ar-id"><?= ar_e($row['no_sep']) ?></td>
              <td class="ar-id"><?= ar_e($row['no_rujukan']) ?></td>
              <td><?= ar_e($row['nmdpjplayanan'] ?: $row['nmdpdjp']) ?></td>
              <td><?= (int)$row['sep_target'] === 1 ? '<span class="badge badge-primary">SEP dipermasalahkan</span>' : '' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$riwayat): ?>
            <tr><td colspan="6" class="text-center text-muted">Belum ada riwayat yang dapat ditampilkan.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>
