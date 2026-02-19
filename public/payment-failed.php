<?php
/**
 * Payment Failed Page
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
    <title>Payment Failed - Telebirr Demo</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="failed-container">
            <div class="failed-icon">❌</div>
            <h1>Payment Failed</h1>
            <p class="failed-message">We couldn't process your payment. Please try again.</p>
            
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
                            <td><?= htmlspecialchars($transaction['amount']) ?> ETB</td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><span class="status-badge status-failed"><?= htmlspecialchars($transaction['status']) ?></span></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="demo.php" class="btn btn-primary">Try Again</a>
                <a href="query-order.php?order_id=<?= urlencode($orderId) ?>" class="btn btn-secondary">Check Status</a>
                <a href="index.php" class="btn btn-outline">Home</a>
            </div>
            
            <div class="help-section">
                <h3>Possible Reasons:</h3>
                <ul>
                    <li>Insufficient balance in Telebirr account</li>
                    <li>Incorrect PIN entered</li>
                    <li>Transaction timeout</li>
                    <li>Network interruption</li>
                    <li>Payment cancelled by user</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>