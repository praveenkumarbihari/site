<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/page_helpers.php';
requireLogin();

$admin = currentAdmin();
$activePage = 'customer';
$pageTitle = 'Customer Profile';
$pageSubtitle = 'Customer summaries and support history';

$selectedCustomer = trim($_GET['name'] ?? '');

try {
    $customers = getCustomerSummaries();
    $customerDetail = null;
    if ($selectedCustomer !== '') {
        $adminId = currentAdminId();
        $orders = getDB()->prepare('SELECT * FROM orders WHERE admin_id = ? AND customer_name = ? ORDER BY created_at DESC');
        $orders->execute([$adminId, $selectedCustomer]);
        $customerOrders = $orders->fetchAll();

        $tickets = getDB()->prepare('SELECT * FROM support_tickets WHERE admin_id = ? AND customer_name = ? ORDER BY created_at DESC');
        $tickets->execute([$adminId, $selectedCustomer]);
        $customerTickets = $tickets->fetchAll();

        if (!empty($customerOrders) || !empty($customerTickets)) {
            $customerDetail = [
                'name' => $selectedCustomer,
                'phone' => $customerOrders[0]['customer_phone'] ?? ($customerTickets[0]['customer_phone'] ?? 'N/A'),
                'email' => $customerOrders[0]['customer_email'] ?? 'N/A',
                'orders' => $customerOrders,
                'tickets' => $customerTickets,
                'total_spent' => array_sum(array_column($customerOrders, 'amount')),
            ];
        }
    }
} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars(dbErrorMessage($e)));
}

include __DIR__ . '/includes/layout_start.php';
?>

<?php if ($customerDetail): ?>
<div class="panel" style="margin-bottom:24px;">
    <div style="display:flex; gap:24px; align-items:flex-start; flex-wrap:wrap;">
        <i class="fas fa-user-circle fa-4x" style="color:#FC8019;"></i>
        <div>
            <h2><?= htmlspecialchars($customerDetail['name']) ?></h2>
            <p style="color:#64748B; margin-top:6px;"><?= htmlspecialchars($customerDetail['phone']) ?> | <?= htmlspecialchars($customerDetail['email']) ?></p>
            <p style="margin-top:8px;">🏅 <?= count($customerDetail['orders']) ?> orders | <?= formatMoney($customerDetail['total_spent']) ?> spent | <?= count($customerDetail['tickets']) ?> tickets</p>
            <a href="customers.php" style="color:#FC8019; font-size:14px; text-decoration:none; margin-top:8px; display:inline-block;">← Back to all customers</a>
        </div>
    </div>
</div>

<div class="chart-grid">
    <div class="panel">
        <h3 style="margin-bottom:16px;">Recent Orders</h3>
        <?php if (empty($customerDetail['orders'])): ?>
            <p class="empty-state">No orders</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Order</th><th>Restaurant</th><th>Status</th><th>Amount</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($customerDetail['orders'], 0, 10) as $o): ?>
                    <tr>
                        <td><?= htmlspecialchars($o['order_id']) ?></td>
                        <td><?= htmlspecialchars($o['restaurant'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($o['status'] ?? '-') ?></td>
                        <td><?= formatMoney($o['amount'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h3 style="margin-bottom:16px;">Support History</h3>
        <?php if (empty($customerDetail['tickets'])): ?>
            <p class="empty-state">No tickets</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Ticket</th><th>Category</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($customerDetail['tickets'] as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['ticket_id']) ?></td>
                        <td><?= htmlspecialchars($t['category'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($t['status'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(formatTicketTime($t['created_at'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<div class="panel">
    <div class="panel-header">
        <h2><i class="fas fa-users"></i> All Customers</h2>
        <div class="search-box">
            <i class="fas fa-search" style="color:#94A3B8;"></i>
            <input type="text" id="searchInput" placeholder="Search customers...">
        </div>
    </div>

    <?php if (empty($customers)): ?>
        <div class="empty-state">
            <h3>No customer data</h3>
            <p><a href="orders.php">Import orders</a> or <a href="tickets.php">import tickets</a> to view profiles.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table" id="customersTable">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Tickets</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['customer_name']) ?></strong></td>
                        <td><?= htmlspecialchars($c['customer_phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['customer_email'] ?? '-') ?></td>
                        <td><?= (int)$c['order_count'] ?></td>
                        <td><?= formatMoney($c['total_spent']) ?></td>
                        <td><?= (int)$c['ticket_count'] ?></td>
                        <td><a href="customers.php?name=<?= urlencode($c['customer_name']) ?>" style="color:#FC8019; font-weight:600; text-decoration:none;">View →</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="count-label"><?= count($customers) ?> customer(s)</p>
    <?php endif; ?>
</div>

<?php if (!empty($customers)): ?>
<script>
document.getElementById('searchInput').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#customersTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/layout_end.php'; ?>
