<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();

    // First check if tournament_days table exists, if not create it
    $conn->exec("
    CREATE TABLE IF NOT EXISTS `tournament_days` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tournament_id` int(11) NOT NULL,
        `day_number` int(11) NOT NULL,
        `date` date NOT NULL,
        `status` enum('upcoming','in_progress','completed') NOT NULL DEFAULT 'upcoming',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `tournament_id` (`tournament_id`),
        CONSTRAINT `tournament_days_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Create tournament_rounds table
    $conn->exec("
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
    ");

    // Create round_teams table
    $conn->exec("
    CREATE TABLE IF NOT EXISTS `round_teams` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `round_id` int(11) NOT NULL,
        `team_id` int(11) NOT NULL,
        `placement` int(11) DEFAULT NULL,
        `kills` int(11) NOT NULL DEFAULT 0,
        `kill_points` int(11) NOT NULL DEFAULT 0,
        `placement_points` int(11) NOT NULL DEFAULT 0,
        `bonus_points` int(11) NOT NULL DEFAULT 0,
        `total_points` int(11) NOT NULL DEFAULT 0,
        `status` enum('selected','eliminated','qualified') NOT NULL DEFAULT 'selected',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `round_team_unique` (`round_id`, `team_id`),
        KEY `team_id` (`team_id`),
        CONSTRAINT `round_teams_ibfk_1` FOREIGN KEY (`round_id`) REFERENCES `tournament_rounds` (`id`) ON DELETE CASCADE,
        CONSTRAINT `round_teams_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Tables created/updated successfully! The tournament system is ready to use.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 