-- Create tournament_days table if not exists
CREATE TABLE IF NOT EXISTS `tournament_days` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tournament_id` int(11) NOT NULL,
    `day_number` int(11) NOT NULL,
    `date` date NOT NULL,
    `status` enum('upcoming', 'in_progress', 'completed') NOT NULL DEFAULT 'upcoming',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `tournament_id` (`tournament_id`),
    CONSTRAINT `tournament_days_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create tournament_rounds table if not exists
CREATE TABLE IF NOT EXISTS `tournament_rounds` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tournament_id` int(11) NOT NULL,
    `day_id` int(11) DEFAULT NULL,
    `round_number` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `description` text,
    `start_time` time NOT NULL,
    `end_time` time NOT NULL,
    `status` enum('upcoming', 'in_progress', 'completed') NOT NULL DEFAULT 'upcoming',
    `players_count` int(11) NOT NULL,
    `qualifying_players` int(11) NOT NULL,
    `round_format` enum('elimination', 'points', 'bracket') NOT NULL,
    `map_name` varchar(255) NOT NULL,
    `special_rules` text,
    `points_system` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `tournament_id` (`tournament_id`),
    KEY `day_id` (`day_id`),
    CONSTRAINT `tournament_rounds_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `tournament_rounds_ibfk_2` FOREIGN KEY (`day_id`) REFERENCES `tournament_days` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create round_teams table if not exists
CREATE TABLE IF NOT EXISTS `round_teams` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `round_id` int(11) NOT NULL,
    `team_id` int(11) NOT NULL,
    `status` enum('selected', 'eliminated', 'qualified') NOT NULL DEFAULT 'selected',
    `points` int(11) DEFAULT 0,
    `rank` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `round_team` (`round_id`, `team_id`),
    KEY `team_id` (`team_id`),
    CONSTRAINT `round_teams_ibfk_1` FOREIGN KEY (`round_id`) REFERENCES `tournament_rounds` (`id`) ON DELETE CASCADE,
    CONSTRAINT `round_teams_ibfk_2` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 