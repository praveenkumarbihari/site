<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ideator_engine.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

try {
    $report = getFullAnalyticsReport();
    $summary = buildIdeatorSummary($report);

    if (($summary['totals']['tickets'] ?? 0) === 0 && ($summary['totals']['orders'] ?? 0) === 0) {
        echo json_encode([
            'success' => false,
            'error'   => 'No analytics data yet. Import orders or tickets first.',
        ]);
        exit;
    }

    $prompt = buildIdeatorPrompt($summary);
    $gemini = callGeminiFlash($prompt);
    $reportData = generateIdeatorReport($summary, $gemini['text'], $gemini['model']);

    $_SESSION['ideator_last_report'] = $reportData;

    echo json_encode([
        'success'       => true,
        'summary'       => $summary,
        'insights_md'   => $reportData['insights_md'],
        'insights_html' => $reportData['insights_html'],
        'generated_at'  => $reportData['generated_at'],
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not read your analytics data. Please try again.']);
} catch (Throwable $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    if (str_contains($msg, 'quota') || str_contains($msg, 'API')) {
        $msg = 'Ideator is taking a short break. Please try again in a minute.';
    }
    echo json_encode(['success' => false, 'error' => $msg]);
}
