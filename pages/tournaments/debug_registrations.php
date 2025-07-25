<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Please log in first.";
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

echo "<h2>Debug: My Tournament Registrations</h2>";
echo "<p><strong>User ID:</strong> " . $_SESSION['user_id'] . "</p>";
echo "<p><strong>Username:</strong> " . ($_SESSION['username'] ?? 'Not set') . "</p>";

// Test query for registrations
$stmt = $db->prepare("
    (
        -- Solo registrations
        SELECT 
            t.id as tournament_id,
            t.name as tournament_name,
            t.game_name,
            t.mode,
            t.banner_image,
            t.playing_start_date,
            t.prize_pool,
            t.prize_currency,
            t.status,
            t.phase,
            NULL as team_id,
            NULL as team_name,
            tr.registration_date,
            tr.status as registration_status,
            0 as is_captain,
            'solo' as registration_type
        FROM tournament_registrations tr
        INNER JOIN tournaments t ON tr.tournament_id = t.id
        WHERE tr.user_id = ? AND tr.team_id IS NULL
    )
    UNION ALL
    (
        -- Team registrations (as captain or member)
        SELECT 
            t.id as tournament_id,
            t.name as tournament_name,
            t.game_name,
            t.mode,
            t.banner_image,
            t.playing_start_date,
            t.prize_pool,
            t.prize_currency,
            t.status,
            t.phase,
            tm.team_id,
            team.name as team_name,
            tr.registration_date,
            tr.status as registration_status,
            CASE 
                WHEN tm.role = 'captain' THEN 1
                ELSE 0
            END as is_captain,
            'team' as registration_type
        FROM tournament_registrations tr
        INNER JOIN tournaments t ON tr.tournament_id = t.id
        INNER JOIN teams team ON tr.team_id = team.id
        INNER JOIN team_members tm ON team.id = tm.team_id
        WHERE tm.user_id = ? AND tm.status = 'active' AND tr.team_id IS NOT NULL
    )
    ORDER BY registration_date DESC
");

try {
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Results:</h3>";
    echo "<p><strong>Total registrations found:</strong> " . count($registrations) . "</p>";
    
    if (empty($registrations)) {
        echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
        echo "<strong>No registrations found.</strong><br>";
        echo "This could mean:<br>";
        echo "- User hasn't registered for any tournaments<br>";
        echo "- There's an issue with the query<br>";
        echo "- Database table structure is different than expected<br>";
        echo "</div>";
        
        // Let's check what's in the tournament_registrations table for this user
        echo "<h4>Checking tournament_registrations table directly:</h4>";
        $stmt2 = $db->prepare("SELECT * FROM tournament_registrations WHERE user_id = ? OR team_id IN (SELECT team_id FROM team_members WHERE user_id = ?)");
        $stmt2->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $raw_registrations = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Raw registrations found:</strong> " . count($raw_registrations) . "</p>";
        if (!empty($raw_registrations)) {
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr style='background-color: #f0f0f0;'>";
            echo "<th style='padding: 8px;'>ID</th>";
            echo "<th style='padding: 8px;'>Tournament ID</th>";
            echo "<th style='padding: 8px;'>User ID</th>";
            echo "<th style='padding: 8px;'>Team ID</th>";
            echo "<th style='padding: 8px;'>Status</th>";
            echo "<th style='padding: 8px;'>Registration Date</th>";
            echo "</tr>";
            foreach ($raw_registrations as $raw) {
                echo "<tr>";
                echo "<td style='padding: 8px;'>" . htmlspecialchars($raw['id']) . "</td>";
                echo "<td style='padding: 8px;'>" . htmlspecialchars($raw['tournament_id']) . "</td>";
                echo "<td style='padding: 8px;'>" . htmlspecialchars($raw['user_id'] ?? 'NULL') . "</td>";
                echo "<td style='padding: 8px;'>" . htmlspecialchars($raw['team_id'] ?? 'NULL') . "</td>";
                echo "<td style='padding: 8px;'>" . htmlspecialchars($raw['status']) . "</td>";
                echo "<td style='padding: 8px;'>" . htmlspecialchars($raw['registration_date']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th style='padding: 8px;'>Tournament</th>";
        echo "<th style='padding: 8px;'>Game</th>";
        echo "<th style='padding: 8px;'>Mode</th>";
        echo "<th style='padding: 8px;'>Type</th>";
        echo "<th style='padding: 8px;'>Team Name</th>";
        echo "<th style='padding: 8px;'>Status</th>";
        echo "<th style='padding: 8px;'>Is Captain</th>";
        echo "<th style='padding: 8px;'>Registration Date</th>";
        echo "</tr>";
        
        foreach ($registrations as $reg) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['tournament_name']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['game_name']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['mode']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['registration_type']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['team_name'] ?? 'N/A') . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['registration_status']) . "</td>";
            echo "<td style='padding: 8px;'>" . ($reg['is_captain'] ? 'Yes' : 'No') . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['registration_date']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

<style>
table {
    width: 100%;
    max-width: 1000px;
}
th, td {
    text-align: left;
    vertical-align: top;
}
</style>
