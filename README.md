# Telebirr C2B Web Payment Integration for PHP

Complete, production-ready Telebirr payment integration solution for PHP applications. Supports one-time payments, order queries, refunds, and webhook notifications.

## Features

- ✅ Complete C2B web payment integration
- ✅ Fabric token management with caching
- ✅ Order creation and checkout URL generation
- ✅ Payment status querying
- ✅ Refund processing
- ✅ Webhook notification handling
- ✅ RSA signature generation and verification
- ✅ Database integration for transaction logging
- ✅ Comprehensive logging with Monolog
- ✅ Interactive demo application
- ✅ Responsive UI with modern CSS
- ✅ Error handling and validation
- ✅ Support for both test and production environments

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher (optional)
- Composer
- OpenSSL extension
- cURL extension
- JSON extension

## Installation

1. Clone or download this repository

2. Install dependencies:

```bash
composer install
```

3.Configure environment:

```bash
cp .env.example .env
# Edit .env with your credentials
```

4.Set up database (optional):

```bash
# Import the database schema
mysql -u username -p database_name < database.sql
```

5.Ensure proper permissions:

```bash
chmod 755 logs/
chmod 644 config/keys/*.pem
```

## Configuration

### Environment Variables

| Variable | Description | Example |
| ---------- | ------------- | --------- |
| BASE_URL | API base URL (test/production) | <https://developerportal.ethiotelebirr.et:38443/apiaccess/payment/gateway> |
| WEB_BASE_URL | Web checkout base URL | <https://developerportal.ethiotelebirr.et:38443/payment/web/paygate?> |
| FABRIC_APP_ID | Your fabric app ID | **************************************** |
| APP_SECRET | Your app secret | **************************************** |
| MERCHANT_APP_ID | Your merchant app ID | ******** |
| MERCHANT_CODE | Your merchant short code | ******** |

### RSA Keys

Place your private and public keys in `config/keys/` directory:
-`private_key.pem` - Your private key (keep secure)
-`public_key.pem` - Your public key (share with Telebirr)

## Usage

### Basic Payment Flow

```php
<?php
require_once 'vendor/autoload.php';

use Telebirr\TelebirrPayment;

// Initialize payment gateway
$telebirr = new TelebirrPayment();

// Initialize payment
$result = $telebirr->initializePayment(
    'Product Title',
    100.00,
    [
        'customer_name' => 'Abebech Kebed',
        'customer_phone' => '0912345678',
        'customer_email' => 'abebech.k@example.com'
    ]
);

if ($result['success']) {
    // Redirect to checkout URL
    header('Location: ' . $result['checkout_url']);
    exit;
} else {
    echo "Error: " . $result['error'];
}
```

### Query Payment Status

```php
$result = $telebirr->queryPayment('ORD_5f8d3b2a1c9e4_1234567890');

if ($result['success']) {
    echo "Status: " . $result['data']['trade_status'];
    echo "Amount: " . $result['data']['total_amount'] . " ETB";
}
```

### Process Refund

```php
$result = $telebirr->refundPayment(
    'ORD_5f8d3b2a1c9e4_1234567890',
    50.00,
    'Customer requested refund'
);

if ($result['success']) {
    echo "Refund initiated: " . $result['refund_request_no'];
}
```

### Handle Webhook Notification

```php
// In your notify_url endpoint
$result = $telebirr->handleNotification($_POST);

http_response_code($result['http_code']);
echo json_encode(['status' => $result['success'] ? 'success' : 'error']);
```

## Demo Application

The package includes a complete demo application to test all features:

- `public/index.php` - Home page with transaction list
- `public/demo.php` - Payment initiation form
- `public/query-order.php` - Order status query
- `public/refund-order.php` - Refund processing
- `public/payment-success.php` - Success page
- `public/payment-failed.php` - Failure page

Access the demo at: `http://your-domain/public/`

## Integration with Moodle

To integrate with Moodle:

1. Copy the `src/` directory to your Moodle installation
2. Create a custom payment gateway plugin
3. Use the `TelebirrPayment` class in your plugin

Example Moodle integration:

