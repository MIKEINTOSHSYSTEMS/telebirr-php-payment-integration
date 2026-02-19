<?php

namespace Telebirr;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class RefundOrder
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

    public function processRefund($merchOrderId, $amount, $reason = '')
    {
        try {
            $this->logger->info("Processing refund for order: {$merchOrderId}, Amount: {$amount} ETB");

            // Get original transaction
            $transaction = $this->getTransaction($merchOrderId);
            if (!$transaction) {
                throw new \Exception("Transaction not found: " . $merchOrderId);
            }

            // Get fabric token
            $token = $this->applyToken->getToken();

            // Generate refund request number (only alphanumeric, no underscores)
            $refundRequestNo = $this->generateRefundRequestNo();

            // Prepare request
            $request = $this->buildRequest($transaction, $amount, $refundRequestNo, $reason);

            // Log the request before signing
            $this->logger->debug("Refund Request (before sign): " . json_encode($request));

            // Sign request using signRequestObject
            $request['sign'] = $this->signer->signRequestObject($request);

            $this->logger->debug("Refund Request (after sign): " . json_encode($request));

            // Send to API
            $response = $this->sendRequest($token, $request);

            $this->logger->debug("Refund Response: " . json_encode($response));

            // Process response
            if (
                isset($response['result']) && $response['result'] == 'SUCCESS' &&
                isset($response['code']) && $response['code'] == '0'
            ) {

                // Store refund record
                $this->storeRefund([
                    'refund_request_no' => $refundRequestNo,
                    'merch_order_id' => $merchOrderId,
                    'amount' => $amount,
                    'reason' => $reason,
                    'response' => $response
                ]);

                return [
                    'success' => true,
                    'refund_request_no' => $refundRequestNo,
                    'data' => $response['biz_content'] ?? [],
                    'response' => $response
                ];
            } else {
                $errorMsg = isset($response['msg']) ? $response['msg'] : (isset($response['errorMsg']) ? $response['errorMsg'] : 'Unknown error');
                return [
                    'success' => false,
                    'error' => $errorMsg
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Refund failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function buildRequest($transaction, $amount, $refundRequestNo, $reason)
    {
        $credentials = $this->config['credentials'];
        $paymentConfig = $this->config['payment'];

        return [
            'timestamp' => (string)time(),
            'nonce_str' => $this->generateNonceStr(),
            'method' => 'payment.refund',
            'version' => $paymentConfig['version'],
            'sign_type' => $paymentConfig['sign_type'],
            'biz_content' => [
                'appid' => $credentials['merchant_app_id'],
                'merch_code' => $credentials['merchant_code'],
                'merch_order_id' => $transaction['merch_order_id'],
                'refund_request_no' => $refundRequestNo,
                'refund_reason' => $reason,
                'actual_amount' => number_format(floatval($amount), 2, '.', ''),
                'trans_currency' => $paymentConfig['currency']
            ]
        ];
    }

    private function sendRequest($token, $request)
    {
        $startTime = microtime(true);
        $url = $this->config['api']['base_url'] . "/payment/v1/merchant/refund";
        $method = "POST";

        $headers = [
            "Content-Type: application/json",
            "X-APP-Key: " . $this->config['credentials']['fabric_app_id'],
            "Authorization: " . $token,
            "Accept: application/json"
        ];

        $jsonRequest = json_encode($request);
        $this->logger->debug("Sending refund to: " . $url);
        $this->logger->debug("Refund JSON: " . $jsonRequest);

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
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        $responseBody = substr($response, $headerSize);
        $duration = microtime(true) - $startTime;

        // Log the API call if apiLogger is available
        if ($this->apiLogger) {
            $this->apiLogger->log($url, $method, $request, $responseBody, $httpCode);
        }

        unset($ch);

        if ($curlError) {
            throw new \Exception("cURL Error: " . $curlError);
        }

        if ($httpCode != 200) {
            $errorData = json_decode($responseBody, true);
            if ($errorData && isset($errorData['errorMsg'])) {
                throw new \Exception("HTTP Error {$httpCode}: " . $errorData['errorMsg']);
            } else {
                throw new \Exception("HTTP Error {$httpCode}: " . ($responseBody ?: 'No response'));
            }
        }

        if (!$responseBody) {
            throw new \Exception("Empty response from server");
        }

        $result = json_decode($responseBody, true);

        if (!$result) {
            throw new \Exception("Invalid JSON response: " . substr($responseBody, 0, 200));
        }

        return $result;
    }

    private function getTransaction($merchOrderId)
    {
        if (!$this->db) {
            // Return minimal data if no database
            return ['merch_order_id' => $merchOrderId];
        }

        try {
            $sql = "SELECT * FROM transactions WHERE merch_order_id = :merch_order_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':merch_order_id' => $merchOrderId]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: ['merch_order_id' => $merchOrderId];
        } catch (\Exception $e) {
            $this->logger->error("Failed to get transaction: " . $e->getMessage());
            return ['merch_order_id' => $merchOrderId];
        }
    }

    private function storeRefund($data)
    {
        if (!$this->db) {
            return;
        }

        try {
            // Get transaction_id
            $transactionId = null;
            $sql = "SELECT id FROM transactions WHERE merch_order_id = :merch_order_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':merch_order_id' => $data['merch_order_id']]);
            $transaction = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($transaction) {
                $transactionId = $transaction['id'];
            }

            $sql = "INSERT INTO refunds (refund_request_no, transaction_id, merch_order_id, amount, reason, status, refund_data) 
                    VALUES (:refund_request_no, :transaction_id, :merch_order_id, :amount, :reason, :status, :refund_data)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':refund_request_no' => $data['refund_request_no'],
                ':transaction_id' => $transactionId,
                ':merch_order_id' => $data['merch_order_id'],
                ':amount' => $data['amount'],
                ':reason' => $data['reason'],
                ':status' => 'PENDING',
                ':refund_data' => json_encode($data['response'])
            ]);

            $this->logger->info("Refund stored: " . $data['refund_request_no']);
        } catch (\Exception $e) {
            $this->logger->error("Failed to store refund: " . $e->getMessage());
        }
    }

    /**
     * Generate refund request number - Must match pattern ^[A-Za-z0-9]*$
     * Only letters and numbers, no special characters
     */
    private function generateRefundRequestNo()
    {
        // Use timestamp + random numbers, no underscores or special chars
        return 'REF' . time() . mt_rand(1000, 9999);
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
}
