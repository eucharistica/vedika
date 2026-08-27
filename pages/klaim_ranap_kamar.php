<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';

function krk_e(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function krk_valid_date(string $value): bool {
  $date = DateTime::createFromFormat('Y-m-d', $value);
  return $date !== false && $date->format('Y-m-d') === $value;
}

function krk_date(?string $value): string {
  if (!$value || $value === '0000-00-00') return '-';
  $time = strtotime($value);
  return $time ? date('d-m-Y', $time) : krk_e($value);
}

$tglAwal = trim((string)($_GET['tanggal_awal'] ?? date('Y-m-d')));
$tglAkhir = trim((string)($_GET['tanggal_akhir'] ?? date('Y-m-d')));
$error = '';
$episodes = [];
$jumlahBaris = 0;

if (!krk_valid_date($tglAwal) || !krk_valid_date($tglAkhir)) {
  $error = 'Format tanggal tidak valid.';
} elseif ($tglAwal > $tglAkhir) {
  $error = 'Tanggal awal tidak boleh melewati tanggal akhir.';
} else {
  $stmt = pdo()->prepare("
    SELECT
      sep.no_sep,
      ki.no_rawat,
      ki.kd_kamar,
      COALESCE(bangsal.nm_bangsal, '') AS nm_bangsal,
      DATE(ki.tgl_masuk) AS tgl_masuk,
      ki.jam_masuk,
      DATE(ki.tgl_keluar) AS tgl_keluar,
      ki.jam_keluar,
      ki.stts_pulang,
      pulang.tgl_pulang_akhir
    FROM kamar_inap AS ki
    INNER JOIN (
      SELECT no_rawat, MAX(no_sep) AS no_sep
      FROM bridging_sep
      WHERE jnspelayanan = '1'
      GROUP BY no_rawat
    ) AS sep ON sep.no_rawat = ki.no_rawat
    INNER JOIN (
      SELECT no_rawat, MAX(DATE(tgl_keluar)) AS tgl_pulang_akhir
      FROM kamar_inap
      WHERE stts_pulang <> 'Pindah Kamar'
        AND DATE(tgl_keluar) BETWEEN :tanggal_awal AND :tanggal_akhir
      GROUP BY no_rawat
    ) AS pulang ON pulang.no_rawat = ki.no_rawat
    LEFT JOIN kamar ON kamar.kd_kamar = ki.kd_kamar
    LEFT JOIN bangsal ON bangsal.kd_bangsal = kamar.kd_bangsal
    ORDER BY pulang.tgl_pulang_akhir, sep.no_sep, ki.tgl_masuk, ki.jam_masuk,
             ki.tgl_keluar, ki.jam_keluar
  ");
  $stmt->execute([
    'tanggal_awal' => $tglAwal,
    'tanggal_akhir' => $tglAkhir,
  ]);

  foreach ($stmt->fetchAll() as $row) {
    $key = $row['no_sep'] . '|' . $row['no_rawat'];
    if (!isset($episodes[$key])) {
      $episodes[$key] = [
        'no_sep' => $row['no_sep'],
        'no_rawat' => $row['no_rawat'],
        'tgl_pulang_akhir' => $row['tgl_pulang_akhir'],
        'rooms' => [],
      ];
    }
    $episodes[$key]['rooms'][] = $row;
    $jumlahBaris++;
  }
}
?>

<style>
  .krk-sep { font-family: Consolas, "Courier New", monospace; font-weight: 600; white-space: nowrap; }
  .krk-table tbody + tbody tr:first-child > * { border-top: 3px solid #6c757d; }
  .krk-table tbody tr:last-child > * { border-bottom: 2px solid #adb5bd; }
  .krk-table td { vertical-align: middle; }
  .krk-table .krk-sep-cell { background: #f3f8fc; border-right: 3px solid #17a2b8; }
  .krk-room-last { background: #f4fbf6; }
  .krk-meta { color: #6c757d; font-size: .86rem; }
</style>

<div class="d-flex align-items-center justify-content-between mb-2">
  <h4 class="mb-0">Klaim Ranap per Kamar</h4>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form action="index.php" method="get">
      <input type="hidden" name="page" value="klaim_ranap_kamar">
      <div class="form-row align-items-end">
        <div class="col-md-3">
          <label for="tanggalAwalRanap">Tanggal Pulang Dari</label>
          <input type="date" class="form-control" id="tanggalAwalRanap" name="tanggal_awal" value="<?= krk_e($tglAwal) ?>" required>
        </div>
        <div class="col-md-3 mt-2 mt-md-0">
          <label for="tanggalAkhirRanap">Tanggal Pulang Sampai</label>
          <input type="date" class="form-control" id="tanggalAkhirRanap" name="tanggal_akhir" value="<?= krk_e($tglAkhir) ?>" required>
        </div>
        <div class="col-md-3 mt-2 mt-md-0">
          <button class="btn btn-info btn-block"><i class="fas fa-search"></i> Tampilkan</button>
        </div>
      </div>
      <small class="text-muted d-block mt-2">Episode dipilih berdasarkan tanggal pulang terakhir. Seluruh perpindahan kamar dalam episode tetap ditampilkan.</small>
    </form>
  </div>
</div>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= krk_e($error) ?></div>
<?php else: ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
    <div class="krk-meta">
      <?= count($episodes) ?> SEP · <?= $jumlahBaris ?> riwayat ruang · pulang <?= krk_date($tglAwal) ?> s.d. <?= krk_date($tglAkhir) ?>
    </div>
    <div class="d-flex align-items-center mt-2 mt-md-0">
      <?php if ($episodes): ?><div id="krkExportButtons" class="mr-2"></div><?php endif; ?>
      <div class="krk-meta"><span class="badge badge-success">Hijau</span> kamar terakhir sebelum pulang</div>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0 p-md-3">
      <div class="table-responsive">
        <table class="table table-bordered table-sm krk-table mb-0" style="width:100%">
          <thead class="thead-light">
            <tr>
              <th>No SEP</th>
              <th>Ruang Rawat</th>
              <th>Tanggal Masuk</th>
              <th>Tanggal Pulang</th>
            </tr>
          </thead>
          <?php foreach ($episodes as $episode): ?>
            <tbody>
            <?php $roomCount = count($episode['rooms']); ?>
            <?php foreach ($episode['rooms'] as $index => $room): ?>
              <?php
                $isLast = $index === $roomCount - 1;
                $roomLabel = trim((string)$room['nm_bangsal']);
                if ($roomLabel === '') $roomLabel = '-';
              ?>
              <tr class="<?= $isLast ? 'krk-room-last' : '' ?>">
                <?php if ($index === 0): ?>
                  <td rowspan="<?= $roomCount ?>" class="krk-sep-cell">
                    <span class="krk-sep"><?= krk_e($episode['no_sep']) ?></span>
                  </td>
                <?php endif; ?>
                <td><?= krk_e($roomLabel) ?></td>
                <td><?= krk_date($room['tgl_masuk']) ?></td>
                <td><?= krk_date($room['tgl_keluar']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          <?php endforeach; ?>
          <?php if (!$episodes): ?>
            <tbody><tr><td colspan="4" class="text-center text-muted py-4">Tidak ada klaim rawat inap yang pulang pada rentang tanggal tersebut.</td></tr></tbody>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>

  <?php if ($episodes): ?>
    <table id="dtKlaimRanapExport" style="display:none">
      <thead>
        <tr><th>No SEP</th><th>Ruang Rawat</th><th>Tanggal Masuk</th><th>Tanggal Pulang</th></tr>
      </thead>
      <tbody>
      <?php foreach ($episodes as $episode): ?>
        <?php foreach ($episode['rooms'] as $room): ?>
          <?php
            $exportRoom = trim((string)$room['nm_bangsal']);
            if ($exportRoom === '') $exportRoom = '-';
          ?>
          <tr>
            <td><?= krk_e($episode['no_sep']) ?></td>
            <td><?= krk_e($exportRoom) ?></td>
            <td><?= krk_date($room['tgl_masuk']) ?></td>
            <td><?= krk_date($room['tgl_keluar']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>

    <script>
    (function () {
      function waitForDataTables(callback, tries) {
        if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
          callback(jQuery);
          return;
        }
        if (tries > 0) setTimeout(function () { waitForDataTables(callback, tries - 1); }, 50);
      }

      waitForDataTables(function ($) {
        if ($.fn.dataTable.isDataTable('#dtKlaimRanapExport')) return;

        var table = $('#dtKlaimRanapExport').DataTable({
          dom: 'B',
          paging: false,
          searching: false,
          ordering: false,
          info: false,
          autoWidth: false,
          buttons: [
            {
              extend: 'copyHtml5',
              text: '<i class="fas fa-copy"></i> Copy',
              title: null,
              header: true,
              exportOptions: { columns: [0, 1, 2, 3] }
            },
            {
              extend: 'excelHtml5',
              text: '<i class="fas fa-file-excel"></i> Excel',
              title: 'Klaim Ranap per Kamar',
              filename: <?= json_encode('Klaim_Ranap_Per_Kamar_' . $tglAwal . '_sd_' . $tglAkhir, JSON_UNESCAPED_SLASHES) ?>,
              exportOptions: { columns: [0, 1, 2, 3] }
            }
          ]
        });

        table.buttons().container().appendTo('#krkExportButtons');
        $('#krkExportButtons .btn').removeClass('btn-secondary').addClass('btn-outline-info btn-sm mr-1');
      }, 100);
    })();
    </script>
  <?php endif; ?>
<?php endif; ?>
