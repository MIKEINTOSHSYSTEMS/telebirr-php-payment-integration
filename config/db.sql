-- Create database
CREATE DATABASE IF NOT EXISTS telebirr_payments
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE telebirr_payments;

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merch_order_id VARCHAR(64) NOT NULL UNIQUE,
    payment_order_id VARCHAR(64),
    trans_id VARCHAR(64),
    appid VARCHAR(32),
    merch_code VARCHAR(16),
    title VARCHAR(512),
    amount DECIMAL(20,2),
    currency VARCHAR(3) DEFAULT 'ETB',
    status VARCHAR(20) DEFAULT 'PENDING',
    trade_status VARCHAR(20),
    prepay_id VARCHAR(128),
    notify_data TEXT,
    customer_phone VARCHAR(20),
    payment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_merch_order (merch_order_id),
    INDEX idx_payment_order (payment_order_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refunds table
CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    refund_request_no VARCHAR(64) NOT NULL UNIQUE,
    transaction_id INT,
    merch_order_id VARCHAR(64),
    payment_order_id VARCHAR(64),
    trans_order_id VARCHAR(64),
    refund_order_id VARCHAR(64),
    amount DECIMAL(20,2),
    currency VARCHAR(3) DEFAULT 'ETB',
    reason TEXT,
    status VARCHAR(20) DEFAULT 'PENDING',
    refund_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    INDEX idx_refund_request (refund_request_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logs table
CREATE TABLE IF NOT EXISTS api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(255),
    method VARCHAR(10),
    request_data TEXT,
    response_data TEXT,
    status_code INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_endpoint (endpoint),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;