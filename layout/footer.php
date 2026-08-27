</main>
</div>

<?php
require_once dirname(__DIR__) . '/_private/lib_flash.php';
$toast = flash_pull('toast');
?>

<script src="assets/js/toast.js"></script>

<?php if (is_array($toast)): ?>
<script>
window.addEventListener('load', function () {
    var t = <?= json_encode($toast, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    if (!window.vdToast) return;

    var type = t.type || 'info';
    var title = t.title || 'Notifikasi';
    var message = t.message || '';
    var duration = Number(t.duration || 3500);

    if (typeof vdToast[type] === 'function') {
        vdToast[type](title, message, duration);
    } else {
        vdToast.show(type, title, message, duration);
    }
});
</script>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script src="assets/js/toast.js"></script>

<script>
function adjustTables() {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.dataTable) return;
    try {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
    } catch (e) {}
}

function initIfExists(selector, title) {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) return;
    if (!document.querySelector(selector)) return;
    if ($.fn.dataTable.isDataTable(selector)) return;

    $(selector).DataTable({
        responsive: true,
        dom: 'lBfrtip',
        buttons: [
            { extend: 'excelHtml5', text: 'Excel', title: title }
        ],
        lengthMenu: [10, 25, 50, 100],
        pageLength: 10,
        ordering: true,
        autoWidth: false
    });
}

$(function () {
    const $sidebar = $('#sidebar');
    const $backdrop = $('#sidebar-backdrop');
    const $btnToggleSidebar = $('#btnToggleSidebar');
    const $btnMobileMenu = $('#btnMobileMenu');
    const $btnCloseMobile = $('#btnCloseMobile');

    const saved = localStorage.getItem('vedika_sidebar_collapsed');
    if (saved === '1') {
        $sidebar.addClass('collapsed');
    }

    $btnToggleSidebar.on('click', function () {
        $sidebar.toggleClass('collapsed');
        localStorage.setItem('vedika_sidebar_collapsed', $sidebar.hasClass('collapsed') ? '1' : '0');
        setTimeout(adjustTables, 260);
    });

    function openMobile() {
        $sidebar.removeClass('collapsed');
        localStorage.setItem('vedika_sidebar_collapsed', '0');
        $sidebar.addClass('mobile-open');
        $backdrop.addClass('show');
    }

    function closeMobile() {
        $sidebar.removeClass('mobile-open');
        $backdrop.removeClass('show');
    }

    $btnMobileMenu.on('click', openMobile);
    $btnCloseMobile.on('click', closeMobile);
    $backdrop.on('click', closeMobile);

    $sidebar.on('click', 'a.sidebar-link', function () {
        if (window.matchMedia('(max-width: 767.98px)').matches) {
            closeMobile();
        }
    });

    initIfExists('#dtMliteRajal', 'MliteRajal');
    initIfExists('#dtMliteRanap', 'MliteRanap');

    setTimeout(adjustTables, 50);

    let tt;
    window.addEventListener('resize', function () {
        clearTimeout(tt);
        tt = setTimeout(adjustTables, 150);
    });

    const params = new URLSearchParams(window.location.search);
    const toast = params.get('toast');

    if (toast === 'login_success') {
        vdToast.login('Login berhasil', 'Selamat datang di sistem VEDIKA');
    } else if (toast === 'saved') {
        vdToast.success('Berhasil', 'Data berhasil disimpan');
    } else if (toast === 'updated') {
        vdToast.update('Berhasil', 'Data berhasil diperbarui');
    } else if (toast === 'deleted') {
        vdToast.delete('Berhasil', 'Data berhasil dihapus');
    } else if (toast === 'download') {
        vdToast.download('Download', 'File sedang diproses');
    } else if (toast === 'error') {
        vdToast.error('Terjadi kesalahan', 'Proses tidak dapat diselesaikan');
    }

    if (toast) {
        const url = new URL(window.location.href);
        url.searchParams.delete('toast');
        window.history.replaceState({}, document.title, url.toString());
    }
});
</script>
</body>
</html>