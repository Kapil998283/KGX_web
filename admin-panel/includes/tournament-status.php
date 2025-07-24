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
 * Gets tournament status information for admin display
 * @param array $tournament Tournament data
 * @return array Status information with display text and CSS class
 */
function getTournamentDisplayStatus($tournament) {
    $now = new DateTime();
    $playStart = new DateTime($tournament['playing_start_date']);
    $finishDate = new DateTime($tournament['finish_date']);
    $regOpen = new DateTime($tournament['registration_open_date']);
    $regClose = new DateTime($tournament['registration_close_date']);
    
    // First check for cancelled status
    if ($tournament['status'] === 'cancelled') {
        return [
            'status' => 'Cancelled',
            'class' => 'status-cancelled',
            'date_label' => null,
            'date_value' => null
        ];
    }
    
    // Check current tournament phase
    if ($now >= $playStart && $now <= $finishDate) {
        return [
            'status' => 'Playing',
            'class' => 'status-playing',
            'date_label' => 'Ends',
            'date_value' => $finishDate->format('M d, Y')
        ];
    }
    
    if ($now >= $regOpen && $now <= $regClose) {
        return [
            'status' => 'Registration Open',
            'class' => 'status-registration',
            'date_label' => 'Closes',
            'date_value' => $regClose->format('M d, Y')
        ];
    }
    
    if ($now < $regOpen) {
        return [
            'status' => 'Upcoming',
            'class' => 'status-upcoming',
            'date_label' => 'Starts',
            'date_value' => $regOpen->format('M d, Y')
        ];
    }
    
    if ($now > $finishDate) {
        return [
            'status' => 'Completed',
            'class' => 'status-completed',
            'date_label' => 'Ended',
            'date_value' => $finishDate->format('M d, Y')
        ];
    }
    
    return [
        'status' => 'Unknown',
        'class' => 'status-unknown',
        'date_label' => null,
        'date_value' => null
    ];
}

/**
 * Checks if a tournament can be cancelled
 * @param array $tournament Tournament data
 * @return bool Whether the tournament can be cancelled
 */
function canCancelTournament($tournament) {
    return !in_array($tournament['status'], ['cancelled', 'completed']);
}

/**
 * Checks if a tournament can be edited
 * @param array $tournament Tournament data
 * @return bool Whether the tournament can be edited
 */
function canEditTournament($tournament) {
    return !in_array($tournament['status'], ['cancelled', 'completed']);
}

/**
 * Gets the registration phase text for display
 * @param string $phase Registration phase from database
 * @return string Human-readable registration phase
 */
function getRegistrationPhaseText($phase) {
    $phases = [
        'open' => 'Open',
        'closed' => 'Closed',
        'playing' => 'In Progress',
        'finished' => 'Finished'
    ];
    
    return isset($phases[$phase]) ? $phases[$phase] : 'Unknown';
} 