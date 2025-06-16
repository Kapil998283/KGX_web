<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();

    // Drop and recreate the tournament_rounds table
    $sql = "
    DROP TABLE IF EXISTS `tournament_rounds`;
    CREATE TABLE IF NOT EXISTS `tournament_rounds` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tournament_id` int(11) NOT NULL,
        `day_id` int(11) DEFAULT NULL,
        `round_number` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `description` text,
        `start_time` datetime NOT NULL,
        `teams_count` int(11) NOT NULL DEFAULT 0,
        `qualifying_teams` int(11) NOT NULL DEFAULT 0,
        `round_format` enum('elimination', 'points', 'bracket') NOT NULL DEFAULT 'points',
        `map_name` varchar(255),
        `special_rules` text,
        `kill_points` int(11) NOT NULL DEFAULT 2 COMMENT 'Points per kill',
        `placement_points` text COMMENT 'JSON array of points for each placement',
        `qualification_points` int(11) NOT NULL DEFAULT 10 COMMENT 'Points for qualifying to next round',
        `status` enum('upcoming', 'in_progress', 'completed') NOT NULL DEFAULT 'upcoming',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `tournament_id` (`tournament_id`),
        KEY `day_id` (`day_id`),
        CONSTRAINT `tournament_rounds_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
        CONSTRAINT `tournament_rounds_ibfk_2` FOREIGN KEY (`day_id`) REFERENCES `tournament_days` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $conn->exec($sql);
    echo "Tournament rounds table updated successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 