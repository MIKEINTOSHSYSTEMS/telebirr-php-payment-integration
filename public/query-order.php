<?php
/**
 * Query Order Status Page
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Query Order - Telebirr Demo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Enhanced Query Page Styles */
        :root {
            --primary-gradient: linear-gradient(135deg, #011777 0%, #001234 100%);
            --success-gradient: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            --warning-gradient: linear-gradient(135deg, #fad0c4 0%, #ffd1ff 100%);
            --error-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: var(--primary-gradient);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        
        header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .subtitle {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .query-grid {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 30px;
        }
        
        /* Query Form Card */
        .query-form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            height: fit-content;
        }
        
        .query-form-card h2 {
            color: #333;
            margin-bottom: 30px;
            font-size: 1.8em;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .query-form {
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            background: white;
        }
        
        .form-group input:hover {
            border-color: #764ba2;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            flex: 2;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }
        
        .btn-outline {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            flex: 1;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(240,147,251,0.3);
        }
        
        .query-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid #667eea;
        }
        
        .query-info h4 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        
        .query-info p {
            color: #666;
            line-height: 1.6;
        }
        
        /* Query Results Card */
        .query-results-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .query-results-card h2 {
            color: #333;
            margin-bottom: 30px;
            font-size: 1.8em;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .result-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .result-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .result-section h3:before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 20px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }
        
        .status-display {
            text-align: center;
            margin: 20px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 1.2em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .status-pending,
        .status-wait_pay {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            color: white;
        }
        
        .status-completed,
        .status-pay_success {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: white;
        }
        
        .status-failed,
        .status-pay_failed {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .status-unknown {
            background: linear-gradient(135deg, #a8c0ff 0%, #3f2b96 100%);
            color: white;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .results-table tr {
            border-bottom: 1px solid #e0e0e0;
        }
        
        .results-table tr:last-child {
            border-bottom: none;
        }
        
        .results-table th {
            padding: 15px;
            text-align: left;
            color: #666;
            font-weight: 600;
            width: 35%;
            background: rgba(102,126,234,0.05);
        }
        
        .results-table td {
            padding: 15px;
            color: #333;
            font-weight: 500;
        }
        
        .results-table code {
            background: #e0e0e0;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.9em;
            color: #333;
        }
        
        .result-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
        }
        
        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            color: white;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .query-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }
            
            .result-actions {
                flex-direction: column;
            }
            
            .results-table th,
            .results-table td {
                display: block;
                width: 100%;
            }
            
            .results-table th {
                background: none;
                padding-bottom: 5px;
            }
            
            .results-table td {
                padding-top: 0;
                padding-bottom: 15px;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .query-results-card {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
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
</body>
</html>