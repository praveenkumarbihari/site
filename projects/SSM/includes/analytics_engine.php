<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/tenant.php';

function analyticsTicketJoin(): string
{
    return 't.order_id = o.order_id AND t.admin_id = o.admin_id';
}

function ensureAnalyticsSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    ensureTenantSchema();
    $done = true;
}

function normalizeCategory(?string $cat): string
{
    $cat = trim($cat ?? 'Others');
    $map = [
        'late delivery'    => 'Delayed Delivery',
        'delayed delivery' => 'Delayed Delivery',
        'missing item'     => 'Missing Item',
        'wrong item'       => 'Wrong Item',
        'refund request'   => 'Refund Status',
        'refund status'    => 'Refund Status',
        'refund'           => 'Refund Status',
        'payment issue'    => 'Others',
    ];
    $key = strtolower($cat);
    return $map[$key] ?? ($cat !== '' ? $cat : 'Others');
}

function getFullAnalyticsReport(?int $adminId = null): array
{
    ensureAnalyticsSchema();
    if ($adminId === null) {
        $adminId = currentAdminId();
    }
    $db = getDB();

    $orderCountStmt = $db->prepare('SELECT COUNT(*) FROM orders WHERE admin_id = ?');
    $orderCountStmt->execute([$adminId]);
    $ticketCountStmt = $db->prepare('SELECT COUNT(*) FROM support_tickets WHERE admin_id = ?');
    $ticketCountStmt->execute([$adminId]);

    return [
        'layer1'  => getLayer1Pareto($db, $adminId),
        'layer2'  => getLayer2DelayContact($db, $adminId),
        'layer3'  => getLayer3Restaurant($db, $adminId),
        'layer4'  => getLayer4Refund($db, $adminId),
        'layer5'  => getLayer5Csat($db, $adminId),
        'layer6'  => getLayer6Compensation($db, $adminId),
        'layer7'  => getLayer7Channel($db, $adminId),
        'layer8'  => getLayer8PeakHour($db, $adminId),
        'layer9'  => getLayer9Weather($db, $adminId),
        'layer10' => getLayer10Segmentation($db, $adminId),
        'layer11' => getLayer11ResolutionTime($db, $adminId),
        'layer12' => getLayer12OrderValue($db, $adminId),
        'layer13' => getLayer13RepeatContact($db, $adminId),
        'layer14' => getLayer14AgentPerformance($db, $adminId),
        'meta'    => [
            'generated_at' => date('Y-m-d H:i:s'),
            'admin_id' => $adminId,
            'total_orders' => (int)$orderCountStmt->fetchColumn(),
            'total_tickets' => (int)$ticketCountStmt->fetchColumn(),
        ],
    ];
}

function getLayer1Pareto(PDO $db, int $adminId): array
{
    $stmt = $db->prepare('SELECT category, COUNT(*) AS cnt FROM support_tickets WHERE admin_id = ? GROUP BY category ORDER BY cnt DESC');
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();
    $grouped = [];
    foreach ($rows as $r) {
        $cat = normalizeCategory($r['category']);
        $grouped[$cat] = ($grouped[$cat] ?? 0) + (int)$r['cnt'];
    }
    arsort($grouped);
    $total = array_sum($grouped) ?: 1;
    $data = [];
    $top2 = 0;
    $i = 0;
    foreach ($grouped as $cat => $cnt) {
        $pct = round($cnt / $total * 100, 1);
        $data[] = ['category' => $cat, 'count' => $cnt, 'pct' => $pct];
        if ($i < 2) {
            $top2 += $pct;
        }
        $i++;
    }
    $insight = count($data) >= 2
        ? round($top2) . '% tickets come from only 2 categories (' . implode(' & ', array_slice(array_column($data, 'category'), 0, 2)) . '). Focus on Delivery Experience & Order Accuracy.'
        : (empty($data) ? 'Import ticket data to see category volume patterns.' : 'Not enough category diversity in ticket data yet.');

    $topTwo = array_slice(array_column($data, 'category'), 0, 2);
    $productDirection = !empty($topTwo)
        ? 'Focus on: ' . implode(' & ', $topTwo)
        : 'Focus on Delivery Experience and Order Accuracy.';

    return [
        'title' => 'Layer 1: What tickets are coming? (Pareto Analysis)',
        'question' => 'Which categories generate most support volume?',
        'data' => $data,
        'insight' => $insight,
        'product_direction' => $productDirection,
        'has_data' => !empty($data),
    ];
}

