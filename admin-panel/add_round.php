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

    // Get tournament game type for default placement points
    $stmt = $conn->prepare("SELECT game_name FROM tournaments WHERE id = ?");
    $stmt->execute([$_POST['tournament_id']]);
    $tournament = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set default placement points based on game type
    $placement_points = [
        'PUBG' => [
            1 => 15,
            2 => 12,
            3 => 10,
            4 => 8,
            5 => 6,
            6 => 4,
            7 => 2,
            8 => 1
        ],
        'BGMI' => [
            1 => 15,
            2 => 12,
            3 => 10,
            4 => 8,
            5 => 6,
            6 => 4,
            7 => 2,
            8 => 1
        ],
        'Free Fire' => [
            1 => 12,
            2 => 9,
            3 => 8,
            4 => 7,
            5 => 6,
            6 => 5,
            7 => 4,
            8 => 3,
            9 => 2,
            10 => 1
        ],
        'Call of Duty Mobile' => [
            1 => 15,
            2 => 12,
            3 => 10,
            4 => 8,
            5 => 6
        ]
    ];

    $default_points = isset($placement_points[$tournament['game_name']]) 
        ? $placement_points[$tournament['game_name']] 
        : $placement_points['PUBG'];

    // Prepare the start time
    $start_time = date('Y-m-d') . ' ' . $_POST['start_time'];

    // Insert the round
    $stmt = $conn->prepare("
        INSERT INTO tournament_rounds (
            tournament_id, round_number, name, description,
            start_time, teams_count, qualifying_teams,
            round_format, map_name, kill_points,
            placement_points, qualification_points,
            special_rules
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, 
            'points', ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $_POST['tournament_id'],
        $_POST['round_number'],
        $_POST['name'],
        $_POST['description'],
        $start_time,
        $_POST['teams_count'],
        $_POST['qualifying_teams'],
        $_POST['map_name'],
        $_POST['kill_points'],
        json_encode($default_points),
        $_POST['qualification_points'],
        $_POST['special_rules']
    ]);

    $_SESSION['success'] = "Round added successfully!";
} catch (PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: tournament-rounds.php?id=" . $_POST['tournament_id']);
exit(); 