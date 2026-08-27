<?php declare(strict_types=1); ?>
<div class="d-flex align-items-center justify-content-between mb-2">
  <h4 class="mb-0">Kunjungan VClaim</h4>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="form-row">
      <div class="col-md-3">
        <label>Tanggal</label>
        <input type="date" id="tglKunjungan" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Jenis Pelayanan</label>
        <select id="jnsKunjungan" class="form-control">
          <option value="2" selected>2 - Rawat Jalan</option>
          <option value="1">1 - Rawat Inap</option>
        </select>
      </div>
      <div class="col-md-3 align-self-end">
        <button id="btnKunjungan" class="btn btn-primary btn-block">Tampilkan</button>
      </div>
    </div>
    <small class="text-muted d-block mt-2" id="statusKunjungan"></small>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <table id="dtKunjungan" class="table table-striped table-bordered table-sm" style="width:100%">
      <thead>
      <tr>
        <th>No SEP</th><th>Tgl SEP</th><th>Jns</th><th>No Kartu</th><th>Nama</th><th>Poli</th><th>Diagnosa</th><th>No Rujukan</th>
      </tr>
      </thead>
    </table>
  </div>
</div>

<script>
(function(){
  function ready(fn){ 
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function(){
    if (typeof window.jQuery === 'undefined') {
      document.getElementById('statusKunjungan').innerText =
        'ERROR: jQuery tidak ter-load. Cek include di footer / koneksi CDN.';
      return;
    }
    if (!jQuery.fn || !jQuery.fn.DataTable) {
      document.getElementById('statusKunjungan').innerText =
        'ERROR: DataTables tidak ter-load. Cek urutan script DataTables.';
      return;
    }

    const API_BASE = 'https://rsudmatraman.my.id/api-website';

    const today = new Date();
    const pad = n => String(n).padStart(2,'0');
    const t = `${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;
    jQuery('#tglKunjungan').val(t);

    const dt = jQuery('#dtKunjungan').DataTable({
      responsive: true,
      responsive: {
        details: {
          display: $.fn.dataTable.Responsive.display.modal({
            header: function (row) {
              var data = row.data();
              return 'Detail SEP: ' + (data.noSep || data.noSEP || '-');
            }
          }),
          renderer: $.fn.dataTable.Responsive.renderer.tableAll({
            tableClass: 'table table-sm table-striped'
          })
        }
      },
      processing: true,
      serverSide: false,
      dom: 'lBfrtip',
      buttons: [{ extend: 'excelHtml5', text: 'Excel', title: 'Kunjungan_VClaim' }],
      lengthMenu: [10,25,50,100],
      pageLength: 10,
      ordering: true,
      autoWidth: false,
      data: [],
      columns: [
        { data:'noSep', defaultContent:'' },
        { data:'tglSep', defaultContent:'' },
        { data:'jnsPelayanan', defaultContent:'' },
        { data:'noKartu', defaultContent:'' },
        { data:'nama', defaultContent:'' },
        { data:'poli', defaultContent:'' },
        { data:'diagnosa', defaultContent:'' },
        { data:'noRujukan', defaultContent:'' }
      ]
    });

    async function load(){
      jQuery('#statusKunjungan').text('Memuat data...');
      const tanggal = jQuery('#tglKunjungan').val();
      const jns = jQuery('#jnsKunjungan').val();
      const url = `${API_BASE}/vclaim/monitoring/kunjungan?tanggal=${encodeURIComponent(tanggal)}&jnsPelayanan=${encodeURIComponent(jns)}`;

      const res = await fetch(url);
      const json = await res.json();
      const rows = (json && json.data) ? json.data : [];

      dt.clear().rows.add(rows).draw();
      jQuery('#statusKunjungan').text(`Total: ${rows.length} data`);
      setTimeout(()=>dt.columns.adjust(), 30);
    }

    jQuery('#btnKunjungan').on('click', function(){ load().catch(err => jQuery('#statusKunjungan').text('Gagal: '+err)); });
    load().catch(()=>{});
  });
})();
</script>


