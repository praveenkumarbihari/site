<?php

require_once __DIR__ . '/analytics_engine.php';
require_once __DIR__ . '/../config/llm.php';

function buildIdeatorSummary(array $report): array
{
    $orders = (int)($report['meta']['total_orders'] ?? 0);
    $tickets = (int)($report['meta']['total_tickets'] ?? 0);
    $ticketRate = $orders > 0 ? round($tickets / $orders * 100, 2) : 0;

    $categories = [];
    foreach ($report['layer1']['data'] ?? [] as $row) {
        $categories[] = [
            'category' => $row['category'],
            'count'    => (int)$row['count'],
            'pct'      => (float)$row['pct'],
        ];
    }

    $delayBuckets = [];
    foreach ($report['layer2']['data'] ?? [] as $row) {
        $delayBuckets[] = [
            'delay'         => $row['delay'],
            'orders'        => (int)$row['orders'],
            'tickets'       => (int)$row['tickets'],
            'contact_rate'  => (float)$row['contact_rate'],
        ];
    }

    $topRestaurants = array_slice($report['layer3']['data'] ?? [], 0, 5);
    $refund = $report['layer4']['data'] ?? [];
    $csatByCategory = $report['layer5']['data'] ?? [];
    $compensation = $report['layer6']['data'] ?? [];
    $channels = $report['layer7']['data'] ?? [];
    $peakHours = $report['layer8']['data'] ?? [];
    $weather = $report['layer9']['data'] ?? [];
    $segmentation = $report['layer10']['data'] ?? [];
    $resolutionTime = $report['layer11']['data'] ?? [];
    $orderValue = $report['layer12']['data'] ?? [];
    $repeatContact = $report['layer13']['data'] ?? [];
    $agentPerformance = array_slice($report['layer14']['data'] ?? [], 0, 8);

    $totalCompensation = 0;
    foreach ($compensation as $c) {
        $totalCompensation += (float)($c['compensation'] ?? 0);
    }

    $avgCsat = null;
    $csatCount = 0;
    $csatSum = 0;
    foreach ($csatByCategory as $c) {
        $csatSum += (float)$c['avg_csat'] * (int)$c['count'];
        $csatCount += (int)$c['count'];
    }
    if ($csatCount > 0) {
        $avgCsat = round($csatSum / $csatCount, 2);
    }

    $statusBreakdown = [];
    try {
        $db = getDB();
        $adminId = (int)($report['meta']['admin_id'] ?? 0);
        if ($adminId > 0) {
            $statusStmt = $db->prepare(
                'SELECT status, COUNT(*) AS cnt FROM support_tickets WHERE admin_id = ? GROUP BY status ORDER BY cnt DESC'
            );
            $statusStmt->execute([$adminId]);
            $statusRows = $statusStmt->fetchAll();
        } else {
            $statusRows = [];
        }
        foreach ($statusRows as $r) {
            $statusBreakdown[] = [
                'status' => $r['status'] ?: 'Unknown',
                'count'  => (int)$r['cnt'],
            ];
        }
    } catch (Throwable) {
        // optional
    }

    return [
        'generated_at' => $report['meta']['generated_at'] ?? date('Y-m-d H:i:s'),
        'totals' => [
            'orders'       => $orders,
            'tickets'      => $tickets,
            'ticket_rate_pct' => $ticketRate,
            'avg_csat'     => $avgCsat,
            'total_compensation_inr' => round($totalCompensation, 2),
        ],
        'category_breakdown' => $categories,
        'ticket_status' => $statusBreakdown,
        'delay_vs_contact' => $delayBuckets,
        'top_restaurants_by_tickets' => array_map(static function ($r) {
            return [
                'restaurant'    => $r['restaurant'],
                'orders'        => (int)$r['orders'],
                'tickets'       => (int)$r['tickets'],
                'complaint_pct' => (float)$r['complaint_pct'],
            ];
        }, $topRestaurants),
        'refund_metrics' => [
            'total_refund_tickets'  => (int)($refund['total_refund_tickets'] ?? 0),
            'avg_hours_after_order' => (float)($refund['avg_hours_after_order'] ?? 0),
            'avg_hours_to_refund'   => (float)($refund['avg_hours_to_refund'] ?? 0),
            'avg_csat'              => $refund['avg_csat'] ?? null,
            'open_refund_tickets'   => (int)($refund['open_refund_tickets'] ?? 0),
            'visibility_issue_pct'  => (float)($refund['visibility_issue_pct'] ?? 0),
        ],
        'csat_by_category' => $csatByCategory,
        'compensation_by_category' => $compensation,
        'support_channel_mix' => $channels,
        'peak_hour_analysis' => $peakHours,
        'weather_impact' => $weather,
        'customer_segmentation' => [
            'first_order'         => $segmentation['first_order'] ?? [],
            'vip_segments'        => $segmentation['vip_segments'] ?? [],
            'repeat_complainers'  => array_slice($segmentation['repeat_complainers'] ?? [], 0, 5),
        ],
        'resolution_time_by_category' => $resolutionTime,
        'order_value_contact_rate' => $orderValue,
        'repeat_contact_orders' => $repeatContact,
        'agent_performance' => $agentPerformance,
        'existing_insights' => [
            'layer1'  => $report['layer1']['insight'] ?? '',
            'layer2'  => $report['layer2']['insight'] ?? '',
            'layer3'  => $report['layer3']['insight'] ?? '',
            'layer4'  => $report['layer4']['insight'] ?? '',
            'layer5'  => $report['layer5']['insight'] ?? '',
            'layer6'  => $report['layer6']['insight'] ?? '',
            'layer7'  => $report['layer7']['insight'] ?? '',
            'layer8'  => $report['layer8']['insight'] ?? '',
            'layer9'  => $report['layer9']['insight'] ?? '',
            'layer10' => $report['layer10']['insight'] ?? '',
            'layer11' => $report['layer11']['insight'] ?? '',
            'layer12' => $report['layer12']['insight'] ?? '',
            'layer13' => $report['layer13']['insight'] ?? '',
            'layer14' => $report['layer14']['insight'] ?? '',
        ],
    ];
}

