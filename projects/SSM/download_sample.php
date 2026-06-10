<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$type = $_GET['type'] ?? '';
$files = [
    'orders'  => ['path' => __DIR__ . '/samples/orders_sample.csv',  'name' => 'orders_sample.csv'],
    'tickets' => ['path' => __DIR__ . '/samples/tickets_sample.csv', 'name' => 'tickets_sample.csv'],
];

if (!isset($files[$type]) || !file_exists($files[$type]['path'])) {
    http_response_code(404);
    exit('Sample file not found.');
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $files[$type]['name'] . '"');
header('Content-Length: ' . filesize($files[$type]['path']));
readfile($files[$type]['path']);
exit;
