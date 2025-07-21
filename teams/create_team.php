<?php
ob_start();
session_start();

// Function to handle redirects
function redirect($url, $message = '', $type = 'error') {
    if ($message) {
        $_SESSION[$type] = $message;
    }
    if (headers_sent()) {
        echo "<script>window.location.href='$url';</script>";
        echo '<noscript><meta http-equiv="refresh" content="0;url='.$url.'"></noscript>';
        exit();
    } else {
        ob_end_clean();
        header("Location: $url");
        exit();
    }
}

require_once '../includes/header.php';
require_once '../config/database.php';
require_once '../includes/user-auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('/KGX/register/login.php', 'Please login first', 'error_message');
}

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get available banners
$sql = "SELECT * FROM team_banners WHERE is_active = 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $logo = trim($_POST['logo']);
    $banner_id = isset($_POST['banner_id']) ? (int)$_POST['banner_id'] : 1;
    $description = trim($_POST['description']);
    $language = trim($_POST['language']);
    $max_members = isset($_POST['max_members']) ? (int)$_POST['max_members'] : 0;

    // Check if user already has 2 teams
    $check_sql = "SELECT COUNT(*) FROM teams WHERE captain_id = :user_id AND is_active = 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $team_count = $check_stmt->fetchColumn();

    if ($team_count >= 2) {
        $_SESSION['error_message'] = 'You have reached the maximum limit of 2 teams per user.';
    }
    // Validate inputs
    elseif (strlen($name) < 3) {
        $_SESSION['error_message'] = 'Team name must be at least 3 characters long';
    } elseif (strlen($name) > 50) {
        $_SESSION['error_message'] = 'Team name cannot exceed 50 characters';
    } elseif ($max_members < 2 || $max_members > 7) {
        $_SESSION['error_message'] = 'Team size must be between 2 and 7 members';
    } elseif (empty($logo)) {
        $_SESSION['error_message'] = 'Team logo URL is required';
    } elseif (empty($description)) {
        $_SESSION['error_message'] = 'Team description is required';
    } elseif (empty($language)) {
        $_SESSION['error_message'] = 'Team language is required';
    } else {
        try {
            $conn->beginTransaction();

            // Check if team name already exists (case-insensitive)
            $check_name_sql = "SELECT COUNT(*) FROM teams WHERE LOWER(name) = LOWER(:name) AND is_active = 1";
            $check_name_stmt = $conn->prepare($check_name_sql);
            $check_name_stmt->execute(['name' => $name]);
            $name_exists = $check_name_stmt->fetchColumn();
            
            if ($name_exists > 0) {
                throw new Exception('This team name is already taken. Please choose a different name.');
            }

            // Insert team with is_active = 1
            $sql = "INSERT INTO teams (name, logo, banner_id, description, language, max_members, captain_id, is_active, created_at) 
                    VALUES (:name, :logo, :banner_id, :description, :language, :max_members, :captain_id, 1, NOW())";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                'name' => htmlspecialchars($name),
                'logo' => filter_var($logo, FILTER_SANITIZE_URL),
                'banner_id' => $banner_id,
                'description' => htmlspecialchars($description),
                'language' => htmlspecialchars($language),
                'max_members' => $max_members,
                'captain_id' => $_SESSION['user_id']
            ]);

            if (!$result) {
                throw new Exception('Failed to create team');
            }

            $team_id = $conn->lastInsertId();

            // Add captain as team member
            $sql = "INSERT INTO team_members (team_id, user_id, role, joined_at) 
                   VALUES (:team_id, :user_id, 'captain', NOW())";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                'team_id' => $team_id,
                'user_id' => $_SESSION['user_id']
            ]);

            if (!$result) {
                throw new Exception('Failed to add captain as team member');
            }

            $conn->commit();
            redirect('/KGX/teams/yourteams.php?team_id=' . $team_id, 'Team created successfully!', 'success_message');

        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error_message'] = $e->getMessage();
        }
    }
    
    if (isset($_SESSION['error_message'])) {
        redirect($_SERVER['PHP_SELF']);
    }
}
?>

<link rel="stylesheet" href="../assets/css/teams.css">
<link rel="stylesheet" href="../assets/css/teams/create-team.css">

