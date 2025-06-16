<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();

    $sql = "CREATE TABLE IF NOT EXISTS `admin_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `admin_id` int(11) NOT NULL,
        `action` varchar(50) NOT NULL,
        `details` text NOT NULL,
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `admin_id` (`admin_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $conn->exec($sql);
    echo "Admin logs table created successfully!";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
} 