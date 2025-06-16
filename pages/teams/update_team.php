<?php
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';

header('Content-Type: application/json');

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get POST data
$team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
$name = trim($_POST['name'] ?? '');
$logo = trim($_POST['logo'] ?? '');
$banner_id = isset($_POST['banner_id']) ? (int)$_POST['banner_id'] : 0;
$language = trim($_POST['language'] ?? '');

// Validate inputs
if (!$team_id || !$name || !$logo || !$banner_id || !$language) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Verify if user is the captain of this team
$captain_check_sql = "SELECT t.* FROM teams t 
                     INNER JOIN team_members tm ON t.id = tm.team_id 
                     WHERE t.id = :team_id AND tm.user_id = :user_id AND tm.role = 'captain'";
$stmt = $conn->prepare($captain_check_sql);
$stmt->execute([
    'team_id' => $team_id,
    'user_id' => $_SESSION['user_id']
]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to edit this team']);
    exit;
}

try {
    // Check if team name already exists (excluding current team)
    $check_name_sql = "SELECT COUNT(*) FROM teams WHERE LOWER(name) = LOWER(:name) AND id != :team_id AND is_active = 1";
    $check_name_stmt = $conn->prepare($check_name_sql);
    $check_name_stmt->execute([
        'name' => $name,
        'team_id' => $team_id
    ]);
    
    if ($check_name_stmt->fetchColumn() > 0) {
        throw new Exception('This team name is already taken. Please choose a different name.');
    }

    // Update team
    $update_sql = "UPDATE teams SET 
                   name = :name,
                   logo = :logo,
                   banner_id = :banner_id,
                   language = :language
                   WHERE id = :team_id";
    
    $stmt = $conn->prepare($update_sql);
    $result = $stmt->execute([
        'name' => htmlspecialchars($name),
        'logo' => filter_var($logo, FILTER_SANITIZE_URL),
        'banner_id' => $banner_id,
        'language' => htmlspecialchars($language),
        'team_id' => $team_id
    ]);

    if (!$result) {
        throw new Exception('Failed to update team');
    }

    echo json_encode(['success' => true, 'message' => 'Team updated successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 