function buildIdeatorPrompt(array $summary): string
{
    $json = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return <<<PROMPT
You are a senior product operations strategist for Swiggy (food delivery customer support).

Analyze the following live support analytics snapshot from the SSM (Swiggy Support Management) system.

Your goal: propose ideas to **reduce support ticket volume**, **increase customer satisfaction (CSAT)**, and **optimize support operations**.

Use the data below. Be specific — reference categories, percentages, and metrics from the data. Do not invent numbers not present in the data.

Respond in clean Markdown with these sections:

## Executive Summary
(2-3 sentences on the biggest opportunity)

## Key Insights
(5-8 bullet points with data-backed observations)

## Root Causes
(What is driving the top ticket categories and low CSAT areas)

## Actionable Recommendations
(Prioritized list: P0 / P1 / P2 with expected impact on ticket reduction and CSAT)

## Process Optimization
(Workflow, automation, self-service, and agent tooling improvements)

## Quick Wins (30 days)
(3-5 items that can be implemented fast)

## Success Metrics
(KPIs to track after implementing recommendations)

---
ANALYTICS DATA (JSON):
{$json}
PROMPT;
}

function callGeminiFlash(string $prompt): array
{
    $apiKey = GEMINI_API_KEY;
    if ($apiKey === '' || $apiKey === 'YOUR_API_KEY_HERE') {
        throw new RuntimeException('Gemini API key is not configured in config/llm.php');
    }

    $model = GEMINI_MODEL;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

    $payload = json_encode([
        'contents' => [
            ['parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => [
            'temperature'     => 0.65,
            'maxOutputTokens' => 4096,
        ],
    ], JSON_UNESCAPED_UNICODE);

    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for Gemini API calls.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Gemini API request failed: ' . $curlError);
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
        throw new RuntimeException('Gemini API error: ' . $msg);
    }

    $text = '';
    foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
        if (isset($part['text'])) {
            $text .= $part['text'];
        }
    }

    if (trim($text) === '') {
        throw new RuntimeException('Gemini returned an empty response.');
    }

    return [
        'text'    => trim($text),
        'model'   => $model,
        'raw'     => $data,
    ];
}

