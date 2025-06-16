<?php

class TournamentNotifications {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Send notification to all team members
     */
    private function notifyTeamMembers($team_id, $message) {
        $stmt = $this->conn->prepare("
            SELECT user_id 
            FROM team_members 
            WHERE team_id = ?
        ");
        $stmt->execute([$team_id]);
        $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($members as $user_id) {
            $this->createNotification($user_id, $message);
        }
    }

    /**
     * Create a notification for a user
     */
    private function createNotification($user_id, $message) {
        $stmt = $this->conn->prepare("
            INSERT INTO notifications (user_id, message, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user_id, $message]);
    }

    /**
     * Notify when registration is approved/rejected
     */
    public function registrationStatus($team_id, $tournament_name, $status) {
        $message = "Your team's registration for tournament '{$tournament_name}' has been {$status}.";
        $this->notifyTeamMembers($team_id, $message);
    }

    /**
     * Notify when round is about to start
     */
    public function roundStarting($round_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                t.name as tournament_name,
                tr.name as round_name,
                tr.start_time,
                tr.map_name,
                rt.team_id
            FROM tournament_rounds tr
            JOIN tournaments t ON tr.tournament_id = t.id
            JOIN round_teams rt ON tr.id = rt.round_id
            WHERE tr.id = ?
        ");
        $stmt->execute([$round_id]);
        $round = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($round) {
            $message = "Upcoming match in '{$round['tournament_name']}' - {$round['round_name']} on map {$round['map_name']} starts at " . date('H:i', strtotime($round['start_time']));
            $this->notifyTeamMembers($round['team_id'], $message);
        }
    }

    /**
     * Notify when team qualifies for next round
     */
    public function teamQualified($team_id, $tournament_name, $round_name) {
        $message = "Congratulations! Your team has qualified for {$round_name} in tournament '{$tournament_name}'!";
        $this->notifyTeamMembers($team_id, $message);
    }

    /**
     * Notify when team is eliminated
     */
    public function teamEliminated($team_id, $tournament_name, $round_name) {
        $message = "Your team has been eliminated from {$round_name} in tournament '{$tournament_name}'. Better luck next time!";
        $this->notifyTeamMembers($team_id, $message);
    }

    /**
     * Notify about round results
     */
    public function roundResults($team_id, $tournament_name, $round_name, $placement, $kills, $points) {
        $message = "Round results for '{$tournament_name}' - {$round_name}: Placement: #{$placement}, Kills: {$kills}, Total Points: {$points}";
        $this->notifyTeamMembers($team_id, $message);
    }
} 