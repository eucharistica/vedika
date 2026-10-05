<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/config.php';

function gd_e(?string $value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function gd_date(?string $value): string {
  if (!$value || $value === '0000-00-00') return '-';
  $time = strtotime($value);
  return $time ? date('d-m-Y', $time) : gd_e($value);
}

$sql = "
  SELECT
    queue.no_rawat,
    queue.nosep,
    bridging_sep.nomr AS no_rkm_medis,
    DATE(bridging_sep.tglsep) AS tglsep,
    bridging_sep.jnspelayanan,
    COALESCE(bridging_sep.nmpolitujuan, '-') AS nama_poli,
    queue.message
  FROM mlite_vedika_grouping_queue AS queue
  INNER JOIN bridging_sep
    ON bridging_sep.no_rawat = queue.no_rawat
   AND bridging_sep.no_sep = queue.nosep
  WHERE queue.status = 'failed'
  ORDER BY COALESCE(queue.finished_at, queue.created_at) DESC, queue.id DESC
";

$rows = pdo()->query($sql)->fetchAll();
?>

<style>
  .gd-id { font-family: Consolas, "Courier New", monospace; white-space: nowrap; }
  .gd-message { min-width: 260px; white-space: normal; }
  #dtGroupingDitolak tbody tr:hover { background: #fff7f7; }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
  <h4 class="mb-0">Grouping Ditolak</h4>
  <span class="badge badge-danger badge-pill mt-2 mt-md-0"><?= count($rows) ?> gagal</span>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table id="dtGroupingDitolak" class="table table-bordered table-striped table-sm" style="width:100%">
        <thead class="thead-light">
          <tr>
            <th>No Rawat</th>
            <th>No SEP</th>
            <th>No RM</th>
            <th>Tanggal</th>
            <th>Jenis</th>
            <th>Nama Poli</th>
            <th>Pesan Gagal Grouping</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td class="gd-id"><?= gd_e($row['no_rawat']) ?></td>
            <td class="gd-id"><?= gd_e($row['nosep']) ?></td>
            <td class="gd-id"><?= gd_e($row['no_rkm_medis'] ?: '-') ?></td>
            <td data-order="<?= gd_e($row['tglsep']) ?>"><?= gd_date($row['tglsep']) ?></td>
            <td>
              <?php if ((string)$row['jnspelayanan'] === '1'): ?>
                <span class="badge badge-primary">Rawat Inap</span>
              <?php elseif ((string)$row['jnspelayanan'] === '2'): ?>
                <span class="badge badge-info">Rawat Jalan</span>
              <?php else: ?>
                <span class="badge badge-secondary">-</span>
              <?php endif; ?>
            </td>
            <td><?= gd_e($row['nama_poli']) ?></td>
            <td class="gd-message"><?= gd_e($row['message'] ?: '-') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

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
    if ($.fn.dataTable.isDataTable('#dtGroupingDitolak')) return;

    $('#dtGroupingDitolak').DataTable({
      responsive: true,
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100],
      ordering: true,
      order: [],
      autoWidth: false,
      language: {
        emptyTable: 'Tidak ada antrean grouping yang gagal.',
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
