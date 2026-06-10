<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/page_helpers.php';
requireLogin();

$admin = currentAdmin();
$activePage = 'refund';
$pageTitle = 'Refund Center';
$pageSubtitle = 'Refund requests and status';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 20;
}
$search = trim($_GET['q'] ?? '');

try {
    $totalRefunds = countRefunds($search);
    $totalPages = max(1, (int)ceil($totalRefunds / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $refunds = getRefundsPaginated($page, $perPage, $search);
} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars(dbErrorMessage($e)));
}

$queryParams = [];
if ($search !== '') {
    $queryParams['q'] = $search;
}

include __DIR__ . '/includes/layout_start.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2><i class="fas fa-coins"></i> Refund Queue</h2>
        <div class="panel-tools">
            <form method="GET" action="refunds.php" class="search-box" style="margin:0;">
                <?php if ($perPage !== 20): ?><input type="hidden" name="per_page" value="<?= $perPage ?>"><?php endif; ?>
                <i class="fas fa-search" style="color:#94A3B8;"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search refunds...">
            </form>
            <form method="GET" action="refunds.php" id="perPageForm">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                <select name="per_page" class="per-page-select" onchange="this.form.submit()">
                    <?php foreach ($allowedPerPage as $n): ?>
                        <option value="<?= $n ?>"<?= $perPage === $n ? ' selected' : '' ?>><?= $n ?> / page</option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if ($totalRefunds === 0): ?>
        <div class="empty-state">
            <i class="fas fa-coins fa-3x" style="color:#FC8019; margin-bottom:16px;"></i>
            <?php if ($search !== ''): ?>
                <h3>No refunds match your search</h3>
                <p><a href="refunds.php">Clear search</a></p>
            <?php else: ?>
                <h3>No refund requests</h3>
                <p>Refund tickets appear when category contains "Refund".</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Customer</th>
                        <th>Order</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Order Amount</th>
                        <th>Compensation</th>
                        <th>CSAT</th>
                        <th>Created</th>
                        <th>Refund Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($refunds as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['ticket_id']) ?></strong></td>
                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td><?= htmlspecialchars($r['order_id'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['category'] ?? '-') ?></td>
                        <td><span class="badge <?= statusBadgeClass($r['status'] ?? 'Open') ?>"><?= htmlspecialchars($r['status'] ?? 'Open') ?></span></td>
                        <td><?= formatMoney($r['order_amount'] ?? 0) ?></td>
                        <td><?= formatMoney($r['compensation_amount'] ?? 0) ?></td>
                        <td><?= $r['csat_score'] !== null ? htmlspecialchars($r['csat_score']) : '-' ?></td>
                        <td><?= htmlspecialchars(formatTicketTime($r['created_at'] ?? null)) ?></td>
                        <td><?= $r['refund_completed_at'] ? htmlspecialchars(formatTicketTime($r['refund_completed_at'])) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= renderPagination($page, $totalPages, $totalRefunds, $perPage, 'refunds.php', $queryParams, 'refunds') ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_end.php'; ?>
