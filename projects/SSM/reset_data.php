<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/page_helpers.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

try {
    $result = resetAllData();
    unset($_SESSION['ideator_last_report']);

    $_SESSION['flash'] = [
        'type'    => 'success',
        'message' => 'All data cleared — ' . number_format($result['orders_deleted']) . ' orders and '
            . number_format($result['tickets_deleted']) . ' tickets removed.',
    ];
} catch (PDOException $e) {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Could not reset data: ' . dbErrorMessage($e),
    ];
}

header('Location: dashboard.php');
exit;
