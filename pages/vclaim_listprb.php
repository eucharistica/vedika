<?php declare(strict_types=1); ?>
<div class="d-flex align-items-center justify-content-between mb-2">
  <h4 class="mb-0">Monitoring Data PRB</h4>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="form-row">
      <div class="col-md-3">
        <label>Tanggal Mulai</label>
        <input type="date" id="tglKlaim" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Tanggal Akhir</label>
        <input type="date" id="tglKlaimAkhir" class="form-control">
      </div>
      <div class="col-md-3 align-self-end">
        <button id="btnKlaim" class="btn btn-primary btn-block">Tampilkan</button>
      </div>
    </div>
    <small class="text-muted d-block mt-2" id="statusKlaim"></small>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <table id="dtKlaim" class="table table-striped table-bordered table-sm" style="width:100%">
      <thead>
      <tr>
        <th>Tgl SEP</th>
        <th>Tgl Pulang</th>
        <th>No SEP</th>
        <th>Poli</th>
        <th>Peserta Nama</th>
        <th>No Kartu</th>
        <th>No MR</th>
        <th>INACBG Kode</th>
        <th>INACBG Nama</th>
        <th>By Pengajuan</th>
        <th>By Tarif RS</th>
        <th>By Setujui</th>
        <!--<th>By Tarif Gruper</th>-->
        <th>By Topup</th>
        <th>Status</th>
      </tr>
      </thead>
    </table>
  </div>
</div>

<script>
(function(){
  function waitJQ(cb, tries = 100) { // 100 x 50ms = 5 detik
    if (window.jQuery && window.jQuery.fn) return cb(window.jQuery);
    if (tries <= 0) {
      const el = document.getElementById('statusKlaim');
      if (el) el.innerText = 'ERROR: jQuery belum ter-load (cek urutan script di footer).';
      return;
    }
    setTimeout(() => waitJQ(cb, tries - 1), 50);
  }

  waitJQ(function($){
    const API_BASE = 'https://rsudmatraman.my.id/api-website';

    const today = new Date();
    const pad = n => String(n).padStart(2,'0');
    const t = `${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;
    $('#tglKlaim').val(t);

    const dtKlaim = $('#dtKlaim').DataTable({
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
      buttons: [{ extend: 'excelHtml5', text: 'Excel', title: 'Monitoring_Klaim' }],
      lengthMenu: [10,25,50,100],
      pageLength: 10,
      ordering: true,
      autoWidth: false,
      data: [],
      columns: [
        { data:'tglSep', defaultContent:'' },
        { data:'tglPulang', defaultContent:'' },
        { data:'noSEP', defaultContent:'' },
        { data:'poli', defaultContent:'' },
        { data:'pesertaNama', defaultContent:'' },
        { data:'pesertaNoKartu', defaultContent:'' },
        { data:'pesertaNoMR', defaultContent:'' },
        { data:'inacbgKode', defaultContent:'' },
        { data:'inacbgNama', defaultContent:'' },
        { data:'byPengajuan', defaultContent:'' },
        { data:'byTarifRS', defaultContent:'' },
        { data:'bySetujui', defaultContent:'' },
        // { data:'byTarifGruper', defaultContent:'' },
        { data:'byTopup', defaultContent:'' },
        { data:'status', defaultContent:'' }
      ]
    });

    async function loadKlaim() {
      $('#statusKlaim').text('Memuat data...');
      const tanggal = $('#tglKlaim').val();
      const jns = $('#jnsKlaim').val();

      const url = `${API_BASE}/vclaim/monitoring/klaim-merge/dt?draw=1&start=0&length=100000&tanggal=${encodeURIComponent(tanggal)}&jnsPelayanan=${encodeURIComponent(jns)}`;
      const res = await fetch(url);
      const json = await res.json();
      const rows = (json && json.data) ? json.data : [];

      dtKlaim.clear().rows.add(rows).draw();
      $('#statusKlaim').text(`Total: ${rows.length} data`);
      setTimeout(()=>dtKlaim.columns.adjust(), 30);
    }

    $('#btnKlaim').on('click', function(){
      loadKlaim().catch(err => $('#statusKlaim').text('Gagal: ' + err));
    });
  });
})();
</script>



