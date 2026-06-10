<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics_engine.php';
require_once __DIR__ . '/includes/ideator_engine.php';
require_once __DIR__ . '/includes/page_helpers.php';
requireLogin();

$admin = currentAdmin();
$activePage = 'analytics';
$pageTitle = 'Analytics';
$loadChartJs = true;

try {
    $report = getFullAnalyticsReport();
} catch (PDOException $e) {
    die('Analytics error: ' . htmlspecialchars(dbErrorMessage($e)));
}

$pageSubtitle = number_format($report['meta']['total_orders']) . ' orders · '
    . number_format($report['meta']['total_tickets']) . ' tickets · Updated '
    . $report['meta']['generated_at'];

$jsonReport = json_encode($report, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);

include __DIR__ . '/includes/layout_start.php';
?>

<style>
    .analytics-toolbar {
        display: flex; justify-content: space-between; align-items: center;
        flex-wrap: wrap; gap: 12px; margin-bottom: 20px;
    }
    .meta-pills { display: flex; gap: 10px; flex-wrap: wrap; }
    .pill {
        background: white; border: 1px solid #EDF2F7; padding: 8px 14px;
        border-radius: 20px; font-size: 13px; color: #475569;
    }
    .pill i { color: var(--swiggy-orange); margin-right: 6px; }
    .btn-sm {
        background: var(--swiggy-orange); color: white; border: none; padding: 8px 18px;
        border-radius: 40px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px;
    }
    .btn-sm-outline { background: white; color: var(--swiggy-orange); border: 1px solid var(--swiggy-orange); }
    .nav-layers { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
    .nav-layers a {
        padding: 6px 14px; background: white; border-radius: 20px; font-size: 12px;
        color: #475569; text-decoration: none; border: 1px solid #E2E8F0;
    }
    .nav-layers a:hover { border-color: var(--swiggy-orange); color: var(--swiggy-orange); }
    .layer {
        background: white; border-radius: 24px; padding: 24px; margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,.03); border: 1px solid #EDF2F7;
    }
    .layer-num { font-size: 12px; color: var(--swiggy-orange); font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
    .layer h2 { font-size: 1.1rem; margin: 6px 0 4px; color: #1E2A3E; }
    .layer-q { color: #64748B; font-size: 13px; margin-bottom: 16px; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; }
    .chart-box { min-height: 240px; position: relative; }
    .stat-mini { background: #F8FAFC; border-radius: 16px; padding: 16px; text-align: center; }
    .stat-mini h3 { color: var(--swiggy-orange); font-size: 1.5rem; }
    .stat-mini p { font-size: 12px; color: #64748B; margin-top: 4px; }
    .no-data-msg {
        display: flex; align-items: center; justify-content: center; height: 200px;
        color: #94A3B8; font-size: 14px; text-align: center; padding: 20px;
    }
    @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }

    .ideator-fab {
        position: fixed; bottom: 28px; right: 28px; z-index: 200;
        display: flex; align-items: center; gap: 10px;
        background: linear-gradient(135deg, #FC8019, #FF5200);
        color: white; border: none; border-radius: 999px;
        padding: 14px 22px 14px 18px; cursor: pointer;
        box-shadow: 0 12px 28px rgba(252,128,25,0.45);
        font-weight: 700; font-size: 14px; transition: transform .2s, box-shadow .2s;
    }
    .ideator-fab:hover { transform: scale(1.04); box-shadow: 0 16px 32px rgba(252,128,25,0.5); }
    .ideator-fab i { font-size: 18px; }
    .ideator-modal.modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
        z-index: 1000; align-items: center; justify-content: center; padding: 20px;
    }
    .ideator-modal.modal-overlay.active { display: flex; }
    .ideator-modal .modal-box {
        background: white; border-radius: 28px; padding: 32px; width: 440px; max-width: 100%;
        box-shadow: 0 25px 60px rgba(0,0,0,0.2); text-align: center;
    }
    .ideator-modal .modal-box.wide {
        width: min(920px, 96vw); max-height: 90vh; overflow: hidden;
        display: flex; flex-direction: column; text-align: left;
    }
    .ideator-modal .modal-icon {
        width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 16px;
        display: flex; align-items: center; justify-content: center; font-size: 28px;
        background: rgba(252,128,25,0.12); color: var(--swiggy-orange);
    }
    .ideator-modal .modal-box.success .modal-icon { background: #DCFCE7; color: #15803D; }
    .ideator-modal .modal-box.error .modal-icon { background: #FEE2E2; color: #B91C1C; }
    .ideator-modal .progress-track {
        background: #F1F5F9; border-radius: 999px; height: 10px; overflow: hidden; margin: 16px 0 8px;
    }
    .ideator-modal .progress-fill {
        height: 100%; width: 0%; background: linear-gradient(90deg, #FC8019, #FF5200);
        border-radius: 999px; transition: width 0.35s ease;
    }
    .ideator-progress-title { font-size: 1.35rem; color: #1E2A3E; margin-bottom: 6px; }
    .ideator-progress-sub { font-size: 13px; color: #94A3B8; margin-bottom: 8px; }
    .ideator-results-head {
        display: flex; justify-content: space-between; align-items: flex-start;
        gap: 12px; margin-bottom: 16px; flex-wrap: wrap;
    }
    .ideator-results-head h3 { font-size: 1.2rem; color: #1E2A3E; margin: 0; }
    .ideator-results-meta { font-size: 13px; color: #64748B; margin-top: 4px; }
    .ideator-results-body {
        overflow-y: auto; flex: 1; padding-right: 4px;
        padding-top: 8px;
    }
    <?= getIdeatorInsightsCss() ?>
    .ideator-actions { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; border-top: 1px solid #EDF2F7; padding-top: 16px; }
    .ideator-actions button, .ideator-actions a {
        background: var(--swiggy-orange); color: white; border: none;
        padding: 10px 20px; border-radius: 40px; font-weight: 600; cursor: pointer;
        text-decoration: none; font-size: 13px;
    }
    .ideator-actions .btn-outline { background: white; color: var(--swiggy-orange); border: 1px solid var(--swiggy-orange); }
</style>
<style id="ideatorPdfStyles"><?= getIdeatorPdfCss() ?></style>

<div class="analytics-toolbar">
    <div class="meta-pills">
        <span class="pill"><i class="fas fa-shopping-bag"></i> <?= number_format($report['meta']['total_orders']) ?> Orders</span>
        <span class="pill"><i class="fas fa-ticket-alt"></i> <?= number_format($report['meta']['total_tickets']) ?> Tickets</span>
        <span class="pill"><i class="fas fa-database"></i> Live from MySQL</span>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="download_report.php?format=csv" class="btn-sm"><i class="fas fa-download"></i> CSV Report</a>
        <a href="download_report.php?format=html" class="btn-sm btn-sm-outline"><i class="fas fa-file-alt"></i> HTML Report</a>
    </div>
</div>

<div class="nav-layers">
    <?php for ($i = 1; $i <= 14; $i++): ?>
        <a href="#layer<?= $i ?>">Layer <?= $i ?></a>
    <?php endfor; ?>
</div>

<?php if ($report['meta']['total_tickets'] === 0 && $report['meta']['total_orders'] === 0): ?>
<div class="panel empty-state">
    <i class="fas fa-chart-line fa-3x" style="color:#FC8019; margin-bottom:16px;"></i>
    <h3>No data for analytics yet</h3>
    <p><a href="orders.php">Import orders</a> or <a href="tickets.php">import tickets</a> to populate analytics.</p>
</div>
<?php endif; ?>

<!-- Layer 1 -->
<div class="layer" id="layer1">
    <div class="layer-num">Layer 1 · Pareto Analysis</div>
    <h2><?= htmlspecialchars($report['layer1']['question']) ?></h2>
    <p class="layer-q">Category vs Ticket Count (%)</p>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL1"></canvas><div id="emptyL1" class="no-data-msg" style="display:none;">No ticket categories in database</div></div>
        <div><table class="data-table" id="tableL1"></table></div>
    </div>
</div>

<!-- Layer 2 -->
<div class="layer" id="layer2">
    <div class="layer-num">Layer 2 · Delay Analysis</div>
    <h2><?= htmlspecialchars($report['layer2']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL2"></canvas><div id="emptyL2" class="no-data-msg" style="display:none;">No delay data — import orders with delay_min</div></div>
        <div><table class="data-table" id="tableL2"></table></div>
    </div>
</div>

<!-- Layer 3 -->
<div class="layer" id="layer3">
    <div class="layer-num">Layer 3 · Restaurant Analysis</div>
    <h2><?= htmlspecialchars($report['layer3']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL3"></canvas><div id="emptyL3" class="no-data-msg" style="display:none;">No restaurant data</div></div>
        <div><table class="data-table" id="tableL3"></table></div>
    </div>
</div>

<!-- Layer 4 -->
<div class="layer" id="layer4">
    <div class="layer-num">Layer 4 · Refund Analysis</div>
    <h2><?= htmlspecialchars($report['layer4']['question']) ?></h2>
    <div class="grid-3" id="statsL4"></div>
</div>

<!-- Layer 5 -->
<div class="layer" id="layer5">
    <div class="layer-num">Layer 5 · CSAT Analysis</div>
    <h2><?= htmlspecialchars($report['layer5']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL5"></canvas><div id="emptyL5" class="no-data-msg" style="display:none;">No CSAT scores in database</div></div>
        <div><table class="data-table" id="tableL5"></table></div>
    </div>
</div>

<!-- Layer 6 -->
<div class="layer" id="layer6">
    <div class="layer-num">Layer 6 · Compensation Leakage</div>
    <h2><?= htmlspecialchars($report['layer6']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL6"></canvas><div id="emptyL6" class="no-data-msg" style="display:none;">No compensation data</div></div>
        <div><table class="data-table" id="tableL6"></table></div>
    </div>
</div>

<!-- Layer 7 -->
<div class="layer" id="layer7">
    <div class="layer-num">Layer 7 · Support Channel Analysis</div>
    <h2><?= htmlspecialchars($report['layer7']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL7"></canvas><div id="emptyL7" class="no-data-msg" style="display:none;">No channel data</div></div>
        <div><table class="data-table" id="tableL7"></table></div>
    </div>
</div>

<!-- Layer 8 -->
<div class="layer" id="layer8">
    <div class="layer-num">Layer 8 · Peak Hour Analysis</div>
    <h2><?= htmlspecialchars($report['layer8']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL8"></canvas><div id="emptyL8" class="no-data-msg" style="display:none;">No peak hour data</div></div>
        <div><table class="data-table" id="tableL8"></table></div>
    </div>
</div>

<!-- Layer 9 -->
<div class="layer" id="layer9">
    <div class="layer-num">Layer 9 · Weather Analysis</div>
    <h2><?= htmlspecialchars($report['layer9']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL9"></canvas><div id="emptyL9" class="no-data-msg" style="display:none;">No weather data on orders</div></div>
        <div><table class="data-table" id="tableL9"></table></div>
    </div>
</div>

<!-- Layer 10 -->
<div class="layer" id="layer10">
    <div class="layer-num">Layer 10 · Customer Segmentation</div>
    <h2><?= htmlspecialchars($report['layer10']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL10"></canvas><div id="emptyL10" class="no-data-msg" style="display:none;">No segmentation data</div></div>
        <div id="segmentL10"></div>
    </div>
</div>

<!-- Layer 11 -->
<div class="layer" id="layer11">
    <div class="layer-num">Layer 11 · Resolution Time</div>
    <h2><?= htmlspecialchars($report['layer11']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL11"></canvas><div id="emptyL11" class="no-data-msg" style="display:none;">No resolution timestamps in database</div></div>
        <div><table class="data-table" id="tableL11"></table></div>
    </div>
</div>

<!-- Layer 12 -->
<div class="layer" id="layer12">
    <div class="layer-num">Layer 12 · Order Value</div>
    <h2><?= htmlspecialchars($report['layer12']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL12"></canvas><div id="emptyL12" class="no-data-msg" style="display:none;">No order amount data</div></div>
        <div><table class="data-table" id="tableL12"></table></div>
    </div>
</div>

<!-- Layer 13 -->
<div class="layer" id="layer13">
    <div class="layer-num">Layer 13 · Repeat Contact</div>
    <h2><?= htmlspecialchars($report['layer13']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL13"></canvas><div id="emptyL13" class="no-data-msg" style="display:none;">No linked order tickets</div></div>
        <div><table class="data-table" id="tableL13"></table></div>
    </div>
</div>

<!-- Layer 14 -->
<div class="layer" id="layer14">
    <div class="layer-num">Layer 14 · Agent Performance</div>
    <h2><?= htmlspecialchars($report['layer14']['question']) ?></h2>
    <div class="grid-2">
        <div class="chart-box"><canvas id="chartL14"></canvas><div id="emptyL14" class="no-data-msg" style="display:none;">No agent data (need 5+ tickets per agent)</div></div>
        <div><table class="data-table" id="tableL14"></table></div>
    </div>
</div>

<script>
const R = <?= $jsonReport ?>;
const colors = ['#FC8019','#FF5200','#FACC15','#F97316','#FB923C','#FDBA74','#0284C7','#15803D'];

function makeTable(el, headers, rows) {
    if (!rows.length) {
        el.innerHTML = '<tbody><tr><td colspan="' + headers.length + '" style="text-align:center;color:#94A3B8;padding:24px;">No data available</td></tr></tbody>';
        return;
    }
    el.innerHTML = '<thead><tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr></thead><tbody>' +
        rows.map(r => '<tr>' + r.map(c => `<td>${c}</td>`).join('') + '</tr>').join('') + '</tbody>';
}

function hasRows(arr) {
    return Array.isArray(arr) && arr.length > 0 && arr.some(d => Object.values(d).some(v => v !== 0 && v !== '0' && v !== null && v !== ''));
}

function showChart(canvasId, emptyId, renderFn) {
    const canvas = document.getElementById(canvasId);
    const empty = document.getElementById(emptyId);
    if (!canvas) return;
    canvas.style.display = '';
    if (empty) empty.style.display = 'none';
    renderFn(canvas);
}

function showEmpty(canvasId, emptyId) {
    const canvas = document.getElementById(canvasId);
    const empty = document.getElementById(emptyId);
    if (canvas) canvas.style.display = 'none';
    if (empty) empty.style.display = 'flex';
}

function barChart(canvas, labels, data, label, color) {
    new Chart(canvas, {
        type: 'bar',
        data: { labels, datasets: [{ label, data, backgroundColor: color || colors[0], borderRadius: 8 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
}

function doughnutChart(canvas, labels, data) {
    new Chart(canvas, {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
}

function lineChart(canvas, labels, data, label) {
    new Chart(canvas, {
        type: 'line',
        data: { labels, datasets: [{ label, data, borderColor: colors[0], backgroundColor: 'rgba(252,128,25,.1)', fill: true, tension: .3 }] },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
}

// Layer 1 — from DB tickets by category
const l1 = R.layer1.data || [];
if (l1.length && l1.some(d => d.count > 0)) {
    showChart('chartL1', 'emptyL1', c => doughnutChart(c, l1.map(d => d.category), l1.map(d => d.count)));
} else { showEmpty('chartL1', 'emptyL1'); }
makeTable(document.getElementById('tableL1'), ['Category', 'Count', '%'],
    l1.map(d => [d.category, d.count, d.pct + '%']));

// Layer 2 — delay vs contact rate from orders+tickets join
const l2 = R.layer2.data || [];
if (l2.some(d => d.orders > 0)) {
    showChart('chartL2', 'emptyL2', c => barChart(c, l2.map(d => d.delay), l2.map(d => d.contact_rate), 'Contact Rate %', '#FF5200'));
} else { showEmpty('chartL2', 'emptyL2'); }
makeTable(document.getElementById('tableL2'), ['Delay', 'Orders', 'Tickets', 'Contact Rate'],
    l2.map(d => [d.delay, d.orders, d.tickets, d.contact_rate + '%']));

// Layer 3 — restaurant complaints from DB
const l3 = R.layer3.data || [];
if (l3.length && l3.some(d => d.tickets > 0)) {
    showChart('chartL3', 'emptyL3', c => barChart(c, l3.map(d => d.restaurant), l3.map(d => d.tickets), 'Support Tickets', '#FC8019'));
} else { showEmpty('chartL3', 'emptyL3'); }
makeTable(document.getElementById('tableL3'), ['Restaurant', 'Orders', 'Tickets', 'Missing %', 'Wrong %', 'Complaint %'],
    l3.map(d => [d.restaurant, d.orders, d.tickets, d.missing_rate + '%', d.wrong_rate + '%', d.complaint_pct + '%']));

// Layer 4 — refund stats from DB
const l4 = R.layer4.data || {};
document.getElementById('statsL4').innerHTML = `
    <div class="stat-mini"><h3>${l4.total_refund_tickets ?? 0}</h3><p>Refund Tickets</p></div>
    <div class="stat-mini"><h3>${l4.avg_hours_after_order ?? 0}h</h3><p>Avg Hours After Order</p></div>
    <div class="stat-mini"><h3>${l4.avg_hours_to_refund ?? 0}h</h3><p>Avg Hours to Refund</p></div>
    <div class="stat-mini"><h3>${l4.avg_csat ?? 'N/A'}</h3><p>Avg CSAT</p></div>
    <div class="stat-mini"><h3>${l4.open_refund_tickets ?? 0}</h3><p>Open/Escalated</p></div>
    <div class="stat-mini"><h3>${l4.visibility_issue_pct ?? 0}%</h3><p>Visibility Issue</p></div>`;

// Layer 5 — CSAT by category from DB
const l5 = R.layer5.data || [];
if (l5.length) {
    showChart('chartL5', 'emptyL5', c => barChart(c, l5.map(d => d.category), l5.map(d => d.avg_csat), 'Avg CSAT', '#0284C7'));
} else { showEmpty('chartL5', 'emptyL5'); }
makeTable(document.getElementById('tableL5'), ['Category', 'Avg CSAT', 'Responses'],
    l5.map(d => [d.category, d.avg_csat, d.count]));

// Layer 6 — compensation from DB
const l6 = (R.layer6.data || []).filter(d => d.compensation > 0);
if (l6.length) {
    showChart('chartL6', 'emptyL6', c => barChart(c, l6.map(d => d.category), l6.map(d => d.compensation), 'Compensation ₹', '#15803D'));
} else { showEmpty('chartL6', 'emptyL6'); }
makeTable(document.getElementById('tableL6'), ['Category', 'Compensation ₹', 'Lakh'],
    l6.map(d => [d.category, '₹' + Number(d.compensation).toLocaleString('en-IN'), d.compensation_lakh]));

// Layer 7 — call % by category from DB
const l7 = R.layer7.data || [];
if (l7.length && l7.some(d => d.total > 0)) {
    showChart('chartL7', 'emptyL7', c => barChart(c, l7.map(d => d.category), l7.map(d => d.call_pct), 'Call %', '#EA580C'));
} else { showEmpty('chartL7', 'emptyL7'); }
makeTable(document.getElementById('tableL7'), ['Category', 'Call %', 'Total Tickets'],
    l7.map(d => [d.category, d.call_pct + '%', d.total]));

// Layer 8 — peak hour from DB
const l8 = R.layer8.data || [];
if (l8.length && l8.some(d => d.orders > 0)) {
    showChart('chartL8', 'emptyL8', c => barChart(c, l8.map(d => d.period), l8.map(d => d.ticket_rate), 'Ticket Rate %', '#FC8019'));
} else { showEmpty('chartL8', 'emptyL8'); }
makeTable(document.getElementById('tableL8'), ['Period', 'Orders', 'Tickets', 'Ticket Rate'],
    l8.map(d => [d.period, d.orders, d.tickets, d.ticket_rate + '%']));

// Layer 9 — weather from DB
const l9 = (R.layer9.data || []).filter(d => d.weather !== 'Unknown');
if (l9.length && l9.some(d => d.orders > 0)) {
    showChart('chartL9', 'emptyL9', c => lineChart(c, l9.map(d => d.weather), l9.map(d => d.contact_rate), 'Contact Rate %'));
} else { showEmpty('chartL9', 'emptyL9'); }
makeTable(document.getElementById('tableL9'), ['Weather', 'Orders', 'Tickets', 'Contact Rate'],
    (R.layer9.data || []).map(d => [d.weather, d.orders, d.tickets, d.contact_rate + '%']));

// Layer 10 — segmentation from DB
const l10 = R.layer10.data || {};
const vip = l10.vip_segments || [];
if (vip.length && vip.some(d => d.orders > 0)) {
    showChart('chartL10', 'emptyL10', c => barChart(c, vip.map(d => d.segment), vip.map(d => d.contact_rate), 'Contact Rate %', '#FC8019'));
} else { showEmpty('chartL10', 'emptyL10'); }

const fo = l10.first_order || { orders: 0, tickets: 0, contact_rate: 0 };
let segHtml = `<div class="stat-mini" style="margin-bottom:12px;"><h3>${fo.contact_rate}%</h3><p>First-Order Contact Rate (${fo.tickets}/${fo.orders})</p></div>`;
if (fo.delayed_orders > 0) {
    segHtml += `<div class="stat-mini" style="margin-bottom:12px;"><h3>${fo.delayed_orders}</h3><p>First Orders with 15+ min Delay</p></div>`;
}
segHtml += '<h4 style="margin:16px 0 8px;font-size:14px;">Repeat Complainers</h4>';
const rc = l10.repeat_complainers || [];
if (rc.length) {
    segHtml += '<table class="data-table"><thead><tr><th>Customer</th><th>Tickets</th></tr></thead><tbody>';
    rc.forEach(c => { segHtml += `<tr><td>${c.customer}</td><td>${c.ticket_count}</td></tr>`; });
    segHtml += '</tbody></table>';
} else {
    segHtml += '<p style="color:#94A3B8;font-size:14px;">No repeat complainers in database</p>';
}
document.getElementById('segmentL10').innerHTML = segHtml;

const l11 = R.layer11.data || [];
if (l11.length && l11.some(d => d.tickets > 0)) {
    showChart('chartL11', 'emptyL11', c => barChart(c, l11.map(d => d.category), l11.map(d => d.avg_hours), 'Avg Hours', '#7C3AED'));
} else { showEmpty('chartL11', 'emptyL11'); }
makeTable(document.getElementById('tableL11'), ['Category', 'Avg Hours', 'Tickets'],
    l11.map(d => [d.category, d.avg_hours + 'h', d.tickets]));

const l12 = R.layer12.data || [];
if (l12.length && l12.some(d => d.orders > 0)) {
    showChart('chartL12', 'emptyL12', c => barChart(c, l12.map(d => d.segment), l12.map(d => d.contact_rate), 'Contact Rate %', '#0284C7'));
} else { showEmpty('chartL12', 'emptyL12'); }
makeTable(document.getElementById('tableL12'), ['Segment', 'Orders', 'Tickets', 'Contact Rate'],
    l12.map(d => [d.segment, d.orders, d.tickets, d.contact_rate + '%']));

const l13 = R.layer13.data || [];
if (l13.length && l13.some(d => d.orders > 0)) {
    showChart('chartL13', 'emptyL13', c => doughnutChart(c, l13.map(d => d.bucket), l13.map(d => d.orders)));
} else { showEmpty('chartL13', 'emptyL13'); }
makeTable(document.getElementById('tableL13'), ['Tickets per Order', 'Orders', 'Total Tickets'],
    l13.map(d => [d.bucket, d.orders, d.tickets]));

const l14 = R.layer14.data || [];
if (l14.length) {
    showChart('chartL14', 'emptyL14', c => barChart(c, l14.map(d => d.agent), l14.map(d => d.tickets), 'Tickets Handled', '#EA580C'));
} else { showEmpty('chartL14', 'emptyL14'); }
makeTable(document.getElementById('tableL14'), ['Agent', 'Tickets', 'Avg CSAT', 'Resolved %', 'Avg Handle'],
    l14.map(d => [d.agent, d.tickets, d.avg_csat ?? '-', d.resolved_pct + '%', (d.avg_handle_hrs ?? '-') + (d.avg_handle_hrs != null ? 'h' : '')]));
</script>

<button type="button" class="ideator-fab" id="ideatorFab" title="Discover ideas from your data">
    <i class="fas fa-lightbulb"></i> Ideator
</button>

<div class="ideator-modal modal-overlay" id="ideatorProgressModal">
    <div class="modal-box" id="ideatorProgressBox">
        <div class="modal-icon"><i class="fas fa-lightbulb spinner"></i></div>
        <h3 class="ideator-progress-title">Ideating…</h3>
        <p class="ideator-progress-sub">Your next big idea is on its way</p>
        <p class="ideator-curious-quote" id="ideatorCuriousQuote">Peeking into the patterns your customers leave behind…</p>
        <div class="progress-track"><div class="progress-fill" id="ideatorProgressFill"></div></div>
    </div>
</div>

<div class="ideator-modal modal-overlay" id="ideatorResultsModal">
    <div class="modal-box wide" id="ideatorResultsBox">
        <div class="ideator-results-head">
            <div>
                <h3><i class="fas fa-lightbulb" style="color:#FC8019;"></i> Your Ideator Brief</h3>
                <p class="ideator-results-meta" id="ideatorResultsMeta"></p>
            </div>
            <button type="button" id="ideatorCloseBtn" style="background:none;border:none;font-size:22px;color:#94A3B8;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div class="ideator-summary-strip" id="ideatorSummaryPills"></div>
        <div class="ideator-results-body" id="ideatorResultsBody"></div>
        <div class="ideator-actions">
            <button type="button" id="ideatorDownloadPdf"><i class="fas fa-file-pdf"></i> Download PDF</button>
            <a href="download_ideator_pdf.php" target="_blank" class="btn-outline" id="ideatorOpenPrint"><i class="fas fa-external-link-alt"></i> Open Print View</a>
            <button type="button" class="btn-outline" id="ideatorRegenerate"><i class="fas fa-redo"></i> Ideate Again</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function() {
    const fab = document.getElementById('ideatorFab');
    const progressModal = document.getElementById('ideatorProgressModal');
    const progressBox = document.getElementById('ideatorProgressBox');
    const progressFill = document.getElementById('ideatorProgressFill');
    const curiousQuote = document.getElementById('ideatorCuriousQuote');
    const resultsModal = document.getElementById('ideatorResultsModal');
    const resultsBody = document.getElementById('ideatorResultsBody');
    const resultsMeta = document.getElementById('ideatorResultsMeta');
    const summaryPills = document.getElementById('ideatorSummaryPills');
    let progressTimer = null;
    let quoteTimer = null;
    let lastReport = null;

    const curiousLines = [
        'Peeking into the patterns your customers leave behind…',
        'Connecting dots between tickets, delays, and smiles…',
        'Finding where frustration hides — and where joy waits…',
        'Tracing the story behind every support conversation…',
        'Something interesting is taking shape…',
        'The quiet signals in your data are speaking up…',
        'Almost there — your breakthrough is brewing…',
        'Turning numbers into ideas worth acting on…'
    ];
    let quoteIndex = 0;

    function rotateQuote() {
        curiousQuote.classList.add('fade');
        setTimeout(() => {
            quoteIndex = (quoteIndex + 1) % curiousLines.length;
            curiousQuote.textContent = curiousLines[quoteIndex];
            curiousQuote.classList.remove('fade');
        }, 350);
    }

    function setProgress(pct) {
        progressFill.style.width = pct + '%';
    }

    function formatFriendlyDate(iso) {
        try {
            const d = new Date(iso.replace(' ', 'T'));
            return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
        } catch (e) {
            return iso;
        }
    }

    function showError(msg) {
        progressBox.classList.add('error');
        progressBox.querySelector('.modal-icon').innerHTML = '<i class="fas fa-cloud-rain"></i>';
        progressBox.querySelector('.ideator-progress-title').textContent = 'Not this time';
        progressBox.querySelector('.ideator-progress-sub').textContent = 'The idea slipped away — give it another go';
        curiousQuote.textContent = msg.includes('Import') ? msg : 'Something interrupted the magic. Try again in a moment.';
        progressFill.parentElement.style.display = 'none';
        clearInterval(quoteTimer);
        setTimeout(resetProgressModal, 3500);
    }

    function resetProgressModal() {
        progressModal.classList.remove('active');
        progressBox.classList.remove('error', 'success');
        progressBox.querySelector('.modal-icon').innerHTML = '<i class="fas fa-lightbulb spinner"></i>';
        progressBox.querySelector('.ideator-progress-title').textContent = 'Ideating…';
        progressBox.querySelector('.ideator-progress-sub').textContent = 'Your next big idea is on its way';
        curiousQuote.textContent = curiousLines[0];
        progressFill.parentElement.style.display = '';
        setProgress(0);
    }

    function renderResults(data) {
        lastReport = data;
        const s = data.summary.totals;
        summaryPills.innerHTML = `
            <div class="ideator-stat-card"><strong>${Number(s.tickets).toLocaleString()}</strong><span>Tickets</span></div>
            <div class="ideator-stat-card"><strong>${Number(s.orders).toLocaleString()}</strong><span>Orders</span></div>
            <div class="ideator-stat-card"><strong>${s.ticket_rate_pct}%</strong><span>Ticket Rate</span></div>
            <div class="ideator-stat-card"><strong>${s.avg_csat ?? '—'}</strong><span>Avg CSAT</span></div>`;
        resultsMeta.textContent = 'Crafted ' + formatFriendlyDate(data.generated_at) + ' · from your live support data';
        resultsBody.innerHTML = data.insights_html;
        resultsModal.classList.add('active');
    }

    async function generateIdeas() {
        progressModal.classList.add('active');
        quoteIndex = 0;
        curiousQuote.textContent = curiousLines[0];
        setProgress(6);
        clearInterval(progressTimer);
        clearInterval(quoteTimer);
        quoteTimer = setInterval(rotateQuote, 2800);

        let pct = 6;
        progressTimer = setInterval(() => {
            if (pct < 88) {
                pct += Math.random() * 5 + 1;
                setProgress(Math.min(pct, 88));
            }
        }, 450);

        try {
            const res = await fetch('ideator_api.php', { method: 'POST', credentials: 'same-origin' });
            const data = await res.json();
            clearInterval(progressTimer);
            clearInterval(quoteTimer);

            if (!data.success) {
                showError(data.error || 'Could not finish ideating.');
                return;
            }

            setProgress(100);
            curiousQuote.textContent = 'Got it — your idea is ready to reveal…';
            progressBox.classList.add('success');
            progressBox.querySelector('.modal-icon').innerHTML = '<i class="fas fa-star"></i>';
            progressBox.querySelector('.ideator-progress-title').textContent = 'Eureka!';

            setTimeout(() => {
                resetProgressModal();
                renderResults(data);
            }, 700);
        } catch (err) {
            clearInterval(progressTimer);
            clearInterval(quoteTimer);
            showError('The connection wavered. Check your network and try again.');
        }
    }

    fab.addEventListener('click', generateIdeas);
    document.getElementById('ideatorRegenerate').addEventListener('click', () => {
        resultsModal.classList.remove('active');
        generateIdeas();
    });
    document.getElementById('ideatorCloseBtn').addEventListener('click', () => resultsModal.classList.remove('active'));
    resultsModal.addEventListener('click', e => { if (e.target === resultsModal) resultsModal.classList.remove('active'); });

    document.getElementById('ideatorDownloadPdf').addEventListener('click', () => {
        if (!lastReport) {
            window.open('download_ideator_pdf.php', '_blank');
            return;
        }
        if (typeof html2pdf === 'undefined') {
            window.open('download_ideator_pdf.php', '_blank');
            return;
        }

        const iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;left:-9999px;top:0;width:820px;height:1px;border:none;';
        document.body.appendChild(iframe);

        const doc = iframe.contentDocument || iframe.contentWindow.document;
        const statsHtml = summaryPills.innerHTML;
        doc.open();
        doc.write(buildIdeatorPdfShell(resultsMeta.textContent, statsHtml, resultsBody.innerHTML));
        doc.close();

        const runExport = () => {
            const target = doc.body;
            html2pdf().set({
                margin: [10, 12, 10, 12],
                filename: 'ideator_brief_' + new Date().toISOString().slice(0, 10) + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, logging: false },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'] }
            }).from(target).save().then(() => iframe.remove()).catch(() => {
                iframe.remove();
                window.open('download_ideator_pdf.php', '_blank');
            });
        };

        setTimeout(runExport, 300);
    });

    function buildIdeatorPdfShell(metaLine, statsHtml, insightsHtml) {
        const css = document.getElementById('ideatorPdfStyles')?.textContent || '';
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' + css + '</style></head>'
            + '<body style="margin:0;padding:28px;font-family:Segoe UI,Arial,sans-serif;color:#1E2A3E;background:#fff;">'
            + '<div style="border-bottom:3px solid #FC8019;padding-bottom:14px;margin-bottom:18px;">'
            + '<h1 style="color:#FC8019;font-size:22px;margin:0 0 6px;">Ideator Strategy Brief</h1>'
            + '<p style="font-size:12px;color:#64748B;margin:0;">' + metaLine + '</p></div>'
            + '<div class="ideator-summary-strip">' + statsHtml + '</div>'
            + insightsHtml + '</body></html>';
    }
})();
</script>

<?php include __DIR__ . '/includes/layout_end.php'; ?>
