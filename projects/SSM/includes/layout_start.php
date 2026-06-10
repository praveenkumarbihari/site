<?php
/** @var string $activePage */
/** @var string $pageTitle */
/** @var array|null $admin */
/** @var bool $showMagicFab */
/** @var bool $loadChartJs */

$initials = adminInitials($admin['name'] ?? 'Admin');
$loadChartJs = $loadChartJs ?? false;
$showMagicFab = $showMagicFab ?? false;

$navItems = [
    'dashboard' => ['href' => 'dashboard.php', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
    'tickets'   => ['href' => 'tickets.php',   'icon' => 'fa-ticket-alt',      'label' => 'Tickets'],
    'orders'    => ['href' => 'orders.php',    'icon' => 'fa-shopping-bag',    'label' => 'Orders'],
    'refund'    => ['href' => 'refunds.php',   'icon' => 'fa-coins',           'label' => 'Refund Center'],
    'customer'  => ['href' => 'customers.php', 'icon' => 'fa-user-circle',     'label' => 'Customer Profile'],
    'analytics' => ['href' => 'analytics.php', 'icon' => 'fa-chart-line',      'label' => 'Analytics'],
    'rocky'     => ['href' => 'rocky.php',     'icon' => 'fa-robot',           'label' => 'Rocky.AI'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | SSM Swiggy Support</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php if ($loadChartJs): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php endif; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F6F9FE; overflow: hidden; }
        :root { --swiggy-orange: #FC8019; }
        .app { display: flex; height: 100vh; width: 100%; }
        .sidebar {
            width: 270px; background: white; border-right: 1px solid #EFF3F8;
            display: flex; flex-direction: column; overflow-y: auto; flex-shrink: 0;
        }
        .logo-area { padding: 28px 24px; border-bottom: 1px solid #eee; }
        .logo-area h2 { display: flex; align-items: center; gap: 10px; color: var(--swiggy-orange); }
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 14px 24px; margin: 4px 12px;
            border-radius: 16px; font-weight: 500; color: #475569; text-decoration: none;
        }
        .nav-item i { width: 24px; }
        .nav-item.active, .nav-item:hover { background: rgba(252,128,25,0.08); color: var(--swiggy-orange); }
        .sidebar-footer { margin-top: auto; padding: 12px 0 20px; border-top: 1px solid #EFF3F8; }
        .nav-item-danger {
            width: calc(100% - 24px); margin: 4px 12px; border: none; background: none;
            cursor: pointer; font-family: inherit; font-size: inherit; text-align: left;
        }
        .nav-item-danger:hover { background: #FEE2E2; color: #B91C1C; }
        .nav-item-danger i { color: #DC2626; }
        .flash-banner {
            padding: 14px 18px; border-radius: 16px; margin-bottom: 20px; font-size: 14px;
            display: flex; align-items: center; gap: 10px;
        }
        .flash-banner-success { background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0; }
        .flash-banner-error { background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }
        .reset-modal.modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
            z-index: 1100; align-items: center; justify-content: center; padding: 20px;
        }
        .reset-modal.modal-overlay.active { display: flex; }
        .reset-modal .modal-box {
            background: white; border-radius: 28px; padding: 32px; width: 420px; max-width: 100%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2); text-align: center;
        }
        .reset-modal .modal-icon {
            width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 16px;
            display: flex; align-items: center; justify-content: center; font-size: 28px;
            background: #FEE2E2; color: #DC2626;
        }
        .reset-modal h3 { color: #1E2A3E; margin-bottom: 8px; font-size: 1.25rem; }
        .reset-modal p { color: #64748B; font-size: 14px; line-height: 1.55; margin-bottom: 24px; }
        .reset-modal-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .reset-modal-actions button {
            padding: 10px 24px; border-radius: 40px; font-weight: 600; font-size: 14px; cursor: pointer; border: none;
        }
        .btn-reset-cancel { background: #F1F5F9; color: #475569; }
        .btn-reset-cancel:hover { background: #E2E8F0; }
        .btn-reset-confirm { background: #DC2626; color: white; }
        .btn-reset-confirm:hover { background: #B91C1C; }
        .btn-reset-confirm:disabled { opacity: 0.6; cursor: not-allowed; }
        .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        .topbar {
            background: white; padding: 16px 28px; display: flex;
            justify-content: space-between; align-items: center; border-bottom: 1px solid #EFF3F8;
        }
        .page-heading h1 { font-size: 1.25rem; color: #1E2A3E; }
        .page-heading p { font-size: 13px; color: #64748B; margin-top: 2px; }
        .user-area { display: flex; gap: 24px; align-items: center; }
        .avatar {
            background: var(--swiggy-orange); width: 42px; height: 42px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;
        }
        .content { padding: 28px; overflow-y: auto; flex: 1; }
        .panel {
            background: white; border-radius: 28px; padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02); border: 1px solid #EDF2F7;
        }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .panel-header h2 { font-size: 1.15rem; color: #1E2A3E; }
        .search-box {
            background: #F1F5F9; border-radius: 40px; padding: 8px 16px;
            display: flex; gap: 8px; align-items: center; min-width: 240px;
        }
        .search-box input { border: none; background: transparent; outline: none; width: 100%; font-size: 14px; }
        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px,1fr)); gap: 18px; margin-bottom: 28px; }
        .kpi-card { background: white; border-radius: 28px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); border: 1px solid #EDF2F7; }
        .kpi-card h3 { font-size: 1.6rem; margin: 8px 0 4px; color: #1E2A3E; }
        .kpi-card p { font-size: 13px; color: #64748B; }
        .kpi-card i { color: var(--swiggy-orange); }
        .badge { padding: 4px 12px; border-radius: 60px; font-size: 12px; font-weight: 600; display: inline-block; background: #E0F2FE; color: #0284C7; }
        .badge-critical { background: #FEE2E2; color: #B91C1C; }
        .badge-high { background: #FFEDD5; color: #EA580C; }
        .badge-medium { background: #E0F2FE; color: #0284C7; }
        .badge-low { background: #DCFCE7; color: #15803D; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .data-table th, .data-table td { padding: 12px 14px; border-bottom: 1px solid #ECF3FA; text-align: left; }
        .data-table th { color: #64748B; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        .data-table tbody tr:hover { background: #FAFBFD; }
        .empty-state { text-align: center; padding: 48px 20px; color: #64748B; }
        .empty-state a { color: var(--swiggy-orange); font-weight: 600; text-decoration: none; }
        .count-label { font-size: 13px; color: #64748B; margin-top: 16px; }
        .pagination-bar {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 20px; flex-wrap: wrap; gap: 12px;
        }
        .pagination { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .page-btn, .page-num {
            padding: 8px 14px; border-radius: 12px; font-size: 13px; font-weight: 600;
            text-decoration: none; color: #475569; border: 1px solid #E2E8F0; background: white;
        }
        .page-num { min-width: 38px; text-align: center; }
        .page-btn:hover, .page-num:hover:not(.active) { border-color: var(--swiggy-orange); color: var(--swiggy-orange); }
        .page-num.active { background: var(--swiggy-orange); color: white; border-color: var(--swiggy-orange); }
        .page-btn.disabled, .page-ellipsis { padding: 8px 10px; color: #94A3B8; font-size: 13px; }
        .page-btn.disabled { border: 1px solid #EDF2F7; background: #F8FAFC; }
        .per-page-select {
            padding: 8px 12px; border-radius: 12px; border: 1px solid #E2E8F0;
            font-size: 13px; background: white; color: #475569;
        }
        .panel-tools { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .import-panel {
            background: white; border-radius: 20px; padding: 18px 22px; margin-bottom: 20px;
            border: 1px solid #EDF2F7; box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .import-panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px; }
        .import-panel-header h3 { font-size: 15px; color: #1E2A3E; font-weight: 600; }
        .import-panel-header h3 i { color: var(--swiggy-orange); margin-right: 6px; }
        .import-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .import-form input[type=file] {
            flex: 1; min-width: 200px; padding: 10px 14px; border-radius: 14px;
            border: 1px solid #E2E8F0; font-size: 13px; background: #FAFBFD;
        }
        .btn-import {
            background: var(--swiggy-orange); color: white; border: none; padding: 10px 22px;
            border-radius: 40px; font-weight: 600; cursor: pointer; font-size: 13px; white-space: nowrap;
        }
        .btn-import:hover { background: #FF5200; }
        .btn-import:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-import-outline {
            background: white; color: var(--swiggy-orange); border: 1px solid var(--swiggy-orange);
            padding: 8px 16px; border-radius: 40px; text-decoration: none; font-weight: 600; font-size: 13px;
        }
        .import-hint { font-size: 12px; color: #94A3B8; margin-top: 8px; }
        .import-alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 12px; font-size: 13px; }
        .import-alert-success { background: #DCFCE7; color: #15803D; }
        .import-alert-error { background: #FEE2E2; color: #B91C1C; }
        .import-modal.modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
            z-index: 1000; align-items: center; justify-content: center; padding: 20px;
        }
        .import-modal.modal-overlay.active { display: flex; }
        .import-modal .modal-box {
            background: white; border-radius: 28px; padding: 36px 32px; width: 420px; max-width: 100%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2); text-align: center;
        }
        .import-modal .modal-icon {
            width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 20px;
            display: flex; align-items: center; justify-content: center; font-size: 28px;
            background: rgba(252,128,25,0.12); color: var(--swiggy-orange);
        }
        .import-modal .modal-box.success .modal-icon { background: #DCFCE7; color: #15803D; }
        .import-modal .modal-box.error .modal-icon { background: #FEE2E2; color: #B91C1C; }
        .import-modal .modal-box h3 { color: #1E2A3E; margin-bottom: 8px; font-size: 1.25rem; }
        .import-modal .modal-status { color: #64748B; font-size: 14px; margin-bottom: 24px; min-height: 20px; }
        .import-modal .progress-track {
            background: #F1F5F9; border-radius: 999px; height: 10px; overflow: hidden; margin-bottom: 10px;
        }
        .import-modal .progress-fill {
            height: 100%; width: 0%; background: linear-gradient(90deg, #FC8019, #FF5200);
            border-radius: 999px; transition: width 0.35s ease;
        }
        .import-modal .progress-pct { font-size: 13px; color: #94A3B8; margin-bottom: 20px; }
        .import-modal .modal-steps { text-align: left; margin: 20px 0; }
        .import-modal .step-item {
            display: flex; align-items: center; gap: 12px; padding: 8px 0;
            font-size: 13px; color: #94A3B8;
        }
        .import-modal .step-item.active { color: #1E2A3E; font-weight: 600; }
        .import-modal .step-item.done { color: #15803D; }
        .import-modal .step-item i { width: 18px; text-align: center; }
        .import-modal .modal-close {
            background: var(--swiggy-orange); color: white; border: none;
            padding: 10px 28px; border-radius: 40px; font-weight: 600; cursor: pointer; display: none;
        }
        .import-modal .modal-close.show { display: inline-block; }
        .spinner { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .magic-fab {
            position: fixed; bottom: 28px; right: 28px; width: 58px; height: 58px;
            border-radius: 50%; border: none; background: linear-gradient(135deg, #FC8019, #FF5200);
            color: white; font-size: 22px; cursor: pointer; z-index: 200;
            box-shadow: 0 12px 28px rgba(252,128,25,0.45); transition: transform .2s, box-shadow .2s;
        }
        .magic-fab:hover { transform: scale(1.08); box-shadow: 0 16px 32px rgba(252,128,25,0.5); }
        .ai-panel {
            position: fixed; bottom: 100px; right: 28px; background: white; width: 320px;
            border-radius: 28px; padding: 20px; box-shadow: 0 20px 35px rgba(0,0,0,0.15);
            border-left: 5px solid var(--swiggy-orange); z-index: 199;
            display: none; animation: slideUp .25s ease;
        }
        .ai-panel.open { display: block; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 900px) { .chart-grid { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .sidebar { width: 80px; } .nav-item span { display: none; } }
    </style>
</head>
<body>
<div class="app">
    <div class="sidebar">
        <div class="logo-area">
            <h2><i class="fas fa-hamburger"></i> SSM</h2>
            <span style="font-size:12px;">Swiggy Support</span>
        </div>
        <nav class="nav-list">
            <?php foreach ($navItems as $key => $item): ?>
                <a href="<?= $item['href'] ?>" class="nav-item<?= $activePage === $key ? ' active' : '' ?>">
                    <i class="fas <?= $item['icon'] ?>"></i><span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <button type="button" class="nav-item nav-item-danger" id="resetDataBtn">
                <i class="fas fa-trash-alt"></i><span>Reset Data</span>
            </button>
        </div>
    </div>
    <div class="main">
        <div class="topbar">
            <div class="page-heading">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if (!empty($pageSubtitle)): ?>
                    <p><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="user-area">
                <span style="font-size:14px; color:#64748B;"><?= htmlspecialchars($admin['name'] ?? '') ?></span>
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                <a href="logout.php" title="Logout"><i class="fas fa-sign-out-alt" style="color:#64748B;"></i></a>
            </div>
        </div>
        <div class="content">
        <?php if (!empty($_SESSION['flash'])):
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            $flashClass = ($flash['type'] ?? '') === 'error' ? 'flash-banner-error' : 'flash-banner-success';
            $flashIcon = ($flash['type'] ?? '') === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
        ?>
            <div class="flash-banner <?= $flashClass ?>">
                <i class="fas <?= $flashIcon ?>"></i>
                <?= htmlspecialchars($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>
