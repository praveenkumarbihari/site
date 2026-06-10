<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/page_helpers.php';
requireLogin();

$admin = currentAdmin();
$activePage = 'dashboard';
$pageTitle = 'Dashboard';
$pageSubtitle = 'Support operations overview';
$loadChartJs = true;

try {
    $stats = getDashboardStats();
    $analytics = getAnalyticsData();
} catch (PDOException $e) {
    die('Database error: ' . htmlspecialchars(dbErrorMessage($e)));
}

$categoryLabels = array_column($analytics['categoryData'], 'category');
$categoryCounts = array_map('intval', array_column($analytics['categoryData'], 'cnt'));
$weeklyLabels = array_column($analytics['weeklyVolume'], 'day');
$weeklyCounts = array_map('intval', array_column($analytics['weeklyVolume'], 'cnt'));

if (empty($categoryLabels)) {
    $categoryLabels = ['No Data'];
    $categoryCounts = [0];
}
if (empty($weeklyLabels)) {
    $weeklyLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $weeklyCounts = [0, 0, 0, 0, 0, 0, 0];
}

include __DIR__ . '/includes/layout_start.php';
?>

<div class="kpi-row">
    <div class="kpi-card"><i class="fas fa-inbox"></i><h3><?= (int)$stats['open'] ?></h3><p>Open Tickets</p></div>
    <div class="kpi-card"><i class="fas fa-check-circle"></i><h3><?= (int)$stats['resolvedToday'] ?></h3><p>Resolved Today</p></div>
    <div class="kpi-card"><i class="fas fa-clock"></i><h3><?= htmlspecialchars($stats['avgTime']) ?></h3><p>Avg Resolution</p></div>
    <div class="kpi-card"><i class="fas fa-star"></i><h3><?= htmlspecialchars($stats['csatDisplay']) ?></h3><p>CSAT Score</p></div>
    <div class="kpi-card"><i class="fas fa-undo-alt"></i><h3><?= (int)$stats['refunds'] ?></h3><p>Refund Requests</p></div>
    <div class="kpi-card"><i class="fas fa-flag"></i><h3><?= (int)$stats['escalated'] ?></h3><p>Escalated</p></div>
</div>

<div class="chart-grid">
    <div class="panel">
        <h4 style="margin-bottom:12px;">Ticket Volume (7 days)</h4>
        <canvas id="trendChart" height="150"></canvas>
    </div>
    <div class="panel">
        <h4 style="margin-bottom:12px;">Tickets by Category</h4>
        <canvas id="categoryChart" height="150"></canvas>
    </div>
</div>

<script>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($weeklyLabels) ?>,
        datasets: [{ label: 'Ticket Volume', data: <?= json_encode($weeklyCounts) ?>, borderColor: '#FC8019', tension: 0.3, fill: false }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($categoryLabels) ?>,
        datasets: [{ data: <?= json_encode($categoryCounts) ?>, backgroundColor: ['#FC8019','#FF5200','#FACC15','#F97316','#FB923C','#FDBA74'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php include __DIR__ . '/includes/layout_end.php'; ?>