function getLayer2DelayContact(PDO $db, int $adminId): array
{
    $buckets = ['0-5 min', '5-10 min', '10-15 min', '15-20 min', '20+ min'];
    $join = analyticsTicketJoin();
    $sql = "
        SELECT
            CASE
                WHEN o.delay_min IS NULL THEN 'Unknown'
                WHEN o.delay_min < 5 THEN '0-5 min'
                WHEN o.delay_min < 10 THEN '5-10 min'
                WHEN o.delay_min < 15 THEN '10-15 min'
                WHEN o.delay_min < 20 THEN '15-20 min'
                ELSE '20+ min'
            END AS bucket,
            COUNT(DISTINCT o.order_id) AS orders,
            COUNT(DISTINCT t.ticket_id) AS tickets
        FROM orders o
        LEFT JOIN support_tickets t ON {$join}
        WHERE o.admin_id = ? AND o.delay_min IS NOT NULL
        GROUP BY 1
        ORDER BY MIN(COALESCE(o.delay_min, 999))
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();
    $byBucket = array_fill_keys($buckets, ['orders' => 0, 'tickets' => 0, 'contact_rate' => 0]);
    foreach ($rows as $r) {
        if ($r['bucket'] === 'Unknown') {
            continue;
        }
        $orders = (int)$r['orders'];
        $tickets = (int)$r['tickets'];
        $byBucket[$r['bucket']] = [
            'orders' => $orders,
            'tickets' => $tickets,
            'contact_rate' => $orders > 0 ? round($tickets / $orders * 100, 1) : 0,
        ];
    }
    $data = [];
    foreach ($buckets as $b) {
        $data[] = array_merge(['delay' => $b], $byBucket[$b]);
    }

    $totalOrdersWithDelay = array_sum(array_map(fn($d) => $d['orders'], $data));
    
    $under15Rates = [];
    $over15Rates = [];
    foreach ($data as $d) {
        if (in_array($d['delay'], ['0-5 min', '5-10 min', '10-15 min'], true) && $d['orders'] > 0) {
            $under15Rates[] = $d['contact_rate'];
        }
        if (in_array($d['delay'], ['15-20 min', '20+ min'], true) && $d['orders'] > 0) {
            $over15Rates[] = $d['contact_rate'];
        }
    }
    $avgUnder15 = !empty($under15Rates) ? round(array_sum($under15Rates) / count($under15Rates), 1) : 0;
    $avgOver15 = !empty($over15Rates) ? round(array_sum($over15Rates) / count($over15Rates), 1) : 0;

    if ($totalOrdersWithDelay === 0) {
        $insight = 'Import orders with eta_shown_min, actual_delivery_min, or delay_min to analyze delay vs contact rate.';
    } elseif ($avgOver15 > $avgUnder15) {
        $insight = "Contact rate jumps from {$avgUnder15}% (under 15 min delay) to {$avgOver15}% (15+ min delay). Reduce orders delayed >15 min — not just reduce tickets.";
    } else {
        $insight = "Average contact rate is {$avgUnder15}% for delays under 15 min and {$avgOver15}% for 15+ min delays.";
    }

    return [
        'title' => 'Layer 2: When do users contact support? (Delay Analysis)',
        'question' => 'How does delivery delay correlate with support contact rate?',
        'data' => $data,
        'insight' => $insight,
        'product_outcome' => 'Reduce orders delayed >15 min.',
        'has_data' => $totalOrdersWithDelay > 0,
    ];
}

