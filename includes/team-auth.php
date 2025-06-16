<?php
function requireTeamCaptain($db) {
    // First make sure user is logged in
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Please login to continue.";
        header('Location: /KGX/pages/auth/login.php');
        exit();
    }

    // Check if user is a team captain
    $stmt = $db->prepare("
        SELECT t.* 
        FROM teams t
        INNER JOIN team_members tm ON t.id = tm.team_id
        WHERE tm.user_id = ? 
        AND tm.role = 'captain'
        AND tm.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        $_SESSION['error'] = "You must be a team captain to access this feature.";
        header('Location: /KGX/pages/tournaments/index.php');
        exit();
    }

    return $team;
}

function isTeamCaptain($db, $userId = null) {
    if ($userId === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $userId = $_SESSION['user_id'];
    }

    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM team_members 
        WHERE user_id = ? 
        AND role = 'captain'
        AND status = 'active'
    ");
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}
?> 