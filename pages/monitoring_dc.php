<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';
require_once __DIR__ . '/../_private/lib_csrf.php';

function mdc_e(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function mdc_valid_date(string $value): bool {
  $date = DateTime::createFromFormat('Y-m-d', $value);
  return $date !== false && $date->format('Y-m-d') === $value;
}

function mdc_datetime(?string $value): string {
  if (!$value || $value === '0000-00-00 00:00:00') return '-';
  $time = strtotime($value);
  return $time ? date('d-m-Y H:i:s', $time) : mdc_e($value);
}

$tanggalAwal = trim((string)($_GET['tanggal_awal'] ?? date('Y-m-d')));
$tanggalAkhir = trim((string)($_GET['tanggal_akhir'] ?? date('Y-m-d')));
$filterStatus = trim((string)($_GET['status_dc'] ?? 'semua'));
$allowedStatus = ['semua', 'belum', 'terkirim'];
$error = '';
$allRows = [];
$rows = [];
$jumlahTerkirim = 0;
$jumlahBelum = 0;
$pendingSeps = [];

if (!in_array($filterStatus, $allowedStatus, true)) $filterStatus = 'semua';

if (!mdc_valid_date($tanggalAwal) || !mdc_valid_date($tanggalAkhir)) {
  $error = 'Format tanggal tidak valid.';
} elseif ($tanggalAwal > $tanggalAkhir) {
  $error = 'Tanggal awal tidak boleh melewati tanggal akhir.';
} else {
  $stmt = pdo()->prepare("
    SELECT
      bridging_sep.no_rawat,
      bridging_sep.no_sep AS nosep,
      CASE
        WHEN bridging_sep.jnspelayanan = '1' THEN 'Ranap'
        WHEN bridging_sep.jnspelayanan = '2' THEN 'Ralan'
        ELSE bridging_sep.jnspelayanan
      END AS jenis_rawat,
      recap.created_at,
      DATE(bridging_sep.tglsep) AS tglsep,
      recap.dc_status,
      recap.kemenkes_dc_status,
      recap.kemenkes_dc_sent_at,
      recap.bpjs_dc_status,
      recap.bpjs_dc_sent_at,
      data_terkirim.no_sep AS legacy_no_sep
    FROM bridging_sep
    LEFT JOIN (
      SELECT grouping_recap.*
      FROM mlite_vedika_grouping_recap AS grouping_recap
      INNER JOIN (
        SELECT nosep, MAX(id) AS latest_id
        FROM mlite_vedika_grouping_recap
        GROUP BY nosep
      ) AS latest ON latest.latest_id = grouping_recap.id
    ) AS recap ON recap.nosep = bridging_sep.no_sep
    LEFT JOIN (
      SELECT no_sep
      FROM inacbg_data_terkirim
      GROUP BY no_sep
    ) AS data_terkirim ON data_terkirim.no_sep = bridging_sep.no_sep
    WHERE bridging_sep.tglsep >= :tanggal_awal
      AND bridging_sep.tglsep < DATE_ADD(:tanggal_akhir, INTERVAL 1 DAY)
    ORDER BY bridging_sep.tglsep DESC, bridging_sep.no_sep DESC
  ");
  $stmt->execute([
    'tanggal_awal' => $tanggalAwal,
    'tanggal_akhir' => $tanggalAkhir,
  ]);
  $allRows = $stmt->fetchAll();

  foreach ($allRows as $row) {
    $isLegacySent = !empty($row['legacy_no_sep']);
    $isRecapSent = strtolower(trim((string)$row['kemenkes_dc_status'])) === 'sent'
      && strtolower(trim((string)$row['bpjs_dc_status'])) === 'sent';
    $isSent = $isLegacySent || $isRecapSent;
    $row['is_legacy_sent'] = $isLegacySent;
    $row['is_recap_sent'] = $isRecapSent;
    $row['is_sent'] = $isSent;

    if ($isSent) {
      $jumlahTerkirim++;
    } else {
      $jumlahBelum++;
      $pendingSeps[] = $row['nosep'];
    }

    if ($filterStatus === 'terkirim' && !$isSent) continue;
    if ($filterStatus === 'belum' && $isSent) continue;
    $rows[] = $row;
  }
}

$syncToken = csrf_token('monitoring_dc_sync');
$syncLimit = 200;
$syncSeps = array_slice(array_values(array_unique($pendingSeps)), 0, $syncLimit);
?>

<style>
  .mdc-id { font-family: Consolas, "Courier New", monospace; white-space: nowrap; }
  .mdc-stat { border-left: 5px solid #6c757d; }
  .mdc-stat.sent { border-left-color: #28a745; }
  .mdc-stat.pending { border-left-color: #ffc107; }
  .mdc-status-detail { font-size: .82rem; line-height: 1.35; }
</style>

<div class="d-flex align-items-center justify-content-between mb-2">
  <h4 class="mb-0">Monitoring Data Center</h4>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form action="index.php" method="get">
      <input type="hidden" name="page" value="monitoring_dc">
      <div class="form-row align-items-end">
        <div class="col-md-3">
          <label for="tanggalAwalDc">Tanggal SEP Dari</label>
          <input type="date" class="form-control" id="tanggalAwalDc" name="tanggal_awal" value="<?= mdc_e($tanggalAwal) ?>" required>
        </div>
        <div class="col-md-3 mt-2 mt-md-0">
          <label for="tanggalAkhirDc">Tanggal SEP Sampai</label>
          <input type="date" class="form-control" id="tanggalAkhirDc" name="tanggal_akhir" value="<?= mdc_e($tanggalAkhir) ?>" required>
        </div>
        <div class="col-md-3 mt-2 mt-md-0">
          <label for="statusDc">Status Pengiriman</label>
          <select class="form-control" id="statusDc" name="status_dc">
            <option value="semua" <?= $filterStatus === 'semua' ? 'selected' : '' ?>>Semua Status</option>
            <option value="belum" <?= $filterStatus === 'belum' ? 'selected' : '' ?>>Belum Terkirim DC</option>
            <option value="terkirim" <?= $filterStatus === 'terkirim' ? 'selected' : '' ?>>Sudah Terkirim DC</option>
          </select>
        </div>
        <div class="col-md-3 mt-2 mt-md-0">
          <button class="btn btn-info btn-block"><i class="fas fa-search"></i> Tampilkan</button>
        </div>
      </div>
      <small class="text-muted d-block mt-2">Daftar utama berasal dari seluruh SEP di bridging_sep. Status terkirim diambil dari inacbg_data_terkirim (historis), atau status Kemenkes dan BPJS pada grouping recap yang keduanya sent.</small>
    </form>
  </div>
</div>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= mdc_e($error) ?></div>
<?php else: ?>
  <?php if ($jumlahBelum > 0): ?>
    <div class="card border-info mb-3">
      <div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between">
        <div class="mr-3">
          <div class="font-weight-bold">Verifikasi ke E-Klaim</div>
          <div class="text-muted small">
            Periksa SEP yang belum tercatat. Jika E-Klaim menyatakan Kemenkes dan BPJS sudah sent, SEP otomatis dimasukkan ke inacbg_data_terkirim.
          </div>
          <?php if ($jumlahBelum > $syncLimit): ?>
            <div class="text-warning small">Maksimal <?= $syncLimit ?> SEP diproses per sekali sinkronisasi.</div>
          <?php endif; ?>
        </div>
        <button type="button" class="btn btn-outline-info mt-2 mt-md-0" id="btnSyncEklaim">
          <i class="fas fa-sync-alt"></i> Cek E-Klaim (<?= count($syncSeps) ?>)
        </button>
      </div>
      <div class="card-footer py-2 small d-none" id="syncEklaimStatus"></div>
    </div>
  <?php endif; ?>

  <div class="row mb-3">
    <div class="col-md-4 mb-2 mb-md-0">
      <div class="card mdc-stat">
        <div class="card-body py-3">
          <div class="text-muted small text-uppercase">Total Diproses</div>
          <div class="h4 mb-0"><?= count($allRows) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4 mb-2 mb-md-0">
      <div class="card mdc-stat pending">
        <div class="card-body py-3">
          <div class="text-muted small text-uppercase">Belum Terkirim DC</div>
          <div class="h4 text-warning mb-0"><?= $jumlahBelum ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card mdc-stat sent">
        <div class="card-body py-3">
          <div class="text-muted small text-uppercase">Sudah Terkirim DC</div>
          <div class="h4 text-success mb-0"><?= $jumlahTerkirim ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table id="dtMonitoringDc" class="table table-bordered table-striped table-sm" style="width:100%">
          <thead class="thead-light">
            <tr>
              <th>No Rawat</th>
              <th>No SEP</th>
              <th>Jenis</th>
              <th>Tanggal SEP</th>
              <th>Kemenkes DC</th>
              <th>BPJS DC</th>
              <th>Status DC</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td class="mdc-id"><?= mdc_e($row['no_rawat']) ?></td>
              <td class="mdc-id"><?= mdc_e($row['nosep']) ?></td>
              <td><?= mdc_e($row['jenis_rawat']) ?></td>
              <td data-order="<?= mdc_e($row['tglsep']) ?>"><?= $row['tglsep'] ? date('d-m-Y', strtotime($row['tglsep'])) : '-' ?></td>
              <td class="mdc-status-detail">
                <?php if ($row['is_legacy_sent']): ?>
                  <span class="badge badge-success">terkirim (historis)</span>
                <?php else: ?>
                  <span class="badge <?= strtolower(trim((string)$row['kemenkes_dc_status'])) === 'sent' ? 'badge-success' : 'badge-warning' ?>">
                    <?= mdc_e($row['kemenkes_dc_status'] ?: 'belum') ?>
                  </span>
                  <div class="text-muted mt-1"><?= mdc_datetime($row['kemenkes_dc_sent_at']) ?></div>
                <?php endif; ?>
              </td>
              <td class="mdc-status-detail">
                <?php if ($row['is_legacy_sent']): ?>
                  <span class="badge badge-success">terkirim (historis)</span>
                <?php else: ?>
                  <span class="badge <?= strtolower(trim((string)$row['bpjs_dc_status'])) === 'sent' ? 'badge-success' : 'badge-warning' ?>">
                    <?= mdc_e($row['bpjs_dc_status'] ?: 'belum') ?>
                  </span>
                  <div class="text-muted mt-1"><?= mdc_datetime($row['bpjs_dc_sent_at']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($row['is_sent']): ?>
                  <span class="badge badge-success">Sudah Terkirim</span>
                  <div class="text-muted mdc-status-detail mt-1"><?= $row['is_legacy_sent'] ? 'inacbg_data_terkirim' : 'grouping_recap' ?></div>
                <?php else: ?>
                  <span class="badge badge-warning">Belum Terkirim</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
(function () {
  var syncButton = document.getElementById('btnSyncEklaim');
  var syncStatus = document.getElementById('syncEklaimStatus');
  var syncSeps = <?= json_encode($syncSeps, JSON_UNESCAPED_SLASHES) ?>;
  var syncToken = <?= json_encode($syncToken, JSON_UNESCAPED_SLASHES) ?>;

  if (syncButton && syncStatus) {
    syncButton.addEventListener('click', async function () {
      if (!syncSeps.length) return;
      if (!window.confirm('Periksa ' + syncSeps.length + ' SEP ke E-Klaim dan catat yang sudah terkirim DC?')) return;

      syncButton.disabled = true;
      syncStatus.classList.remove('d-none', 'text-danger', 'text-success');
      syncStatus.classList.add('text-info');

      var checked = 0;
      var saved = 0;
      var already = 0;
      var notSent = 0;
      var failed = 0;

      for (var i = 0; i < syncSeps.length; i++) {
        var noSep = syncSeps[i];
        syncStatus.textContent = 'Memeriksa ' + (i + 1) + '/' + syncSeps.length + ': ' + noSep;

        try {
          var body = new URLSearchParams();
          body.set('_csrf', syncToken);
          body.set('no_sep', noSep);
          var response = await fetch('_private/api_monitoring_dc_sync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
          });
          var result = await response.json();
          checked++;

          if (!response.ok || !result.ok) {
            failed++;
          } else if (result.sent && result.saved) {
            saved++;
          } else if (result.sent) {
            already++;
          } else {
            notSent++;
          }
        } catch (error) {
          checked++;
          failed++;
        }
      }

      syncStatus.classList.remove('text-info');
      syncStatus.classList.add(failed > 0 ? 'text-danger' : 'text-success');
      syncStatus.textContent = 'Selesai: ' + checked + ' diperiksa, ' + saved + ' baru dicatat, ' + already +
        ' sudah tercatat, ' + notSent + ' memang belum terkirim, ' + failed + ' gagal diperiksa. Memuat ulang data...';

      window.setTimeout(function () { window.location.reload(); }, 1500);
    });
  }

  function waitForDataTables(callback, tries) {
    if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
      callback(jQuery);
      return;
    }
    if (tries > 0) setTimeout(function () { waitForDataTables(callback, tries - 1); }, 50);
  }

  waitForDataTables(function ($) {
    if ($.fn.dataTable.isDataTable('#dtMonitoringDc')) return;
    $('#dtMonitoringDc').DataTable({
      responsive: true,
      dom: 'lBfrtip',
      buttons: [
        {
          extend: 'copyHtml5',
          text: '<i class="fas fa-copy"></i> Copy',
          title: null,
          header: true,
          exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
        },
        {
          extend: 'excelHtml5',
          text: '<i class="fas fa-file-excel"></i> Excel',
          title: 'Monitoring Data Center',
          filename: <?= json_encode('Monitoring_Data_Center_' . $tanggalAwal . '_sd_' . $tanggalAkhir . '_' . $filterStatus, JSON_UNESCAPED_SLASHES) ?>,
          exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
        }
      ],
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100],
      ordering: true,
      order: [],
      autoWidth: false,
      language: {
        emptyTable: 'Tidak ada proses Data Center pada tanggal tersebut.',
        search: 'Cari:',
        lengthMenu: 'Tampilkan _MENU_ data',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
        infoEmpty: 'Tidak ada data',
        zeroRecords: 'Data tidak ditemukan',
        paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
      }
    });
  }, 100);
})();
</script>
