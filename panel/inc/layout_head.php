<?php
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/support_lib.php';
$pageLede = $pageLede ?? '';
$activeNav = $activeNav ?? '';
$showPageHead = $showPageHead ?? true;
$currentUser = $_SESSION['admin_user'] ?? 'Admin';
$initials = mb_strtoupper(mb_substr($currentUser, 0, 1, 'UTF-8'), 'UTF-8');
$supportUnansweredCount = isset($pdo) && $pdo instanceof PDO ? panel_support_unanswered_count($pdo) : 0;
$withdrawPendingCount = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $withdrawPendingCount = (int) $pdo->query("SELECT COUNT(*) FROM wallet_withdraw WHERE status = 'pending'")->fetchColumn();
    } catch (Throwable $e) {
        $withdrawPendingCount = 0;
    }
}
$devModeOn = function_exists('mirza_is_development_mode')
    ? mirza_is_development_mode()
    : !empty($GLOBALS['development_mode']);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover,interactive-widget=resizes-content">
  <meta name="theme-color" content="#0F172A" id="mtc">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="manifest" href="/panel/manifest.webmanifest">
  <link rel="apple-touch-icon" href="/panel/icons/apple-touch-icon.png">
  <title>Picha Admin Panel</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(panel_asset('css/style.css')) ?>">
  <?php panel_sw_register_script(); ?>
  <script>
    window.openModal = function (id) {
      var m = document.getElementById(id);
      if (!m) return;
      if (m.parentNode !== document.body) document.body.appendChild(m);
      m.offsetWidth;
      m.classList.add('open');
    };
    window.closeModal = function (id) { var m = document.getElementById(id); if (m) m.classList.remove('open'); };
  </script>
  <script>
    (function () {
      var t = localStorage.getItem('panel-theme') || 'navy';
      var bg = {
        navy: '#0F172A', purple: '#180D2E', emerald: '#0A1F1C',
        sunset: '#1A0D0D', slate: '#080808', light: '#F1F5F9',
        linen: '#FAF7F2', mint: '#F0FDF4', lavender: '#FAF5FF'
      };

      var root = document.documentElement;
      root.style.backgroundColor = bg[t] || '#0F172A';
      root.setAttribute('data-theme', t);

      root.style.colorScheme = (t === 'light' || t === 'linen' || t === 'mint' || t === 'lavender') ? 'light' : 'dark';
      var mtc = document.getElementById('mtc');
      if (mtc && bg[t]) mtc.content = bg[t];
      if (localStorage.getItem('panel-sb-collapsed') === '1' && window.innerWidth > 768)
        root.classList.add('sb-pre-collapsed');
    }());
  </script>
</head>

