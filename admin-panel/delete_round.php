<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

if (!isset($_GET['id']) || !isset($_GET['tournament_id'])) {
    header('Location: tournaments.php');
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Delete the round (will cascade delete round_teams entries)
    $stmt = $conn->prepare("
        DELETE FROM tournament_rounds 
        WHERE id = ? AND tournament_id = ?
    ");
    $stmt->execute([$_GET['id'], $_GET['tournament_id']]);

    $_SESSION['success'] = "Round deleted successfully!";
} catch (PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: tournament-rounds.php?id=" . $_GET['tournament_id']);
exit(); 