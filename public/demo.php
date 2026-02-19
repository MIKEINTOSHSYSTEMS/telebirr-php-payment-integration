<?php
/**
 * Telebirr Payment Demo - Payment Form
 */

$page_title = 'Make Payment - Telebirr Payment Gateway';
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/header.php';

use Telebirr\TelebirrPayment;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize payment gateway
try {
    $telebirr = new TelebirrPayment($config);
} catch (Exception $e) {
    die("Failed to initialize payment gateway: " . $e->getMessage());
}

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$result = null;
$error = null;
$debug = [];
$rawRequest = null;

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'initiate_payment') {
        // Clean the title before processing
        $rawTitle = $_POST['title'] ?? 'Product Payment';
        // Remove any special characters that might cause issues
        $title = preg_replace('/[^a-zA-Z0-9\s]/', '', $rawTitle);
        $title = trim(preg_replace('/\s+/', ' ', $title));
        if (empty($title)) {
            $title = 'Product Payment';
        }
        
        $amount = floatval($_POST['amount'] ?? 0);
        $customerName = $_POST['customer_name'] ?? '';
        $customerPhone = $_POST['customer_phone'] ?? '';
        $customerEmail = $_POST['customer_email'] ?? '';
        
        if ($amount <= 0) {
            $error = "Please enter a valid amount greater than 0";
        } else {
            $additionalData = [
                'callback_info' => 'Payment from demo',
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
                'description' => $title
            ];
            
            // Test token first
            try {
                $applyToken = new \Telebirr\ApplyFabricToken($config);
                $tokenResult = $applyToken->applyToken();
                $debug['token_test'] = $tokenResult;
            } catch (Exception $e) {
                $debug['token_error'] = $e->getMessage();
            }
            
            // Capture the raw request for debugging
            ob_start();
            $result = $telebirr->initializePayment($title, $amount, $additionalData);
            $debug['output'] = ob_get_clean();
            
            if ($result['success']) {
                // Store order ID in session for later use
                $_SESSION['last_order_id'] = $result['merch_order_id'];
                
                // Show debug info before redirect (only in debug mode)
                if ($config['app']['debug']) {
                    echo "<div style='background: #f0f0f0; padding: 20px; margin: 20px; border-radius: 5px;'>";
                    echo "<h3>Debug Information</h3>";
                    echo "<p><strong>Redirecting to:</strong> <br><code style='word-break: break-all;'>" . htmlspecialchars($result['checkout_url']) . "</code></p>";
                    echo "<p><strong>Order ID:</strong> " . htmlspecialchars($result['merch_order_id']) . "</p>";
                    echo "<p><strong>Prepay ID:</strong> " . htmlspecialchars($result['prepay_id']) . "</p>";
                    echo "</div>";
                    echo "<p><a href='" . htmlspecialchars($result['checkout_url']) . "' target='_blank' class='btn btn-primary'>Click here to proceed to payment</a></p>";
                    echo "<p><small>If you're not redirected automatically, click the button above.</small></p>";
                    
                    // Add auto-redirect with JavaScript
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = '" . addslashes($result['checkout_url']) . "';
                        }, 3000);
                    </script>";
                    
                    // Don't exit, let the page continue rendering
                } else {
                    // In production, redirect immediately
                    header("Location: " . $result['checkout_url']);
                    exit;
                }
            } else {
                $error = $result['error'];
            }
        }
    }
}

// Helper function for default title
function getCleanDefaultTitle() {
    return "Product Payment " . date('Ymd');
}
?>

