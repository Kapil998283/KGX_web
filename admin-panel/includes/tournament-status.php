<?php

/**
 * Updates tournament status based on current date and tournament dates
 * @param PDO $db Database connection
 */
function updateTournamentStatus($db) {
    $current_date = date('Y-m-d');
    
    $sql = "UPDATE tournaments 
            SET status = CASE
                WHEN status = 'cancelled' THEN 'cancelled'
                WHEN playing_start_date <= :current_date AND finish_date >= :current_date THEN 'ongoing'
                WHEN finish_date < :current_date THEN 'completed'
                ELSE 'upcoming'
            END,
            registration_phase = CASE
                WHEN status = 'cancelled' THEN 'closed'
                WHEN registration_open_date <= :current_date AND registration_close_date >= :current_date THEN 'open'
                WHEN playing_start_date <= :current_date AND finish_date >= :current_date THEN 'playing'
                WHEN finish_date < :current_date THEN 'finished'
                ELSE 'closed'
            END
            WHERE status != 'cancelled'";
            
    $stmt = $db->prepare($sql);
    $stmt->execute(['current_date' => $current_date]);
}

/**
 * Gets tournament status information for display
 * @param array $tournament Tournament data
 * @return array Status information with display text and CSS class
 */
function getTournamentDisplayStatus($tournament) {
    $now = new DateTime();
    $playStart = new DateTime($tournament['playing_start_date']);
    $finishDate = new DateTime($tournament['finish_date']);
    $regOpen = new DateTime($tournament['registration_open_date']);
    $regClose = new DateTime($tournament['registration_close_date']);
    
    if ($tournament['status'] === 'cancelled') {
        return ['status' => 'Cancelled', 'class' => 'status-cancelled'];
    }
    
    switch ($tournament['status']) {
        case 'ongoing':
            return ['status' => 'Playing', 'class' => 'status-playing'];
            
        case 'upcoming':
            if ($now >= $regOpen && $now <= $regClose) {
                return ['status' => 'Registration Open', 'class' => 'status-registration'];
            }
            return ['status' => 'Upcoming', 'class' => 'status-upcoming'];
            
        case 'completed':
            return ['status' => 'Completed', 'class' => 'status-completed'];
            
        default:
            return ['status' => 'Unknown', 'class' => 'status-unknown'];
    }
} 