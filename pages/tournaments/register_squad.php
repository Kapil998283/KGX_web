<?php
ob_start();
session_start();

require_once '../../config/database.php';
require_once __DIR__ . '/tournament_validator.php';

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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php', 'Please login to register for tournaments.');
}

// Check if tournament ID is provided
if (!isset($_GET['id'])) {
    redirect('index.php', 'Invalid tournament ID.');
}

try {
    $database = new Database();
    $db = $database->connect();

    // Validate tournament and user
    $validation = validateTournament($db, $_GET['id'], $_SESSION['user_id']);
    
    if (!$validation['valid']) {
        redirect('details.php?id=' . $_GET['id'], $validation['error']);
    }

    $tournament = $validation['tournament'];

    // Verify it's a squad tournament
    if ($tournament['mode'] !== 'Squad') {
        redirect('details.php?id=' . $tournament['id'], 'This is not a squad tournament.');
    }

    // Get user's team (must be captain)
    $stmt = $db->prepare("
        SELECT t.* 
        FROM teams t
        INNER JOIN team_members tm ON t.id = tm.team_id
        WHERE tm.user_id = ? AND tm.role = 'captain' AND tm.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        redirect('../../pages/teams/create_team.php?redirect=tournament&id=' . $tournament['id'], 
                'You must be a team captain to register for squad tournaments.');
    }

    // Get available team members
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.profile_image,
            EXISTS (
                SELECT 1 FROM tournament_registrations tr
                WHERE tr.tournament_id = ? 
                AND tr.team_id IN (
                    SELECT team_id FROM team_members 
                    WHERE user_id = u.id AND status = 'active'
                )
            ) as is_registered
        FROM users u
        INNER JOIN team_members tm ON u.id = tm.user_id
        WHERE tm.team_id = ?
        AND tm.status = 'active'
        AND tm.role = 'member'
        AND tm.user_id != ?
    ");
    $stmt->execute([$tournament['id'], $team['id'], $_SESSION['user_id']]);
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $available_members = array_filter($team_members, function($member) {
        return !$member['is_registered'];
    });

    if (empty($team_members)) {
        $error_message = "You need at least three team members to register for squad mode.";
    } elseif (count($available_members) < 3) {
        $error_message = "You need three available team members. Some of your members are already registered.";
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
        try {
            $db->beginTransaction();

            // Validate selected players
            if (!isset($_POST['teammates']) || !is_array($_POST['teammates']) || count($_POST['teammates']) !== 3) {
                throw new Exception("Please select exactly three teammates.");
            }

            // Verify the selected teammates are valid and available
            $selected_teammates = array_filter($available_members, function($member) {
                return in_array($member['id'], $_POST['teammates']);
            });

            if (count($selected_teammates) !== 3) {
                throw new Exception("Invalid teammate selection or some teammates are already registered.");
            }

            // Check if already registered and not rejected
            $stmt = $db->prepare("
                SELECT tr.* 
                FROM tournament_registrations tr
                INNER JOIN teams t ON tr.team_id = t.id
                INNER JOIN team_members tm ON t.id = tm.team_id
                WHERE tr.tournament_id = ? 
                AND tm.user_id = ?
                AND tm.status = 'active'
                AND tr.status IN ('pending', 'approved')
                ORDER BY tr.registration_date DESC
                LIMIT 1
            ");
            $stmt->execute([$tournament['id'], $_SESSION['user_id']]);
            $existing_registration = $stmt->fetch();

            if ($existing_registration) {
                throw new Exception("You are already registered for this tournament.");
            }

            // Register team with pending status
            $stmt = $db->prepare("INSERT INTO tournament_registrations (tournament_id, team_id, status, registration_date) VALUES (?, ?, 'approved', NOW())");
            $stmt->execute([$tournament['id'], $team['id']]);

            // Award 5 points for registration
            $stmt = $db->prepare("UPDATE teams SET total_score = total_score + 5 WHERE id = ?");
            $stmt->execute([$team['id']]);

            $db->commit();
            redirect('my-registrations.php', 'Registration submitted successfully! Waiting for admin approval.', 'success');

        } catch (Exception $e) {
            $db->rollBack();
            $error_message = $e->getMessage();
        }
    }

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    redirect('index.php', 'An error occurred. Please try again later.');
}

require_once '../../includes/header.php';
?>

<main>
    <section class="registration-section" style="padding: 120px 20px 60px;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="text-center mb-0">Squad Registration</h2>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-warning">
                                    <h4 class="alert-heading">Team Requirements Not Met</h4>
                                    <p><?php echo $error_message; ?></p>
                                    <hr>
                                    <p class="mb-0">
                                        As team captain, you need to:
                                        <ul>
                                            <?php if (empty($team_members)): ?>
                                                <li>Add at least three members to your team</li>
                                                <li>Wait for members to accept their invitations</li>
                                            <?php else: ?>
                                                <li>Ensure you have three available members</li>
                                                <li>Check if your members are already registered</li>
                                            <?php endif; ?>
                                        </ul>
                                    </p>
                                </div>
                            <?php else: ?>
                                <form method="POST" id="registrationForm">
                                    <div class="mb-3">
                                        <label class="form-label">Your Team</label>
                                        <div class="team-info">
                                            <h4><?php echo htmlspecialchars($team['name']); ?></h4>
                                            <span class="badge bg-primary">Captain</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Select Three Teammates</label>
                                        <div class="team-members-grid">
                                            <?php foreach ($team_members as $member): ?>
                                                <div class="member-card <?php echo $member['is_registered'] ? 'unavailable' : ''; ?>">
                                                    <input type="checkbox" name="teammates[]" 
                                                           value="<?php echo $member['id']; ?>"
                                                           class="member-checkbox"
                                                           <?php echo $member['is_registered'] ? 'disabled' : ''; ?>>
                                                    <?php
                                                    $profile_image = $member['profile_image'];
                                                    
                                                    // If user has no profile image, get the default one from profile_images table
                                                    if (empty($profile_image)) {
                                                        $default_img_sql = "SELECT image_path FROM profile_images WHERE is_default = 1 AND is_active = 1 LIMIT 1";
                                                        $default_img_stmt = $db->prepare($default_img_sql);
                                                        $default_img_stmt->execute();
                                                        $default_img = $default_img_stmt->fetch(PDO::FETCH_ASSOC);
                                                        $profile_image = $default_img ? $default_img['image_path'] : '/KGX/assets/images/guest-icon.png';
                                                    }
                                                    
                                                    // If the path is a full URL, use it as is
                                                    if (filter_var($profile_image, FILTER_VALIDATE_URL)) {
                                                        // URL is already complete, use as is
                                                    }
                                                    // If it's a local path and doesn't start with /KGX, add it
                                                    else if (strpos($profile_image, '/KGX') !== 0) {
                                                        $profile_image = '/KGX' . $profile_image;
                                                    }
                                                    ?>
                                                    <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                                                         alt="<?php echo htmlspecialchars($member['username']); ?>"
                                                         class="member-avatar"
                                                         onerror="this.src='/KGX/assets/images/guest-icon.png'">
                                                    <span class="member-name">
                                                        <?php echo htmlspecialchars($member['username']); ?>
                                                    </span>
                                                    <?php if ($member['is_registered']): ?>
                                                        <span class="badge bg-warning">Already Registered</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">Register Now</button>
                                        <a href="details.php?id=<?php echo $tournament['id']; ?>" 
                                           class="btn btn-secondary">Cancel</a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
.registration-section {
    background: var(--raisin-black-1);
    color: var(--white);
    padding: 120px 20px 60px;
}

.card {
    background: var(--raisin-black-2);
    border: none;
    border-radius: 15px;
    overflow: hidden;
    margin: 0 10px;
}

.card-header {
    background: var(--raisin-black-3);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 20px;
}

.card-body {
    padding: 20px 15px;
}

.team-info {
    background: var(--raisin-black-3);
    padding: 15px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.team-info h4 {
    margin: 0;
    color: var(--white);
}

.team-members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.member-card {
    position: relative;
    background: var(--raisin-black-3);
    border-radius: 10px;
    padding: 12px 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 160px;
}

.member-card:not(.unavailable):hover {
    background: var(--raisin-black-4);
}

.member-card.unavailable {
    opacity: 0.6;
    cursor: not-allowed;
}

.member-checkbox {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1;
}

.member-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    margin-bottom: 8px;
    object-fit: cover;
    border: 2px solid var(--raisin-black-4);
}

.member-name {
    display: block;
    font-size: 0.9rem;
    color: var(--white);
    margin-bottom: 5px;
    word-break: break-word;
    max-width: 100%;
    padding: 0 5px;
}

.member-card.selected {
    border: 2px solid var(--orange);
    transform: translateY(-2px);
}

.badge {
    position: absolute;
    bottom: 5px;
    left: 50%;
    transform: translateX(-50%);
    white-space: nowrap;
    font-size: 0.8rem;
    padding: 4px 8px;
}

.btn-primary {
    background: var(--orange);
    border: none;
    padding: 12px;
}

.btn-secondary {
    background: var(--raisin-black-4);
    border: none;
    padding: 12px;
}

@media (max-width: 576px) {
    .team-members-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    }

    .member-card {
        padding: 8px 5px;
        min-height: 140px;
    }

    .member-avatar {
        width: 50px;
        height: 50px;
    }

    .member-name {
        font-size: 0.8rem;
    }

    .badge {
        font-size: 0.7rem;
        padding: 3px 6px;
    }
}

@media (max-width: 768px) {
    .card-body {
        padding: 15px 10px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registrationForm');
    if (!form) return;

    const checkboxes = form.querySelectorAll('.member-checkbox:not([disabled])');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const selectedCount = form.querySelectorAll('.member-checkbox:checked').length;
            if (selectedCount > 3) {
                this.checked = false;
                alert('You can only select three teammates for squad mode.');
            }
            this.closest('.member-card').classList.toggle('selected', this.checked);
        });
    });

    form.addEventListener('submit', function(e) {
        const selectedCount = form.querySelectorAll('.member-checkbox:checked').length;
        if (selectedCount !== 3) {
            e.preventDefault();
            alert('Please select exactly three teammates for squad mode.');
        }
    });
});
</script>

<?php 
require_once '../../includes/footer.php';
ob_end_flush();
?> 