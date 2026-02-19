<?php

namespace Telebirr;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CreateOrder
{
    private $config;
    private $applyToken;
    private $signer;
    private $logger;
    private $db;
    private $apiLogger;

    public function __construct($config, $applyToken, $signer, $logger = null, $db = null, $apiLogger = null)
    {
        $this->config = $config;
        $this->applyToken = $applyToken;
        $this->signer = $signer;
        $this->logger = $logger ?: $this->initLogger($config['logging']);
        $this->db = $db;
        $this->apiLogger = $apiLogger;
    }

    private function initLogger($loggingConfig)
    {
        $log = new Logger('telebirr');
        $log->pushHandler(new StreamHandler(
            $loggingConfig['path'],
            Logger::DEBUG
        ));
        return $log;
    }

    public function createOrder($title, $amount, $additionalData = [])
    {
        try {
            $this->logger->info("Creating order: {$title} - Amount: {$amount} ETB");

            // Validate amount
            if ($amount <= 0) {
                throw new \Exception("Invalid amount: {$amount}");
            }

            // Get fabric token
            try {
                $token = $this->applyToken->getToken();
                $this->logger->debug("Token obtained successfully");
            } catch (\Exception $e) {
                $this->logger->error("Failed to obtain token: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Failed to authenticate with Telebirr: ' . $e->getMessage()
                ];
            }

            // Generate merchant order ID (only numbers)
            $merchOrderId = $this->generateMerchantOrderId();

            // Prepare request
            $request = $this->buildRequest($title, $amount, $merchOrderId, $additionalData);

            // Sign request using Signer
            try {
                $request['sign'] = $this->signer->signRequestObject($request);
            } catch (\Exception $e) {
                $this->logger->error("Failed to sign request: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Failed to sign request: ' . $e->getMessage()
                ];
            }

            $this->logger->debug("Request: " . json_encode($request));

            // Send to API
            try {
                $response = $this->sendRequest($token, $request);
            } catch (\Exception $e) {
                $this->logger->error("API request failed: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Telebirr API error: ' . $e->getMessage()
                ];
            }

            $this->logger->debug("Response: " . json_encode($response));

            // Process response
            if (
                isset($response['result']) && $response['result'] == 'SUCCESS' &&
                isset($response['code']) && $response['code'] == '0'
            ) {

                if (!isset($response['biz_content']['prepay_id'])) {
                    return [
                        'success' => false,
                        'error' => 'No prepay_id in response'
                    ];
                }

                $prepayId = $response['biz_content']['prepay_id'];

                // Store transaction in database
                $this->storeTransaction([
                    'merch_order_id' => $merchOrderId,
                    'prepay_id' => $prepayId,
                    'title' => $title,
                    'amount' => $amount,
                    'status' => 'PENDING',
                    'additional_data' => $additionalData
                ]);

                return [
                    'success' => true,
                    'merch_order_id' => $merchOrderId,
                    'prepay_id' => $prepayId,
                    'response' => $response
                ];
            } else {
                $errorMsg = isset($response['msg']) ? $response['msg'] : (isset($response['errorMsg']) ? $response['errorMsg'] : 'Unknown error');
                return [
                    'success' => false,
                    'error' => "Order creation failed: {$errorMsg}"
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Order creation failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function buildRequest($title, $amount, $merchOrderId, $additionalData = [])
    {
        $paymentConfig = $this->config['payment'];
        $callbacks = $this->config['callbacks'];
        $credentials = $this->config['credentials'];

        $timestamp = $this->generateTimestamp();
        $nonceStr = $this->generateNonceStr();

        // Ensure amount is formatted correctly (2 decimal places)
        $formattedAmount = number_format(floatval($amount), 2, '.', '');

        // Clean title - ONLY allow alphanumeric characters and spaces
        $cleanTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
        $cleanTitle = preg_replace('/\s+/', ' ', $cleanTitle);
        $cleanTitle = trim($cleanTitle);
        $cleanTitle = substr($cleanTitle, 0, 100);

        if (empty($cleanTitle)) {
            $cleanTitle = 'Product Payment';
        }

        $this->logger->debug("Original title: " . $title);
        $this->logger->debug("Cleaned title: " . $cleanTitle);

        // Base biz_content with all required fields
        $bizContent = [
            'notify_url' => $callbacks['notify_url'],
            'redirect_url' => $callbacks['redirect_url'],
            'appid' => $credentials['merchant_app_id'],
            'merch_code' => $credentials['merchant_code'],
            'merch_order_id' => $merchOrderId,
            'trade_type' => $paymentConfig['trade_type'],
            'title' => $cleanTitle,
            'total_amount' => $formattedAmount,
            'trans_currency' => $paymentConfig['currency'],
            'timeout_express' => $paymentConfig['timeout_express'],
            'business_type' => $paymentConfig['business_type']
        ];

        // Add payee info (required by Telebirr)
        $bizContent['payee_identifier'] = $credentials['merchant_code'];
        $bizContent['payee_identifier_type'] = '04';
        $bizContent['payee_type'] = '5000';

        // Add optional fields if they exist
        if (!empty($additionalData)) {
            if (isset($additionalData['callback_info'])) {
                $callbackInfo = preg_replace('/[^a-zA-Z0-9\s]/', '', $additionalData['callback_info']);
                $bizContent['callback_info'] = substr($callbackInfo, 0, 255);
            }
            if (isset($additionalData['customer_phone']) && !empty($additionalData['customer_phone'])) {
                $bizContent['customer_phone'] = preg_replace('/[^0-9]/', '', $additionalData['customer_phone']);
            }
        }

        // Build complete request WITH SIGN_TYPE at root level
        $request = [
            'timestamp' => $timestamp,
            'nonce_str' => $nonceStr,
            'method' => 'payment.preorder',
            'version' => $paymentConfig['version'],
            'sign_type' => $paymentConfig['sign_type'],
            'biz_content' => $bizContent
        ];

        return $request;
    }

    private function sendRequest($token, $request)
    {
        $startTime = microtime(true);
        $url = $this->config['api']['base_url'] . "/payment/v1/merchant/preOrder";
        $method = "POST";

        $headers = [
            "Content-Type: application/json",
            "X-APP-Key: " . $this->config['credentials']['fabric_app_id'],
            "Authorization: " . $token,
            "Accept: application/json"
        ];

        $jsonRequest = json_encode($request);
        $this->logger->debug("Sending request to: " . $url);
        $this->logger->debug("Request JSON: " . $jsonRequest);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $jsonRequest,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => $this->config['api']['verify_ssl'] ?? false,
            CURLOPT_SSL_VERIFYHOST => ($this->config['api']['verify_ssl'] ?? false) ? 2 : 0,
            CURLOPT_TIMEOUT => $this->config['api']['timeout'] ?? 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        $duration = microtime(true) - $startTime;

        // Log the API call if apiLogger is available
        if ($this->apiLogger) {
            $this->apiLogger->log($url, $method, $request, $response, $httpCode);
        }

        unset($ch);

        if ($curlError) {
            throw new \Exception("cURL Error: " . $curlError);
        }

        if ($httpCode != 200) {
            $errorData = json_decode($response, true);
            if ($errorData && isset($errorData['errorMsg'])) {
                throw new \Exception("HTTP Error {$httpCode}: " . $errorData['errorMsg']);
            } else {
                throw new \Exception("HTTP Error {$httpCode}: " . ($response ?: 'No response'));
            }
        }

        if (!$response) {
            throw new \Exception("Empty response from server");
        }

        $result = json_decode($response, true);

        if (!$result) {
            throw new \Exception("Invalid JSON response: " . substr($response, 0, 200));
        }

        return $result;
    }

    public function generateCheckoutUrl($prepayId)
    {
        $credentials = $this->config['credentials'];
        $paymentConfig = $this->config['payment'];

        // Build raw request parameters
        $params = [
            'appid' => $credentials['merchant_app_id'],
            'merch_code' => $credentials['merchant_code'],
            'nonce_str' => $this->generateNonceStr(),
            'prepay_id' => $prepayId,
            'timestamp' => $this->generateTimestamp()
        ];

        // Generate signature using Signer
        $sign = $this->signer->generateRawRequestSignature($params);

        // Build raw request string
        $rawRequest = [];
        foreach ($params as $key => $value) {
            $rawRequest[] = $key . '=' . urlencode($value);
        }
        $rawRequest[] = 'sign=' . urlencode($sign);
        $rawRequest[] = 'sign_type=' . urlencode($paymentConfig['sign_type']);

        $rawRequestStr = implode('&', $rawRequest);

        // Build complete checkout URL
        $webBaseUrl = rtrim($this->config['api']['web_base_url'], '?');
        if (strpos($webBaseUrl, '?') === false) {
            $webBaseUrl .= '?';
        }

        $checkoutUrl = $webBaseUrl . $rawRequestStr . "&version=1.0&trade_type=Checkout";

        return $checkoutUrl;
    }

    private function generateMerchantOrderId()
    {
        return time() . mt_rand(1000, 9999);
    }

    private function generateTimestamp()
    {
        return (string)time();
    }

    private function generateNonceStr($length = 32)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    private function storeTransaction($data)
    {
        if (!$this->db) {
            return;
        }

        try {
            $credentials = $this->config['credentials'];

            $sql = "INSERT INTO transactions 
                    (merch_order_id, prepay_id, title, amount, currency, status, 
                    appid, merch_code, customer_phone, notify_data) 
                    VALUES 
                    (:merch_order_id, :prepay_id, :title, :amount, :currency, :status,
                    :appid, :merch_code, :customer_phone, :notify_data)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':merch_order_id' => $data['merch_order_id'],
                ':prepay_id' => $data['prepay_id'],
                ':title' => $data['title'],
                ':amount' => $data['amount'],
                ':currency' => 'ETB',
                ':status' => 'PENDING',
                ':appid' => $credentials['merchant_app_id'],
                ':merch_code' => $credentials['merchant_code'],
                ':customer_phone' => $data['additional_data']['customer_phone'] ?? null,
                ':notify_data' => json_encode($data['additional_data'])
            ]);

            $this->logger->info("Transaction stored: " . $data['merch_order_id']);
        } catch (\Exception $e) {
            $this->logger->error("Failed to store transaction: " . $e->getMessage());
        }
    }
}
