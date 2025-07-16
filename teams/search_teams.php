<?php
require_once '../config/database.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get search query
$search = isset($_GET['query']) ? trim($_GET['query']) : '';

try {
    // Prepare the SQL query with search functionality
    $sql = "SELECT t.*, u.username as captain_name, 
            (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as current_members 
            FROM teams t 
            LEFT JOIN users u ON t.captain_id = u.id 
            WHERE t.name LIKE :search 
            OR t.description LIKE :search 
            OR t.language LIKE :search 
            ORDER BY t.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $searchParam = "%{$search}%";
    $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
    $stmt->execute();
    
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = array_map(function($team) {
        return [
            'id' => $team['id'],
            'name' => $team['name'],
            'logo' => $team['logo'],
            'description' => $team['description'],
            'language' => $team['language'],
            'max_members' => $team['max_members'],
            'current_members' => $team['current_members'],
            'captain_name' => $team['captain_name']
        ];
    }, $teams);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'teams' => $response]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error searching teams']);
} 