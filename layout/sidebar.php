<?php
declare(strict_types=1);
$p = $_GET['page'] ?? 'mlite_rajal';
$u = $user ?? ['fullname'=>'','username'=>''];
?>
<aside id="sidebar">
  <div class="brand d-flex align-items-center justify-content-between">
    <span class="brand-text">VEDIKA</span>

    <div class="d-flex align-items-center">
      <!-- toggle desktop collapse -->
      <button class="btn btn-sm btn-outline-light d-none d-md-inline-block" id="btnToggleSidebar" title="Collapse sidebar">
        <i class="fas fa-angle-double-left"></i>
      </button>
      <!-- close mobile drawer -->
      <button class="btn btn-sm btn-outline-light d-md-none ml-2" id="btnCloseMobile" title="Close">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>

  <div class="px-2 mt-3 text-uppercase small" style="opacity:.65;">Monitoring Mlite</div>
  <a class="sidebar-link <?= $p==='mlite_rajal'?'active':'' ?>" href="index.php?page=mlite_rajal">
    <i class="fas fa-stethoscope"></i><span class="menu-text">Kunjungan Rajal</span>
  </a>
  <a class="sidebar-link <?= $p==='mlite_ranap'?'active':'' ?>" href="index.php?page=mlite_ranap">
    <i class="fas fa-procedures"></i><span class="menu-text">Kunjungan Ranap</span>
  </a>

  <div class="px-2 mt-2 text-uppercase small" style="opacity:.65;">Monitoring VClaim</div>
  <a class="sidebar-link <?= $p==='vclaim_kunjungan'?'active':'' ?>" href="index.php?page=vclaim_kunjungan">
    <i class="fas fa-procedures"></i><span class="menu-text">Monitoring Kunjungan</span>
  </a>
  <a class="sidebar-link <?= $p==='vclaim_klaim'?'active':'' ?>" href="index.php?page=vclaim_klaim">
    <i class="fas fa-file-invoice-dollar"></i><span class="menu-text">Monitoring Klaim</span>
  </a>

  <div class="px-2 mt-2 text-uppercase small" style="opacity:.65;">Analisis Klaim</div>
  <a class="sidebar-link <?= $p==='analisis_rujukan'?'active':'' ?>" href="index.php?page=analisis_rujukan">
    <i class="fas fa-search-plus"></i><span class="menu-text">Bukti Rujukan Aktif</span>
  </a>
  <a class="sidebar-link <?= $p==='klaim_ranap_kamar'?'active':'' ?>" href="index.php?page=klaim_ranap_kamar">
    <i class="fas fa-bed"></i><span class="menu-text">Klaim Ranap per Kamar</span>
  </a>

  <hr style="border-color: rgba(255,255,255,.1)">

  <div class="px-3 small" style="opacity:.85;">
    <div class="menu-text">Login:</div>
    <div class="menu-text font-weight-bold"><?= htmlspecialchars($u['fullname'] ?? '-', ENT_QUOTES) ?></div>
    <div class="menu-text"><?= htmlspecialchars($u['username'] ?? '-', ENT_QUOTES) ?></div>
  </div>

  <a class="sidebar-link mt-2" href="logout.php">
    <i class="fas fa-sign-out-alt"></i><span class="menu-text">Logout</span>
  </a>
</aside>

<main id="content" class="p-2 p-md-3">
  <!-- topbar untuk mobile -->
  <div class="d-flex align-items-center justify-content-between mb-3 d-md-none">
    <button class="btn btn-outline-secondary" id="btnMobileMenu">
      <i class="fas fa-bars"></i>
    </button>
    <div class="text-muted small">
      <?= htmlspecialchars(($u['fullname'] ?? ''), ENT_QUOTES) ?>
    </div>
  </div>
