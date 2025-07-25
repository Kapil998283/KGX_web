<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

// Test if basic functionality works
$database = new Database();
$conn = $database->connect();

// Test query to check if we can see any pending registrations
echo "<h3>Testing Tournament Registration System</h3>";
echo "<h4>Pending Registrations:</h4>";

try {
    // Check for team registrations
    $stmt = $conn->prepare("
        SELECT 
            tr.id as reg_id,
            tr.tournament_id,
            tr.team_id,
            tr.user_id,
            tr.status,
            tr.registration_date,
            t.name as tournament_name,
            teams.name as team_name,
            u.username
        FROM tournament_registrations tr
        LEFT JOIN tournaments t ON tr.tournament_id = t.id
        LEFT JOIN teams ON tr.team_id = teams.id
        LEFT JOIN users u ON tr.user_id = u.id
        WHERE tr.status = 'pending'
        ORDER BY tr.registration_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($registrations)) {
        echo "<p>No pending registrations found.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background-color: #f0f0f0;'>
                <th style='padding: 8px;'>Reg ID</th>
                <th style='padding: 8px;'>Tournament</th>
                <th style='padding: 8px;'>Type</th>
                <th style='padding: 8px;'>Name</th>
                <th style='padding: 8px;'>Status</th>
                <th style='padding: 8px;'>Date</th>
                <th style='padding: 8px;'>Test Actions</th>
              </tr>";
        
        foreach ($registrations as $reg) {
            $type = $reg['team_id'] ? 'Team' : 'Solo';
            $name = $reg['team_id'] ? $reg['team_name'] : $reg['username'];
            $id = $reg['team_id'] ? $reg['team_id'] : $reg['user_id'];
            
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['reg_id']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['tournament_name']) . "</td>";
            echo "<td style='padding: 8px;'>" . $type . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($name) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['status']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($reg['registration_date']) . "</td>";
            echo "<td style='padding: 8px;'>";
            echo "<button onclick=\"testUpdate('$id', 'approved', '{$reg['tournament_id']}', '" . strtolower($type) . "')\">Approve</button> ";
            echo "<button onclick=\"testUpdate('$id', 'rejected', '{$reg['tournament_id']}', '" . strtolower($type) . "')\">Reject</button>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<div id="testResult" style="margin: 20px 0; padding: 10px; border: 1px solid #ccc; display: none;"></div>

<script>
function testUpdate(id, status, tournamentId, type) {
    const resultDiv = document.getElementById('testResult');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = 'Testing...';
    resultDiv.style.backgroundColor = '#fff3cd';
    
    const formData = new FormData();
    if (type === 'solo') {
        formData.append('user_id', id);
    } else {
        formData.append('team_id', id);
    }
    formData.append('tournament_id', tournamentId);
    formData.append('status', status);
    
    console.log('Sending request:', {
        id: id,
        status: status,
        tournamentId: tournamentId,
        type: type
    });
    
    fetch('update_registration.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers.get('content-type'));
        
        return response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 200) + '...');
            }
        });
    })
    .then(data => {
        console.log('Parsed data:', data);
        if (data.success) {
            resultDiv.innerHTML = '<strong>Success:</strong> ' + (data.message || 'Registration updated successfully');
            resultDiv.style.backgroundColor = '#d4edda';
            // Refresh page after 2 seconds
            setTimeout(() => location.reload(), 2000);
        } else {
            resultDiv.innerHTML = '<strong>Error:</strong> ' + (data.error || 'Unknown error');
            resultDiv.style.backgroundColor = '#f8d7da';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        resultDiv.innerHTML = '<strong>Error:</strong> ' + error.message;
        resultDiv.style.backgroundColor = '#f8d7da';
    });
}
</script>

<style>
button {
    padding: 5px 10px;
    margin: 2px;
    border: 1px solid #ccc;
    background: #f8f9fa;
    cursor: pointer;
}
button:hover {
    background: #e9ecef;
}
table {
    width: 100%;
    max-width: 1000px;
}
th, td {
    text-align: left;
    vertical-align: top;
}
</style>
