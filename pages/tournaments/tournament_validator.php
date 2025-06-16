<?php

function validateTournament($db, $tournament_id, $user_id) {
    try {
        // Get tournament details
        $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_id]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tournament) {
            return ['valid' => false, 'error' => 'Tournament not found.'];
        }

        // Check registration phase
        if ($tournament['registration_phase'] !== 'open') {
            return ['valid' => false, 'error' => 'Tournament registration is not available.'];
        }

        // Check if tournament is full
        if ($tournament['current_teams'] >= $tournament['max_teams']) {
            return ['valid' => false, 'error' => 'This tournament is full.'];
        }

        // Check if user has enough tickets
        $stmt = $db->prepare("SELECT tickets FROM user_tickets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_tickets = $stmt->fetch();

        if (!$user_tickets || $user_tickets['tickets'] < $tournament['entry_fee']) {
            return [
                'valid' => false, 
                'error' => "You need {$tournament['entry_fee']} tickets to register."
            ];
        }

        // Check if user is already registered
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM tournament_registrations tr
            INNER JOIN team_members tm ON tr.team_id = tm.team_id
            WHERE tr.tournament_id = ? AND tm.user_id = ? AND tm.status = 'active'
        ");
        $stmt->execute([$tournament_id, $user_id]);
        
        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            return ['valid' => false, 'error' => 'You are already registered for this tournament.'];
        }

        return ['valid' => true, 'tournament' => $tournament];
    } catch (PDOException $e) {
        error_log("Tournament validation error: " . $e->getMessage());
        return ['valid' => false, 'error' => 'An error occurred while validating tournament.'];
    }
} 