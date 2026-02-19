<?php
/**
 * Telebirr Payment Checkout Handler
 * Handles payment notifications
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Telebirr\TelebirrPayment;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize payment gateway
$telebirr = new TelebirrPayment($config);

// Handle POST notification from Telebirr
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = $telebirr->handleNotification($_POST);
    
    // Set HTTP response code
    http_response_code($response['http_code']);
    
    // Return response
    header('Content-Type: application/json');
    echo json_encode(['status' => $response['success'] ? 'success' : 'error']);
    exit;
}

// Handle GET request (redirect after payment)
$merchOrderId = $_GET['merch_order_id'] ?? null;
$status = $_GET['status'] ?? 'pending';

if ($merchOrderId) {
    $transaction = $telebirr->getTransaction($merchOrderId);
} else {
    $transaction = null;
}

// Redirect based on status
if ($status === 'success') {
    header("Location: payment-success.php?order_id=" . urlencode($merchOrderId));
} else {
    header("Location: payment-failed.php?order_id=" . urlencode($merchOrderId));
}
exit;