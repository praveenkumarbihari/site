<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/page_helpers.php';
requireLogin();

$admin = currentAdmin();
$activePage = 'tickets';
$pageTitle = 'Tickets';
$pageSubtitle = 'All support tickets';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
$allowedPerPage = [10, 20, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 20;
}
$search = trim($_GET['q'] ?? '');

try {
    $totalTickets = countTickets($search);
    $totalPages = max(1, (int)ceil($totalTickets / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $tickets = getTicketsPaginated($page, $perPage, $search);
} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars(dbErrorMessage($e)));
}

$queryParams = [];
if ($search !== '') {
    $queryParams['q'] = $search;
}

include __DIR__ . '/includes/layout_start.php';

$importType = 'tickets';
$importLabel = 'Support Tickets';
$sampleType = 'tickets';
include __DIR__ . '/includes/import_widget.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2><i class="fas fa-ticket-alt"></i> Support Tickets</h2>
        <div class="panel-tools">
            <form method="GET" action="tickets.php" class="search-box" style="margin:0;">
                <?php if ($perPage !== 20): ?><input type="hidden" name="per_page" value="<?= $perPage ?>"><?php endif; ?>
                <i class="fas fa-search" style="color:#94A3B8;"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search tickets...">
            </form>
            <form method="GET" action="tickets.php" id="perPageForm">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                <select name="per_page" class="per-page-select" onchange="this.form.submit()">
                    <?php foreach ($allowedPerPage as $n): ?>
                        <option value="<?= $n ?>"<?= $perPage === $n ? ' selected' : '' ?>><?= $n ?> / page</option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if ($totalTickets === 0): ?>
        <div class="empty-state">
            <i class="fas fa-ticket-alt fa-3x" style="color:#FC8019; margin-bottom:16px;"></i>
            <?php if ($search !== ''): ?>
                <h3>No tickets match your search</h3>
                <p><a href="tickets.php">Clear search</a></p>
            <?php else: ?>
                <h3>No tickets yet</h3>
                <p>Upload a file using the import section above.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table" id="ticketsTable">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Customer</th>
                        <th>Order</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Agent</th>
                        <th>CSAT</th>
                        <th>Channel</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['ticket_id']) ?></strong></td>
                        <td><?= htmlspecialchars($t['customer_name']) ?></td>
                        <td><?= htmlspecialchars($t['order_id'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($t['category'] ?? '-') ?></td>
                        <td><span class="badge <?= priorityBadgeClass($t['priority'] ?? 'Medium') ?>"><?= htmlspecialchars($t['priority'] ?? 'Medium') ?></span></td>
                        <td><span class="badge <?= statusBadgeClass($t['status'] ?? 'Open') ?>"><?= htmlspecialchars($t['status'] ?? 'Open') ?></span></td>
                        <td><?= htmlspecialchars($t['agent'] ?? '-') ?></td>
                        <td><?= $t['csat_score'] !== null ? htmlspecialchars($t['csat_score']) : '-' ?></td>
                        <td><?= htmlspecialchars($t['support_channel'] ?? 'Chat') ?></td>
                        <td><?= htmlspecialchars(formatTicketTime($t['created_at'] ?? null)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= renderPagination($page, $totalPages, $totalTickets, $perPage, 'tickets.php', $queryParams, 'tickets') ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/layout_end.php'; ?>
