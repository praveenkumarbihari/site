<?php

function getRockyDummyTickets(): array
{
    return [
        [
            'ticket_id' => 'SIM-1001',
            'customer_name' => 'Priya Sharma',
            'order_id' => 'ORD882341',
            'category' => 'Delayed Delivery',
            'status' => 'Resolved',
            'agent' => 'Ananya K.',
            'support_channel' => 'Chat',
            'created_at' => '2026-06-10 14:22:00',
            'time_label' => 'Today, 2:22 PM',
        ],
        [
            'ticket_id' => 'SIM-1002',
            'customer_name' => 'Rahul Mehta',
            'order_id' => 'ORD771902',
            'category' => 'Missing Item',
            'status' => 'Open',
            'agent' => 'Vikram S.',
            'support_channel' => 'Call',
            'created_at' => '2026-06-10 11:05:00',
            'time_label' => 'Today, 11:05 AM',
        ],
        [
            'ticket_id' => 'SIM-1003',
            'customer_name' => 'Sneha Reddy',
            'order_id' => 'ORD665430',
            'category' => 'Wrong Item',
            'status' => 'In Progress',
            'agent' => 'Meera P.',
            'support_channel' => 'Chat',
            'created_at' => '2026-06-09 19:40:00',
            'time_label' => 'Yesterday',
        ],
        [
            'ticket_id' => 'SIM-1004',
            'customer_name' => 'Arjun Patel',
            'order_id' => 'ORD554821',
            'category' => 'Refund Status',
            'status' => 'Escalated',
            'agent' => 'Karan D.',
            'support_channel' => 'Chat',
            'created_at' => '2026-06-09 16:18:00',
            'time_label' => 'Yesterday',
        ],
        [
            'ticket_id' => 'SIM-1005',
            'customer_name' => 'Divya Iyer',
            'order_id' => 'ORD443210',
            'category' => 'Payment Issue',
            'status' => 'Resolved',
            'agent' => 'Ananya K.',
            'support_channel' => 'Call',
            'created_at' => '2026-06-08 20:55:00',
            'time_label' => 'Jun 8',
        ],
        [
            'ticket_id' => 'SIM-1006',
            'customer_name' => 'Karthik Nair',
            'order_id' => 'ORD332109',
            'category' => 'Delayed Delivery',
            'status' => 'Resolved',
            'agent' => 'Rohit M.',
            'support_channel' => 'Chat',
            'created_at' => '2026-06-08 13:30:00',
            'time_label' => 'Jun 8',
        ],
        [
            'ticket_id' => 'SIM-1007',
            'customer_name' => 'Anita Desai',
            'order_id' => 'ORD221098',
            'category' => 'Missing Item',
            'status' => 'Open',
            'agent' => 'Vikram S.',
            'support_channel' => 'Chat',
            'created_at' => '2026-06-07 18:12:00',
            'time_label' => 'Jun 7',
        ],
        [
            'ticket_id' => 'SIM-1008',
            'customer_name' => 'Mohammed Ali',
            'order_id' => 'ORD110987',
            'category' => 'Wrong Item',
            'status' => 'Resolved',
            'agent' => 'Meera P.',
            'support_channel' => 'Call',
            'created_at' => '2026-06-07 12:45:00',
            'time_label' => 'Jun 7',
        ],
        [
            'ticket_id' => 'SIM-1009',
            'customer_name' => 'Lakshmi Venkat',
            'order_id' => 'ORD009876',
            'category' => 'Refund Status',
            'status' => 'In Progress',
            'agent' => 'Karan D.',
            'support_channel' => 'Chat',
            'created_at' => '2026-06-06 21:20:00',
            'time_label' => 'Jun 6',
        ],
        [
            'ticket_id' => 'SIM-1010',
            'customer_name' => 'Vivek Singh',
            'order_id' => 'ORD998765',
            'category' => 'Delayed Delivery',
            'status' => 'Resolved',
            'agent' => 'Rohit M.',
            'support_channel' => 'Chat',
            'created_at' => '2026-06-06 09:15:00',
            'time_label' => 'Jun 6',
        ],
    ];
}

function getRockyTicketById(string $ticketId): ?array
{
    foreach (getRockyDummyTickets() as $ticket) {
        if ($ticket['ticket_id'] === $ticketId) {
            return $ticket;
        }
    }
    return null;
}

function getRockyTicketsForSidebar(string $search = ''): array
{
    $tickets = getRockyDummyTickets();
    $search = strtolower(trim($search));
    if ($search === '') {
        return $tickets;
    }

    return array_values(array_filter($tickets, static function ($t) use ($search) {
        $haystack = strtolower(implode(' ', [
            $t['ticket_id'],
            $t['customer_name'],
            $t['order_id'],
            $t['category'],
            $t['agent'],
            $t['support_channel'],
        ]));
        return str_contains($haystack, $search);
    }));
}

function rockySeed(string $ticketId): int
{
    return abs(crc32($ticketId)) ?: 1;
}

