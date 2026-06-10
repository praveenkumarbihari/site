<?php

require_once __DIR__ . '/auth.php';

function ensureTenantSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
}

function currentAdminId(): int
{
    if (empty($_SESSION['admin_id'])) {
        throw new RuntimeException('Admin not authenticated');
    }
    return (int)$_SESSION['admin_id'];
}

/** @return array{0: string, 1: array<int, mixed>} */
function scopeWhere(string $baseWhere, array $params, string $column = 'admin_id'): array
{
    ensureTenantSchema();
    $adminId = currentAdminId();
    $clause = $column . ' = ?';
    if ($baseWhere === '') {
        return ['WHERE ' . $clause, [$adminId, ...$params]];
    }
    if (stripos($baseWhere, 'WHERE') === 0) {
        return [$baseWhere . ' AND ' . $clause, [...$params, $adminId]];
    }
    return ['WHERE ' . $baseWhere . ' AND ' . $clause, [$adminId, ...$params]];
}

/** @return array{0: string, 1: array<int, mixed>} */
function scopeWhereAlias(string $baseWhere, array $params, string $alias): array
{
    return scopeWhere($baseWhere, $params, $alias . '.admin_id');
}
