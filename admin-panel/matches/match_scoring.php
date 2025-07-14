<?php
require_once '../includes/admin-auth.php';
require_once '../../config/database.php';
include '../includes/admin-header.php';

// Add these headers at the top of the file, after the require statements
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Add payment_confirmed column to matches table if it doesn't exist
try {
    $db->exec("ALTER TABLE matches ADD COLUMN IF NOT EXISTS payment_confirmed BOOLEAN DEFAULT FALSE");
} catch (Exception $e) {
    error_log("Error adding payment_confirmed column: " . $e->getMessage());
}

// Get match ID from URL
$match_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch match details
$stmt = $db->prepare("SELECT m.*, g.name as game_name, g.image_url as game_image,
                            t1.name as team1_name, t2.name as team2_name,
                            DATE(m.match_date) as match_date,
                            TIME(m.match_date) as match_time,
                            t.name as tournament_name,
                            m.website_currency_type,
                            m.website_currency_amount,
                            m.prize_distribution,
                            m.coins_per_kill
                     FROM matches m 
                     LEFT JOIN games g ON m.game_id = g.id 
                     LEFT JOIN teams t1 ON m.team1_id = t1.id
                     LEFT JOIN teams t2 ON m.team2_id = t2.id
                     LEFT JOIN tournaments t ON m.tournament_id = t.id
                     WHERE m.id = ?");
$stmt->execute([$match_id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    header("Location: " . strtolower($match['game_name']) . ".php");
    exit;
}

// Fetch participants
$stmt = $db->prepare("SELECT mp.*, u.username, u.email, u.phone,
                            ug.game_uid, ug.game_username,
                            COALESCE(uk.kills, 0) as total_kills
                     FROM match_participants mp
                     JOIN users u ON mp.user_id = u.id
                     JOIN matches m ON m.id = mp.match_id
                     LEFT JOIN user_games ug ON ug.user_id = u.id AND ug.game_name = m.game_id
                     LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id
                     WHERE mp.match_id = ?
                     ORDER BY uk.kills DESC, u.username ASC");
$stmt->execute([$match_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add this function after the require statements at the top
function distributePrize($db, $match_id, $winner_id, $match) {
    if (!$winner_id) {
        error_log("Error in distributePrize: No winner_id provided");
        return;
    }

    try {
        // Get all participants sorted by position and kills
        $stmt = $db->prepare("SELECT mp.user_id, mp.team_id, mp.position, COALESCE(uk.kills, 0) as kills 
                             FROM match_participants mp 
                             LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id 
                             WHERE mp.match_id = ? 
                             ORDER BY 
                                CASE 
                                    WHEN mp.position IS NULL THEN 999999 
                                    ELSE mp.position 
                                END ASC,
                                uk.kills DESC");
        $stmt->execute([$match_id]);
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($participants)) {
            error_log("Error in distributePrize: No participants found for match_id: " . $match_id);
            return;
        }

        // Update permanent statistics for each participant
        foreach ($participants as $participant) {
            // Update or insert user match stats
            $stmt = $db->prepare("INSERT INTO user_match_stats (user_id, total_matches_played, total_kills) 
                                VALUES (?, 1, ?) 
                                ON DUPLICATE KEY UPDATE 
                                total_matches_played = total_matches_played + 1,
                                total_kills = total_kills + ?");
            $stmt->execute([
                $participant['user_id'], 
                $participant['kills'],
                $participant['kills']
            ]);
        }

        // First, award kill rewards to all participants
        if ($match['coins_per_kill'] > 0) {
            foreach ($participants as $participant) {
                if ($participant['kills'] > 0) {
                    $kill_coins = $participant['kills'] * $match['coins_per_kill'];
                    try {
                        $stmt = $db->prepare("INSERT INTO user_coins (user_id, coins) 
                                            VALUES (?, ?) 
                                            ON DUPLICATE KEY UPDATE coins = coins + ?");
                        $stmt->execute([$participant['user_id'], $kill_coins, $kill_coins]);
                        
                        // Log the kill rewards
                        error_log("Awarded {$kill_coins} coins to user {$participant['user_id']} for {$participant['kills']} kills");
                    } catch (Exception $e) {
                        error_log("Error awarding kill coins: " . $e->getMessage());
                        throw $e;
                    }
                }
            }
        }

        // Then distribute position-based website currency prizes
        if ($match['website_currency_type'] && $match['website_currency_amount'] > 0) {
            $total_prize = $match['website_currency_amount'];
            $currency_type = $match['website_currency_type'];
            
            // Define prize distribution percentages
            $distribution_percentages = [];
            switch($match['prize_distribution']) {
                case 'top3':
                    $distribution_percentages = [60, 30, 10];
                    break;
                case 'top5':
                    $distribution_percentages = [50, 25, 15, 7, 3];
                    break;
                default: // 'single' - winner takes all
                    $distribution_percentages = [100];
                    break;
            }

            // Distribute position-based prizes
            foreach ($participants as $index => $participant) {
                if ($index >= count($distribution_percentages)) break;
                
                $prize_amount = floor($total_prize * $distribution_percentages[$index] / 100);
                if ($prize_amount <= 0) continue;

                try {
                    if ($currency_type === 'coins') {
                        $stmt = $db->prepare("INSERT INTO user_coins (user_id, coins) 
                                            VALUES (?, ?) 
                                            ON DUPLICATE KEY UPDATE coins = coins + ?");
                    } else { // tickets
                        $stmt = $db->prepare("INSERT INTO user_tickets (user_id, tickets) 
                                            VALUES (?, ?) 
                                            ON DUPLICATE KEY UPDATE tickets = tickets + ?");
                    }
                    $stmt->execute([$participant['user_id'], $prize_amount, $prize_amount]);
                    
                    // Log the prize distribution
                    error_log("Awarded {$prize_amount} {$currency_type} to user {$participant['user_id']} for position " . ($index + 1));
                } catch (Exception $e) {
                    error_log("Error distributing website currency: " . $e->getMessage());
                    throw $e;
                }
            }
        }

        // Handle real money distribution (for admin reference)
        if ($match['prize_pool'] > 0) {
            $total_prize = $match['prize_pool'];
            $currency_type = $match['prize_type'];
            
            // Define prize distribution percentages
            $distribution_percentages = [];
            switch($match['prize_distribution']) {
                case 'top3':
                    $distribution_percentages = [60, 30, 10];
                    break;
                case 'top5':
                    $distribution_percentages = [50, 25, 15, 7, 3];
                    break;
                default: // 'single'
                    $distribution_percentages = [100];
                    break;
            }

            // Calculate and store prize amounts for each winner
            foreach ($participants as $index => $participant) {
                if ($index >= count($distribution_percentages)) break;
                
                $prize_amount = round($total_prize * $distribution_percentages[$index] / 100, 2);
                if ($prize_amount <= 0) continue;

                try {
                    // Store in match_results table
                    $stmt = $db->prepare("INSERT INTO match_results (match_id, team_id, score, prize_amount, prize_currency) 
                                            VALUES (?, ?, ?, ?, ?) 
                                            ON DUPLICATE KEY UPDATE 
                                            score = VALUES(score),
                                            prize_amount = VALUES(prize_amount),
                                            prize_currency = VALUES(prize_currency)");
                    $stmt->execute([
                        $match_id, 
                        $participant['team_id'], 
                        $participant['kills'], // Using kills as score
                        $prize_amount, 
                        $currency_type
                    ]);
                } catch (Exception $e) {
                    error_log("Error storing real money prize: " . $e->getMessage());
                    throw $e;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error in distributePrize function: " . $e->getMessage());
        throw $e;
    }
}

// Handle POST requests for updating kills and completing match
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_kills':
                $user_id = $_POST['user_id'];
                $kills = intval($_POST['kills']);
                
                try {
                    $db->beginTransaction();
                    
                    // Update or insert kills
                    $stmt = $db->prepare("INSERT INTO user_kills (match_id, user_id, kills) 
                                        VALUES (?, ?, ?) 
                                        ON DUPLICATE KEY UPDATE kills = ?");
                    $stmt->execute([$match_id, $user_id, $kills, $kills]);
                    
                    // Calculate and award coins for kills
                    if ($match['coins_per_kill'] > 0) {
                        $coins_earned = $kills * $match['coins_per_kill'];
                        $stmt = $db->prepare("INSERT INTO user_coins (user_id, coins) 
                                            VALUES (?, ?) 
                                            ON DUPLICATE KEY UPDATE coins = coins + ?");
                        $stmt->execute([$user_id, $coins_earned, $coins_earned]);
                    }
                    
                    $db->commit();
                    header("Location: match_scoring.php?id=" . $match_id . "&success=1");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error updating kills: " . $e->getMessage());
                    header("Location: match_scoring.php?id=" . $match_id . "&error=1");
                    exit;
                }
                break;

            case 'select_winner':
                $db->beginTransaction(); // Start transaction before any operations
                try {
                    $winner_id = $_POST['winner_id'];
                    
                    // Verify the user is a participant in this match
                    $stmt = $db->prepare("SELECT COUNT(*) FROM match_participants WHERE match_id = ? AND user_id = ?");
                    $stmt->execute([$match_id, $winner_id]);
                    if ($stmt->fetchColumn() == 0) {
                        throw new Exception("Selected user is not a participant in this match");
                    }
                    
                    // Check if all required positions are set based on prize distribution
                    $requiredPositions = ($match['prize_distribution'] === 'top5') ? 5 : 
                                       ($match['prize_distribution'] === 'top3' ? 3 : 1);
                    
                    $stmt = $db->prepare("SELECT COUNT(*) FROM match_participants 
                                        WHERE match_id = ? AND position IS NOT NULL AND position <= ?");
                    $stmt->execute([$match_id, $requiredPositions]);
                    $positionsSet = $stmt->fetchColumn();
                    
                    if ($positionsSet < $requiredPositions) {
                        throw new Exception("Please set positions for all winners (top " . $requiredPositions . ") before selecting the winner.");
                    }
                    
                    // Verify the winner has position 1
                    $stmt = $db->prepare("SELECT position FROM match_participants WHERE match_id = ? AND user_id = ?");
                    $stmt->execute([$match_id, $winner_id]);
                    $winnerPosition = $stmt->fetchColumn();
                    
                    if ($winnerPosition != 1) {
                        throw new Exception("The selected winner must be in position 1.");
                    }
                    
                    // Update match status and winner
                    $stmt = $db->prepare("UPDATE matches SET 
                                        status = 'completed', 
                                        completed_at = NOW(), 
                                        winner_user_id = ?
                                        WHERE id = ?");
                    $stmt->execute([$winner_id, $match_id]);
                    
                    // Award prizes based on distribution type
                    distributePrize($db, $match_id, $winner_id, $match);
                    
                    // Get match and winner details for notification
                    $stmt = $db->prepare("
                        SELECT m.id, m.match_type, g.name as game_name, u.username as winner_name
                        FROM matches m
                        JOIN games g ON m.game_id = g.id
                        JOIN users u ON u.id = ?
                        WHERE m.id = ?
                    ");
                    $stmt->execute([$winner_id, $match_id]);
                    $matchInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get all users participating in this match
                    $stmt = $db->prepare("
                        SELECT DISTINCT u.id
                        FROM users u
                        JOIN match_participants mp ON u.id = mp.user_id
                        WHERE mp.match_id = ?
                    ");
                    $stmt->execute([$match_id]);
                    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Create notifications for participating users
                    $notificationMessage = "Match completed! {$matchInfo['winner_name']} won the {$matchInfo['game_name']} {$matchInfo['match_type']} match";
                    
                    foreach ($users as $user_id) {
                        $stmt = $db->prepare("
                            INSERT INTO notifications (
                                user_id, 
                                type, 
                                message, 
                                related_id, 
                                related_type,
                                created_at
                            ) VALUES (
                                ?, 
                                'match_completed', 
                                ?, 
                                ?, 
                                'match',
                                NOW()
                            )
                        ");
                        $stmt->execute([$user_id, $notificationMessage, $match_id]);
                    }
                    
                    $db->commit();
                    header("Location: match_scoring.php?id=" . $match_id . "&success=1");
                    exit;
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    header("Location: match_scoring.php?id=" . $match_id . "&error=" . urlencode($e->getMessage()));
                    exit;
                }
                break;

            case 'complete_match':
                $db->beginTransaction();
                try {
                    // Check if payment confirmation is required and confirmed
                    if ($match['prize_pool'] > 0 && !$match['website_currency_type']) {
                        $stmt = $db->prepare("SELECT payment_confirmed FROM matches WHERE id = ?");
                        $stmt->execute([$match_id]);
                        $payment_status = $stmt->fetchColumn();
                        
                        if (!$payment_status) {
                            throw new Exception("Payment must be confirmed before completing match with real currency prizes.");
                        }
                    }

                    // Check if positions have been set for the required number of winners
                    $requiredPositions = ($match['prize_distribution'] === 'top5') ? 5 : 
                                        ($match['prize_distribution'] === 'top3' ? 3 : 1);
                    
                    $stmt = $db->prepare("SELECT COUNT(*) FROM match_participants 
                                        WHERE match_id = ? AND position IS NOT NULL AND position <= ?");
                    $stmt->execute([$match_id, $requiredPositions]);
                    $positionsSet = $stmt->fetchColumn();
                    
                    if ($positionsSet < $requiredPositions) {
                        throw new Exception("Please set positions for all winners (top " . $requiredPositions . ") before completing the match.");
                    }

                    // Get the first position player as the main winner
                    $stmt = $db->prepare("SELECT user_id FROM match_participants 
                                        WHERE match_id = ? AND position = 1");
                    $stmt->execute([$match_id]);
                    $firstPlace = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$firstPlace) {
                        throw new Exception("First position must be set before completing the match.");
                    }
                    
                    // Update match status and set the first position player as winner
                    $stmt = $db->prepare("UPDATE matches SET 
                                        status = 'completed', 
                                        completed_at = NOW(), 
                                        winner_user_id = ?
                                        WHERE id = ?");
                    $stmt->execute([$firstPlace['user_id'], $match_id]);

                    // Distribute prizes
                    distributePrize($db, $match_id, $firstPlace['user_id'], $match);

                    // Get match and winner details for notification
                    $stmt = $db->prepare("
                        SELECT m.id, m.match_type, g.name as game_name, u.username as winner_name
                        FROM matches m
                        JOIN games g ON m.game_id = g.id
                        JOIN users u ON u.id = ?
                        WHERE m.id = ?
                    ");
                    $stmt->execute([$firstPlace['user_id'], $match_id]);
                    $matchInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get all users participating in this match
                    $stmt = $db->prepare("
                        SELECT DISTINCT u.id
                        FROM users u
                        JOIN match_participants mp ON u.id = mp.user_id
                        WHERE mp.match_id = ?
                    ");
                    $stmt->execute([$match_id]);
                    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Create notifications for participating users
                    $notificationMessage = "Match completed! {$matchInfo['winner_name']} won the {$matchInfo['game_name']} {$matchInfo['match_type']} match";
                    
                    foreach ($users as $user_id) {
                        $stmt = $db->prepare("
                            INSERT INTO notifications (
                                user_id, 
                                type, 
                                message, 
                                related_id, 
                                related_type,
                                created_at
                            ) VALUES (
                                ?, 
                                'match_completed', 
                                ?, 
                                ?, 
                                'match',
                                NOW()
                            )
                        ");
                        $stmt->execute([$user_id, $notificationMessage, $match_id]);
                    }

                    $db->commit();
                    header("Location: match_scoring.php?id=" . $match_id . "&success=completed");
                    exit;
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    header("Location: match_scoring.php?id=" . $match_id . "&error=" . urlencode($e->getMessage()));
                    exit;
                }
                break;

            case 'cancel_match':
                try {
                    $db->beginTransaction();
                    
                    // Get match details and participants
                    $stmt = $db->prepare("SELECT m.*, mp.user_id, mp.status,
                                        CASE 
                                            WHEN m.entry_type = 'coins' THEN uc.coins 
                                            WHEN m.entry_type = 'tickets' THEN ut.tickets 
                                        END as current_balance
                                        FROM matches m
                                        LEFT JOIN match_participants mp ON m.id = mp.match_id
                                        LEFT JOIN user_coins uc ON mp.user_id = uc.user_id
                                        LEFT JOIN user_tickets ut ON mp.user_id = ut.user_id
                                        WHERE m.id = ?");
                    $stmt->execute([$match_id]);
                    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($participants)) {
                        throw new Exception("No participants found for this match");
                    }
                    
                    $match_info = $participants[0];
                    
                    // Only process refunds for paid matches
                    if ($match_info['entry_type'] !== 'free' && $match_info['entry_fee'] > 0) {
                        foreach ($participants as $participant) {
                            if ($participant['user_id']) {
                                // Refund entry fee
                                if ($match_info['entry_type'] === 'coins') {
                                    $stmt = $db->prepare("UPDATE user_coins SET coins = coins + ? WHERE user_id = ?");
                                } else {
                                    $stmt = $db->prepare("UPDATE user_tickets SET tickets = tickets + ? WHERE user_id = ?");
                                }
                                $stmt->execute([$match_info['entry_fee'], $participant['user_id']]);
                                
                                // Send notification about refund
                                $refund_message = "Match cancelled: Your {$match_info['entry_fee']} {$match_info['entry_type']} entry fee has been refunded.";
                                $stmt = $db->prepare("INSERT INTO notifications (user_id, type, message, related_id, related_type, created_at)
                                                    VALUES (?, 'match_cancelled', ?, ?, 'match', NOW())");
                                $stmt->execute([$participant['user_id'], $refund_message, $match_id]);
                            }
                        }
                    }
                    
                    // Update match status
                    $stmt = $db->prepare("UPDATE matches SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$match_id]);
                    
                    $db->commit();
                    $_SESSION['success'] = "Match cancelled successfully and refunds processed.";
                    header("Location: match_details.php?id=" . $match_id);
                    exit;
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error cancelling match: " . $e->getMessage());
                    $_SESSION['error'] = "Error cancelling match: " . $e->getMessage();
                    header("Location: match_details.php?id=" . $match_id);
                    exit;
                }
                break;

            case 'update_position':
                $db->beginTransaction(); // Start transaction before any operations
                try {
                    $user_id = $_POST['user_id'];
                    $position = intval($_POST['position']);
                    
                    // Validate position is positive
                    if ($position <= 0) {
                        throw new Exception("Position must be a positive number");
                    }
                    
                    // Check if this position is already taken by another player
                    $stmt = $db->prepare("SELECT user_id FROM match_participants 
                                        WHERE match_id = ? AND position = ? AND user_id != ?");
                    $stmt->execute([$match_id, $position, $user_id]);
                    if ($stmt->fetch()) {
                        throw new Exception("Position " . $position . " is already taken by another player. Please choose a different position.");
                    }
                    
                    // Update the position
                    $stmt = $db->prepare("UPDATE match_participants 
                                        SET position = ? 
                                        WHERE match_id = ? AND user_id = ?");
                    $stmt->execute([$position, $match_id, $user_id]);

                    // Get match and player details for notification
                    $stmt = $db->prepare("
                        SELECT m.id, m.match_type, g.name as game_name, u.username
                        FROM matches m
                        JOIN games g ON m.game_id = g.id
                        JOIN users u ON u.id = ?
                        WHERE m.id = ?
                    ");
                    $stmt->execute([$user_id, $match_id]);
                    $matchInfo = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Get position suffix
                    $suffix = 'th';
                    if ($position == 1) $suffix = 'st';
                    else if ($position == 2) $suffix = 'nd';
                    else if ($position == 3) $suffix = 'rd';

                    // Create notification for the player
                    $notificationMessage = "You secured {$position}{$suffix} position in {$matchInfo['game_name']} {$matchInfo['match_type']} match!";
                    
                    // Insert notification
                    $stmt = $db->prepare("
                        INSERT INTO notifications (
                            user_id, 
                            type, 
                            message, 
                            related_id, 
                            related_type,
                            created_at
                        ) VALUES (
                            ?, 
                            'position_update', 
                            ?, 
                            ?, 
                            'match',
                            NOW()
                        )
                    ");
                    $stmt->execute([
                        $user_id,
                        $notificationMessage,
                        $match_id
                    ]);
                    
                    $db->commit();
                    header("Location: match_scoring.php?id=" . $match_id . "&success=position");
                    exit;
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    header("Location: match_scoring.php?id=" . $match_id . "&error=" . urlencode($e->getMessage()));
                    exit;
                }
                break;

            case 'confirm_payment':
                try {
                    $stmt = $db->prepare("UPDATE matches SET payment_confirmed = TRUE WHERE id = ?");
                    $stmt->execute([$match_id]);
                    header("Location: match_scoring.php?id=" . $match_id . "&payment_confirmed=1");
                    exit;
                } catch (Exception $e) {
                    error_log("Error confirming payment: " . $e->getMessage());
                    header("Location: match_scoring.php?id=" . $match_id . "&error=payment_confirmation_failed");
                    exit;
                }
                break;
        }
    }
}

// Handle match cancellation
if (isset($_GET['action']) && $_GET['action'] === 'cancel_match' && isset($_GET['match_id'])) {
    $match_id = (int)$_GET['match_id'];
    
    try {
        $db->beginTransaction();
        
        // Get match details first
        $stmt = $db->prepare("
            SELECT m.*, g.name as game_name 
            FROM matches m 
            JOIN games g ON m.game_id = g.id 
            WHERE m.id = ? AND m.status = 'upcoming'
        ");
        $stmt->execute([$match_id]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$match) {
            throw new Exception("Match not found or cannot be cancelled!");
        }
        
        // Get all participants who paid entry fee
        $stmt = $db->prepare("
            SELECT user_id 
            FROM match_participants 
            WHERE match_id = ?
        ");
        $stmt->execute([$match_id]);
        $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Process refunds for each participant
        foreach ($participants as $user_id) {
            if ($match['entry_type'] === 'coins') {
                // Refund coins
                $stmt = $db->prepare("
                    UPDATE user_coins 
                    SET coins = coins + ? 
                    WHERE user_id = ?
                ");
            } else {
                // Refund tickets
                $stmt = $db->prepare("
                    UPDATE user_tickets 
                    SET tickets = tickets + ? 
                    WHERE user_id = ?
                ");
            }
            $stmt->execute([$match['entry_fee'], $user_id]);
            
            // Create refund notification
            $notificationMessage = "Match cancelled: Refunded " . $match['entry_fee'] . " " . 
                                 $match['entry_type'] . " for " . $match['game_name'] . " match";
            
            $stmt = $db->prepare("
                INSERT INTO notifications (
                    user_id, 
                    type, 
                    message, 
                    related_id, 
                    related_type,
                    created_at
                ) VALUES (
                    ?, 
                    'match_cancelled', 
                    ?, 
                    ?, 
                    'match',
                    NOW()
                )
            ");
            $stmt->execute([$user_id, $notificationMessage, $match_id]);
        }
        
        // Update match status to cancelled
        $stmt = $db->prepare("
            UPDATE matches 
            SET status = 'cancelled', 
                cancelled_at = NOW(),
                cancellation_reason = 'Cancelled by admin'
            WHERE id = ?
        ");
        $stmt->execute([$match_id]);
        
        $db->commit();
        $_SESSION['success'] = "Match cancelled successfully. All participants have been refunded.";
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error cancelling match: " . $e->getMessage();
    }
    
    // Redirect back to the appropriate game page based on the game name
    $redirect_page = strtolower(str_replace(' ', '', $match['game_name'])) . '.php';
    header("Location: " . $redirect_page);
    exit;
}

// Add success/error messages
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch($_GET['success']) {
        case 'position':
            $success_message = 'Position updated successfully!';
            break;
        case 'completed':
            $success_message = 'Match completed successfully!';
            break;
        case 'payment_confirmed':
            $success_message = 'Prize payment confirmed!';
            break;
        default:
            $success_message = 'Operation completed successfully!';
    }
} elseif (isset($_GET['error'])) {
    $error_message = isset($_GET['error']) && $_GET['error'] !== '1' 
        ? urldecode($_GET['error']) 
        : 'An error occurred. Please try again.';
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Update Match Score</h3>
                        <div>
                            <a href="match_details.php?id=<?= $match_id ?>" class="btn btn-info me-2">
                                <i class="bi bi-people"></i> View Participants
                            </a>
                            <a href="javascript:history.back()" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Match Info -->
                    <div class="match-info-header mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h4><?= htmlspecialchars($match['game_name']) ?> - <?= ucfirst($match['match_type']) ?></h4>
                                <p class="text-muted">
                                    <i class="bi bi-calendar"></i> <?= date('M j, Y', strtotime($match['match_date'])) ?>
                                    <i class="bi bi-clock ms-3"></i> <?= date('g:i A', strtotime($match['match_time'])) ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <div class="prize-info">
                                    <?php if ($match['website_currency_type'] && $match['website_currency_amount'] > 0): ?>
                                        <h5>Prize Pool: <?= number_format($match['website_currency_amount']) ?> <?= ucfirst($match['website_currency_type']) ?></h5>
                                    <?php else: ?>
                                        <h5>Prize Pool: <?= $match['prize_type'] === 'USD' ? '$' : '₹' ?><?= number_format($match['prize_pool']) ?></h5>
                                    <?php endif; ?>
                                    <?php if ($match['coins_per_kill'] > 0): ?>
                                        <p class="text-success">
                                            <i class="bi bi-star"></i> <?= number_format($match['coins_per_kill']) ?> Coins per Kill
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Payment Confirmation Button for Real Currency Matches -->
                    <?php if ($match['status'] === 'in_progress' && !$match['website_currency_type'] && $match['prize_pool'] > 0): ?>
                        <div class="text-end mb-4">
                            <?php
                            // Check if payment is confirmed
                            $stmt = $db->prepare("SELECT payment_confirmed FROM matches WHERE id = ?");
                            $stmt->execute([$match_id]);
                            $payment_confirmed = $stmt->fetchColumn();
                            ?>
                            <?php if (!$payment_confirmed): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="confirm_payment">
                                    <button type="submit" class="btn btn-warning me-2">
                                        <i class="bi bi-cash"></i> Confirm Prize Payment
                                    </button>
                                </form>
                                <div class="alert alert-warning mt-2">
                                    <i class="bi bi-exclamation-triangle"></i> You must confirm prize payment before completing the match.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mt-2 mb-3">
                                    <i class="bi bi-check-circle"></i> Prize payment has been confirmed.
                                </div>
                            <?php endif; ?>
                            <form method="POST" id="completeMatchForm" class="d-inline">
                                <input type="hidden" name="action" value="complete_match">
                                <button type="submit" class="btn btn-success" <?= !$payment_confirmed ? 'disabled' : '' ?>>
                                    <i class="bi bi-check-lg"></i> Complete Match
                                </button>
                            </form>
                        </div>
                    <?php elseif ($match['status'] === 'in_progress'): ?>
                        <div class="text-end mb-4">
                            <form method="POST" id="completeMatchForm">
                                <input type="hidden" name="action" value="complete_match">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg"></i> Complete Match
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Add success/error messages -->
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Add search box -->
                    <div class="mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Search by username, game UID, or in-game name...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Participants Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="participantsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Player</th>
                                    <th>Contact</th>
                                    <th>Game UID</th>
                                    <th>In-Game Name</th>
                                    <th>Kills</th>
                                    <th>Coins Earned</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $index => $participant): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($participant['username']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($participant['email']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($participant['phone']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($participant['game_uid']) ?></td>
                                    <td><?= htmlspecialchars($participant['game_username']) ?></td>
                                    <td><?= $participant['total_kills'] ?></td>
                                    <td><?= $participant['total_kills'] * $match['coins_per_kill'] ?></td>
                                    <td>
                                        <?php if ($match['status'] === 'in_progress'): ?>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="updateKills(<?= $participant['user_id'] ?>, '<?= htmlspecialchars($participant['username']) ?>', <?= $participant['total_kills'] ?>)">
                                            <i class="bi bi-pencil"></i> Update Kills
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                onclick="updatePosition(<?= $participant['user_id'] ?>, '<?= htmlspecialchars($participant['username']) ?>')">
                                            <i class="bi bi-trophy"></i> Set Position
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Add this near the buttons section -->
                    <?php if ($match['status'] === 'upcoming'): ?>
                    <div class="text-end mb-4">
                        <!-- Check if match has enough participants -->
                        <?php $canStart = ($match['current_participants'] >= $match['max_participants']); ?>
                        <div class="action-buttons">
                            <?php if ($canStart): ?>
                                <button type="button" class="btn btn-success" onclick="startMatch(<?= $match['id'] ?>)">
                                    <i class="bi bi-play-fill"></i> Start Match
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-success" disabled title="Cannot start match until it's full">
                                    <i class="bi bi-play-fill"></i> Start Match (<?= $match['current_participants'] ?>/<?= $match['max_participants'] ?>)
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-danger" onclick="cancelMatch(<?= $match['id'] ?>)">
                                <i class="bi bi-x-circle"></i> Cancel Match
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Kills Modal -->
<div class="modal fade" id="updateKillsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_kills">
                <input type="hidden" name="user_id" id="kill_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Kills</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Update kills for <strong id="kill_username"></strong></p>
                    <div class="mb-3">
                        <label for="kills" class="form-label">Number of Kills</label>
                        <input type="number" class="form-control" id="kills" name="kills" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Winner Modal -->
<div class="modal fade" id="selectWinnerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="select_winner">
                <input type="hidden" name="winner_id" id="winner_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Select Winner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to select <strong id="winner_username"></strong> as the winner?</p>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This action will:
                        <ul class="mb-0">
                            <li>Mark this player as the match winner</li>
                            <li>Award the prize pool to this player</li>
                            <li>Cannot be undone</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Winner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add this modal for position selection -->
<div class="modal fade" id="updatePositionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_position">
                <input type="hidden" name="user_id" id="position_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Set position for <strong id="position_username"></strong></p>
                    <div class="mb-3">
                        <label for="position" class="form-label">Position</label>
                        <select class="form-control" id="position" name="position" required>
                            <?php
                            // Generate options based on prize distribution
                            $maxPositions = ($match['prize_distribution'] === 'top5') ? 5 : 
                                          ($match['prize_distribution'] === 'top3' ? 3 : 1);
                            for($i = 1; $i <= $maxPositions; $i++) {
                                echo "<option value='$i'>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize modals
const updateKillsModal = new bootstrap.Modal(document.getElementById('updateKillsModal'));
const positionModal = new bootstrap.Modal(document.getElementById('updatePositionModal'));

function updateKills(userId, username, currentKills) {
    document.getElementById('kill_user_id').value = userId;
    document.getElementById('kill_username').textContent = username;
    document.getElementById('kills').value = currentKills;
    updateKillsModal.show();
}

function updatePosition(userId, username) {
    document.getElementById('position_user_id').value = userId;
    document.getElementById('position_username').textContent = username;
    positionModal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('participantsTable');
    const rows = table.getElementsByTagName('tr');

    searchInput.addEventListener('keyup', function() {
        const searchTerm = searchInput.value.toLowerCase();

        // Start from index 1 to skip the header row
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const username = row.cells[1].textContent.toLowerCase();
            const gameUid = row.cells[3].textContent.toLowerCase();
            const inGameName = row.cells[4].textContent.toLowerCase();

            if (username.includes(searchTerm) || 
                gameUid.includes(searchTerm) || 
                inGameName.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
});

// Add form validation and confirmation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Add confirmation for complete match
        if (form.id === 'completeMatchForm') {
            if (!confirm('Are you sure you want to complete this match? This will finalize scores and award prizes.')) {
                e.preventDefault();
                return;
            }
        }
        
        form.classList.add('was-validated');
    });
});

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

function cancelMatch(matchId) {
    if (confirm('Are you sure you want to cancel this match? This will refund all participants and cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="cancel_match">
            <input type="hidden" name="match_id" value="${matchId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<style>
.match-info-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.prize-info {
    background: #e8f5e9;
    padding: 1rem;
    border-radius: 8px;
    display: inline-block;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}

.btn-sm {
    margin: 0.25rem;
}

.modal-content {
    border-radius: 8px;
    border: none;
}

.modal-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.alert {
    border-left: 4px solid #0dcaf0;
}

.alert ul {
    padding-left: 1.25rem;
}

/* Add styles for form validation */
.was-validated .form-control:invalid {
    border-color: #dc3545;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.was-validated .form-control:valid {
    border-color: #198754;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
</style>

<?php include '../includes/admin-footer.php'; ?> 