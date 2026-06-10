<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/page_helpers.php';
requireLogin();

$admin = currentAdmin();
$activePage = 'orders';
$pageTitle = 'Orders';
$pageSubtitle = 'All available orders';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 20;
}
$search = trim($_GET['q'] ?? '');

try {
    $totalOrders = countOrders($search);
    $totalPages = max(1, (int)ceil($totalOrders / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $orders = getOrdersPaginated($page, $perPage, $search);
} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars(dbErrorMessage($e)));
}

$queryParams = [];
if ($search !== '') {
    $queryParams['q'] = $search;
}

include __DIR__ . '/includes/layout_start.php';

$importType = 'orders';
$importLabel = 'Orders';
$sampleType = 'orders';
include __DIR__ . '/includes/import_widget.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2><i class="fas fa-shopping-bag"></i> Orders</h2>
        <div class="panel-tools">
            <form method="GET" action="orders.php" class="search-box" style="margin:0;">
                <?php if ($perPage !== 20): ?><input type="hidden" name="per_page" value="<?= $perPage ?>"><?php endif; ?>
                <i class="fas fa-search" style="color:#94A3B8;"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search orders...">
            </form>
            <form method="GET" action="orders.php" id="perPageForm">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                <select name="per_page" class="per-page-select" onchange="this.form.submit()">
                    <?php foreach ($allowedPerPage as $n): ?>
                        <option value="<?= $n ?>"<?= $perPage === $n ? ' selected' : '' ?>><?= $n ?> / page</option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if ($totalOrders === 0): ?>
        <div class="empty-state">
            <i class="fas fa-shopping-bag fa-3x" style="color:#FC8019; margin-bottom:16px;"></i>
            <?php if ($search !== ''): ?>
                <h3>No orders match your search</h3>
                <p><a href="orders.php">Clear search</a></p>
            <?php else: ?>
                <h3>No orders yet</h3>
                <p>Upload a file using the import section above.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table" id="ordersTable">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Restaurant</th>
                        <th>Partner</th>
                        <th>Status</th>
                        <th>ETA</th>
                        <th>Delay</th>
                        <th>Amount</th>
                        <th>Weather</th>
                        <th>Peak Hour</th>
                        <th>Order Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($o['order_id']) ?></strong></td>
                        <td><?= htmlspecialchars($o['customer_name']) ?></td>
                        <td><?= htmlspecialchars($o['restaurant'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($o['delivery_partner'] ?? '-') ?></td>
                        <td><span class="badge <?= statusBadgeClass($o['status'] ?? 'Pending') ?>"><?= htmlspecialchars($o['status'] ?? 'Pending') ?></span></td>
                        <td><?= htmlspecialchars($o['eta'] ?? ($o['eta_shown_min'] ? $o['eta_shown_min'] . ' min' : '-')) ?></td>
                        <td><?= $o['delay_min'] !== null ? (int)$o['delay_min'] . ' min' : '-' ?></td>
                        <td><?= formatMoney($o['amount'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($o['weather'] ?? '-') ?></td>
                        <td><?= pgBool($o['is_peak_hour'] ?? false) ? 'Yes' : 'No' ?></td>
                        <td><?= formatDate($o['order_date'] ?? null) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= renderPagination($page, $totalPages, $totalOrders, $perPage, 'orders.php', $queryParams) ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_end.php'; ?>
