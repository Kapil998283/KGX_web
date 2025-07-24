<?php

/**
 * Updates tournament status based on current date and tournament dates
 * @param PDO $db Database connection
 */
function adminUpdateTournamentStatus($db) {
    try {
        $current_date = date('Y-m-d');
        
        $sql = "UPDATE tournaments 
                SET status = CASE
                    WHEN status = 'cancelled' THEN 'cancelled'
                    WHEN playing_start_date <= :current_date AND finish_date >= :current_date THEN 'in_progress'
                    WHEN registration_open_date <= :current_date AND registration_close_date >= :current_date THEN 'registration_open'
                    WHEN registration_close_date < :current_date AND playing_start_date > :current_date THEN 'registration_closed'
                    WHEN finish_date < :current_date THEN 'completed'
                    ELSE 'announced'
                END,
                phase = CASE
                    WHEN status = 'cancelled' THEN 'finished'
                    WHEN :current_date < registration_open_date THEN 'pre_registration'
                    WHEN :current_date BETWEEN registration_open_date AND registration_close_date THEN 'registration'
                    WHEN :current_date BETWEEN registration_close_date AND playing_start_date THEN 'pre_tournament'
                    WHEN :current_date BETWEEN playing_start_date AND finish_date THEN 'playing'
                    WHEN :current_date > finish_date THEN 'finished'
                    ELSE 'pre_registration'
                END,
                updated_at = CURRENT_TIMESTAMP
                WHERE status != 'cancelled'";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['current_date' => $current_date]);
    } catch (PDOException $e) {
        error_log("Error updating tournament statuses: " . $e->getMessage());
    }
}

/**
 * Gets tournament status information for admin display
 * @param array $tournament Tournament data
 * @return array Status information with display text and CSS class
 */
function adminGetTournamentDisplayStatus($tournament) {
    $status_info = [
        'draft' => [
            'status' => 'Draft',
            'class' => 'status-draft',
            'icon' => 'document-outline'
        ],
        'announced' => [
            'status' => 'Announced',
            'class' => 'status-announced',
            'icon' => 'megaphone-outline'
        ],
        'registration_open' => [
            'status' => 'Registration Open',
            'class' => 'status-registration',
            'icon' => 'person-add-outline'
        ],
        'registration_closed' => [
            'status' => 'Registration Closed',
            'class' => 'status-registration-closed',
            'icon' => 'lock-closed-outline'
        ],
        'in_progress' => [
            'status' => 'In Progress',
            'class' => 'status-playing',
            'icon' => 'play-circle-outline'
        ],
        'completed' => [
            'status' => 'Completed',
            'class' => 'status-completed',
            'icon' => 'checkmark-circle-outline'
        ],
        'archived' => [
            'status' => 'Archived',
            'class' => 'status-archived',
            'icon' => 'archive-outline'
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
        'post_tournament' => 'Payment Date',
        'payment' => 'Payment Due',
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
            case 'post_tournament':
            case 'payment':
                $date_value = $tournament['payment_date'];
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
 * Checks if a tournament can be cancelled
 * @param array $tournament Tournament data
 * @return bool Whether the tournament can be cancelled
 */
function adminCanCancelTournament($tournament) {
    $non_cancellable = ['completed', 'archived', 'cancelled'];
    return !in_array($tournament['status'], $non_cancellable);
}

/**
 * Checks if a tournament can be edited
 * @param array $tournament Tournament data
 * @return bool Whether the tournament can be edited
 */
function adminCanEditTournament($tournament) {
    $non_editable = ['completed', 'archived', 'cancelled'];
    return !in_array($tournament['status'], $non_editable);
}

/**
 * Gets CSS styles for tournament status display
 * @return string CSS styles
 */
function getAdminTournamentStatusStyles() {
    return '
    .status-draft {
        background-color: #e2e8f0;
        color: #475569;
    }
    .status-announced {
        background-color: #dbeafe;
        color: #1e40af;
    }
    .status-registration {
        background-color: #dcfce7;
        color: #166534;
    }
    .status-registration-closed {
        background-color: #fef3c7;
        color: #92400e;
    }
    .status-playing {
        background-color: #fee2e2;
        color: #991b1b;
    }
    .status-completed {
        background-color: #f3e8ff;
        color: #6b21a8;
    }
    .status-archived {
        background-color: #f5f5f5;
        color: #525252;
    }
    .status-cancelled {
        background-color: #fecaca;
        color: #991b1b;
    }
    .status-unknown {
        background-color: #f3f4f6;
        color: #374151;
    }
    ';
} 