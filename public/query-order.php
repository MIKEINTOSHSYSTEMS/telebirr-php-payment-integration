<?php

/**
 * Query Order Status Page
 */

$page_title = 'Query Order - Telebirr Payment Gateway';
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/header.php';

use Telebirr\TelebirrPayment;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize payment gateway
$telebirr = new TelebirrPayment($config);

$queryResult = null;
$transaction = null;
$error = null;
$bizContent = [];

// Handle query form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['order_id'])) {
    $orderId = $_POST['order_id'] ?? $_GET['order_id'] ?? '';

    if ($orderId) {
        // First get local transaction
        $transaction = $telebirr->getTransaction($orderId);

        // Then query Telebirr for live status
        $queryResult = $telebirr->queryPayment($orderId);

        if ($queryResult['success']) {
            $bizContent = $queryResult['data'];
        } else {
            $error = $queryResult['error'];
        }
    } else {
        $error = "Please enter an Order ID";
    }
}
?>

<div class="container">
    <header class="page-header">
        <h1>🔍 Query Payment Status</h1>
        <p class="subtitle">Check the current status of any payment in real-time</p>
    </header>

    <div class="query-grid">
        <!-- Query Form -->
        <div class="query-form-card">
            <h2>📝 Enter Order ID</h2>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>❌ Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="query-form">
                <div class="form-group">
                    <label for="order_id">Merchant Order ID</label>
                    <input type="text" id="order_id" name="order_id" required
                        placeholder="e.g., 17714632549580"
                        value="<?= htmlspecialchars($_GET['order_id'] ?? $_POST['order_id'] ?? '') ?>">
                    <small style="display: block; margin-top: 5px; color: #666;">Enter the exact order ID from your transaction</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        🔍 Query Status
                    </button>
                    <a href="index.php" class="btn btn-outline">🏠 Home</a>
                </div>
            </form>

            <div class="query-info">
                <h4>📋 Quick Tips</h4>
                <p>• Order IDs are numeric only (e.g., 17714632549580)</p>
                <p>• Check the transactions table on the home page for recent order IDs</p>
                <p>• Status updates in real-time from Telebirr</p>
                <p>• Pending payments may take a few moments to complete</p>
            </div>
        </div>

        <!-- Query Results -->
        <?php if ($queryResult || $transaction): ?>
            <div class="query-results-card">
                <h2>📊 Query Results</h2>

                <?php if ($queryResult && $queryResult['success']): ?>
                    <div class="result-section">
                        <h3>🔄 Live Status from Telebirr</h3>
                        <div class="status-display">
                            <?php
                            $status = strtolower($bizContent['order_status'] ?? 'unknown');
                            $displayStatus = $bizContent['order_status'] ?? 'UNKNOWN';
                            ?>
                            <span class="status-badge status-<?= $status ?>">
                                <?= htmlspecialchars($displayStatus) ?>
                            </span>
                        </div>

                        <table class="results-table">
                            <?php if (!empty($bizContent)): ?>
                                <?php foreach ($bizContent as $key => $value): ?>
                                    <?php if (!is_array($value) && $value !== null): ?>
                                        <tr>
                                            <th><?= ucwords(str_replace('_', ' ', htmlspecialchars($key))) ?>:</th>
                                            <td>
                                                <?php if (strpos($key, 'id') !== false || strpos($key, 'order') !== false): ?>
                                                    <code><?= htmlspecialchars($value) ?></code>
                                                <?php elseif ($key === 'total_amount'): ?>
                                                    <strong style="color: #28a745;"><?= htmlspecialchars($value) ?> ETB</strong>
                                                <?php elseif ($key === 'trans_time' && !empty($value)): ?>
                                                    <?= date('Y-m-d H:i:s', strtotime(htmlspecialchars($value))) ?>
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
                <?php endif; ?>

                <?php if ($transaction): ?>
                    <div class="result-section">
                        <h3>💾 Local Database Record</h3>
                        <table class="results-table">
                            <?php foreach ($transaction as $key => $value): ?>
                                <?php if (!is_array($value) && !in_array($key, ['notify_data']) && $value !== null): ?>
                                    <tr>
                                        <th><?= ucwords(str_replace('_', ' ', htmlspecialchars($key))) ?>:</th>
                                        <td>
                                            <?php if (strpos($key, 'id') !== false || strpos($key, 'order') !== false): ?>
                                                <code><?= htmlspecialchars($value) ?></code>
                                            <?php elseif ($key === 'amount'): ?>
                                                <strong style="color: #28a745;"><?= htmlspecialchars($value) ?> ETB</strong>
                                            <?php elseif ($key === 'status'): ?>
                                                <span class="status-badge status-<?= strtolower($value) ?>" style="font-size: 0.9em; padding: 5px 15px;">
                                                    <?= htmlspecialchars($value) ?>
                                                </span>
                                            <?php elseif ($key === 'created_at' || $key === 'updated_at' || $key === 'completed_at'): ?>
                                                <?= $value ? date('Y-m-d H:i:s', strtotime($value)) : '-' ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($value ?: '-') ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($queryResult && !$queryResult['success']): ?>
                    <div class="alert alert-warning">
                        ⚠️ Could not get live status: <?= htmlspecialchars($queryResult['error']) ?>
                    </div>
                <?php endif; ?>

                <div class="result-actions">
                    <a href="demo.php" class="btn btn-primary">💰 New Payment</a>
                    <?php if (!empty($_GET['order_id']) || !empty($_POST['order_id'])): ?>
                        <a href="refund-order.php?order_id=<?= urlencode($_GET['order_id'] ?? $_POST['order_id'] ?? '') ?>"
                            class="btn btn-warning">↩️ Process Refund</a>
                    <?php endif; ?>
                    <a href="query-order.php" class="btn btn-outline">🔄 New Query</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>