<body>

  <div id="load-bar"></div>
  <div id="toast-area"></div>

  <div class="confirm-veil" id="confirm-veil">
    <div class="confirm-box">
      <div class="confirm-icon"><?= icon('block', 26) ?></div>
      <h4 id="confirm-title">Confirm action</h4>
      <p id="confirm-msg">Are you sure? This action cannot be undone.</p>
      <div class="confirm-btns">
        <button class="btn btn-no" id="confirm-ok">Yes, continue</button>
        <button class="btn btn-ghost" onclick="closeConfirm()">Cancel</button>
      </div>
    </div>
  </div>

  <div class="app">
    <div class="sidebar-backdrop" id="backdrop" onclick="closeSidebar()"></div>

    <aside class="sidebar" id="sidebar">
      <div class="sidebar-brand">
        <div class="brand-mark"><img src="/panel/icons/icon-192.png" alt="Picha" width="28" height="28" style="display:block;border-radius:8px"></div>
        <div class="brand-name">Picha<span> · Panel</span></div>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section">
          <div class="nav-heading">General</div>
          <a href="index.php" class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
            <span class="nav-icon"><?= icon('dashboard') ?></span><span class="nav-label">Dashboard</span>
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-heading">Manage</div>
          <a href="users.php" class="nav-item <?= $activeNav === 'users' ? 'active' : '' ?>" title="Users">
            <span class="nav-icon"><?= icon('users') ?></span><span class="nav-label">Users</span>
          </a>
          <a href="agents.php" class="nav-item <?= $activeNav === 'agents' ? 'active' : '' ?>" title="Agents">
            <span class="nav-icon"><?= icon('users') ?></span><span class="nav-label">Agents</span>
          </a>
          <a href="invoice.php" class="nav-item <?= $activeNav === 'invoice' ? 'active' : '' ?>" title="Orders">
            <span class="nav-icon"><?= icon('invoice') ?></span><span class="nav-label">Orders</span>
          </a>
          <a href="product.php" class="nav-item <?= $activeNav === 'product' ? 'active' : '' ?>" title="Products">
            <span class="nav-icon"><?= icon('package') ?></span><span class="nav-label">Products</span>
          </a>
          <a href="affiliates.php" class="nav-item <?= $activeNav === 'referral' ? 'active' : '' ?>" title="Referral">
            <span class="nav-icon"><?= icon('users') ?></span><span class="nav-label">Referral</span>
          </a>
          <a href="categories.php" class="nav-item <?= $activeNav === 'categories' ? 'active' : '' ?>" title="Categories">
            <span class="nav-icon"><?= icon('package') ?></span><span class="nav-label">Categories</span>
          </a>
          <a href="discounts.php" class="nav-item <?= $activeNav === 'discounts' ? 'active' : '' ?>" title="Discounts">
            <span class="nav-icon"><?= icon('wallet') ?></span><span class="nav-label">Discounts</span>
          </a>
          <a href="payment.php" class="nav-item <?= $activeNav === 'payment' ? 'active' : '' ?>" title="Finance">
            <span class="nav-icon"><?= icon('card') ?></span><span class="nav-label">Finance</span>
          </a>
          <a href="wallet_withdraw.php" class="nav-item <?= $activeNav === 'wallet_withdraw' ? 'active' : '' ?>" title="Wallet withdrawals">
            <span class="nav-icon"><?= icon('wallet') ?></span><span class="nav-label">Withdrawals</span>
            <?php if (($withdrawPendingCount ?? 0) > 0): ?><span class="nav-count"><?= number_format($withdrawPendingCount) ?></span><?php endif; ?>
          </a>
          <a href="payment_methods.php" class="nav-item <?= $activeNav === 'payment_methods' ? 'active' : '' ?>" title="Payment gateways">
            <span class="nav-icon"><?= icon('settings') ?></span><span class="nav-label">Gateways</span>
          </a>
          <a href="panels.php" class="nav-item <?= $activeNav === 'panels' ? 'active' : '' ?>" title="VPN panels">
            <span class="nav-icon"><?= icon('server') ?></span><span class="nav-label">VPN panels</span>
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-heading">Panel</div>
          <a href="stats.php" class="nav-item <?= $activeNav === 'stats' ? 'active' : '' ?>" title="Statistics">
            <span class="nav-icon"><?= icon('chart') ?></span><span class="nav-label">Statistics</span>
          </a>
          <a href="reports.php" class="nav-item <?= $activeNav === 'reports' ? 'active' : '' ?>" title="Reports">
            <span class="nav-icon"><?= icon('search') ?></span><span class="nav-label">Reports</span>
          </a>
          <a href="support.php" class="nav-item <?= $activeNav === 'support' ? 'active' : '' ?>" title="Support inbox">
            <span class="nav-icon"><?= icon('message') ?></span><span class="nav-label">Support inbox</span>
            <?php if ($supportUnansweredCount > 0): ?><span class="nav-count"><?= number_format($supportUnansweredCount) ?></span><?php endif; ?>
          </a>
          <a href="settings.php?tab=bot" class="nav-item <?= $activeNav === 'bot_menu' ? 'active' : '' ?>" title="Bot menu">
            <span class="nav-icon"><?= icon('menu') ?></span><span class="nav-label">Bot menu</span>
          </a>
          <a href="settings.php" class="nav-item <?= $activeNav === 'settings' ? 'active' : '' ?>" title="Settings">
            <span class="nav-icon"><?= icon('settings') ?></span><span class="nav-label">Settings</span>
          </a>
          <a href="logout.php" class="nav-item" title="Log out">
            <span class="nav-icon"><?= icon('logout') ?></span><span class="nav-label">Log out</span>
          </a>
        </div>
      </nav>
      <div class="sidebar-foot">
        <div class="user-pill">
          <div class="user-mono"><?= htmlspecialchars($initials) ?></div>
          <div class="user-info">
            <div class="uname"><?= htmlspecialchars($currentUser) ?></div>
            <div class="urole">Panel admin</div>
          </div>
        </div>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-btn menu-toggle" onclick="openSidebar()"><?= icon('menu', 18) ?></button>
          <button class="icon-btn sb-toggle" onclick="toggleSidebar()"><?= icon('menu', 17) ?></button>
          <div>
            <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
            <div class="crumb"><span>Picha</span><span
                style="opacity:.4;margin:0 3px">/</span><span><?= htmlspecialchars($pageTitle) ?></span></div>
          </div>
        </div>
        <div class="topbar-tools">
          <a href="settings.php" class="icon-btn" title="Settings"><?= icon('settings', 16) ?></a>
          <a href="logout.php" class="icon-btn" title="Log out"><?= icon('logout', 16) ?></a>
        </div>
      </header>
      <main class="content">
        <?php
        $s = get_flash('success');
        $e = get_flash('error');
        $w = get_flash('warning');
        if ($s): ?>
          <div class="notice notice-ok"><?= htmlspecialchars($s) ?></div><?php endif;
        if ($e): ?>
          <div class="notice notice-no"><?= htmlspecialchars($e) ?></div><?php endif;
        if ($w): ?>
          <div class="notice notice-warn"><?= htmlspecialchars($w) ?></div><?php endif;
        if ($showPageHead): ?>
          <div class="page-head fade-up">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if ($pageLede): ?>
              <p><?= htmlspecialchars($pageLede) ?></p><?php endif; ?>
          </div>
        <?php endif; ?>