<main>
    <article>
        <section class="teams-section">
            <div class="container">
                <h2 class="section-title">Create Your Team</h2>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="team-form" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label for="name">Team Name</label>
                        <input type="text" id="name" name="name" required minlength="3" maxlength="100" 
                               oninput="checkTeamName(this.value)">
                        <div id="nameStatus" class="validation-message"></div>
                    </div>

                    <div class="form-group">
                        <label for="logo">Team Logo URL</label>
                        <input type="url" id="logo" name="logo" required>
                    </div>

                    <div class="form-group">
                        <label>Team Banner</label>
                        <div class="banner-selection-grid">
                            <?php foreach ($banners as $banner): ?>
                                <div class="banner-radio-option">
                                    <input type="radio" 
                                           name="banner_id" 
                                           id="banner_<?php echo $banner['id']; ?>" 
                                           value="<?php echo $banner['id']; ?>" 
                                           <?php echo ($banner === reset($banners)) ? 'checked' : ''; ?>>
                                    <label for="banner_<?php echo $banner['id']; ?>" class="banner-label">
                                        <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($banner['name']); ?>">
                                        <div class="banner-select-indicator">
                                            <i class="bi bi-check-circle-fill"></i>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="bannerError" class="validation-message"></div>
                    </div>

                    <div class="form-group">
                        <label for="description">Team Description</label>
                        <textarea id="description" name="description" rows="4" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="language">Team Language</label>
                        <select id="language" name="language" required>
                            <option value="">Select Language</option>
                            <option value="English">English</option>
                            <option value="Hindi">Hindi</option>
                            <option value="Arabic">Arabic</option>
                            <option value="Urdu">Urdu</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="max_members">Maximum Team Members</label>
                        <select id="max_members" name="max_members" required>
                            <option value="">Select Members</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                            <option value="6">6</option>
                            <option value="7">7</option>
                        </select>
                        <small class="form-text text-muted">Maximum 7 members (8 total including captain)</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Team</button>
                </form>
            </div>
        </section>
    </article>
</main>

<script>
let checkTimeout;
let isValidName = false;

function checkTeamName(name) {
    const nameStatus = document.getElementById('nameStatus');
    
    // Clear previous timeout and status
    clearTimeout(checkTimeout);
    nameStatus.textContent = '';
    nameStatus.className = 'validation-message';
    isValidName = false;
    
    if (name.length < 3) {
        nameStatus.textContent = '✕ Team name must be at least 3 characters long';
        nameStatus.classList.add('error');
        return;
    }
    
    // Show checking message
    nameStatus.textContent = 'Checking availability...';
    
    // Wait for user to stop typing before checking
    checkTimeout = setTimeout(() => {
        fetch('check_team_name.php?name=' + encodeURIComponent(name))
            .then(response => response.text())
            .then(result => {
                result = result.trim();
                if (result === 'available') {
                    nameStatus.textContent = '✓ Team name is available';
                    nameStatus.classList.add('success');
                    isValidName = true;
                } else if (result === 'taken') {
                    nameStatus.textContent = '✕ This team name is already taken';
                    nameStatus.classList.add('error');
                    isValidName = false;
                } else {
                    nameStatus.textContent = '✕ ' + result;
                    nameStatus.classList.add('error');
                    isValidName = false;
                }
            })
            .catch(error => {
                nameStatus.textContent = '✕ Error checking team name';
                nameStatus.classList.add('error');
                isValidName = false;
            });
    }, 500);
}

function validateForm() {
    const nameStatus = document.getElementById('nameStatus');
    let isValid = true;

    // Check team name
    if (!isValidName || nameStatus.classList.contains('error')) {
        alert('Please choose a valid team name');
        return false;
    }

    // Check other required fields
    const requiredFields = ['logo', 'description', 'language', 'max_members'];
    for (const field of requiredFields) {
        const element = document.getElementById(field);
        if (!element.value.trim()) {
            alert(`Please fill in the ${field.replace('_', ' ')}`);
            element.focus();
            return false;
        }
    }

    return true;
}
</script>

<script src="../assets/js/teams.js"></script>

<?php require_once '../includes/footer.php'; ?> 