/**
 * toast.js - Sistem notifikasi toast universal VEDIKA
 * Cara pakai:
 *   vdToast.success("Berhasil", "Data berhasil disimpan");
 *   vdToast.error("Gagal", "Terjadi kesalahan pada server");
 *   vdToast.login("Login", "Selamat datang, Rais!");
 *   vdToast.logout("Logout", "Anda telah keluar");
 *   vdToast.create("Dibuat", "Data klaim baru ditambahkan");
 *   vdToast.update("Diupdate", "Data berhasil diperbarui");
 *   vdToast.delete("Dihapus", "Data berhasil dihapus");
 *   vdToast.warning("Perhatian", "Periksa kembali input Anda");
 *   vdToast.info("Info", "Sinkronisasi data berjalan");
 */
(function (window) {
    const ICONS = {
        success: '&#10003;',
        error: '&#10005;',
        warning: '&#9888;',
        info: '&#8505;',
        login: '&#128274;',
        logout: '&#128682;',
        create: '&#43;',
        add: '&#43;',
        update: '&#9998;',
        delete: '&#128465;',
        download: '&#8681;',
    };

    const TITLES = {
        success: 'Berhasil',
        error: 'Terjadi Kesalahan',
        warning: 'Perhatian',
        info: 'Informasi',
        login: 'Login',
        logout: 'Logout',
        create: 'Data Dibuat',
        add: 'Data Ditambahkan',
        update: 'Data Diperbarui',
        delete: 'Data Dihapus',
        download: 'Download',
    };

    function ensureContainer() {
        let c = document.querySelector('.vd-toast-container');
        if (!c) {
            c = document.createElement('div');
            c.className = 'vd-toast-container';
            document.body.appendChild(c);
        }
        return c;
    }

    function show(type, title, message, duration = 3500) {
        const container = ensureContainer();
        const toast = document.createElement('div');
        toast.className = `vd-toast ${type}`;

        toast.innerHTML = `
            <div class="vd-toast-icon">${ICONS[type] || ICONS.info}</div>
            <div class="vd-toast-body">
                <div class="vd-toast-title">${title || TITLES[type] || 'Notifikasi'}</div>
                ${message ? `<div class="vd-toast-msg">${message}</div>` : ''}
            </div>
            <button class="vd-toast-close">&times;</button>
            <div class="vd-toast-progress" style="animation-duration:${duration}ms"></div>
        `;

        container.appendChild(toast);

        const remove = () => {
            toast.classList.add('vd-hide');
            setTimeout(() => toast.remove(), 300);
        };

        toast.querySelector('.vd-toast-close').addEventListener('click', remove);
        const timer = setTimeout(remove, duration);

        toast.addEventListener('mouseenter', () => clearTimeout(timer));
    }

    window.vdToast = {
        show,
        success: (t, m, d) => show('success', t, m, d),
        error:   (t, m, d) => show('error', t, m, d),
        warning: (t, m, d) => show('warning', t, m, d),
        info:    (t, m, d) => show('info', t, m, d),
        login:   (t, m, d) => show('login', t, m, d),
        logout:  (t, m, d) => show('logout', t, m, d),
        create:  (t, m, d) => show('create', t, m, d),
        add:     (t, m, d) => show('add', t, m, d),
        update:  (t, m, d) => show('update', t, m, d),
        delete:  (t, m, d) => show('delete', t, m, d),
        download:(t, m, d) => show('download', t, m, d),
    };
})(window);