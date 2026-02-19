<?php
/**
 * Payment Success Page
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Telebirr\TelebirrPayment;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize payment gateway
$telebirr = new TelebirrPayment($config);

$orderId = $_GET['order_id'] ?? '';
$transaction = $orderId ? $telebirr->getTransaction($orderId) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Telebirr Demo</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="success-container">
            <div class="success-icon">✅</div>
            <h1>Payment Successful!</h1>
            <p class="success-message">Your payment has been processed successfully.</p>
            
            <?php if ($transaction): ?>
                <div class="transaction-details">
                    <h2>Transaction Details</h2>
                    <table class="details-table">
                        <tr>
                            <th>Order ID:</th>
                            <td><code><?= htmlspecialchars($transaction['merch_order_id']) ?></code></td>
                        </tr>
                        <tr>
                            <th>Title:</th>
                            <td><?= htmlspecialchars($transaction['title']) ?></td>
                        </tr>
                        <tr>
                            <th>Amount:</th>
                            <td><strong><?= htmlspecialchars($transaction['amount']) ?> ETB</strong></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><span class="status-badge status-completed"><?= htmlspecialchars($transaction['status']) ?></span></td>
                        </tr>
                        <tr>
                            <th>Date:</th>
                            <td><?= date('F j, Y H:i:s', strtotime($transaction['created_at'])) ?></td>
                        </tr>
                        <?php if ($transaction['payment_order_id']): ?>
                        <tr>
                            <th>Payment Order ID:</th>
                            <td><code><?= htmlspecialchars($transaction['payment_order_id']) ?></code></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="demo.php" class="btn btn-primary">Make Another Payment</a>
                <a href="query-order.php?order_id=<?= urlencode($orderId) ?>" class="btn btn-secondary">Verify Status</a>
                <a href="index.php" class="btn btn-outline">Home</a>
            </div>
        </div>
    </div>
</body>
</html>