<?php
/**
 * Refund Order Page
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Telebirr\TelebirrPayment;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize payment gateway
$telebirr = new TelebirrPayment($config);

$refundResult = null;
$transaction = null;
$error = null;

// Handle refund form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'process_refund') {
        $orderId = $_POST['order_id'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        
        if (!$orderId) {
            $error = "Please enter an Order ID";
        } elseif ($amount <= 0) {
            $error = "Please enter a valid refund amount";
        } else {
            $refundResult = $telebirr->refundPayment($orderId, $amount, $reason);
            
            if (!$refundResult['success']) {
                $error = $refundResult['error'];
            }
        }
    }
} elseif (isset($_GET['order_id'])) {
    // Load transaction for pre-filling form
    $transaction = $telebirr->getTransaction($_GET['order_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Refund - Telebirr Demo</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>↩️ Process Refund</h1>
            <p class="subtitle">Refund payments to customers</p>
        </header>
        
        <div class="refund-grid">
            <!-- Refund Form -->
            <div class="refund-form-card">
                <h2>Refund Details</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($refundResult && $refundResult['success']): ?>
                    <div class="alert alert-success">
                        ✅ Refund processed successfully!
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="refund-form">
                    <input type="hidden" name="action" value="process_refund">
                    
                    <div class="form-group">
                        <label for="order_id">Original Order ID *</label>
                        <input type="text" id="order_id" name="order_id" required 
                               placeholder="e.g., ORD_5f8d3b2a1c9e4_1234567890"
                               value="<?= htmlspecialchars($_GET['order_id'] ?? $transaction['merch_order_id'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Refund Amount (ETB) *</label>
                        <input type="number" id="amount" name="amount" required 
                               min="0.01" step="0.01" placeholder="Enter amount"
                               value="<?= htmlspecialchars($transaction['amount'] ?? '') ?>">
                        <?php if ($transaction): ?>
                            <small>Original amount: <?= htmlspecialchars($transaction['amount']) ?> ETB</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="reason">Refund Reason</label>
                        <textarea id="reason" name="reason" rows="3" 
                                  placeholder="Why are you processing this refund?"><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-warning btn-large">
                            ↩️ Process Refund
                        </button>
                        <a href="index.php" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
                
                <div class="refund-warning">
                    <strong>⚠️ Important:</strong>
                    <ul>
                        <li>Refunds can only be processed for completed transactions</li>
                        <li>Refund amount cannot exceed the original transaction amount</li>
                        <li>Refund processing may take 1-3 business days</li>
                        <li>The customer will be notified via SMS</li>
                    </ul>
                </div>
            </div>
            
            <!-- Refund Result -->
            <?php if ($refundResult): ?>
                <div class="refund-result-card">
                    <h2>📋 Refund Result</h2>
                    
                    <?php if ($refundResult['success']): ?>
                        <div class="result-success">
                            <div class="success-icon">✅</div>
                            <h3>Refund Initiated Successfully</h3>
                            
                            <table class="result-table">
                                <tr>
                                    <th>Refund Request No:</th>
                                    <td><code><?= htmlspecialchars($refundResult['refund_request_no']) ?></code></td>
                                </tr>
                                <?php if (!empty($refundResult['data'])): ?>
                                    <?php foreach ($refundResult['data'] as $key => $value): ?>
                                        <?php if (!is_array($value)): ?>
                                        <tr>
                                            <th><?= htmlspecialchars($key) ?>:</th>
                                            <td>
                                                <?php if (strpos($key, 'id') !== false || strpos($key, 'order') !== false): ?>
                                                    <code><?= htmlspecialchars($value) ?></code>
                                                <?php elseif ($key === 'refund_amount'): ?>
                                                    <strong><?= htmlspecialchars($value) ?> ETB</strong>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($value) ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="result-failed">
                            <div class="failed-icon">❌</div>
                            <h3>Refund Failed</h3>
                            <p><?= htmlspecialchars($refundResult['error']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="result-actions">
                        <a href="refund-order.php" class="btn btn-primary">New Refund</a>
                        <a href="query-order.php?order_id=<?= urlencode($_POST['order_id'] ?? '') ?>" 
                           class="btn btn-secondary">Check Order</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>