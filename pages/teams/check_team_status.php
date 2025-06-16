<?php
function checkTeamStatus($conn, $user_id) {
    // Check if user is already a captain or member of any team
    $check_sql = "SELECT t.id, t.name, tm.role 
                  FROM team_members tm 
                  JOIN teams t ON tm.team_id = t.id 
                  WHERE tm.user_id = :user_id AND t.is_active = 1";
    $stmt = $conn->prepare($check_sql);
    $stmt->execute(['user_id' => $user_id]);
    $existing_team = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_team) {
        return [
            'is_member' => true,
            'team_name' => $existing_team['name'],
            'role' => $existing_team['role'],
            'message' => $existing_team['role'] === 'captain' 
                ? 'You are already a captain of team "' . $existing_team['name'] . '"' 
                : 'You are already a member of team "' . $existing_team['name'] . '". Leave that team first to create or join another one.'
        ];
    }

    return ['is_member' => false];
} 