function getLayer3Restaurant(PDO $db, int $adminId): array
{
    $join = analyticsTicketJoin();
    $sql = "
        SELECT
            COALESCE(o.restaurant_id, o.restaurant, 'Unknown') AS restaurant,
            COUNT(DISTINCT o.order_id) AS orders,
            COUNT(DISTINCT t.ticket_id) AS tickets,
            COUNT(DISTINCT CASE WHEN t.category LIKE '%missing%' THEN t.ticket_id END) AS missing,
            COUNT(DISTINCT CASE WHEN t.category LIKE '%wrong%' THEN t.ticket_id END) AS wrong_item
        FROM orders o
        LEFT JOIN support_tickets t ON {$join}
        WHERE o.admin_id = ?
        GROUP BY 1
        HAVING COUNT(DISTINCT o.order_id) > 0
        ORDER BY tickets DESC
        LIMIT 10
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();
    $totalTickets = array_sum(array_column($rows, 'tickets')) ?: 1;
    $data = [];
    foreach ($rows as $r) {
        $orders = (int)$r['orders'];
        $data[] = [
            'restaurant' => $r['restaurant'],
            'orders' => $orders,
            'tickets' => (int)$r['tickets'],
            'missing_rate' => $orders > 0 ? round((int)$r['missing'] / $orders * 100, 1) : 0,
            'wrong_rate' => $orders > 0 ? round((int)$r['wrong_item'] / $orders * 100, 1) : 0,
            'complaint_pct' => round((int)$r['tickets'] / $totalTickets * 100, 1),
        ];
    }
    $topShare = !empty($data) ? $data[0]['complaint_pct'] : 0;
    $insight = !empty($data)
        ? 'Top restaurants drive disproportionate complaints. ' . ($topShare >= 20 ? 'A few merchants generate most complaints.' : 'Monitor merchant quality scores.')
        : 'Import order and ticket data linked by order_id.';
    return [
        'title' => 'Layer 3: Restaurant Analysis',
        'question' => 'Which restaurants generate the most complaints?',
        'data' => $data,
        'insight' => $insight,
        'product_decision' => 'Merchant Quality Score / Packing Verification / Merchant Warning Dashboard.',
    ];
}

