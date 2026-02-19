<?php

namespace Telebirr;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class QueryOrder
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

    public function queryOrder($merchOrderId)
    {
        try {
            $this->logger->info("Querying order: " . $merchOrderId);

            // Get fabric token
            $token = $this->applyToken->getToken();

            // Prepare request
            $request = $this->buildRequest($merchOrderId);

            // Log the request before signing
            $this->logger->debug("Query Request (before sign): " . json_encode($request));

            // Sign request using signRequestObject
            $request['sign'] = $this->signer->signRequestObject($request);

            $this->logger->debug("Query Request (after sign): " . json_encode($request));

            // Send to API
            $response = $this->sendRequest($token, $request);

            $this->logger->debug("Query Response: " . json_encode($response));

            // Process response
            if (
                isset($response['result']) && $response['result'] == 'SUCCESS' &&
                isset($response['code']) && $response['code'] == '0'
            ) {

                // Update transaction in database
                if (isset($response['biz_content'])) {
                    $this->updateTransaction($merchOrderId, $response['biz_content']);
                }

                return [
                    'success' => true,
                    'data' => $response['biz_content'] ?? [],
                    'response' => $response
                ];
            } else {
                $errorMsg = isset($response['msg']) ? $response['msg'] : (isset($response['errorMsg']) ? $response['errorMsg'] : 'Query failed');
                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'response' => $response
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Order query failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function buildRequest($merchOrderId)
    {
        $credentials = $this->config['credentials'];
        $paymentConfig = $this->config['payment'];

        return [
            'timestamp' => (string)time(),
            'nonce_str' => $this->generateNonceStr(),
            'method' => 'payment.queryorder',
            'version' => $paymentConfig['version'],
            'sign_type' => $paymentConfig['sign_type'],
            'biz_content' => [
                'appid' => $credentials['merchant_app_id'],
                'merch_code' => $credentials['merchant_code'],
                'merch_order_id' => $merchOrderId
            ]
        ];
    }

    private function sendRequest($token, $request)
    {
        $startTime = microtime(true);
        $url = $this->config['api']['base_url'] . "/payment/v1/merchant/queryOrder";
        $method = "POST";

        $headers = [
            "Content-Type: application/json",
            "X-APP-Key: " . $this->config['credentials']['fabric_app_id'],
            "Authorization: " . $token,
            "Accept: application/json"
        ];

        $jsonRequest = json_encode($request);
        $this->logger->debug("Sending query to: " . $url);
        $this->logger->debug("Query JSON: " . $jsonRequest);

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

    private function updateTransaction($merchOrderId, $bizContent)
    {
        if (!$this->db) {
            return;
        }

        try {
            // Map the status correctly
            $status = $this->mapStatus($bizContent['order_status'] ?? 'UNKNOWN');
            $tradeStatus = $bizContent['order_status'] ?? null;
            $paymentOrderId = $bizContent['payment_order_id'] ?? null;
            $transId = $bizContent['trans_id'] ?? null;

            $completedAt = null;
            if (isset($bizContent['trans_time']) && !empty($bizContent['trans_time'])) {
                $completedAt = date('Y-m-d H:i:s', strtotime($bizContent['trans_time']));
            }

            $sql = "UPDATE transactions SET 
                    payment_order_id = :payment_order_id,
                    trans_id = :trans_id,
                    status = :status,
                    trade_status = :trade_status,
                    completed_at = :completed_at
                    WHERE merch_order_id = :merch_order_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':merch_order_id' => $merchOrderId,
                ':payment_order_id' => $paymentOrderId,
                ':trans_id' => $transId,
                ':status' => $status,
                ':trade_status' => $tradeStatus,
                ':completed_at' => $completedAt
            ]);

            $this->logger->info("Transaction updated: " . $merchOrderId . " with status: " . $status);
        } catch (\Exception $e) {
            $this->logger->error("Failed to update transaction: " . $e->getMessage());
        }
    }

    private function mapStatus($orderStatus)
    {
        $map = [
            'PAY_SUCCESS' => 'COMPLETED',
            'PAY_FAILED' => 'FAILED',
            'WAIT_PAY' => 'PENDING',
            'ORDER_CLOSED' => 'CLOSED',
            'PAYING' => 'PROCESSING',
            'ACCEPTED' => 'ACCEPTED',
            'REFUNDING' => 'REFUNDING',
            'REFUND_SUCCESS' => 'REFUNDED',
            'REFUND_FAILED' => 'REFUND_FAILED'
        ];

        return $map[$orderStatus] ?? 'UNKNOWN';
    }
}