function rockyPick(array $items, int $seed, int $offset = 0): string
{
    return $items[($seed + $offset) % count($items)];
}

function generateRockyChat(array $ticket): array
{
    $seed = rockySeed($ticket['ticket_id']);
    $customer = $ticket['customer_name'] ?? 'Customer';
    $agent = $ticket['agent'] ?? 'Support Agent';
    $category = $ticket['category'] ?? 'General';
    $orderId = $ticket['order_id'] ?? 'your order';
    $channel = $ticket['support_channel'] ?? 'Chat';

    $openers = [
        "Hi, I need help with {$category} for order {$orderId}.",
        "Hello, I'm facing an issue — {$category}. Order ID: {$orderId}.",
        "My order {$orderId} has a problem. Can someone assist?",
        "I've been waiting too long. This is about {$category}.",
    ];

    $agentGreet = [
        "Hi {$customer}, I'm {$agent} from Swiggy Support. I understand you're reaching out about {$category}. Let me help you.",
        "Hello {$customer}! Thanks for contacting us via {$channel}. I see your concern regarding {$category}. I'm on it.",
        "Good day {$customer}, this is {$agent}. Sorry for the trouble with order {$orderId}. I'll resolve this for you.",
    ];

    $agentProbe = [
        'Could you confirm if the delivery partner has marked the order as delivered?',
        'I can see the order details on my end. Was any item missing or incorrect?',
        'Let me check the restaurant and delivery timeline for this order.',
        'Have you received any refund notification on the app yet?',
    ];

    $customerReply = [
        "Yes, that's exactly what happened. It's quite frustrating.",
        "No update so far — that's why I contacted support.",
        "The app shows delivered but I didn't receive the full order.",
        "I hope this can be fixed quickly. I'm a regular customer.",
    ];

    $agentResolve = [
        "I've initiated a ₹" . (50 + ($seed % 200)) . " coupon and escalated this to our delivery team.",
        "A full refund of the affected item will reflect within 24–48 hours.",
        "I've re-assigned a senior agent and you'll get a callback within 30 minutes.",
        "I've updated your order status and added priority compensation for the delay.",
    ];

    $customerClose = [
        'Thank you, that helps. Appreciate the quick response.',
        "Okay, I'll wait for the refund. Thanks for explaining.",
        'Hope this gets resolved soon. Thanks for your help.',
        'Alright, thanks for looking into it.',
    ];

    $agentClose = [
        "You're welcome! Is there anything else I can help you with today?",
        "Happy to help. We'll monitor this ticket until it's fully resolved.",
        'Thank you for your patience. Have a great day!',
        'I have noted everything on ticket ' . $ticket['ticket_id'] . '. Take care!',
    ];

    $baseTime = strtotime($ticket['created_at'] ?? 'now');

    $messages = [
        ['role' => 'customer', 'name' => $customer, 'text' => rockyPick($openers, $seed, 0), 'time' => $baseTime],
        ['role' => 'agent', 'name' => $agent, 'text' => rockyPick($agentGreet, $seed, 1), 'time' => $baseTime + 45],
        ['role' => 'agent', 'name' => $agent, 'text' => rockyPick($agentProbe, $seed, 2), 'time' => $baseTime + 90],
        ['role' => 'customer', 'name' => $customer, 'text' => rockyPick($customerReply, $seed, 3), 'time' => $baseTime + 150],
        ['role' => 'agent', 'name' => $agent, 'text' => rockyPick($agentResolve, $seed, 4), 'time' => $baseTime + 240],
        ['role' => 'customer', 'name' => $customer, 'text' => rockyPick($customerClose, $seed, 5), 'time' => $baseTime + 300],
        ['role' => 'agent', 'name' => $agent, 'text' => rockyPick($agentClose, $seed, 6), 'time' => $baseTime + 360],
    ];

    return array_map(static function ($m) {
        $m['time_label'] = date('g:i A', $m['time']);
        return $m;
    }, $messages);
}

function rockyChatToHtml(array $messages): string
{
    $html = '';
    foreach ($messages as $msg) {
        $isAgent = $msg['role'] === 'agent';
        $class = $isAgent ? 'rocky-bubble rocky-bubble--agent' : 'rocky-bubble rocky-bubble--customer';
        $html .= '<div class="' . $class . '">';
        $html .= '<div class="rocky-bubble-meta"><strong>' . htmlspecialchars($msg['name']) . '</strong>';
        $html .= '<span>' . htmlspecialchars($msg['time_label']) . '</span></div>';
        $html .= '<p>' . htmlspecialchars($msg['text']) . '</p></div>';
    }
    return $html;
}

function rockyTicketToJson(array $ticket): array
{
    return [
        'ticket_id'     => $ticket['ticket_id'],
        'customer_name' => $ticket['customer_name'],
        'category'      => $ticket['category'],
        'status'        => $ticket['status'],
        'agent'         => $ticket['agent'],
        'order_id'      => $ticket['order_id'],
        'channel'       => $ticket['support_channel'],
        'created_at'    => $ticket['time_label'] ?? $ticket['created_at'],
    ];
}