function markdownToRichHtml(string $md): string
{
    $lines = preg_split('/\r\n|\r|\n/', $md);
    $html = '<div class="ideator-report-content">';
    $inList = false;
    $listType = 'ul';
    $sectionOpen = false;
    $sectionBodyOpen = false;

    $closeList = static function () use (&$html, &$inList) {
        if ($inList) {
            $html .= '</ul>';
            $inList = false;
        }
    };

    $closeSection = static function () use (&$html, &$sectionOpen, &$sectionBodyOpen, $closeList) {
        $closeList();
        if ($sectionBodyOpen) {
            $html .= '</div></div>';
            $sectionBodyOpen = false;
        }
        $sectionOpen = false;
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            $closeList();
            continue;
        }

        if (preg_match('/^## (.+)$/', $trimmed, $m)) {
            $closeSection();
            $title = $m[1];
            $icon = ideatorSectionIcon($title);
            $variant = ideatorSectionVariant($title);
            $html .= '<div class="ideator-section ideator-section--' . $variant . '">';
            $html .= '<div class="ideator-section-head"><span class="ideator-section-icon"><i class="fas ' . $icon . '"></i></span>';
            $html .= '<h3 class="ideator-section-title">' . htmlspecialchars($title) . '</h3></div>';
            $html .= '<div class="ideator-section-body">';
            $sectionOpen = true;
            $sectionBodyOpen = true;
            continue;
        }

        if (preg_match('/^### (.+)$/', $trimmed, $m)) {
            $closeList();
            $html .= '<h4 class="ideator-subtitle">' . htmlspecialchars($m[1]) . '</h4>';
            continue;
        }

        if (preg_match('/^# (.+)$/', $trimmed, $m)) {
            $closeSection();
            $html .= '<div class="ideator-hero"><h2>' . htmlspecialchars($m[1]) . '</h2></div>';
            continue;
        }

        if (preg_match('/^(\d+)\.\s+(.+)$/', $trimmed, $m)) {
            if (!$inList || $listType !== 'ol') {
                $closeList();
                $html .= '<ol class="ideator-list ideator-list--numbered">';
                $inList = true;
                $listType = 'ol';
            }
            $html .= '<li>' . formatIdeatorListItem($m[2]) . '</li>';
            continue;
        }

        if (preg_match('/^[-*] (.+)$/', $trimmed, $m)) {
            if (!$inList || $listType !== 'ul') {
                $closeList();
                $html .= '<ul class="ideator-list">';
                $inList = true;
                $listType = 'ul';
            }
            $html .= '<li>' . formatIdeatorListItem($m[1]) . '</li>';
            continue;
        }

        $closeList();
        $html .= '<p class="ideator-paragraph">' . formatInlineMarkdown($trimmed) . '</p>';
    }

    $closeSection();
    $html .= '</div>';
    return $html;
}

function ideatorSectionIcon(string $title): string
{
    $t = strtolower($title);
    if (str_contains($t, 'executive') || str_contains($t, 'summary')) return 'fa-compass';
    if (str_contains($t, 'insight')) return 'fa-eye';
    if (str_contains($t, 'root')) return 'fa-microscope';
    if (str_contains($t, 'action') || str_contains($t, 'recommend')) return 'fa-bolt';
    if (str_contains($t, 'process') || str_contains($t, 'optim')) return 'fa-cogs';
    if (str_contains($t, 'quick') || str_contains($t, 'win')) return 'fa-rocket';
    if (str_contains($t, 'metric') || str_contains($t, 'success')) return 'fa-chart-line';
    return 'fa-lightbulb';
}

function ideatorSectionVariant(string $title): string
{
    $t = strtolower($title);
    if (str_contains($t, 'executive') || str_contains($t, 'summary')) return 'hero';
    if (str_contains($t, 'action') || str_contains($t, 'recommend')) return 'action';
    if (str_contains($t, 'quick') || str_contains($t, 'win')) return 'wins';
    if (str_contains($t, 'metric') || str_contains($t, 'success')) return 'metrics';
    return 'default';
}

function formatIdeatorListItem(string $text): string
{
    $badgeHtml = '';
    if (preg_match('/^(P0|P1|P2)\s*[:\-–—]\s*/i', $text, $m)) {
        $badge = strtoupper($m[1]);
        $class = strtolower($badge);
        $badgeHtml = '<span class="ideator-priority ideator-priority--' . $class . '">' . $badge . '</span> ';
        $text = preg_replace('/^(P0|P1|P2)\s*[:\-–—]\s*/i', '', $text, 1);
    }
    return $badgeHtml . formatInlineMarkdown($text);
}

function formatInlineMarkdown(string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    return $text;
}