<div class="container">
    <header class="page-header">
        <h1>🇪🇹 Telebirr Payment Demo</h1>
        <p class="subtitle">Test the complete payment flow</p>
    </header>
    
    <?php if (!empty($debug)): ?>
        <div class="debug-info">
            <h4>🔧 Debug Information</h4>
            <pre><?php print_r($debug); ?></pre>
        </div>
    <?php endif; ?>
    
    <?php if ($result && $result['success'] && $config['app']['debug']): ?>
        <div class="alert alert-success">
            <h3>✅ Payment Initialized Successfully!</h3>
            <p>You are being redirected to Telebirr payment page...</p>
        </div>
    <?php endif; ?>
    
    <div class="demo-grid">
        <!-- Payment Form -->
        <div class="demo-form-card">
            <h2>📝 Initiate Payment</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($result) && !$result['success']): ?>
                <div class="alert alert-error">
                    <strong>Payment Failed:</strong> <?= htmlspecialchars($result['error']) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="payment-form">
                <input type="hidden" name="action" value="initiate_payment">
                
                <div class="form-group">
                    <label for="title">Product/Service Title *</label>
                    <input type="text" id="title" name="title" required 
                           placeholder="e.g., Premium Package" 
                           value="<?= htmlspecialchars($_POST['title'] ?? getCleanDefaultTitle()) ?>">
                </div>
                
                <div class="form-group">
                    <label for="amount">Amount (ETB) *</label>
                    <input type="number" id="amount" name="amount" required 
                           min="1" step="0.01" placeholder="100.00"
                           value="<?= htmlspecialchars($_POST['amount'] ?? '100') ?>">
                </div>
                
                <div class="form-group">
                    <label for="customer_name">Customer Name (Optional)</label>
                    <input type="text" id="customer_name" name="customer_name" 
                           placeholder="John Doe"
                           value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="customer_phone">Phone Number (Optional)</label>
                        <input type="tel" id="customer_phone" name="customer_phone" 
                               placeholder="09xxxxxxxx"
                               value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group half">
                        <label for="customer_email">Email (Optional)</label>
                        <input type="email" id="customer_email" name="customer_email" 
                               placeholder="john@example.com"
                               value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
                        💳 Pay with Telebirr
                    </button>
                </div>
            </form>
            
            <div class="demo-info">
                <h4>📌 Test Information</h4>
                <ul>
                    <li>Minimum amount: 1 ETB</li>
                    <li>Maximum amount: No limit</li>
                    <li>Currency: ETB only</li>
                    <li>You will be redirected to Telebirr payment page</li>
                </ul>
            </div>
        </div>
        
        <!-- Quick Amounts -->
        <div class="demo-quick-card">
            <h2>⚡ Quick Amounts</h2>
            <div class="quick-amounts">
                <button class="quick-amount" data-amount="10">10 ETB</button>
                <button class="quick-amount" data-amount="25">25 ETB</button>
                <button class="quick-amount" data-amount="50">50 ETB</button>
                <button class="quick-amount" data-amount="100">100 ETB</button>
                <button class="quick-amount" data-amount="250">250 ETB</button>
                <button class="quick-amount" data-amount="500">500 ETB</button>
                <button class="quick-amount" data-amount="1000">1000 ETB</button>
                <button class="quick-amount" data-amount="5000">5000 ETB</button>
            </div>
            
            <div class="demo-info">
                <h4>📋 Test Scenarios</h4>
                <div class="scenario-list">
                    <div class="scenario-item">
                        <strong>Successful Payment:</strong> Any valid amount
                    </div>
                    <div class="scenario-item">
                        <strong>Failed Payment:</strong> Cancel on Telebirr
                    </div>
                    <div class="scenario-item">
                        <strong>Timeout:</strong> Don't complete payment within 120 minutes
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- API Response Preview -->
    <div class="response-card">
        <h3>🔄 API Response Preview</h3>
        <div class="code-block" id="apiResponse">
<?php if ($result): ?>
<?php if ($result['success']): ?>
{
    "success": true,
    "merch_order_id": "<?= $result['merch_order_id'] ?>",
    "prepay_id": "<?= $result['prepay_id'] ?>",
    "checkout_url": "<?= htmlspecialchars($result['checkout_url']) ?>",
    "response": <?= json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>
}
<?php else: ?>
{
    "success": false,
    "error": "<?= addslashes($result['error']) ?>"
}
<?php endif; ?>
<?php else: ?>
{
    "message": "Submit the form to see API response"
}
<?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>