<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ideator_engine.php';
requireLogin();

$report = $_SESSION['ideator_last_report'] ?? null;
if (!$report) {
    http_response_code(404);
    exit('No ideator report found. Generate ideas from the Analytics page first.');
}

$filename = 'ideator_brief_' . date('Y-m-d_His');
$summary = $report['summary'];
$totals = $summary['totals'] ?? [];

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="' . $filename . '.html"');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($report['title']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @page { margin: 16mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif; color: #1E2A3E;
            max-width: 860px; margin: 0 auto; padding: 28px; line-height: 1.55; font-size: 13px;
            background: #F8FAFC;
        }
        .report-sheet {
            background: white; border-radius: 20px; padding: 32px;
            box-shadow: 0 4px 24px rgba(15,23,42,0.08); border: 1px solid #E8EDF3;
        }
        .report-header {
            border-bottom: 3px solid #FC8019; padding-bottom: 16px; margin-bottom: 22px;
        }
        .report-header h1 { color: #FC8019; font-size: 24px; margin: 0 0 6px; }
        .report-header .meta { color: #64748B; font-size: 12px; margin: 0; }
        .print-hint {
            background: #FFF7ED; border: 1px dashed #FDBA74; padding: 12px 16px;
            border-radius: 12px; margin-bottom: 20px; font-size: 12px; color: #9A3412;
        }
        <?= getIdeatorPdfCss() ?>
        .ideator-section-body { padding-left: 18px !important; }
        .ideator-section-head { padding-top: 12px; }
        .ideator-section-icon i { display: none; }
        @media print {
            .print-hint, .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .report-sheet { box-shadow: none; border: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="print-hint no-print">
        Press <strong>Ctrl+P</strong> (or Cmd+P) → <strong>Save as PDF</strong> for a polished export.
        <button onclick="window.print()" style="margin-left:12px;padding:7px 16px;background:#FC8019;color:white;border:none;border-radius:20px;cursor:pointer;font-weight:600;">Print / Save PDF</button>
    </div>

    <div class="report-sheet">
        <div class="report-header">
            <h1><i class="fas fa-lightbulb"></i> <?= htmlspecialchars($report['title']) ?></h1>
            <p class="meta">
                Crafted <?= htmlspecialchars(date('M j, Y · g:i A', strtotime($report['generated_at']))) ?>
                · from live support data (<?= htmlspecialchars($summary['generated_at'] ?? '') ?>)
            </p>
        </div>

        <div class="ideator-summary-strip" style="margin-bottom:20px;">
            <div class="ideator-stat-card"><strong><?= number_format($totals['orders'] ?? 0) ?></strong><span>Orders</span></div>
            <div class="ideator-stat-card"><strong><?= number_format($totals['tickets'] ?? 0) ?></strong><span>Tickets</span></div>
            <div class="ideator-stat-card"><strong><?= $totals['ticket_rate_pct'] ?? 0 ?>%</strong><span>Ticket Rate</span></div>
            <div class="ideator-stat-card"><strong><?= $totals['avg_csat'] !== null ? $totals['avg_csat'] : '—' ?></strong><span>Avg CSAT</span></div>
        </div>

        <?= $report['insights_html'] ?>
    </div>
</body>
</html>