function getIdeatorInsightsCss(): string
{
    return <<<'CSS'
.ideator-report-content { display: flex; flex-direction: column; gap: 14px; }
.ideator-hero {
    background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
    border-radius: 18px; padding: 18px 20px; border: 1px solid #FED7AA;
}
.ideator-hero h2 { margin: 0; color: #C2410C; font-size: 1.05rem; }
.ideator-section {
    background: #FAFBFC; border: 1px solid #E8EDF3; border-radius: 18px;
    overflow: hidden; box-shadow: 0 2px 8px rgba(15,23,42,0.04);
}
.ideator-section--hero { background: linear-gradient(180deg, #FFFBF5 0%, #FFFFFF 100%); border-color: #FED7AA; }
.ideator-section--action { border-left: 4px solid #FC8019; }
.ideator-section--wins { border-left: 4px solid #16A34A; }
.ideator-section--metrics { border-left: 4px solid #0284C7; }
.ideator-section-head {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 18px 0; background: transparent;
}
.ideator-section-icon {
    width: 36px; height: 36px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(252,128,25,0.12); color: #EA580C; font-size: 15px;
}
.ideator-section--action .ideator-section-icon { background: rgba(252,128,25,0.15); color: #C2410C; }
.ideator-section--wins .ideator-section-icon { background: #DCFCE7; color: #15803D; }
.ideator-section--metrics .ideator-section-icon { background: #E0F2FE; color: #0284C7; }
.ideator-section-title { margin: 0; font-size: 15px; font-weight: 700; color: #1E293B; letter-spacing: -0.01em; }
.ideator-section-body { padding: 10px 18px 16px 66px; }
.ideator-section--hero .ideator-section-body { padding-left: 18px; }
.ideator-subtitle { margin: 12px 0 6px; font-size: 13px; font-weight: 700; color: #475569; }
.ideator-paragraph { margin: 0 0 10px; color: #334155; font-size: 14px; line-height: 1.65; }
.ideator-list { margin: 6px 0 4px; padding: 0; list-style: none; }
.ideator-list li {
    position: relative; padding: 9px 12px 9px 28px; margin-bottom: 8px;
    background: white; border-radius: 12px; border: 1px solid #EEF2F6;
    color: #334155; font-size: 14px; line-height: 1.55;
}
.ideator-list li::before {
    content: ''; position: absolute; left: 12px; top: 15px;
    width: 7px; height: 7px; border-radius: 50%; background: #FC8019;
}
.ideator-list--numbered { counter-reset: ideator-num; }
.ideator-list--numbered li { counter-increment: ideator-num; padding-left: 36px; }
.ideator-list--numbered li::before {
    content: counter(ideator-num); width: auto; height: auto; background: none;
    color: #FC8019; font-weight: 700; font-size: 12px; top: 10px; left: 12px;
}
.ideator-priority {
    display: inline-block; font-size: 10px; font-weight: 800; letter-spacing: 0.04em;
    padding: 2px 8px; border-radius: 999px; margin-right: 4px; vertical-align: middle;
}
.ideator-priority--p0 { background: #FEE2E2; color: #B91C1C; }
.ideator-priority--p1 { background: #FEF3C7; color: #B45309; }
.ideator-priority--p2 { background: #E0F2FE; color: #0369A1; }
.ideator-summary-strip {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px; margin-bottom: 4px;
}
.ideator-stat-card {
    background: linear-gradient(180deg, #FFFFFF 0%, #F8FAFC 100%);
    border: 1px solid #E2E8F0; border-radius: 14px; padding: 12px 14px; text-align: center;
}
.ideator-stat-card strong { display: block; font-size: 1.25rem; color: #FC8019; line-height: 1.2; }
.ideator-stat-card span { font-size: 11px; color: #64748B; text-transform: uppercase; letter-spacing: 0.04em; }
.ideator-curious-quote {
    font-size: 15px; color: #64748B; font-style: italic; min-height: 44px;
    line-height: 1.5; transition: opacity 0.35s ease;
}
.ideator-curious-quote.fade { opacity: 0; }
CSS;
}

function getIdeatorPdfCss(): string
{
    return getIdeatorInsightsCss() . <<<'CSS'

/* PDF-safe overrides (html2canvas) */
.ideator-report-content { display: block !important; }
.ideator-section { display: block; margin-bottom: 14px; page-break-inside: avoid; }
.ideator-section-head { display: block; padding: 14px 18px 8px; }
.ideator-section-icon { display: inline-block; vertical-align: middle; margin-right: 10px; }
.ideator-section-title { display: inline-block; vertical-align: middle; }
.ideator-section-body { display: block; padding: 8px 18px 16px 18px !important; }
.ideator-summary-strip { display: block; overflow: hidden; margin-bottom: 16px; }
.ideator-stat-card { display: inline-block; width: 22%; margin: 0 1% 8px 0; vertical-align: top; box-sizing: border-box; }
.ideator-list { display: block; }
.ideator-list li { display: block; page-break-inside: avoid; }
.ideator-section-icon i { display: none !important; }
CSS;
}

function buildIdeatorPdfShell(string $metaLine, string $statsHtml, string $insightsHtml): string
{
    $css = htmlspecialchars(getIdeatorPdfCss(), ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body style="margin:0;padding:28px;font-family:Segoe UI,Arial,sans-serif;color:#1E2A3E;background:#fff;">'
        . '<div style="border-bottom:3px solid #FC8019;padding-bottom:14px;margin-bottom:18px;">'
        . '<h1 style="color:#FC8019;font-size:22px;margin:0 0 6px;">Ideator Strategy Brief</h1>'
        . '<p style="font-size:12px;color:#64748B;margin:0;">' . htmlspecialchars($metaLine) . '</p></div>'
        . '<div class="ideator-summary-strip">' . $statsHtml . '</div>'
        . $insightsHtml
        . '</body></html>';
}

function generateIdeatorReport(array $summary, string $insightsMarkdown, string $model): array
{
    return [
        'title'         => 'Ideator Strategy Brief',
        'generated_at'  => date('Y-m-d H:i:s'),
        'model'         => $model,
        'summary'       => $summary,
        'insights_md'   => $insightsMarkdown,
        'insights_html' => markdownToRichHtml($insightsMarkdown),
    ];
}
