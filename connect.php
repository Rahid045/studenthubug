<?php
require_once __DIR__ . '/includes/db.php';

if (!isset($connect) || !$connect instanceof mysqli) {
    $connect = getDbConnection();
}

function eduConnectColumnExists($connect, $table, $column) {
    $result = mysqli_query($connect, "SHOW COLUMNS FROM `" . mysqli_real_escape_string($connect, $table) . "` LIKE '" . mysqli_real_escape_string($connect, $column) . "'");
    return $result && mysqli_num_rows($result) > 0;
}

function eduConnectTableExists($connect, $table) {
    $result = mysqli_query($connect, "SHOW TABLES LIKE '" . mysqli_real_escape_string($connect, $table) . "'");
    return $result && mysqli_num_rows($result) > 0;
}

function ensureEduConnectPaymentSchema($connect) {
    $resourceTable = 'resources';
    if (!eduConnectTableExists($connect, $resourceTable)) {
        return;
    }

    if (!eduConnectColumnExists($connect, $resourceTable, 'is_paid')) {
        mysqli_query($connect, "ALTER TABLE `resources` ADD COLUMN `is_paid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `downloads`");
    }

    if (!eduConnectColumnExists($connect, $resourceTable, 'price')) {
        mysqli_query($connect, "ALTER TABLE `resources` ADD COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `is_paid`");
    }

    if (!eduConnectColumnExists($connect, $resourceTable, 'currency')) {
        mysqli_query($connect, "ALTER TABLE `resources` ADD COLUMN `currency` VARCHAR(10) NOT NULL DEFAULT 'GHS' AFTER `price`");
    }

    if (!eduConnectTableExists($connect, 'resource_purchases')) {
        mysqli_query($connect, "CREATE TABLE `resource_purchases` (
            `purchase_id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `resource_id` INT NOT NULL,
            `tx_ref` VARCHAR(255) NOT NULL,
            `transaction_id` VARCHAR(255) DEFAULT NULL,
            `amount` DECIMAL(10,2) NOT NULL,
            `currency` VARCHAR(10) NOT NULL DEFAULT 'GHS',
            `provider` VARCHAR(50) DEFAULT NULL,
            `status` ENUM('pending','successful','failed','cancelled') NOT NULL DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_resource` (`user_id`,`resource_id`),
            UNIQUE KEY `unique_tx_ref` (`tx_ref`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_resource_id` (`resource_id`),
            CONSTRAINT `fk_purchases_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
            CONSTRAINT `fk_purchases_resource` FOREIGN KEY (`resource_id`) REFERENCES `resources`(`resource_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

ensureEduConnectPaymentSchema($connect);
?>