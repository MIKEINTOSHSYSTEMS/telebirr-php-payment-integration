<?php

namespace Telebirr;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class NotifyHandler
{
    private $config;
    private $verifier;
    private $logger;
    private $db;

    public function __construct($config, $verifier, $logger = null, $db = null)
    {
        $this->config = $config;
        $this->verifier = $verifier;
        $this->logger = $logger ?: $this->initLogger($config['logging']);
        $this->db = $db;
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

    public function handleNotification($postData)
    {
        try {
            $this->logger->info("Received payment notification");
            $this->logger->debug("Notification data: " . json_encode($postData));

            // Validate required fields
            $requiredFields = ['merch_order_id', 'payment_order_id', 'trade_status', 'sign'];
            foreach ($requiredFields as $field) {
                if (!isset($postData[$field])) {
                    throw new \Exception("Missing required field: " . $field);
                }
            }

            // Verify signature using SignatureVerifier
            if (!$this->verifier->verify($postData)) {
                throw new \Exception("Invalid signature");
            }

            // Process based on trade status
            $tradeStatus = $postData['trade_status'];
            $merchOrderId = $postData['merch_order_id'];

            $this->logger->info("Processing notification for order: {$merchOrderId}, Status: {$tradeStatus}");

            // Update transaction in database
            $this->updateTransaction($postData);

            // Handle different statuses
            switch ($tradeStatus) {
                case 'Completed':
                case 'PAY_SUCCESS':
                    $this->handleSuccessPayment($postData);
                    break;

                case 'Failure':
                case 'PAY_FAILED':
                    $this->handleFailedPayment($postData);
                    break;

                case 'Paying':
                case 'PAYING':
                    $this->handleProcessingPayment($postData);
                    break;

                case 'Expired':
                case 'ORDER_CLOSED':
                    $this->handleExpiredPayment($postData);
                    break;
            }

            // Return success response to Telebirr
            return [
                'success' => true,
                'message' => 'Notification processed successfully',
                'http_code' => 200
            ];
        } catch (\Exception $e) {
            $this->logger->error("Notification handling failed: " . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'http_code' => 400
            ];
        }
    }

    /**
     * Update transaction in database
     */
    private function updateTransaction($data)
    {
        if (!$this->db) {
            return;
        }

        try {
            $sql = "UPDATE transactions SET 
                    payment_order_id = :payment_order_id,
                    trans_id = :trans_id,
                    status = :status,
                    trade_status = :trade_status,
                    completed_at = :completed_at,
                    notify_data = :notify_data
                    WHERE merch_order_id = :merch_order_id";

            $completedAt = null;
            if (isset($data['trans_end_time'])) {
                $completedAt = date('Y-m-d H:i:s', substr($data['trans_end_time'], 0, 10));
            } elseif (isset($data['trans_time'])) {
                $completedAt = date('Y-m-d H:i:s', strtotime($data['trans_time']));
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':merch_order_id' => $data['merch_order_id'],
                ':payment_order_id' => $data['payment_order_id'] ?? null,
                ':trans_id' => $data['trans_id'] ?? null,
                ':status' => $this->mapStatus($data['trade_status']),
                ':trade_status' => $data['trade_status'],
                ':completed_at' => $completedAt,
                ':notify_data' => json_encode($data)
            ]);

            $this->logger->info("Transaction updated from notification: " . $data['merch_order_id']);
        } catch (\Exception $e) {
            $this->logger->error("Failed to update transaction from notification: " . $e->getMessage());
        }
    }

    /**
     * Map Telebirr status to local status
     */
    private function mapStatus($tradeStatus)
    {
        $map = [
            'Completed' => 'COMPLETED',
            'PAY_SUCCESS' => 'COMPLETED',
            'Failure' => 'FAILED',
            'PAY_FAILED' => 'FAILED',
            'Paying' => 'PROCESSING',
            'PAYING' => 'PROCESSING',
            'Expired' => 'EXPIRED',
            'ORDER_CLOSED' => 'CLOSED'
        ];

        return $map[$tradeStatus] ?? 'UNKNOWN';
    }

    /**
     * Handle successful payment
     */
    private function handleSuccessPayment($data)
    {
        $this->logger->info("Payment successful for order: " . $data['merch_order_id']);

        // TODO: Implement your business logic here
        // - Send confirmation email
        // - Update inventory
        // - Generate receipt
        // - Notify customer
    }

    /**
     * Handle failed payment
     */
    private function handleFailedPayment($data)
    {
        $this->logger->warning("Payment failed for order: " . $data['merch_order_id']);

        // TODO: Implement your business logic here
        // - Notify customer
        // - Release holds
    }

    /**
     * Handle processing payment
     */
    private function handleProcessingPayment($data)
    {
        $this->logger->info("Payment processing for order: " . $data['merch_order_id']);

        // TODO: Implement your business logic here
    }

    /**
     * Handle expired payment
     */
    private function handleExpiredPayment($data)
    {
        $this->logger->info("Payment expired for order: " . $data['merch_order_id']);

        // TODO: Implement your business logic here
        // - Cancel order
        // - Release inventory
    }
}
