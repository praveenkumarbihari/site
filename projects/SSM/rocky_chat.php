<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/rocky_simulator.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$ticketId = trim($_GET['ticket_id'] ?? '');
if ($ticketId === '') {
    echo json_encode(['success' => false, 'message' => 'Ticket ID required.']);
    exit;
}

$ticket = getRockyTicketById($ticketId);
if (!$ticket) {
    echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
    exit;
}

$messages = generateRockyChat($ticket);
echo json_encode([
    'success'  => true,
    'ticket'   => rockyTicketToJson($ticket),
    'messages' => $messages,
    'html'     => rockyChatToHtml($messages),
], JSON_UNESCAPED_UNICODE);
