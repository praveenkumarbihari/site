<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics_engine.php';
requireLogin();

$format = $_GET['format'] ?? 'csv';

try {
    $report = getFullAnalyticsReport();
} catch (PDOException $e) {
    http_response_code(500);
    exit(dbErrorMessage($e));
}

$filename = 'ssm_analytics_report_' . date('Y-m-d_His');

if ($format === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.html"');
    echo generateReportHtml($report);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
echo generateReportCsv($report);

function generateReportHtml(array $report): string
{
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SSM Analytics Report</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #1E2A3E; }
        h1 { color: #FC8019; }
        h2 { color: #FC8019; border-bottom: 2px solid #FC8019; padding-bottom: 8px; margin-top: 32px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 14px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #FFF7ED; }
        .meta { color: #64748B; font-size: 14px; }
    </style>
</head>
<body>
    <h1>SSM Swiggy Support Analytics Report</h1>
    <p class="meta">Generated: <?= htmlspecialchars($report['meta']['generated_at']) ?> |
       Orders: <?= $report['meta']['total_orders'] ?> |
       Tickets: <?= $report['meta']['total_tickets'] ?></p>

    <?php foreach (analyticsLayerKeys() as $key):
        $layer = $report[$key]; ?>
    <h2><?= htmlspecialchars($layer['title']) ?></h2>
    <p><strong>Question:</strong> <?= htmlspecialchars($layer['question'] ?? '') ?></p>

    <?php if (!empty($layer['data']) && isset($layer['data'][0]) && is_array($layer['data'][0])): ?>
    <table>
        <thead><tr><?php foreach (array_keys($layer['data'][0]) as $h): ?><th><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($layer['data'] as $row): ?>
        <tr><?php foreach ($row as $v): ?><td><?= htmlspecialchars((string)$v) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <table>
        <?php foreach ($layer['data'] as $k => $v): ?>
        <tr><th><?= htmlspecialchars($k) ?></th><td><?= htmlspecialchars(is_array($v) ? json_encode($v) : (string)$v) ?></td></tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php endforeach; ?>
</body>
</html>
    <?php
    return ob_get_clean();
}
