-- Create database
CREATE DATABASE IF NOT EXISTS KGX_Esports;
USE KGX_Esports;

-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Admin activity log table
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    profile_image VARCHAR(2083) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    role ENUM('user', 'admin', 'organizer') DEFAULT 'user',
    ticket_balance INT DEFAULT 0,
    phone VARCHAR(20) DEFAULT NULL COMMENT 'User phone number with country code',
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Profile images table
CREATE TABLE IF NOT EXISTS profile_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(2083) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Team banners table
CREATE TABLE IF NOT EXISTS team_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    name VARCHAR(50) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    logo VARCHAR(255),
    banner_id INT DEFAULT 1,
    description TEXT,
    language VARCHAR(50),
    max_members INT DEFAULT 5,
    current_members INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    captain_id INT,
    total_score BIGINT DEFAULT 0,
    FOREIGN KEY (captain_id) REFERENCES users(id),
    FOREIGN KEY (banner_id) REFERENCES team_banners(id),
    INDEX idx_teams_is_active (is_active),
    INDEX idx_teams_captain (captain_id),
    INDEX idx_teams_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Team members table
CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('captain', 'member') DEFAULT 'member',
    status ENUM('active', 'pending', 'rejected') DEFAULT 'active',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team_user (team_id, user_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_team_members_status (status),
    INDEX idx_team_members_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Team join requests table
CREATE TABLE IF NOT EXISTS team_join_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_request (team_id, user_id, status),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_join_requests_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tournaments table
CREATE TABLE IF NOT EXISTS tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    game_name ENUM('BGMI', 'PUBG', 'Free Fire', 'Call of Duty Mobile') NOT NULL,
    description TEXT,
    banner_image VARCHAR(2083) DEFAULT NULL,
    prize_pool DECIMAL(10,2) DEFAULT 0.00,
    prize_currency ENUM('USD', 'INR') DEFAULT 'USD',
    entry_fee INT DEFAULT 0,
    max_teams INT DEFAULT 100,
    current_teams INT DEFAULT 0,
    mode ENUM('Solo', 'Duo', 'Squad', 'Team') DEFAULT 'Squad',
    format ENUM('Elimination', 'Round Robin', 'Swiss') DEFAULT 'Elimination',
    match_type ENUM('Single', 'Best of 3', 'Best of 5') DEFAULT 'Single',
    registration_open_date DATE NOT NULL,
    registration_close_date DATE NOT NULL,
    playing_start_date DATE NOT NULL,
    finish_date DATE NOT NULL,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    registration_phase ENUM('open', 'closed', 'playing', 'finished') DEFAULT 'closed',
    rules TEXT,
    created_by INT,
    allow_waitlist TINYINT(1) DEFAULT 0,
    waitlist_limit INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tournament days table
CREATE TABLE IF NOT EXISTS tournament_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    day_number INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('upcoming', 'in_progress', 'completed') NOT NULL DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tournament rounds table
CREATE TABLE IF NOT EXISTS tournament_rounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    day_id INT DEFAULT NULL,
    round_number INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_time DATETIME NOT NULL,
    teams_count INT NOT NULL DEFAULT 0,
    qualifying_teams INT NOT NULL DEFAULT 0,
    round_format ENUM('elimination', 'points', 'bracket') NOT NULL DEFAULT 'points',
    map_name VARCHAR(255),
    special_rules TEXT,
    kill_points INT NOT NULL DEFAULT 2 COMMENT 'Points per kill',
    placement_points TEXT COMMENT 'JSON array of points for each placement',
    qualification_points INT NOT NULL DEFAULT 10 COMMENT 'Points for qualifying to next round',
    status ENUM('upcoming', 'in_progress', 'completed') NOT NULL DEFAULT 'upcoming',
    room_code VARCHAR(50) DEFAULT NULL,
    room_password VARCHAR(50) DEFAULT NULL,
    room_details_added_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (day_id) REFERENCES tournament_days(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Round teams table
CREATE TABLE IF NOT EXISTS round_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    round_id INT NOT NULL,
    team_id INT NOT NULL,
    status ENUM('selected', 'eliminated', 'qualified') NOT NULL DEFAULT 'selected',
    placement INT DEFAULT NULL COMMENT 'Final placement in the round',
    kills INT DEFAULT 0 COMMENT 'Total kills in the round',
    kill_points INT DEFAULT 0 COMMENT 'Points from kills',
    placement_points INT DEFAULT 0 COMMENT 'Points from placement',
    bonus_points INT DEFAULT 0 COMMENT 'Additional points (qualification, etc)',
    total_points INT DEFAULT 0 COMMENT 'Total points for this round',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY round_team (round_id, team_id),
    FOREIGN KEY (round_id) REFERENCES tournament_rounds(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tournament registrations table
CREATE TABLE IF NOT EXISTS tournament_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    team_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    INDEX idx_tournament_team (tournament_id, team_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tournament winners table
CREATE TABLE IF NOT EXISTS tournament_winners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    team_id INT NOT NULL,
    position INT NOT NULL,
    prize_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    UNIQUE KEY unique_tournament_winner (tournament_id, position),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tournament player history table
CREATE TABLE IF NOT EXISTS tournament_player_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    registration_date DATETIME NOT NULL,
    rounds_played INT DEFAULT 0,
    total_kills INT DEFAULT 0,
    total_points INT DEFAULT 0,
    best_placement INT DEFAULT NULL,
    final_position INT DEFAULT NULL,
    prize_amount DECIMAL(10,2) DEFAULT 0.00,
    prize_currency VARCHAR(20) DEFAULT NULL,
    website_currency_earned INT DEFAULT 0,
    website_currency_type VARCHAR(20) DEFAULT NULL,
    status ENUM('registered', 'playing', 'completed', 'eliminated') DEFAULT 'registered',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tournament_player (tournament_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Trigger to create tournament history records
DELIMITER //
CREATE TRIGGER after_tournament_registration
AFTER INSERT ON tournament_registrations
FOR EACH ROW
BEGIN
    -- Insert history records for all active team members
    INSERT INTO tournament_player_history (
        tournament_id,
        user_id,
        team_id,
        registration_date,
        status
    )
    SELECT 
        NEW.tournament_id,
        tm.user_id,
        NEW.team_id,
        NEW.registration_date,
        CASE 
            WHEN NEW.status = 'approved' THEN 'registered'
            ELSE 'pending'
        END
    FROM team_members tm
    WHERE tm.team_id = NEW.team_id
    AND tm.status = 'active';
END //
DELIMITER ;

-- Tournament waitlist table
CREATE TABLE IF NOT EXISTS tournament_waitlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    user_id INT NOT NULL,
    join_date DATETIME NOT NULL,
    status ENUM('waiting', 'promoted', 'expired') DEFAULT 'waiting',
    UNIQUE KEY unique_tournament_user (tournament_id, user_id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Video categories table
CREATE TABLE IF NOT EXISTS video_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Live streams table
CREATE TABLE IF NOT EXISTS live_streams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT DEFAULT NULL,
    round_id INT DEFAULT NULL,
    stream_title VARCHAR(255) NOT NULL,
    stream_link VARCHAR(2083) NOT NULL,
    streamer_name VARCHAR(100) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('scheduled', 'live', 'completed', 'cancelled') DEFAULT 'scheduled',
    video_type ENUM('tournament', 'earning') DEFAULT 'tournament',
    category_id INT DEFAULT NULL,
    coin_reward INT DEFAULT 50,
    minimum_watch_duration INT DEFAULT 300,
    thumbnail_url VARCHAR(2083) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE SET NULL,
    FOREIGN KEY (round_id) REFERENCES tournament_rounds(id) ON DELETE SET NULL,
    INDEX idx_video_type (video_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Stream rewards table
CREATE TABLE IF NOT EXISTS stream_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    stream_id INT NOT NULL,
    coins_earned INT NOT NULL,
    watch_duration INT NOT NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reward (user_id, stream_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (stream_id) REFERENCES live_streams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Video watch history table
CREATE TABLE IF NOT EXISTS video_watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    watch_duration INT NOT NULL,
    watched_at DATETIME NOT NULL,
    coins_earned INT DEFAULT 0,
    UNIQUE KEY unique_watch (user_id, video_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES live_streams(id) ON DELETE CASCADE,
    INDEX idx_user_watches (user_id),
    INDEX idx_video_watches (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Games table
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name ENUM('BGMI', 'PUBG', 'Free Fire', 'Call of Duty Mobile') NOT NULL,
    image_url VARCHAR(2083) DEFAULT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_game_status (status),
    INDEX idx_game_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default games
INSERT INTO games (name, status) VALUES
('BGMI', 'active'),
('PUBG', 'active'),
('Free Fire', 'active'),
('Call of Duty Mobile', 'active');

-- Matches table
CREATE TABLE IF NOT EXISTS matches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    game_id INT NOT NULL,
    tournament_id INT,
    team1_id INT,
    team2_id INT,
    match_type VARCHAR(50) NOT NULL,
    match_date DATETIME NOT NULL,
    entry_type VARCHAR(20) NOT NULL,
    entry_fee INT DEFAULT 0,
    prize_type VARCHAR(20) DEFAULT 'INR',
    prize_pool DECIMAL(10,2) DEFAULT 0,
    prize_distribution VARCHAR(20) DEFAULT 'single' COMMENT 'single, top3, or top5',
    website_currency_type VARCHAR(20) DEFAULT NULL COMMENT 'coins or tickets',
    website_currency_amount INT DEFAULT 0 COMMENT 'amount in website currency',
    coins_per_kill INT DEFAULT 0 COMMENT 'Coins awarded per kill',
    max_participants INT NOT NULL,
    map_name VARCHAR(50),
    room_code VARCHAR(50),
    room_password VARCHAR(50),
    status VARCHAR(20) DEFAULT 'upcoming',
    score_team1 INT DEFAULT 0,
    score_team2 INT DEFAULT 0,
    winner_id INT,
    winner_user_id INT COMMENT 'For individual match winners',
    started_at DATETIME,
    completed_at DATETIME,
    room_details_added_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
    FOREIGN KEY (team1_id) REFERENCES teams(id),
    FOREIGN KEY (team2_id) REFERENCES teams(id),
    FOREIGN KEY (winner_id) REFERENCES teams(id),
    FOREIGN KEY (winner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Match results table
CREATE TABLE IF NOT EXISTS match_results (
    match_id INT,
    team_id INT,
    score INT DEFAULT NULL,
    prize_amount DECIMAL(10,2) DEFAULT NULL,
    prize_currency VARCHAR(20) DEFAULT NULL,
    PRIMARY KEY (match_id, team_id),
    FOREIGN KEY (match_id) REFERENCES matches(id),
    FOREIGN KEY (team_id) REFERENCES teams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User coins table
CREATE TABLE IF NOT EXISTS user_coins (
    user_id INT PRIMARY KEY,
    coins INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User tickets table
CREATE TABLE IF NOT EXISTS user_tickets (
    user_id INT PRIMARY KEY,
    tickets INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    related_id INT DEFAULT NULL,
    related_type VARCHAR(50) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_deleted_at (deleted_at),
    INDEX idx_is_read (is_read),
    INDEX idx_related (related_id, related_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Hero settings table
CREATE TABLE IF NOT EXISTS hero_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subtitle VARCHAR(100) NOT NULL,
    title VARCHAR(100) NOT NULL,
    primary_btn_text VARCHAR(50) NOT NULL,
    primary_btn_icon VARCHAR(50) NOT NULL,
    secondary_btn_text VARCHAR(50) NOT NULL,
    secondary_btn_icon VARCHAR(50) NOT NULL,
    secondary_btn_url VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Password resets table
CREATE TABLE IF NOT EXISTS password_resets (
    email VARCHAR(100) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expiry DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email, token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Redeemable items table
CREATE TABLE IF NOT EXISTS redeemable_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    coin_cost INT NOT NULL,
    image_url VARCHAR(255),
    stock INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT TRUE,
    is_unlimited TINYINT(1) DEFAULT FALSE,
    requires_approval TINYINT(1) DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Redemption history table
CREATE TABLE IF NOT EXISTS redemption_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    item_id INT,
    coins_spent INT NOT NULL,
    redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (item_id) REFERENCES redeemable_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table for storing device tokens for push notifications
CREATE TABLE IF NOT EXISTS device_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Match Participants table
CREATE TABLE IF NOT EXISTS match_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    user_id INT NOT NULL,
    team_id INT,
    join_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('joined', 'disqualified', 'winner') DEFAULT 'joined',
    position INT DEFAULT NULL,
    FOREIGN KEY (match_id) REFERENCES matches(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (team_id) REFERENCES teams(id),
    INDEX idx_match_participants_user (user_id),
    INDEX idx_match_participants_match (match_id),
    INDEX idx_match_participants_team (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User Kills table
CREATE TABLE IF NOT EXISTS user_kills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    user_id INT NOT NULL,
    kills INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_match_user (match_id, user_id),
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_match_kills (match_id),
    INDEX idx_user_kills (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table for storing permanent user match statistics
CREATE TABLE IF NOT EXISTS user_match_stats (
    user_id INT NOT NULL,
    total_matches_played INT DEFAULT 0,
    total_kills INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default data
INSERT INTO admin_users (username, email, password, full_name, role) 
VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'super_admin');

INSERT INTO hero_settings (subtitle, title, primary_btn_text, primary_btn_icon, secondary_btn_text, secondary_btn_icon, secondary_btn_url)
VALUES ('THE SEASON 1', 'TOURNAMENTS', '+TICKET', 'wallet-outline', 'GAMES', 'game-controller-outline', '#games');

INSERT INTO team_banners (image_path, name) VALUES
('/newapp/assets/images/hero-banner1.png', 'Banner 1'),
('/newapp/assets/images/hero-banner.jpg', 'Banner 2'),
('/newapp/assets/images/hero-banner1.png', 'Banner 3'),
('/newapp/assets/images/hero-banner.jpg', 'Banner 4'),
('/assets/images/team-banners/banner1.jpg', 'Banner 5'),
('/assets/images/team-banners/banner2.jpg', 'Banner 6'),
('/assets/images/team-banners/banner3.jpg', 'Banner 7'),
('/assets/images/team-banners/banner4.jpg', 'Banner 8'),
('/assets/images/team-banners/banner5.jpg', 'Banner 9'),
('/assets/images/team-banners/banner6.jpg', 'Banner 10');

INSERT INTO video_categories (name, description) VALUES
('Tournament Highlights', 'Best moments from our tournaments'),
('Tutorial Videos', 'Learn and improve your gaming skills'),
('Gaming Tips', 'Professional tips and tricks'),
('Event Coverage', 'Coverage of gaming events and competitions');

INSERT INTO profile_images (image_path, is_active, is_default) VALUES
('https://t3.ftcdn.net/jpg/09/68/64/82/360_F_968648260_97v6FNQWP3alhvyfLWtQTWGcrWZvAr1C.jpg', 1, 1);

-- Add position column to match_participants table if it doesn't exist
ALTER TABLE match_participants
ADD COLUMN position INT DEFAULT NULL;

-- Create user_games table
CREATE TABLE IF NOT EXISTS user_games (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    game_name ENUM('PUBG', 'BGMI', 'FREE FIRE', 'COD') NOT NULL,
    game_username VARCHAR(50),
    game_uid VARCHAR(20),
    is_primary BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_game (user_id, game_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create match_history_archive table
CREATE TABLE IF NOT EXISTS match_history_archive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_match_id INT,
    user_id INT NOT NULL,
    game_name VARCHAR(50) NOT NULL,
    match_type VARCHAR(50) NOT NULL,
    match_date DATETIME NOT NULL,
    entry_type VARCHAR(20),
    entry_fee INT,
    position INT,
    kills INT DEFAULT 0,
    coins_earned INT DEFAULT 0,
    coins_per_kill INT DEFAULT 0,
    prize_amount DECIMAL(10,2) DEFAULT 0,
    prize_type VARCHAR(20),
    participation_status VARCHAR(20),
    match_status VARCHAR(20),
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_matches (user_id, game_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create tournament_history_archive table
CREATE TABLE IF NOT EXISTS tournament_history_archive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_tournament_id INT,
    user_id INT NOT NULL,
    tournament_name VARCHAR(255) NOT NULL,
    game_name VARCHAR(50) NOT NULL,
    team_name VARCHAR(100),
    registration_date DATETIME NOT NULL,
    rounds_played INT DEFAULT 0,
    total_kills INT DEFAULT 0,
    total_points INT DEFAULT 0,
    best_placement INT,
    final_position INT,
    prize_amount DECIMAL(10,2) DEFAULT 0,
    prize_currency VARCHAR(20),
    website_currency_earned INT DEFAULT 0,
    website_currency_type VARCHAR(20),
    participation_status VARCHAR(20),
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_tournaments (user_id, game_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create triggers to archive match history before deletion
DELIMITER //

CREATE TRIGGER before_match_delete
BEFORE DELETE ON matches
FOR EACH ROW
BEGIN
    INSERT INTO match_history_archive (
        original_match_id,
        user_id,
        game_name,
        match_type,
        match_date,
        entry_type,
        entry_fee,
        position,
        kills,
        coins_earned,
        coins_per_kill,
        prize_amount,
        prize_type,
        participation_status,
        match_status
    )
    SELECT 
        m.id,
        mp.user_id,
        g.name,
        m.match_type,
        m.match_date,
        m.entry_type,
        m.entry_fee,
        mp.position,
        COALESCE(uk.kills, 0),
        (COALESCE(uk.kills, 0) * m.coins_per_kill),
        m.coins_per_kill,
        CASE 
            WHEN mp.position = 1 AND m.prize_distribution = 'single' THEN m.prize_pool
            WHEN mp.position <= 3 AND m.prize_distribution = 'top3' THEN m.prize_pool / 3
            WHEN mp.position <= 5 AND m.prize_distribution = 'top5' THEN m.prize_pool / 5
            ELSE 0
        END,
        m.prize_type,
        mp.status,
        m.status
    FROM matches m
    JOIN games g ON m.game_id = g.id
    JOIN match_participants mp ON m.id = mp.match_id
    LEFT JOIN user_kills uk ON uk.match_id = m.id AND uk.user_id = mp.user_id
    WHERE m.id = OLD.id;
END //

-- Create trigger to archive tournament history before deletion
CREATE TRIGGER before_tournament_delete
BEFORE DELETE ON tournaments
FOR EACH ROW
BEGIN
    INSERT INTO tournament_history_archive (
        original_tournament_id,
        user_id,
        tournament_name,
        game_name,
        team_name,
        registration_date,
        rounds_played,
        total_kills,
        total_points,
        best_placement,
        final_position,
        prize_amount,
        prize_currency,
        website_currency_earned,
        website_currency_type,
        participation_status
    )
    SELECT 
        t.id,
        tph.user_id,
        t.name,
        t.game_name,
        tm.name,
        tph.registration_date,
        tph.rounds_played,
        tph.total_kills,
        tph.total_points,
        tph.best_placement,
        tph.final_position,
        tph.prize_amount,
        tph.prize_currency,
        tph.website_currency_earned,
        tph.website_currency_type,
        tph.status
    FROM tournaments t
    JOIN tournament_player_history tph ON t.id = tph.tournament_id
    JOIN teams tm ON tph.team_id = tm.id
    WHERE t.id = OLD.id;
END //

DELIMITER ;

-- Add trigger to ensure only one primary game per user
DELIMITER //
CREATE TRIGGER before_user_games_insert 
BEFORE INSERT ON user_games
FOR EACH ROW
BEGIN
    IF NEW.is_primary = 1 THEN
        UPDATE user_games 
        SET is_primary = 0 
        WHERE user_id = NEW.user_id;
    END IF;
END//

CREATE TRIGGER before_user_games_update
BEFORE UPDATE ON user_games
FOR EACH ROW
BEGIN
    IF NEW.is_primary = 1 AND OLD.is_primary = 0 THEN
        UPDATE user_games 
        SET is_primary = 0 
        WHERE user_id = NEW.user_id 
        AND id != NEW.id;
    END IF;
END//
DELIMITER ;

-- Streak Tasks Table
CREATE TABLE IF NOT EXISTS streak_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    reward_points INT NOT NULL DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Streaks Table
CREATE TABLE IF NOT EXISTS user_streaks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    current_streak INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    streak_points INT DEFAULT 0,
    total_tasks_completed INT DEFAULT 0,
    last_activity_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User Streak Tasks Table
CREATE TABLE IF NOT EXISTS user_streak_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    completion_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    points_earned INT NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES streak_tasks(id) ON DELETE CASCADE
);

-- Streak Milestones Table
CREATE TABLE IF NOT EXISTS streak_milestones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    points_required INT NOT NULL,
    reward_points INT NOT NULL DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Streak Milestones Table
CREATE TABLE IF NOT EXISTS user_streak_milestones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    milestone_id INT NOT NULL,
    achieved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (milestone_id) REFERENCES streak_milestones(id) ON DELETE CASCADE
);

-- Insert some default streak tasks
INSERT INTO streak_tasks (name, description, reward_points) VALUES
('Daily Login', 'Log in to your account', 5),
('Join a Match', 'Participate in any match', 10),
('Win a Match', 'Win any match you participate in', 20),
('Complete Profile', 'Update your game profiles', 15),
('Invite Friends', 'Invite new players to join', 25);

-- Insert some default streak milestones
INSERT INTO streak_milestones (name, description, points_required, reward_points) VALUES
('Bronze Streak', 'Reach 100 streak points', 100, 50),
('Silver Streak', 'Reach 500 streak points', 500, 100),
('Gold Streak', 'Reach 1000 streak points', 1000, 200),
('Diamond Streak', 'Reach 5000 streak points', 5000, 500);

