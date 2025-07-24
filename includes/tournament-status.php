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
    $status_info = [
        'draft' => [
            'status' => 'Coming Soon',
            'class' => 'status-upcoming',
            'icon' => 'time-outline'
        ],
        'announced' => [
            'status' => 'Upcoming',
            'class' => 'status-upcoming',
            'icon' => 'time-outline'
        ],
        'registration_open' => [
            'status' => 'Registration Open',
            'class' => 'status-registration',
            'icon' => 'person-add-outline'
        ],
        'registration_closed' => [
            'status' => 'Starting Soon',
            'class' => 'status-upcoming',
            'icon' => 'hourglass-outline'
        ],
        'in_progress' => [
            'status' => 'Playing',
            'class' => 'status-playing',
            'icon' => 'play-circle-outline'
        ],
        'completed' => [
            'status' => 'Completed',
            'class' => 'status-completed',
            'icon' => 'checkmark-circle-outline'
        ],
        'archived' => [
            'status' => 'Completed',
            'class' => 'status-completed',
            'icon' => 'checkmark-circle-outline'
        ],
        'cancelled' => [
            'status' => 'Cancelled',
            'class' => 'status-cancelled',
            'icon' => 'close-circle-outline'
        ]
    ];

    $phase_info = [
        'pre_registration' => 'Registration Opens',
        'registration' => 'Registration Closes',
        'pre_tournament' => 'Tournament Starts',
        'playing' => 'Tournament Ends',
        'post_tournament' => null,
        'payment' => null,
        'finished' => null
    ];

    $status = $status_info[$tournament['status']] ?? [
        'status' => 'Unknown',
        'class' => 'status-unknown',
        'icon' => 'help-circle-outline'
    ];

    // Get the relevant date based on the current phase
    $date_value = null;
    $date_label = $phase_info[$tournament['phase']] ?? null;
    
    if ($date_label) {
        switch ($tournament['phase']) {
            case 'pre_registration':
                $date_value = $tournament['registration_open_date'];
                break;
            case 'registration':
                $date_value = $tournament['registration_close_date'];
                break;
            case 'pre_tournament':
                $date_value = $tournament['playing_start_date'];
                break;
            case 'playing':
                $date_value = $tournament['finish_date'];
                break;
        }
    }

    return [
        'status' => $status['status'],
        'class' => $status['class'],
        'icon' => $status['icon'],
        'date_label' => $date_label,
        'date_value' => $date_value ? date('M d, Y', strtotime($date_value)) : null
    ];
}

/**
 * Gets CSS styles for tournament status display
 * @return string CSS styles
 */
function getTournamentStatusStyles() {
    return '
    .status-upcoming {
        background-color: #e3f2fd;
        color: #1976d2;
    }
    .status-registration {
        background-color: #fff3e0;
        color: #e65100;
    }
    .status-playing {
        background-color: #e8f5e9;
        color: #2e7d32;
    }
    .status-completed {
        background-color: #f5f5f5;
        color: #616161;
    }
    .status-cancelled {
        background-color: #ffebee;
        color: #c62828;
    }
    .status-unknown {
        background-color: #f3f4f6;
        color: #374151;
    }
    ';
}

/**
 * Checks if a tournament is open for registration
 * @param array $tournament Tournament data
 * @return bool Whether registration is open
 */
function isTournamentRegistrationOpen($tournament) {
    return $tournament['status'] === 'registration_open';
}

/**
 * Checks if a tournament can be viewed
 * @param array $tournament Tournament data
 * @return bool Whether the tournament can be viewed
 */
function canViewTournament($tournament) {
    return $tournament['status'] !== 'draft';
}

/**
 * Gets the registration button text for a tournament
 * @param array $tournament Tournament data
 * @return string Button text
 */
function getRegistrationButtonText($tournament) {
    switch ($tournament['status']) {
        case 'draft':
        case 'announced':
            return 'Coming Soon';
        case 'registration_open':
            return 'Register Now';
        case 'registration_closed':
            return 'Registration Closed';
        case 'in_progress':
            return 'In Progress';
        case 'completed':
        case 'archived':
            return 'Tournament Ended';
        case 'cancelled':
            return 'Cancelled';
        default:
            return 'View Details';
    }
} 