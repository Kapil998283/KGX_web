<?php
// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Format date
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

// Get file extension
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Check if string is valid URL
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// Get user avatar URL
function getUserAvatar($avatar = null) {
    if ($avatar && isValidUrl($avatar)) {
        return $avatar;
    }
    return '/newapp/assets/images/default_avatar.png';
}

// Get team logo URL
function getTeamLogo($logo = null) {
    if ($logo && isValidUrl($logo)) {
        return $logo;
    }
    return '/newapp/assets/images/default_logo.png';
}

// Get team banner URL
function getTeamBanner($banner = null) {
    if ($banner && isValidUrl($banner)) {
        return $banner;
    }
    return '/newapp/assets/images/default_banner.jpg';
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

// Check if user is team captain
function isTeamCaptain($conn, $userId, $teamId) {
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND user_id = ? AND role = 'captain'");
    $stmt->bind_param('ii', $teamId, $userId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Check if user is team member
function isTeamMember($conn, $userId, $teamId) {
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $teamId, $userId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Get team details
function getTeamDetails($conn, $teamId) {
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get team members
function getTeamMembers($conn, $teamId) {
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar, tm.role, tm.joined_date 
        FROM users u 
        INNER JOIN team_members tm ON u.id = tm.user_id 
        WHERE tm.team_id = ?
    ");
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get team join requests
function getTeamRequests($conn, $teamId) {
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar, tr.request_date 
        FROM users u 
        INNER JOIN team_requests tr ON u.id = tr.user_id 
        WHERE tr.team_id = ? AND tr.status = 'pending'
    ");
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Updates tournament player history after a round is completed
 */
function updateTournamentPlayerHistory($round_id, $conn) {
    try {
        // Get round details and team performances
        $stmt = $conn->prepare("
            SELECT 
                tr.tournament_id,
                rt.team_id,
                rt.placement,
                rt.kills,
                rt.total_points
            FROM tournament_rounds tr
            JOIN round_teams rt ON tr.id = rt.round_id
            WHERE tr.id = ?
        ");
        $stmt->execute([$round_id]);
        $round_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Update history for each team's players
        foreach ($round_results as $result) {
            // Get team members
            $stmt = $conn->prepare("
                SELECT user_id 
                FROM team_members 
                WHERE team_id = ? AND status = 'active'
            ");
            $stmt->execute([$result['team_id']]);
            $team_members = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Update history for each player
            foreach ($team_members as $user_id) {
                $stmt = $conn->prepare("
                    UPDATE tournament_player_history
                    SET 
                        rounds_played = rounds_played + 1,
                        total_kills = total_kills + ?,
                        total_points = total_points + ?,
                        best_placement = CASE 
                            WHEN best_placement IS NULL OR ? < best_placement 
                            THEN ? 
                            ELSE best_placement 
                        END,
                        status = 'playing',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE tournament_id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $result['kills'],
                    $result['total_points'],
                    $result['placement'],
                    $result['placement'],
                    $result['tournament_id'],
                    $user_id
                ]);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Error updating tournament history: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates tournament history when a tournament is completed
 */
function updateTournamentHistory($tournament_id, $conn) {
    try {
        // Get all teams and their final standings
        $stmt = $conn->prepare("
            SELECT 
                t.id as team_id,
                SUM(rt.total_points) as total_points,
                MIN(rt.placement) as best_placement,
                COUNT(rt.id) as rounds_played,
                SUM(rt.kills) as total_kills
            FROM teams t
            JOIN round_teams rt ON t.id = rt.team_id
            JOIN tournament_rounds tr ON rt.round_id = tr.id
            WHERE tr.tournament_id = ?
            GROUP BY t.id
            ORDER BY total_points DESC
        ");
        $stmt->execute([$tournament_id]);
        $team_standings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Update history for each team's players
        foreach ($team_standings as $position => $team) {
            // Get team members
            $stmt = $conn->prepare("
                SELECT user_id 
                FROM team_members 
                WHERE team_id = ? AND status = 'active'
            ");
            $stmt->execute([$team['team_id']]);
            $team_members = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Get prize information if team is in winners
            $stmt = $conn->prepare("
                SELECT prize_amount, prize_currency 
                FROM tournament_winners 
                WHERE tournament_id = ? AND team_id = ?
            ");
            $stmt->execute([$tournament_id, $team['team_id']]);
            $prize_info = $stmt->fetch(PDO::FETCH_ASSOC);

            // Update history for each player
            foreach ($team_members as $user_id) {
                $stmt = $conn->prepare("
                    UPDATE tournament_player_history
                    SET 
                        rounds_played = ?,
                        total_kills = ?,
                        total_points = ?,
                        best_placement = ?,
                        final_position = ?,
                        prize_amount = ?,
                        prize_currency = ?,
                        status = 'completed',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE tournament_id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $team['rounds_played'],
                    $team['total_kills'],
                    $team['total_points'],
                    $team['best_placement'],
                    $position + 1,
                    $prize_info ? $prize_info['prize_amount'] : 0,
                    $prize_info ? $prize_info['prize_currency'] : null,
                    $tournament_id,
                    $user_id
                ]);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Error updating tournament history: " . $e->getMessage());
        return false;
    }
}

/**
 * Checks if a tournament is completed and updates its status
 */
function checkAndUpdateTournamentStatus($tournament_id, $conn) {
    try {
        // Get tournament details
        $stmt = $conn->prepare("
            SELECT t.*, 
                COUNT(tr.id) as total_rounds,
                COUNT(CASE WHEN tr.status = 'completed' THEN 1 END) as completed_rounds
            FROM tournaments t
            LEFT JOIN tournament_rounds tr ON t.id = tr.tournament_id
            WHERE t.id = ?
            GROUP BY t.id
        ");
        $stmt->execute([$tournament_id]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tournament) {
            return false;
        }

        // If all rounds are completed, update tournament status
        if ($tournament['total_rounds'] > 0 && $tournament['total_rounds'] == $tournament['completed_rounds']) {
            // Start transaction
            $conn->beginTransaction();

            // Update tournament status
            $stmt = $conn->prepare("
                UPDATE tournaments 
                SET status = 'completed', 
                    registration_phase = 'finished',
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$tournament_id]);

            // Update tournament history for all players
            updateTournamentHistory($tournament_id, $conn);

            // Send notifications to all participants
            $stmt = $conn->prepare("
                SELECT DISTINCT u.id
                FROM users u
                JOIN team_members tm ON u.id = tm.user_id
                JOIN teams t ON tm.team_id = t.id
                JOIN tournament_registrations tr ON t.id = tr.team_id
                WHERE tr.tournament_id = ? AND tr.status = 'approved'
            ");
            $stmt->execute([$tournament_id]);
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($users as $user_id) {
                $stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, message, created_at)
                    VALUES (?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    $user_id,
                    "Tournament '{$tournament['name']}' has been completed. Check your final standings!"
                ]);
            }

            // Commit transaction
            $conn->commit();
            return true;
        }

        return false;
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        error_log("Error checking tournament status: " . $e->getMessage());
        return false;
    }
} 