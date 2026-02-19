<?php

/**
 * Export API Logs to CSV
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Telebirr\TelebirrPayment;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize payment gateway
$telebirr = new TelebirrPayment($config);

// Get filter parameters (same as logs.php)
$endpoint = $_GET['endpoint'] ?? '';
$method = $_GET['method'] ?? '';
$statusCode = $_GET['status_code'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build filters
$filters = [];
if (!empty($endpoint)) $filters['endpoint'] = $endpoint;
if (!empty($method)) $filters['method'] = $method;
if (!empty($statusCode)) $filters['status_code'] = $statusCode;
if (!empty($dateFrom)) $filters['date_from'] = $dateFrom . ' 00:00:00';
if (!empty($dateTo)) $filters['date_to'] = $dateTo . ' 23:59:59';
if (!empty($search)) $filters['search'] = $search;

// Get all logs (use a high per_page value to get all records)
$page = 1;
$perPage = 10000; // Get up to 10,000 records
$logsResult = $telebirr->getApiLogs($page, $perPage, $filters);

if (!$logsResult['success']) {
    die('Error fetching logs: ' . ($logsResult['error'] ?? 'Unknown error'));
}

$logs = $logsResult['data'];

// Set headers for CSV download
$filename = 'telebirr_logs_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Add headers
fputcsv($output, [
    'ID',
    'Timestamp',
    'Method',
    'Endpoint',
    'Status Code',
    'IP Address',
    'User Agent',
    'Request Data',
    'Response Data'
]);

// Add data rows
foreach ($logs as $log) {
    // Truncate very long data for CSV readability
    $requestData = strlen($log['request_data'] ?? '') > 1000
        ? substr($log['request_data'] ?? '', 0, 1000) . '... [truncated]'
        : ($log['request_data'] ?? '');

    $responseData = strlen($log['response_data'] ?? '') > 1000
        ? substr($log['response_data'] ?? '', 0, 1000) . '... [truncated]'
        : ($log['response_data'] ?? '');

    fputcsv($output, [
        $log['id'] ?? '',
        $log['created_at'] ?? '',
        $log['method'] ?? '',
        $log['endpoint'] ?? '',
        $log['status_code'] ?? '',
        $log['ip_address'] ?? '',
        $log['user_agent'] ?? '',
        $requestData,
        $responseData
    ]);
}

fclose($output);
exit;
