<?php

require_once __DIR__ . '/../config/database.php';

function normalizeImportHeader(string $header): string
{
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
    $header = strtolower(trim($header));
    $header = preg_replace('/[\s\-]+/', '_', $header);
    return preg_replace('/[^a-z0-9_]/', '', $header);
}

function normalizeImportRow(array $row): array
{
    $normalized = [];
    foreach ($row as $key => $value) {
        $normalized[normalizeImportHeader((string)$key)] = is_string($value) ? trim($value) : $value;
    }
    return $normalized;
}

function rowValue(array $row, array $keys, $default = ''): string
{
    $row = normalizeImportRow($row);
    foreach ($keys as $key) {
        $nk = normalizeImportHeader($key);
        if (isset($row[$nk]) && trim((string)$row[$nk]) !== '') {
            return trim((string)$row[$nk]);
        }
    }
    return (string)$default;
}

function parseImportBool(string $raw, bool $default = false): bool
{
    $raw = strtolower(trim($raw));
    if ($raw === '') {
        return $default;
    }
    if (in_array($raw, ['1', 'true', 'yes', 'y', 't'], true)) {
        return true;
    }
    if (in_array($raw, ['0', 'false', 'no', 'n', 'f'], true)) {
        return false;
    }
    return $default;
}

/** MySQL boolean import param (TINYINT 0/1). */
function importBoolParam(bool $value): int
{
    return $value ? 1 : 0;
}

function getImportColumns(array $rows): array
{
    if (empty($rows)) {
        return [];
    }
    return array_keys(normalizeImportRow($rows[0]));
}