```php
// In your payment gateway class
class gateway_telebirr extends \core_payment\gateway {
    public function execute_payment($paymentid, $userid, $amount, $currency) {
        $telebirr = new \Telebirr\TelebirrPayment();
        
        $result = $telebirr->initializePayment(
            'Moodle Course Payment',
            $amount,
            ['userid' => $userid]
        );
        
        if ($result['success']) {
            // Store transaction ID
            redirect($result['checkout_url']);
        }
    }
}
```

## Error Handling

The library provides comprehensive error handling:

```php
try {
    $result = $telebirr->initializePayment($title, $amount);
    
    if (!$result['success']) {
        // Handle business logic error
        echo "Payment failed: " . $result['error'];
    }
} catch (\Exception $e) {
    // Handle system error
    error_log("Telebirr error: " . $e->getMessage());
}
```

## Logging

Logs are written to `logs/payment.log` with different levels:
-ERROR: Critical errors
-WARNING: Non-critical issues
-INFO: General information
-DEBUG: Detailed debugging information

## Security Considerations

1. **Keep your private key secure** - Never expose it in client-side code
2. **Use HTTPS** in production
3. **Validate all input** before processing
4. **Verify webhook signatures** to prevent forgery
5. **Implement rate limiting** on your endpoints
6. **Regularly update dependencies**
7. **Monitor logs** for suspicious activities

## Testing

### Test Credentials

For testing, use the provided credentials:

- Fabric App ID: ***********
- App Secret: ****************************************
- Merchant Code: **********

### Test Scenarios

1. **Successful Payment**: Use any valid amount
2. **Failed Payment**: Use 0.01 or cancel on Telebirr
3. **Timeout**: Don't complete payment within 120 minutes

## Production Deployment Checklist

- [ ] Update `.env` with production credentials
- [ ] Set `DEBUG_MODE=false`
- [ ] Enable SSL verification (`verify_ssl=true`)
- [ ] Configure proper web server
- [ ] Set up database backups
- [ ] Configure monitoring and alerts
- [ ] Test complete payment flow
- [ ] Verify webhook endpoints are accessible
- [ ] Set up error reporting
- [ ] Configure proper file permissions

## Troubleshooting

### Common Issues

1. **"Invalid signature" error**
   - Check your private key format
   - Verify request parameter ordering
   - Ensure timestamp is correct

2. **"Token expired" error**
   - Token caching issue
   - Clear session and retry
   - Check system time synchronization

3. **"Order not found" error**
   - Verify order ID format
   - Check if order was created
   - Ensure correct merchant code

4. **CURL errors**
   - Check SSL certificates
   - Verify network connectivity
   - Ensure firewall allows outbound connections

## Support

For issues and questions:
-Check the [official Telebirr documentation](https://developer.ethiotelecom.et/docs/category/h5-c2b-web-payment-integration)
-Contact Ethio Telecom support
-Open an issue on GitHub

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Credits

- Ethio Telecom for Telebirr API
- PHP community for excellent libraries
- Contributors and testers

## Changelog

### Version 1.0.0

- Initial release
- Complete C2B payment integration
- Demo application
- Database support
- Comprehensive documentation

## Summary

This complete Telebirr payment integration solution includes:

1. **Core Classes**:
   - `TelebirrPayment` - Main facade class
   - `ApplyFabricToken` - Token management
   - `CreateOrder` - Order creation
   - `QueryOrder` - Status queries
   - `RefundOrder` - Refund processing
   - `NotifyHandler` - Webhook handling
   - `SignatureHelper` - RSA signature generation/verification

2. **Configuration**:
   - Environment-based configuration
   - Database integration (optional)
   - RSA key management

3. **Demo Application**:
   - Complete payment flow testing
   - Order status queries
   - Refund processing
   - Transaction history
   - Responsive UI

4. **Features**:
   - Production-ready code
   - Comprehensive error handling
   - Logging with Monolog
   - Database transaction storage
   - Signature verification
   - Webhook handling

5. **Documentation**:
   - Installation instructions
   - Usage examples
   - Integration guide for Moodle
   - Troubleshooting tips
   - Security considerations

````markdown

