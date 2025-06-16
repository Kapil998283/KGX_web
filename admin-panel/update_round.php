<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tournaments.php');
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Prepare the start time
    $start_time = date('Y-m-d') . ' ' . $_POST['start_time'];

    // Update the round
    $stmt = $conn->prepare("
        UPDATE tournament_rounds SET
            round_number = ?,
            name = ?,
            description = ?,
            start_time = ?,
            teams_count = ?,
            qualifying_teams = ?,
            map_name = ?,
            kill_points = ?,
            qualification_points = ?,
            special_rules = ?,
            status = ?
        WHERE id = ? AND tournament_id = ?
    ");

    $stmt->execute([
        $_POST['round_number'],
        $_POST['name'],
        $_POST['description'],
        $start_time,
        $_POST['teams_count'],
        $_POST['qualifying_teams'],
        $_POST['map_name'],
        $_POST['kill_points'],
        $_POST['qualification_points'],
        $_POST['special_rules'],
        $_POST['status'],
        $_POST['id'],
        $_POST['tournament_id']
    ]);

    $_SESSION['success'] = "Round updated successfully!";
} catch (PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: tournament-rounds.php?id=" . $_POST['tournament_id']);
exit(); 