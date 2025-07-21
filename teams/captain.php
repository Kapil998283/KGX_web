<?php
require_once '../includes/header.php';
require_once '../config/database.php';
require_once '../includes/user-auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get team ID from URL parameter
$team_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Verify if user is the captain of this team
$captain_check_sql = "SELECT t.* FROM teams t 
                     INNER JOIN team_members tm ON t.id = tm.team_id 
                     WHERE t.id = :team_id AND tm.user_id = :user_id AND tm.role = 'captain'";
$stmt = $conn->prepare($captain_check_sql);
$stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    header('Location: /KGX/teams/yourteams.php');
    exit();
}

// Fetch available banners
$banners_sql = "SELECT * FROM team_banners";
$stmt = $conn->query($banners_sql);
$banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Team - <?php echo htmlspecialchars($team['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/yourteam.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="edit-container">
        <h2>Edit Team</h2>
        <div id="errorMessage" class="error-message"></div>
        
        <form id="editTeamForm">
            <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
            
            <div class="form-group">
                <label for="teamName">Team Name</label>
                <input type="text" id="teamName" name="name" value="<?php echo htmlspecialchars($team['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="teamLogo">Team Avatar URL</label>
                <div class="logo-preview-container">
                    <img src="<?php echo htmlspecialchars($team['logo']); ?>" 
                         alt="Current Team Logo" 
                         class="current-logo">
                    <input type="url" 
                           id="teamLogo" 
                           name="logo" 
                           value="<?php echo htmlspecialchars($team['logo']); ?>" 
                           placeholder="Enter new team avatar URL"
                           required>
                </div>
                <div id="logoPreview" class="logo-preview"></div>
            </div>

            <div class="form-group">
                <label>Select Team Banner</label>
                <div class="banner-grid">
                    <?php foreach ($banners as $banner): ?>
                    <div class="banner-option" onclick="selectBanner(this)">
                        <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($banner['name']); ?>">
                        <input type="radio" name="banner_id" value="<?php echo $banner['id']; ?>" 
                               <?php echo ($team['banner_id'] == $banner['id']) ? 'checked' : ''; ?> required>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="teamLanguage">Team Language</label>
                <select id="teamLanguage" name="language" required>
                    <option value="English" <?php echo ($team['language'] == 'English') ? 'selected' : ''; ?>>English</option>
                    <option value="Spanish" <?php echo ($team['language'] == 'Spanish') ? 'selected' : ''; ?>>Spanish</option>
                    <option value="French" <?php echo ($team['language'] == 'French') ? 'selected' : ''; ?>>French</option>
                    <option value="German" <?php echo ($team['language'] == 'German') ? 'selected' : ''; ?>>German</option>
                    <option value="Hindi" <?php echo ($team['language'] == 'Hindi') ? 'selected' : ''; ?>>Hindi</option>
                    <option value="Urdu" <?php echo ($team['language'] == 'Urdu') ? 'selected' : ''; ?>>Urdu</option>
                </select>
            </div>

            <div class="form-group">
                <label for="maxMembers">Maximum Team Members</label>
                <input type="number" 
                       id="maxMembers" 
                       name="max_members" 
                       value="<?php echo htmlspecialchars($team['max_members']); ?>" 
                       min="2" 
                       max="7" 
                       required>
                <small class="form-text text-muted">Minimum 2 members (including captain), maximum 7 members</small>
            </div>

            <div class="form-actions">
                <button type="button" class="delete-btn" onclick="confirmDelete()">Delete Team</button>
                <button type="submit" class="save-btn">Save Changes</button>
            </div>
        </form>
    </div>

   
    <script>
    function selectBanner(element) {
        // Remove selected class from all options
        document.querySelectorAll('.banner-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        // Add selected class to clicked option
        element.classList.add('selected');
        
        // Check the radio input
        element.querySelector('input[type="radio"]').checked = true;
    }

    // Preview new logo URL
    document.getElementById('teamLogo').addEventListener('input', function() {
        const preview = document.getElementById('logoPreview');
        const url = this.value.trim();
        
        if (url) {
            preview.style.display = 'block';
            preview.innerHTML = `<img src="${url}" alt="New Team Logo" onerror="this.src='/KGX/assets/images/default-avatar.png'">`;
        } else {
            preview.style.display = 'none';
        }
    });

    function confirmDelete() {
        if (confirm('Are you sure you want to delete this team? This action cannot be undone.')) {
            deleteTeam();
        }
    }

    function deleteTeam() {
        const formData = new FormData();
        formData.append('team_id', <?php echo $team_id; ?>);
        
        fetch('/KGX/teams/delete_team.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/KGX/teams/yourteams.php';
            } else {
                const errorDiv = document.getElementById('errorMessage');
                errorDiv.textContent = data.message || 'Error deleting team';
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('errorMessage').textContent = 'An error occurred while deleting the team';
            document.getElementById('errorMessage').style.display = 'block';
        });
    }

    document.getElementById('editTeamForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('/KGX/teams/update_team.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/KGX/teams/yourteams.php';
            } else {
                const errorDiv = document.getElementById('errorMessage');
                errorDiv.textContent = data.message || 'Error updating team';
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('errorMessage').textContent = 'An error occurred while updating the team';
            document.getElementById('errorMessage').style.display = 'block';
        });
    });
    </script>
</body>
</html>
