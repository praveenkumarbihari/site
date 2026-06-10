<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
requireLoginApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

set_time_limit(0);
ini_set('memory_limit', '512M');

$action = $_POST['action'] ?? 'start';

try {
    if ($action === 'chunk') {
        handleImportChunk();
    } elseif ($action === 'cancel') {
        handleImportCancel();
    } else {
        handleImportStart();
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => dbErrorMessage($e)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
}

function handleImportStart(): void
{
    $importType = $_POST['import_type'] ?? '';

    if (!isset($_FILES['data_file']) || $_FILES['data_file']['error'] !== UPLOAD_ERR_OK) {
        $code = $_FILES['data_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $msg = match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large for server upload limits.',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please try again.',
            default => 'Please select a valid file to upload.',
        };
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    if (!in_array($importType, ['orders', 'tickets'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid import type selected.']);
        exit;
    }

    $file = $_FILES['data_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
        echo json_encode(['success' => false, 'message' => 'Only CSV and Excel (.xlsx, .xls) files are allowed.']);
        exit;
    }

    if (!empty($_SESSION['import_job'])) {
        cleanupImportJob($_SESSION['import_job']);
    }

    $job = createImportJob($file['tmp_name'], $ext, $importType);
    if (empty($job['success'])) {
        echo json_encode(['success' => false, 'message' => $job['message'] ?? 'Could not read file.']);
        exit;
    }

    $_SESSION['import_job'] = [
        'job_id'  => $job['job_id'],
        'ndjson'  => $job['ndjson'],
        'type'    => $importType,
        'total'   => $job['total'],
        'offset'  => 0,
        'inserted'=> 0,
        'skipped' => 0,
        'errors'  => [],
        'hint'    => null,
        'columns' => null,
    ];

    echo json_encode([
        'success' => true,
        'phase'   => 'started',
        'job_id'  => $job['job_id'],
        'total'   => $job['total'],
        'message' => 'File ready. Importing ' . number_format($job['total']) . ' rows…',
    ]);
}

function handleImportChunk(): void
{
    $job = $_SESSION['import_job'] ?? null;
    if (!$job || empty($job['ndjson']) || !is_file($job['ndjson'])) {
        echo json_encode(['success' => false, 'message' => 'Import session expired. Please upload the file again.']);
        exit;
    }

    $chunkSize = 2500;
    $offset = (int)($job['offset'] ?? 0);
    $rows = readImportJobChunk($job['ndjson'], $offset, $chunkSize);

    if (empty($rows) && $offset >= (int)$job['total']) {
        finishImportJob($job);
        return;
    }

    if ($job['type'] === 'orders') {
        $result = importOrders($rows);
        $label = 'Orders';
    } else {
        $result = importTickets($rows);
        $label = 'Tickets';
    }

    $job['offset'] = $offset + count($rows);
    $job['inserted'] += (int)$result['inserted'];
    $job['skipped'] += (int)$result['skipped'];
    if (!empty($result['hint']) && $job['hint'] === null) {
        $job['hint'] = $result['hint'];
    }
    if (!empty($result['columns']) && $job['columns'] === null) {
        $job['columns'] = $result['columns'];
    }
    if (!empty($result['errors'])) {
        $job['errors'] = array_merge($job['errors'], array_slice($result['errors'], 0, 3));
        $job['errors'] = array_slice($job['errors'], 0, 5);
    }

    $_SESSION['import_job'] = $job;

    $done = $job['offset'] >= (int)$job['total'];
    if ($done) {
        finishImportJob($job, $result, $label);
        return;
    }

    echo json_encode([
        'success'  => true,
        'phase'    => 'chunk',
        'offset'   => $job['offset'],
        'total'    => (int)$job['total'],
        'inserted' => $job['inserted'],
        'skipped'  => $job['skipped'],
        'done'     => false,
        'message'  => 'Imported ' . number_format($job['offset']) . ' of ' . number_format($job['total']) . ' rows…',
    ]);
}

function finishImportJob(array $job, ?array $lastResult = null, string $label = 'Records'): void
{
    $message = formatImportResultMessage(
        $job['type'] === 'orders' ? 'Orders' : 'Tickets',
        [
            'hint'   => $job['hint'],
            'errors' => $job['errors'],
        ],
        (int)$job['inserted'],
        (int)$job['skipped'],
        (int)$job['total']
    );

    $success = (int)$job['inserted'] > 0 || ((int)$job['skipped'] === 0 && empty($job['hint']));

    cleanupImportJob($job);
    unset($_SESSION['import_job']);

    echo json_encode([
        'success'  => $success,
        'phase'    => 'done',
        'done'     => true,
        'message'  => $message,
        'inserted' => (int)$job['inserted'],
        'skipped'  => (int)$job['skipped'],
        'total'    => (int)$job['total'],
        'hint'     => $job['hint'],
        'columns'  => $job['columns'],
    ]);
}

function handleImportCancel(): void
{
    if (!empty($_SESSION['import_job'])) {
        cleanupImportJob($_SESSION['import_job']);
        unset($_SESSION['import_job']);
    }
    echo json_encode(['success' => true, 'message' => 'Import cancelled.']);
}