function parseCsvFile(string $filepath): array
{
    $rows = [];
    if (($handle = fopen($filepath, 'r')) === false) {
        return $rows;
    }

    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return $rows;
    }

    $headers = array_map(fn($h) => normalizeImportHeader((string)$h), $headers);

    while (($data = fgetcsv($handle)) !== false) {
        if (count(array_filter($data, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($headers as $i => $key) {
            if ($key === '') {
                continue;
            }
            $row[$key] = isset($data[$i]) ? trim((string)$data[$i]) : '';
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function parseExcelFile(string $filepath): array
{
    require_once __DIR__ . '/SimpleXLSX.php';

    if ($xlsx = \Shuchkin\SimpleXLSX::parse($filepath)) {
        $sheet = $xlsx->rows();
        if (empty($sheet)) {
            return [];
        }
        $headers = array_map(fn($h) => normalizeImportHeader((string)$h), $sheet[0]);
        $rows = [];
        for ($i = 1; $i < count($sheet); $i++) {
            $data = $sheet[$i];
            if (count(array_filter($data, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($headers as $j => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = isset($data[$j]) ? trim((string)$data[$j]) : '';
            }
            $rows[] = $row;
        }
        return $rows;
    }
    return [];
}

function parseUploadFile(string $filepath, string $ext): array
{
    $ext = strtolower($ext);
    if ($ext === 'csv') {
        return parseCsvFile($filepath);
    }
    if (in_array($ext, ['xlsx', 'xls'])) {
        return parseExcelFile($filepath);
    }
    return [];
}

function importOrders(array $rows): array
{
    require_once __DIR__ . '/tenant.php';
    ensureTenantSchema();
    $adminId = currentAdminId();

    $db = getDB();
    $inserted = 0;
    $skipped = 0;
    $errors = [];

    if (empty($rows)) {
        return ['inserted' => 0, 'skipped' => 0, 'errors' => [], 'hint' => 'File is empty.'];
    }

    $columns = getImportColumns($rows);
    $hasOrderCol = (bool) array_intersect($columns, ['order_id', 'orderid', 'order_no', 'order_number']);
    $hasTicketCol = (bool) array_intersect($columns, ['ticket_id', 'ticketid', 'ticket_number']);

    if (!$hasOrderCol && $hasTicketCol) {
        return [
            'inserted' => 0,
            'skipped' => count($rows),
            'errors' => [],
            'hint' => 'This file looks like TICKETS data (has ticket_id but no order_id). Choose Import Type: Support Tickets.',
            'columns' => $columns,
        ];
    }

    $sql = 'INSERT INTO orders (admin_id, order_id, customer_name, customer_phone, customer_email, restaurant, restaurant_id, delivery_partner, status, eta, eta_shown_min, actual_delivery_min, delay_min, amount, order_date, is_peak_hour, weather, is_first_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                customer_name = VALUES(customer_name),
                customer_phone = VALUES(customer_phone),
                customer_email = VALUES(customer_email),
                restaurant = VALUES(restaurant),
                restaurant_id = VALUES(restaurant_id),
                delivery_partner = VALUES(delivery_partner),
                status = VALUES(status),
                eta = VALUES(eta),
                eta_shown_min = VALUES(eta_shown_min),
                actual_delivery_min = VALUES(actual_delivery_min),
                delay_min = VALUES(delay_min),
                amount = VALUES(amount),
                order_date = VALUES(order_date),
                is_peak_hour = VALUES(is_peak_hour),
                weather = VALUES(weather),
                is_first_order = VALUES(is_first_order)';

    $stmt = $db->prepare($sql);

    foreach ($rows as $i => $row) {
        $orderId = rowValue($row, ['order_id', 'orderid', 'order_no', 'order_number', 'order']);
        if ($orderId === '') {
            $skipped++;
            continue;
        }

        try {
            $amount = rowValue($row, ['amount', 'order_amount', 'total_amount', 'order_value'], '0');
            $amount = preg_replace('/[^0-9.]/', '', $amount);

            $etaShown = rowValue($row, ['eta_shown_min', 'eta_shown', 'eta_minutes', 'eta_min']);
            $actualDel = rowValue($row, ['actual_delivery_min', 'actual_delivery', 'delivery_min', 'actual_delivery_time_min', 'actual_delivery_time']);
            $delayMin = rowValue($row, ['delay_min', 'delay', 'delay_minutes']);
            if ($delayMin === '' && $etaShown !== '' && $actualDel !== '') {
                $delayMin = (string) max(0, (int)$actualDel - (int)$etaShown);
            }

            $isPeak = parseImportBool(rowValue($row, ['is_peak_hour', 'peak_hour', 'peak'], 'false'));
            $isFirst = parseImportBool(rowValue($row, ['is_first_order', 'first_order'], 'false'));

            $restaurant = rowValue($row, ['restaurant', 'restaurant_name', 'restaurant_type']);
            $restaurantId = rowValue($row, ['restaurant_id', 'restaurantid']);

            $stmt->execute([
                $adminId,
                $orderId,
                rowValue($row, ['customer_name', 'customer', 'name', 'customer_id'], 'Unknown'),
                rowValue($row, ['customer_phone', 'phone', 'mobile']) ?: null,
                rowValue($row, ['customer_email', 'email']) ?: null,
                $restaurant !== '' ? $restaurant : ($restaurantId !== '' ? $restaurantId : null),
                $restaurantId !== '' ? $restaurantId : ($restaurant !== '' ? $restaurant : null),
                rowValue($row, ['delivery_partner', 'partner', 'rider', 'delivery_partner_id']) ?: null,
                rowValue($row, ['status', 'order_status'], 'Pending'),
                rowValue($row, ['eta']) ?: null,
                $etaShown !== '' ? (int)$etaShown : null,
                $actualDel !== '' ? (int)$actualDel : null,
                $delayMin !== '' ? (int)$delayMin : null,
                $amount !== '' ? (float)$amount : 0,
                rowValue($row, ['order_date', 'date']) ?: null,
                importBoolParam($isPeak),
                rowValue($row, ['weather']) ?: null,
                importBoolParam($isFirst),
            ]);
            $inserted++;
        } catch (Exception $e) {
            $errors[] = 'Row ' . ($i + 2) . ': ' . $e->getMessage();
        }
    }

    return ['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors, 'columns' => $columns];
}

function importTickets(array $rows): array
{
    require_once __DIR__ . '/tenant.php';
    ensureTenantSchema();
    $adminId = currentAdminId();

    $db = getDB();
    $inserted = 0;
    $skipped = 0;
    $errors = [];

    if (empty($rows)) {
        return ['inserted' => 0, 'skipped' => 0, 'errors' => [], 'hint' => 'File is empty.'];
    }

    $columns = getImportColumns($rows);
    $ticketIdKeys = ['ticket_id', 'ticketid', 'ticket_number', 'ticketnumber', 'ticket_no', 'support_ticket_id', 'query_id', 'queryid'];
    $orderOnlyKeys = ['order_id', 'orderid', 'order_no', 'order_number'];
    $hasTicketCol = (bool) array_intersect($columns, $ticketIdKeys);
    $hasOrderOnlyCol = (bool) array_intersect($columns, $orderOnlyKeys);

    if (!$hasTicketCol && $hasOrderOnlyCol && !in_array('customer_order_id', $columns, true)) {
        return [
            'inserted' => 0,
            'skipped' => count($rows),
            'errors' => [],
            'hint' => 'This file looks like ORDERS data (has order_id but no ticket_id). Choose Import Type: Orders, or use tickets_sample.csv.',
            'columns' => $columns,
        ];
    }

    if (!$hasTicketCol) {
        return [
            'inserted' => 0,
            'skipped' => count($rows),
            'errors' => [],
            'hint' => 'Missing ticket ID column (expected ticket_id or Query_ID). Found: ' . implode(', ', $columns) . '. Download tickets_sample.csv for reference.',
            'columns' => $columns,
        ];
    }

    $sql = 'INSERT INTO support_tickets (admin_id, ticket_id, customer_name, order_id, category, priority, status, agent, csat_score, compensation_amount, support_channel, created_at, resolved_at, refund_completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                customer_name = VALUES(customer_name),
                order_id = VALUES(order_id),
                category = VALUES(category),
                priority = VALUES(priority),
                status = VALUES(status),
                agent = VALUES(agent),
                csat_score = VALUES(csat_score),
                compensation_amount = VALUES(compensation_amount),
                support_channel = VALUES(support_channel),
                created_at = VALUES(created_at),
                resolved_at = VALUES(resolved_at),
                refund_completed_at = VALUES(refund_completed_at)';

    $stmt = $db->prepare($sql);

    foreach ($rows as $i => $row) {
        $ticketId = rowValue($row, ['ticket_id', 'ticketid', 'ticket_number', 'ticketnumber', 'ticket_no', 'support_ticket_id', 'query_id', 'queryid', 'id']);
        if ($ticketId === '') {
            $skipped++;
            continue;
        }

        try {
            $csat = rowValue($row, ['csat_score', 'csat', 'rating', 'customer_rating', 'customer_rating_score']);
            if ($csat !== '') {
                $csat = (float)preg_replace('/[^0-9.]/', '', $csat);
            } else {
                $csat = null;
            }

            $createdAt = rowValue($row, ['created_at', 'created', 'ticket_date', 'date_created', 'timestamp']) ?: null;
            $resolvedAt = rowValue($row, ['resolved_at', 'resolved', 'resolution_date', 'resolution_timestamp']) ?: null;
            $refundAt = rowValue($row, ['refund_completed_at', 'refund_date', 'refund_at']) ?: null;

            $comp = rowValue($row, ['compensation_amount', 'compensation', 'refund_amount'], '0');
            $comp = preg_replace('/[^0-9.]/', '', $comp);

            $channel = rowValue($row, ['support_channel', 'channel', 'contact_channel'], 'Chat');

            $orderId = rowValue($row, ['order_id', 'orderid', 'order', 'customer_order_id', 'customerorderid']) ?: null;
            $customerName = rowValue($row, ['customer_name', 'customer', 'name']);
            if ($customerName === '' && $orderId !== '') {
                $lookup = $db->prepare('SELECT customer_name FROM orders WHERE admin_id = ? AND order_id = ? LIMIT 1');
                $lookup->execute([$adminId, $orderId]);
                $customerName = $lookup->fetchColumn() ?: 'Unknown';
            }
            if ($customerName === '') {
                $customerName = 'Unknown';
            }

            $stmt->execute([
                $adminId,
                $ticketId,
                $customerName,
                $orderId,
                rowValue($row, ['category', 'issue', 'issue_type', 'complaint_type']) ?: null,
                rowValue($row, ['priority'], 'Medium'),
                rowValue($row, ['status'], 'Open'),
                rowValue($row, ['agent', 'assigned_agent', 'agent_name', 'agent_id']) ?: null,
                $csat,
                $comp !== '' ? (float)$comp : 0,
                $channel,
                $createdAt ?: date('Y-m-d H:i:s'),
                $resolvedAt ?: null,
                $refundAt ?: null,
            ]);
            $inserted++;
        } catch (Exception $e) {
            $errors[] = 'Row ' . ($i + 2) . ': ' . $e->getMessage();
        }
    }

    $hint = null;
    if ($inserted === 0 && $skipped > 0) {
        $hint = 'All rows skipped — ticket_id column is empty on every row. Check column names match tickets_sample.csv.';
    }

    return ['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors, 'hint' => $hint, 'columns' => $columns];
}

function getDashboardStats(): array
{
    require_once __DIR__ . '/tenant.php';
    $adminId = currentAdminId();
    $db = getDB();

    $stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE admin_id = ? AND status NOT IN ('Resolved', 'Closed')");
    $stmt->execute([$adminId]);
    $open = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE admin_id = ? AND status IN ('Resolved', 'Closed') AND DATE(resolved_at) = CURDATE()");
    $stmt->execute([$adminId]);
    $resolvedToday = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT ROUND(AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at) / 3600), 1) FROM support_tickets WHERE admin_id = ? AND resolved_at IS NOT NULL");
    $stmt->execute([$adminId]);
    $avgHours = $stmt->fetchColumn();
    $avgTime = $avgHours ? $avgHours . 'h' : 'N/A';

    $stmt = $db->prepare('SELECT ROUND(AVG(csat_score), 1) FROM support_tickets WHERE admin_id = ? AND csat_score IS NOT NULL');
    $stmt->execute([$adminId]);
    $csat = $stmt->fetchColumn();
    $csatDisplay = $csat ? $csat . '/5' : 'N/A';

    $stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE admin_id = ? AND category LIKE '%refund%'");
    $stmt->execute([$adminId]);
    $refunds = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM support_tickets WHERE admin_id = ? AND status = 'Escalated'");
    $stmt->execute([$adminId]);
    $escalated = (int)$stmt->fetchColumn();

    return compact('open', 'resolvedToday', 'avgTime', 'csatDisplay', 'refunds', 'escalated');
}

function getTickets(int $limit = 50): array
{
    require_once __DIR__ . '/tenant.php';
    $adminId = currentAdminId();
    $stmt = getDB()->prepare('SELECT * FROM support_tickets WHERE admin_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $adminId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getOrders(int $limit = 50): array
{
    require_once __DIR__ . '/tenant.php';
    $adminId = currentAdminId();
    $stmt = getDB()->prepare('SELECT * FROM orders WHERE admin_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $adminId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getRefundTickets(): array
{
    require_once __DIR__ . '/tenant.php';
    $adminId = currentAdminId();
    $stmt = getDB()->prepare("SELECT ticket_id, order_id, category, status, customer_name FROM support_tickets WHERE admin_id = ? AND category LIKE '%refund%' ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$adminId]);
    return $stmt->fetchAll();
}

function getAnalyticsData(): array
{
    require_once __DIR__ . '/tenant.php';
    $adminId = currentAdminId();
    $db = getDB();

    $stmt = $db->prepare("SELECT category, COUNT(*) as cnt FROM support_tickets WHERE admin_id = ? AND category IS NOT NULL GROUP BY category ORDER BY cnt DESC LIMIT 8");
    $stmt->execute([$adminId]);
    $categoryData = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT status, COUNT(*) as cnt FROM support_tickets WHERE admin_id = ? GROUP BY status');
    $stmt->execute([$adminId]);
    $statusData = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%a') AS day, COUNT(*) AS cnt
        FROM support_tickets
        WHERE admin_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE_FORMAT(created_at, '%a'), DAYOFWEEK(created_at)
        ORDER BY DAYOFWEEK(created_at)
    ");
    $stmt->execute([$adminId]);
    $weeklyVolume = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT
            DATE_FORMAT(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY), '%b %d') AS week,
            ROUND(100.0 * SUM(CASE WHEN status IN ('Resolved','Closed') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 1) AS rate
        FROM support_tickets
        WHERE admin_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 WEEK)
        GROUP BY YEARWEEK(created_at, 1)
        ORDER BY YEARWEEK(created_at, 1)
        LIMIT 4
    ");
    $stmt->execute([$adminId]);
    $resolutionRate = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT DATE_FORMAT(created_at, '%b') AS month,
               ROUND(AVG(csat_score), 1) AS avg_csat
        FROM support_tickets
        WHERE admin_id = ? AND csat_score IS NOT NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY YEAR(created_at), MONTH(created_at)
    ");
    $stmt->execute([$adminId]);
    $csatTrend = $stmt->fetchAll();

    return compact('categoryData', 'statusData', 'weeklyVolume', 'resolutionRate', 'csatTrend');
}

function formatTicketTime(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    if (date('Y-m-d') === date('Y-m-d', $ts)) {
        return date('g:i A', $ts);
    }
    if (date('Y-m-d', strtotime('-1 day')) === date('Y-m-d', $ts)) {
        return 'Yesterday';
    }
    return date('M j, Y', $ts);
}

function priorityBadgeClass(string $priority): string
{
    return match (strtolower($priority)) {
        'critical' => 'badge-critical',
        'high'     => 'badge-high',
        'medium'   => 'badge-medium',
        'low'      => 'badge-low',
        default    => 'badge-medium',
    };
}

function importJobDir(): string
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssm_imports';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function cleanupImportJob(?array $job): void
{
    if (!$job || empty($job['ndjson']) || !is_file($job['ndjson'])) {
        return;
    }
    @unlink($job['ndjson']);
}

function createImportJob(string $tmpFile, string $ext, string $importType): array
{
    set_time_limit(0);
    ini_set('memory_limit', '512M');

    $rows = parseUploadFile($tmpFile, $ext);
    if (empty($rows)) {
        return ['success' => false, 'message' => 'No data found in file. Check format matches the sample file.'];
    }

    $jobId = bin2hex(random_bytes(8));
    $ndjsonPath = importJobDir() . DIRECTORY_SEPARATOR . 'job_' . $jobId . '.ndjson';
    $handle = fopen($ndjsonPath, 'wb');
    if ($handle === false) {
        return ['success' => false, 'message' => 'Could not prepare import on server.'];
    }

    $count = 0;
    foreach ($rows as $row) {
        fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n");
        $count++;
    }
    fclose($handle);
    unset($rows);

    return [
        'success' => true,
        'job_id'  => $jobId,
        'total'   => $count,
        'type'    => $importType,
        'ndjson'  => $ndjsonPath,
    ];
}

function readImportJobChunk(string $ndjsonPath, int $offset, int $limit): array
{
    $rows = [];
    if (!is_file($ndjsonPath)) {
        return $rows;
    }

    $file = new SplFileObject($ndjsonPath, 'r');
    $file->seek($offset);
    $read = 0;
    while (!$file->eof() && $read < $limit) {
        $line = trim($file->fgets());
        if ($line === '') {
            continue;
        }
        $row = json_decode($line, true);
        if (is_array($row)) {
            $rows[] = $row;
            $read++;
        }
    }
    return $rows;
}

function formatImportResultMessage(string $label, array $result, int $totalImported, int $totalSkipped, int $totalRows): string
{
    $message = "{$label} import complete: " . number_format($totalImported) . ' records imported';
    if ($totalSkipped > 0) {
        $message .= ', ' . number_format($totalSkipped) . ' skipped';
    }
    $message .= ' (of ' . number_format($totalRows) . ' rows).';
    if (!empty($result['hint'])) {
        $message .= ' ' . $result['hint'];
    }
    if (!empty($result['errors'])) {
        $message .= ' Sample errors: ' . implode('; ', array_slice($result['errors'], 0, 2));
    }
    return $message;
}
