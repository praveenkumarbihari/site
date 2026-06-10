<?php

require_once __DIR__ . '/tenant.php';

function getAllOrders(): array
{
    [$where, $params] = scopeWhere('', []);
    $stmt = getDB()->prepare("SELECT * FROM orders $where ORDER BY created_at DESC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function countOrders(string $search = ''): int
{
    [$where, $params] = ordersSearchClause($search);
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM orders $where");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function getOrdersPaginated(int $page, int $perPage, string $search = ''): array
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    [$where, $params] = ordersSearchClause($search);
    $sql = "SELECT * FROM orders $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = getDB()->prepare($sql);

    $i = 1;
    foreach ($params as $p) {
        $stmt->bindValue($i++, $p);
    }
    $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($i, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function ordersSearchClause(string $search): array
{
    $search = trim($search);
    if ($search === '') {
        return scopeWhere('', []);
    }
    $term = '%' . $search . '%';
    $where = 'WHERE order_id LIKE ? OR customer_name LIKE ? OR restaurant LIKE ? OR status LIKE ? OR delivery_partner LIKE ?';
    return scopeWhere($where, [$term, $term, $term, $term, $term]);
}

function renderPagination(int $page, int $totalPages, int $totalItems, int $perPage, string $basePath, array $queryParams = [], string $itemLabel = 'orders'): string
{
    if ($totalPages <= 1) {
        $start = $totalItems > 0 ? ($page - 1) * $perPage + 1 : 0;
        $end = min($page * $perPage, $totalItems);
        if ($totalItems === 0) {
            return '';
        }
        return '<p class="count-label">Showing ' . $start . '–' . $end . ' of ' . number_format($totalItems) . ' ' . htmlspecialchars($itemLabel) . '</p>';
    }

    $start = $totalItems > 0 ? ($page - 1) * $perPage + 1 : 0;
    $end = min($page * $perPage, $totalItems);

    $buildUrl = function (int $p) use ($basePath, $queryParams, $perPage) {
        $q = array_merge($queryParams, ['page' => $p, 'per_page' => $perPage]);
        return htmlspecialchars($basePath . '?' . http_build_query($q));
    };

    $html = '<div class="pagination-bar">';
    $html .= '<p class="count-label">Showing ' . $start . '–' . $end . ' of ' . number_format($totalItems) . ' ' . htmlspecialchars($itemLabel) . '</p>';
    $html .= '<nav class="pagination" aria-label="Pagination">';

    if ($page > 1) {
        $html .= '<a href="' . $buildUrl($page - 1) . '" class="page-btn"><i class="fas fa-chevron-left"></i> Prev</a>';
    } else {
        $html .= '<span class="page-btn disabled"><i class="fas fa-chevron-left"></i> Prev</span>';
    }

    $range = paginationRange($page, $totalPages);
    foreach ($range as $p) {
        if ($p === '...') {
            $html .= '<span class="page-ellipsis">…</span>';
        } elseif ($p === $page) {
            $html .= '<span class="page-num active">' . $p . '</span>';
        } else {
            $html .= '<a href="' . $buildUrl($p) . '" class="page-num">' . $p . '</a>';
        }
    }

    if ($page < $totalPages) {
        $html .= '<a href="' . $buildUrl($page + 1) . '" class="page-btn">Next <i class="fas fa-chevron-right"></i></a>';
    } else {
        $html .= '<span class="page-btn disabled">Next <i class="fas fa-chevron-right"></i></span>';
    }

    $html .= '</nav></div>';
    return $html;
}

function paginationRange(int $current, int $total): array
{
    if ($total <= 7) {
        return range(1, $total);
    }
    $pages = [1];
    if ($current > 3) {
        $pages[] = '...';
    }
    for ($i = max(2, $current - 1); $i <= min($total - 1, $current + 1); $i++) {
        $pages[] = $i;
    }
    if ($current < $total - 2) {
        $pages[] = '...';
    }
    $pages[] = $total;
    return $pages;
}

function getAllTickets(): array
{
    [$where, $params] = scopeWhere('', []);
    $stmt = getDB()->prepare("SELECT * FROM support_tickets $where ORDER BY created_at DESC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function countTickets(string $search = ''): int
{
    [$where, $params] = ticketsSearchClause($search);
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM support_tickets $where");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function getTicketsPaginated(int $page, int $perPage, string $search = ''): array
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    [$where, $params] = ticketsSearchClause($search);
    $sql = "SELECT * FROM support_tickets $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = getDB()->prepare($sql);

    $i = 1;
    foreach ($params as $p) {
        $stmt->bindValue($i++, $p);
    }
    $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($i, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function ticketsSearchClause(string $search): array
{
    $search = trim($search);
    if ($search === '') {
        return scopeWhere('', []);
    }
    $term = '%' . $search . '%';
    $where = 'WHERE ticket_id LIKE ? OR customer_name LIKE ? OR order_id LIKE ? OR category LIKE ? OR status LIKE ? OR agent LIKE ? OR support_channel LIKE ?';
    return scopeWhere($where, [$term, $term, $term, $term, $term, $term, $term]);
}

function resetAllData(): array
{
    $db = getDB();
    $adminId = currentAdminId();

    $stmt = $db->prepare('SELECT COUNT(*) FROM support_tickets WHERE admin_id = ?');
    $stmt->execute([$adminId]);
    $ticketCount = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM orders WHERE admin_id = ?');
    $stmt->execute([$adminId]);
    $orderCount = (int)$stmt->fetchColumn();

    $db->prepare('DELETE FROM support_tickets WHERE admin_id = ?')->execute([$adminId]);
    $db->prepare('DELETE FROM orders WHERE admin_id = ?')->execute([$adminId]);

    return [
        'tickets_deleted' => $ticketCount,
        'orders_deleted'  => $orderCount,
    ];
}

function getAllRefundTickets(): array
{
    $adminId = currentAdminId();
    $stmt = getDB()->prepare("
        SELECT t.*, o.amount AS order_amount
        FROM support_tickets t
        LEFT JOIN orders o ON o.order_id = t.order_id AND o.admin_id = t.admin_id
        WHERE t.admin_id = ? AND t.category LIKE '%refund%'
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$adminId]);
    return $stmt->fetchAll();
}

function refundsWhereClause(string $search): array
{
    $conditions = ["t.category LIKE '%refund%'"];
    $params = [];
    $search = trim($search);
    if ($search !== '') {
        $term = '%' . $search . '%';
        $conditions[] = '(t.ticket_id LIKE ? OR t.customer_name LIKE ? OR t.order_id LIKE ? OR t.category LIKE ? OR t.status LIKE ? OR t.agent LIKE ?)';
        $params = [$term, $term, $term, $term, $term, $term];
    }
    return scopeWhereAlias('WHERE ' . implode(' AND ', $conditions), $params, 't');
}

function countRefunds(string $search = ''): int
{
    [$where, $params] = refundsWhereClause($search);
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM support_tickets t $where");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function getRefundsPaginated(int $page, int $perPage, string $search = ''): array
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    [$where, $params] = refundsWhereClause($search);
    $sql = "SELECT t.*, o.amount AS order_amount
            FROM support_tickets t
            LEFT JOIN orders o ON o.order_id = t.order_id AND o.admin_id = t.admin_id
            $where
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = getDB()->prepare($sql);

    $i = 1;
    foreach ($params as $p) {
        $stmt->bindValue($i++, $p);
    }
    $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($i, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function getCustomerSummaries(): array
{
    $adminId = currentAdminId();
    $stmt = getDB()->prepare("
        SELECT
            o.customer_name,
            MAX(o.customer_phone) AS customer_phone,
            MAX(o.customer_email) AS customer_email,
            COUNT(DISTINCT o.order_id) AS order_count,
            COALESCE(SUM(o.amount), 0) AS total_spent,
            COUNT(DISTINCT t.ticket_id) AS ticket_count
        FROM orders o
        LEFT JOIN support_tickets t ON t.customer_name = o.customer_name AND t.admin_id = o.admin_id
        WHERE o.admin_id = ?
        GROUP BY o.customer_name
        ORDER BY ticket_count DESC, total_spent DESC
    ");
    $stmt->execute([$adminId]);
    return $stmt->fetchAll();
}

function statusBadgeClass(string $status): string
{
    $s = strtolower($status);
    if (str_contains($s, 'deliver')) return 'badge-low';
    if (str_contains($s, 'cancel')) return 'badge-critical';
    if (str_contains($s, 'escalat')) return 'badge-critical';
    if (str_contains($s, 'progress')) return 'badge-medium';
    if (str_contains($s, 'open')) return 'badge-high';
    return 'badge-medium';
}

function formatMoney($amount): string
{
    return '₹' . number_format((float)$amount, 0, '.', ',');
}

function formatDate(?string $date): string
{
    if (!$date) return '-';
    return date('M j, Y', strtotime($date));
}

function pgBool($value): bool
{
    return $value === true || $value === 't' || $value === '1' || $value === 1;
}