function getLayer4Refund(PDO $db, int $adminId): array
{
    $stmt = $db->prepare("
        SELECT
            ticket_id,
            TIMESTAMPDIFF(HOUR, (SELECT created_at FROM orders WHERE order_id = support_tickets.order_id AND admin_id = support_tickets.admin_id LIMIT 1), created_at) AS hours_after_order,
            TIMESTAMPDIFF(HOUR, created_at, COALESCE(refund_completed_at, resolved_at)) AS hours_to_refund,
            csat_score,
            status
        FROM support_tickets
        WHERE admin_id = ? AND category LIKE '%refund%'
    ");
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();

    $avgComplaintHrs = 0;
    $avgRefundHrs = 0;
    $avgCsat = 0;
    $csatCount = 0;
    $openCount = 0;
    foreach ($rows as $r) {
        if ($r['hours_after_order'] !== null) {
            $avgComplaintHrs += (float)$r['hours_after_order'];
        }
        if ($r['hours_to_refund'] !== null) {
            $avgRefundHrs += (float)$r['hours_to_refund'];
        }
        if ($r['csat_score'] !== null) {
            $avgCsat += (float)$r['csat_score'];
            $csatCount++;
        }
        if (!in_array($r['status'], ['Resolved', 'Closed'])) {
            $openCount++;
        }
    }
    $n = count($rows) ?: 1;
    $data = [
        'total_refund_tickets' => count($rows),
        'avg_hours_after_order' => round($avgComplaintHrs / $n, 1),
        'avg_hours_to_refund' => round($avgRefundHrs / max(1, $csatCount ?: $n), 1),
        'avg_csat' => $csatCount > 0 ? round($avgCsat / $csatCount, 1) : null,
        'open_refund_tickets' => $openCount,
        'visibility_issue_pct' => count($rows) > 0 ? round($openCount / count($rows) * 100, 1) : 0,
    ];
    $insight = count($rows) > 0
        ? 'Users often complain because refund status is invisible, not because refund is late. ' . $openCount . ' refund tickets still open/escalated.'
        : 'Import tickets with Refund Status category for refund analysis.';
    return [
        'title' => 'Layer 4: Refund Analysis',
        'question' => 'How long after complaint? How long until refund? What is CSAT?',
        'data' => $data,
        'insight' => $insight,
        'product_outcome' => 'Increase refund visibility — not just refund speed.',
    ];
}

function getLayer5Csat(PDO $db, int $adminId): array
{
    $stmt = $db->prepare("
        SELECT category, ROUND(AVG(csat_score), 1) AS avg_csat, COUNT(*) AS cnt
        FROM support_tickets WHERE admin_id = ? AND csat_score IS NOT NULL
        GROUP BY category ORDER BY avg_csat ASC
    ");
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();
    $grouped = [];
    foreach ($rows as $r) {
        $cat = normalizeCategory($r['category']);
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = ['total' => 0, 'count' => 0];
        }
        $grouped[$cat]['total'] += (float)$r['avg_csat'] * (int)$r['cnt'];
        $grouped[$cat]['count'] += (int)$r['cnt'];
    }
    $data = [];
    foreach ($grouped as $cat => $g) {
        $data[] = ['category' => $cat, 'avg_csat' => round($g['total'] / max(1, $g['count']), 1), 'count' => $g['count']];
    }
    usort($data, fn($a, $b) => $a['avg_csat'] <=> $b['avg_csat']);
    $worst = $data[0]['category'] ?? 'Missing Item';
    $insight = !empty($data)
        ? "$worst hurts satisfaction most. Prioritize this initiative before lower-volume categories."
        : 'Import tickets with csat_score for satisfaction analysis.';
    return [
        'title' => 'Layer 5: CSAT Analysis',
        'question' => 'Which issues hurt satisfaction most?',
        'data' => $data,
        'insight' => $insight,
        'product_decision' => 'Prioritize lowest CSAT category initiatives first.',
    ];
}

function getLayer6Compensation(PDO $db, int $adminId): array
{
    $stmt = $db->prepare("
        SELECT category, SUM(COALESCE(compensation_amount, 0)) AS total_comp
        FROM support_tickets WHERE admin_id = ? GROUP BY category ORDER BY total_comp DESC
    ");
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();
    $grouped = [];
    foreach ($rows as $r) {
        $cat = normalizeCategory($r['category']);
        $grouped[$cat] = ($grouped[$cat] ?? 0) + (float)$r['total_comp'];
    }
    arsort($grouped);
    $data = [];
    foreach ($grouped as $cat => $amt) {
        $data[] = ['category' => $cat, 'compensation' => round($amt, 0), 'compensation_lakh' => round($amt / 100000, 2)];
    }
    $top = $data[0]['category'] ?? 'Missing Item';
    $insight = !empty($data)
        ? "$top has highest compensation leakage. Business impact may exceed ticket volume."
        : 'Import tickets with compensation_amount for leakage analysis.';
    return [
        'title' => 'Layer 6: Compensation Leakage',
        'question' => 'Compensation amount per category?',
        'data' => $data,
        'insight' => $insight,
    ];
}

function getLayer7Channel(PDO $db, int $adminId): array
{
    $stmt = $db->prepare("
        SELECT category,
               COUNT(*) AS total,
               SUM(CASE WHEN UPPER(support_channel) = 'CALL' THEN 1 ELSE 0 END) AS calls
        FROM support_tickets WHERE admin_id = ? GROUP BY category
    ");
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();
    $grouped = [];
    foreach ($rows as $r) {
        $cat = normalizeCategory($r['category']);
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = ['total' => 0, 'calls' => 0];
        }
        $grouped[$cat]['total'] += (int)$r['total'];
        $grouped[$cat]['calls'] += (int)$r['calls'];
    }
    $data = [];
    foreach ($grouped as $cat => $g) {
        $data[] = [
            'category' => $cat,
            'call_pct' => $g['total'] > 0 ? round($g['calls'] / $g['total'] * 100, 1) : 0,
            'total' => $g['total'],
        ];
    }
    usort($data, fn($a, $b) => $b['call_pct'] <=> $a['call_pct']);
    $top = $data[0]['category'] ?? 'Delayed Delivery';
    $insight = !empty($data)
        ? "$top creates highest call volume. Calls are expensive — reduce escalation drivers for this category."
        : 'Import tickets with support_channel (Call/Chat).';
    return [
        'title' => 'Layer 7: Support Channel Analysis',
        'question' => 'Which tickets become calls?',
        'data' => $data,
        'insight' => $insight,
    ];
}

function getLayer8PeakHour(PDO $db, int $adminId): array
{
    $join = analyticsTicketJoin();
    $stmt = $db->prepare("
        SELECT
            CASE WHEN o.is_peak_hour THEN 'Peak Hour (Yes)' ELSE 'Non-Peak (No)' END AS period,
            COUNT(DISTINCT o.order_id) AS orders,
            COUNT(DISTINCT t.ticket_id) AS tickets
        FROM orders o
        LEFT JOIN support_tickets t ON {$join}
        WHERE o.admin_id = ?
        GROUP BY o.is_peak_hour
    ");
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();
    $data = [];
    foreach ($rows as $r) {
        $orders = (int)$r['orders'];
        $data[] = [
            'period' => $r['period'],
            'orders' => $orders,
            'tickets' => (int)$r['tickets'],
            'ticket_rate' => $orders > 0 ? round((int)$r['tickets'] / $orders * 100, 1) : 0,
        ];
    }

    $peakRate = 0;
    $nonPeakRate = 0;
    foreach ($data as $d) {
        if (str_contains($d['period'], 'Yes')) {
            $peakRate = $d['ticket_rate'];
        } else {
            $nonPeakRate = $d['ticket_rate'];
        }
    }

    if (empty($data)) {
        $insight = 'Import orders with is_peak_hour (true/false) for peak analysis.';
    } elseif ($peakRate > $nonPeakRate) {
        $insight = "Peak hours ticket rate is {$peakRate}% vs {$nonPeakRate}% non-peak — operational capacity issue. Consider demand prediction & dynamic rider allocation.";
    } else {
        $insight = "Peak hour ticket rate: {$peakRate}%. Non-peak: {$nonPeakRate}%.";
    }

    return [
        'title' => 'Layer 8: Peak Hour Analysis',
        'question' => 'Tickets by peak hour?',
        'data' => $data,
        'insight' => $insight,
        'product_decision' => 'Demand Prediction & Dynamic Rider Allocation.',
        'has_data' => !empty($data),
    ];
}

function getLayer9Weather(PDO $db, int $adminId): array
{
    $join = analyticsTicketJoin();
    $stmt = $db->prepare("
        SELECT
            COALESCE(NULLIF(o.weather, ''), 'Unknown') AS weather,
            COUNT(DISTINCT o.order_id) AS orders,
            COUNT(DISTINCT t.ticket_id) AS tickets
        FROM orders o
        LEFT JOIN support_tickets t ON {$join}
        WHERE o.admin_id = ?
        GROUP BY 1
        ORDER BY MIN(CASE o.weather WHEN 'Clear' THEN 1 WHEN 'Rain' THEN 2 WHEN 'Heavy Rain' THEN 3 ELSE 4 END)
    ");
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();
    $data = [];
    foreach ($rows as $r) {
        $orders = (int)$r['orders'];
        $data[] = [
            'weather' => $r['weather'],
            'orders' => $orders,
            'tickets' => (int)$r['tickets'],
            'contact_rate' => $orders > 0 ? round((int)$r['tickets'] / $orders * 100, 1) : 0,
        ];
    }
    $clearRate = 0;
    $rainRate = 0;
    foreach ($data as $d) {
        if (strcasecmp($d['weather'], 'Clear') === 0) {
            $clearRate = $d['contact_rate'];
        }
        if (stripos($d['weather'], 'Rain') !== false && strcasecmp($d['weather'], 'Clear') !== 0) {
            $rainRate = max($rainRate, $d['contact_rate']);
        }
    }

    if (empty($data)) {
        $insight = 'Import orders with weather column (Clear/Rain/Heavy Rain).';
    } elseif ($rainRate > $clearRate && $clearRate > 0) {
        $insight = "Contact rate rises from {$clearRate}% (Clear) to {$rainRate}% (Rain/Heavy Rain) — ETA prediction breaks in bad weather.";
    } elseif ($rainRate > 0 || $clearRate > 0) {
        $insight = "Clear weather contact rate: {$clearRate}%. Rain conditions: up to {$rainRate}%.";
    } else {
        $insight = 'Add weather data to orders for weather-impact analysis.';
    }

    return [
        'title' => 'Layer 9: Weather Analysis',
        'question' => 'Rain vs Support Tickets?',
        'data' => $data,
        'insight' => $insight,
        'product_outcome' => 'Weather-aware ETA model.',
        'has_data' => !empty($data),
    ];
}

function getLayer10Segmentation(PDO $db, int $adminId): array
{
    $join = analyticsTicketJoin();
    $firstOrderStmt = $db->prepare("
        SELECT
            COUNT(DISTINCT o.order_id) AS first_orders,
            COUNT(DISTINCT t.ticket_id) AS first_order_tickets,
            COUNT(DISTINCT CASE WHEN o.delay_min >= 15 THEN o.order_id END) AS delayed_first_orders
        FROM orders o
        LEFT JOIN support_tickets t ON {$join}
        WHERE o.admin_id = ? AND o.is_first_order = 1
    ");
    $firstOrderStmt->execute([$adminId]);
    $firstOrderDelay = $firstOrderStmt->fetch();

    $repeatStmt = $db->prepare("
        SELECT customer_name, COUNT(*) AS ticket_count
        FROM support_tickets WHERE admin_id = ? GROUP BY customer_name HAVING COUNT(*) >= 2
        ORDER BY ticket_count DESC LIMIT 5
    ");
    $repeatStmt->execute([$adminId]);
    $repeatComplainer = $repeatStmt->fetchAll();

    $vipStmt = $db->prepare("
        SELECT
            CASE WHEN o.amount >= 1000 THEN 'High Value (VIP)' ELSE 'Regular' END AS segment,
            COUNT(DISTINCT o.order_id) AS orders,
            COUNT(DISTINCT t.ticket_id) AS tickets
        FROM orders o
        LEFT JOIN support_tickets t ON {$join}
        WHERE o.admin_id = ?
        GROUP BY 1
    ");
    $vipStmt->execute([$adminId]);
    $vipData = $vipStmt->fetchAll();

    $fo = (int)$firstOrderDelay['first_orders'];
    $foTickets = (int)$firstOrderDelay['first_order_tickets'];

    $data = [
        'first_order' => [
            'orders' => $fo,
            'tickets' => $foTickets,
            'contact_rate' => $fo > 0 ? round($foTickets / $fo * 100, 1) : 0,
            'delayed_orders' => (int)$firstOrderDelay['delayed_first_orders'],
        ],
        'repeat_complainers' => array_map(fn($r) => [
            'customer' => $r['customer_name'],
            'ticket_count' => (int)$r['ticket_count'],
        ], $repeatComplainer),
        'vip_segments' => array_map(fn($r) => [
            'segment' => $r['segment'],
            'orders' => (int)$r['orders'],
            'tickets' => (int)$r['tickets'],
            'contact_rate' => (int)$r['orders'] > 0 ? round((int)$r['tickets'] / (int)$r['orders'] * 100, 1) : 0,
        ], $vipData),
    ];

    $regularRate = 0;
    foreach ($data['vip_segments'] as $seg) {
        if ($seg['segment'] === 'Regular') {
            $regularRate = $seg['contact_rate'];
        }
    }

    if ($fo === 0) {
        $insight = 'Import orders with is_first_order flag for segmentation analysis.';
    } else {
        $delayedPct = $fo > 0 ? round($data['first_order']['delayed_orders'] / $fo * 100, 1) : 0;
        $insight = "First-order contact rate: {$data['first_order']['contact_rate']}% ({$foTickets}/{$fo} orders). {$delayedPct}% of first orders had 15+ min delay.";
        if ($data['first_order']['contact_rate'] > $regularRate && $regularRate > 0) {
            $insight .= " First-time customers contact support more than regular customers ({$regularRate}%).";
        }
    }

    return [
        'title' => 'Layer 10: Customer Segmentation',
        'question' => 'Do VIP users complain more? Do first-time users churn after bad experience?',
        'data' => $data,
        'insight' => $insight,
        'product_priority' => 'Protect first-order experience.',
        'has_data' => $fo > 0 || !empty($repeatComplainer),
    ];
}

function analyticsLayerKeys(): array
{
    return array_map(static fn($n) => 'layer' . $n, range(1, 14));
}

function getLayer11ResolutionTime(PDO $db, int $adminId): array
{
    $stmt = $db->prepare("
        SELECT category,
               AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at) / 3600) AS avg_hours,
               COUNT(*) AS cnt
        FROM support_tickets
        WHERE admin_id = ? AND resolved_at IS NOT NULL AND created_at IS NOT NULL
          AND resolved_at >= created_at
        GROUP BY category
        ORDER BY avg_hours DESC
    ");
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $r) {
        $cat = normalizeCategory($r['category']);
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = ['hours' => 0, 'count' => 0];
        }
        $grouped[$cat]['hours'] += (float)$r['avg_hours'] * (int)$r['cnt'];
        $grouped[$cat]['count'] += (int)$r['cnt'];
    }

    $data = [];
    foreach ($grouped as $cat => $g) {
        $data[] = [
            'category' => $cat,
            'avg_hours' => round($g['hours'] / max(1, $g['count']), 1),
            'tickets' => $g['count'],
        ];
    }
    usort($data, fn($a, $b) => $b['avg_hours'] <=> $a['avg_hours']);

    $slowest = $data[0]['category'] ?? 'Unknown';
    $slowHrs = $data[0]['avg_hours'] ?? 0;
    $insight = !empty($data)
        ? "$slowest takes longest to resolve (avg {$slowHrs}h). Streamline workflows for high-volume slow categories."
        : 'Import tickets with created_at and resolved_at for resolution time analysis.';

    return [
        'title' => 'Layer 11: Resolution Time Analysis',
        'question' => 'Which categories take longest to resolve?',
        'data' => $data,
        'insight' => $insight,
        'product_outcome' => 'SLA targets & auto-escalation rules by category.',
        'has_data' => !empty($data),
    ];
}

function getLayer12OrderValue(PDO $db, int $adminId): array
{
    $join = analyticsTicketJoin();
    $stmt = $db->prepare("
        SELECT
            CASE
                WHEN o.amount IS NULL OR o.amount < 300 THEN 'Low (< ₹300)'
                WHEN o.amount < 800 THEN 'Mid (₹300–800)'
                ELSE 'High (₹800+)'
            END AS segment,
            COUNT(DISTINCT o.order_id) AS orders,
            COUNT(DISTINCT t.ticket_id) AS tickets
        FROM orders o
        LEFT JOIN support_tickets t ON {$join}
        WHERE o.admin_id = ?
        GROUP BY 1
        ORDER BY MIN(COALESCE(o.amount, 0))
    ");
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $r) {
        $orders = (int)$r['orders'];
        $data[] = [
            'segment' => $r['segment'],
            'orders' => $orders,
            'tickets' => (int)$r['tickets'],
            'contact_rate' => $orders > 0 ? round((int)$r['tickets'] / $orders * 100, 2) : 0,
        ];
    }

    $highRate = 0;
    $lowRate = 0;
    foreach ($data as $d) {
        if (str_contains($d['segment'], 'High')) {
            $highRate = $d['contact_rate'];
        }
        if (str_contains($d['segment'], 'Low')) {
            $lowRate = $d['contact_rate'];
        }
    }

    $insight = !empty($data)
        ? ($highRate > $lowRate && $lowRate >= 0
            ? "High-value orders contact rate: {$highRate}% vs low-value: {$lowRate}%. Protect premium customer experience."
            : "Contact rates by order value — Low: {$lowRate}%, High: {$highRate}%.")
        : 'Import orders with amount for order-value segmentation.';

    return [
        'title' => 'Layer 12: Order Value vs Contact Rate',
        'question' => 'Do high-value customers contact support more?',
        'data' => $data,
        'insight' => $insight,
        'product_decision' => 'VIP proactive outreach for high-value delayed orders.',
        'has_data' => !empty($data),
    ];
}

function getLayer13RepeatContact(PDO $db, int $adminId): array
{
    $stmt = $db->prepare("
        SELECT
            CASE
                WHEN tc.cnt = 1 THEN '1 ticket'
                WHEN tc.cnt = 2 THEN '2 tickets'
                ELSE '3+ tickets'
            END AS bucket,
            COUNT(*) AS order_count,
            SUM(tc.cnt) AS total_tickets
        FROM (
            SELECT order_id, COUNT(*) AS cnt
            FROM support_tickets
            WHERE admin_id = ? AND order_id IS NOT NULL AND order_id <> ''
            GROUP BY order_id
        ) tc
        GROUP BY 1
        ORDER BY MIN(tc.cnt)
    ");
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();

    $data = [];
    $multiOrders = 0;
    $totalLinkedOrders = 0;
    foreach ($rows as $r) {
        $data[] = [
            'bucket' => $r['bucket'],
            'orders' => (int)$r['order_count'],
            'tickets' => (int)$r['total_tickets'],
        ];
        $totalLinkedOrders += (int)$r['order_count'];
        if ($r['bucket'] !== '1 ticket') {
            $multiOrders += (int)$r['order_count'];
        }
    }

    $repeatPct = $totalLinkedOrders > 0 ? round($multiOrders / $totalLinkedOrders * 100, 1) : 0;
    $insight = !empty($data)
        ? "{$repeatPct}% of orders with tickets had 2+ support contacts — repeat contact drives avoidable volume."
        : 'Link tickets to order_id to analyze repeat contact patterns.';

    return [
        'title' => 'Layer 13: Repeat Contact Analysis',
        'question' => 'How many orders generate multiple support tickets?',
        'data' => $data,
        'insight' => $insight,
        'product_outcome' => 'First-contact resolution (FCR) improvement program.',
        'has_data' => !empty($data),
    ];
}

function getLayer14AgentPerformance(PDO $db, int $adminId): array
{
    $stmt = $db->prepare("
        SELECT
            COALESCE(NULLIF(TRIM(agent), ''), 'Unassigned') AS agent,
            COUNT(*) AS tickets,
            ROUND(AVG(csat_score), 1) AS avg_csat,
            SUM(CASE WHEN UPPER(status) IN ('RESOLVED', 'CLOSED') THEN 1 ELSE 0 END) AS resolved,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, created_at, COALESCE(resolved_at, NOW())) / 3600), 1) AS avg_handle_hrs
        FROM support_tickets
        WHERE admin_id = ?
        GROUP BY 1
        HAVING COUNT(*) >= 5
        ORDER BY tickets DESC
        LIMIT 12
    ");
    $stmt->execute([$adminId]);
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $r) {
        $tickets = (int)$r['tickets'];
        $data[] = [
            'agent' => $r['agent'],
            'tickets' => $tickets,
            'avg_csat' => $r['avg_csat'] !== null ? (float)$r['avg_csat'] : null,
            'resolved_pct' => $tickets > 0 ? round((int)$r['resolved'] / $tickets * 100, 1) : 0,
            'avg_handle_hrs' => $r['avg_handle_hrs'] !== null ? (float)$r['avg_handle_hrs'] : null,
        ];
    }

    $topAgent = $data[0]['agent'] ?? 'N/A';
    $lowCsatAgents = array_filter($data, fn($d) => $d['avg_csat'] !== null && $d['avg_csat'] < 3.5);
    $insight = !empty($data)
        ? "Top volume agent: {$topAgent}. " . (count($lowCsatAgents) > 0
            ? count($lowCsatAgents) . ' agent(s) below CSAT 3.5 — coaching & playbook opportunity.'
            : 'Review handle time vs CSAT balance across agents.')
        : 'Import tickets with agent and csat_score for agent performance analysis.';

    return [
        'title' => 'Layer 14: Agent Performance',
        'question' => 'Which agents handle the most tickets and how satisfied are customers?',
        'data' => $data,
        'insight' => $insight,
        'product_decision' => 'Agent coaching dashboard & best-practice sharing.',
        'has_data' => !empty($data),
    ];
}

function generateReportCsv(array $report): string
{
    $lines = [];
    $lines[] = 'SSM Swiggy Support Analytics Report';
    $lines[] = 'Generated,' . $report['meta']['generated_at'];
    $lines[] = 'Total Orders,' . $report['meta']['total_orders'];
    $lines[] = 'Total Tickets,' . $report['meta']['total_tickets'];
    $lines[] = '';

    foreach (analyticsLayerKeys() as $key) {
        $layer = $report[$key];
        $lines[] = '=== ' . $layer['title'] . ' ===';
        $lines[] = 'Question,' . ($layer['question'] ?? '');

        if (!empty($layer['data']) && isset($layer['data'][0]) && is_array($layer['data'][0])) {
            $lines[] = implode(',', array_keys($layer['data'][0]));
            foreach ($layer['data'] as $row) {
                $lines[] = implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $row));
            }
        } else {
            foreach ($layer['data'] as $k => $v) {
                $lines[] = $k . ',"' . str_replace('"', '""', is_array($v) ? json_encode($v) : (string)$v) . '"';
            }
        }
        $lines[] = '';
    }

    return implode("\n", $lines);
}
