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
    redirect('../../register/login.php', 'Please login to register for tournaments.');
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
    $error_message = null;
    $team = null;
    $team_members = [];
    $available_members = [];
    $required_members = ($tournament['mode'] === 'Squad') ? 3 : ($tournament['mode'] === 'Duo' ? 1 : 0);

    // For team modes (Duo and Squad), check if user is a team captain or member
    if ($tournament['mode'] !== 'Solo') {
        // First check if user is a member of any team
        $stmt = $db->prepare("
            SELECT t.*, tm.role, tm.status as member_status,
            (SELECT username FROM users u 
             INNER JOIN team_members tm2 ON u.id = tm2.user_id 
             WHERE tm2.team_id = t.id AND tm2.role = 'captain' AND tm2.status = 'active'
             LIMIT 1) as captain_name
            FROM teams t
            INNER JOIN team_members tm ON t.id = tm.team_id
            WHERE tm.user_id = ? AND tm.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $team_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$team_info) {
            $error_message = "You need to be part of a team to register for {$tournament['mode']} tournaments. ";
            $error_message .= "<a href='../../pages/teams/create_team.php?redirect=tournament&id={$tournament['id']}' class='create-team-link'>Create or Join a Team</a>";
        } elseif ($team_info['role'] !== 'captain') {
            // Check if the team is already registered
            $stmt = $db->prepare("
                SELECT tr.status
                FROM tournament_registrations tr
                WHERE tr.tournament_id = ? AND tr.team_id = ?
                AND tr.status IN ('pending', 'approved')
                LIMIT 1
            ");
            $stmt->execute([$tournament['id'], $team_info['id']]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($registration) {
                $status = $registration['status'] === 'approved' ? 'registered' : 'pending approval';
                $error_message = "Your team is already {$status} for this tournament.";
            } else {
                $error_message = "Sorry, only team captains can register for {$tournament['mode']} tournaments.<br><br>";
                $error_message .= "Please contact your team captain <strong>" . htmlspecialchars($team_info['captain_name']) . "</strong> to register the team.";
                $error_message .= "<br><br><a href='details.php?id=" . $tournament['id'] . "' class='btn btn-secondary'>Go Back</a>";
            }
        } else {
            $team = $team_info; // For existing code compatibility
            
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
                $error_message = "You need at least {$required_members} team member(s) to register for {$tournament['mode']} mode.";
            } elseif (count($available_members) < $required_members) {
                $error_message = "You need {$required_members} available team member(s). Some of your members are already registered.";
            }
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
        try {
            $db->beginTransaction();

            // Check if already registered
            $stmt = $db->prepare("
                SELECT tr.* 
                FROM tournament_registrations tr
                LEFT JOIN teams t ON tr.team_id = t.id
                LEFT JOIN team_members tm ON t.id = tm.team_id
                WHERE tr.tournament_id = ? 
                AND (tr.user_id = ? OR tm.user_id = ?)
                AND (tm.status IS NULL OR tm.status = 'active')
                AND tr.status IN ('pending', 'approved')
                ORDER BY tr.registration_date DESC
                LIMIT 1
            ");
            $stmt->execute([$tournament['id'], $_SESSION['user_id'], $_SESSION['user_id']]);
            $existing_registration = $stmt->fetch();

            if ($existing_registration) {
                throw new Exception("You are already registered for this tournament.");
            }

            if ($tournament['mode'] === 'Solo') {
                // For solo tournaments, directly create the registration with user_id
                $stmt = $db->prepare("
                    INSERT INTO tournament_registrations 
                    (tournament_id, user_id, status, registration_date) 
                    VALUES (?, ?, 'approved', NOW())
                ");
                $stmt->execute([$tournament['id'], $_SESSION['user_id']]);

                // Check if user has a team and award points
                $stmt = $db->prepare("
                    SELECT t.id 
                    FROM teams t 
                    JOIN team_members tm ON t.id = tm.team_id 
                    WHERE tm.user_id = ? AND tm.status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $user_team = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user_team) {
                    // Award 5 points to the team
                    $stmt = $db->prepare("UPDATE teams SET total_score = total_score + 5 WHERE id = ?");
                    $stmt->execute([$user_team['id']]);
                }
            } else {
                // Validate selected teammates
                if (!isset($_POST['teammates']) || !is_array($_POST['teammates'])) {
                    throw new Exception("Please select your teammate(s).");
                }

                $selected_count = count($_POST['teammates']);
                if ($selected_count !== $required_members) {
                    throw new Exception("Please select exactly {$required_members} teammate(s).");
                }

                // Verify the selected teammates are valid and available
                $selected_teammates = array_filter($available_members, function($member) {
                    return in_array($member['id'], $_POST['teammates']);
                });

                if (count($selected_teammates) !== $required_members) {
                    throw new Exception("Invalid teammate selection or some teammates are already registered.");
                }

                // Register team
                $stmt = $db->prepare("
                    INSERT INTO tournament_registrations 
                    (tournament_id, team_id, status, registration_date) 
                    VALUES (?, ?, 'approved', NOW())
                ");
                $stmt->execute([$tournament['id'], $team['id']]);

                // Award points to team
                $stmt = $db->prepare("UPDATE teams SET total_score = total_score + 5 WHERE id = ?");
                $stmt->execute([$team['id']]);
            }

            // Update tournament count
            $stmt = $db->prepare("
                UPDATE tournaments 
                SET current_teams = current_teams + 1 
                WHERE id = ?
            ");
            $stmt->execute([$tournament['id']]);

            $db->commit();
            redirect('my-registrations.php', 'Registration submitted successfully!', 'success');

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

<link rel="stylesheet" href="../../assets/css/tournament/registration.css">

<main>
    <section class="registration-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="text-center mb-0"><?php echo $tournament['mode']; ?> Registration</h2>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error_message)): ?>
                                                                 <div class="alert alert-warning">
                                    <h4 class="alert-heading">Registration Requirements</h4>
                                    <p><?php echo $error_message; ?></p>
                                    <?php if ($tournament['mode'] !== 'Solo'): ?>
                                        <?php if (!isset($team_info)): ?>
                                            <hr>
                                            <p class="mb-0">
                                                To register for <?php echo $tournament['mode']; ?> tournaments:
                                                <ul>
                                                    <li>Create a team or join an existing team</li>
                                                    <li>Become the team captain</li>
                                                    <li>Have enough active team members</li>
                                                </ul>
                                            </p>
                                        <?php elseif ($team_info['role'] === 'captain' && isset($team_members)): ?>
                                            <hr>
                                            <p class="mb-0">
                                                As team captain, you need to:
                                                <ul>
                                                    <?php if (empty($team_members)): ?>
                                                        <li>Add at least <?php echo $required_members; ?> member(s) to your team</li>
                                                        <li>Wait for members to accept their invitations</li>
                                                    <?php else: ?>
                                                        <li>Ensure you have <?php echo $required_members; ?> available member(s)</li>
                                                        <li>Check if your members are already registered</li>
                                                    <?php endif; ?>
                                                </ul>
                                            </p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                 </div>
                            <?php else: ?>
                                <form method="POST" id="registrationForm">
                                    <div class="tournament-info mb-4">
                                        <h4><?php echo htmlspecialchars($tournament['name']); ?></h4>
                                        <p class="text-muted mb-3"><?php echo htmlspecialchars($tournament['game_name']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="entry-fee">
                                                <ion-icon name="ticket-outline"></ion-icon>
                                                <span><?php echo $tournament['entry_fee']; ?> Tickets</span>
                                            </div>
                                            <div class="prize-pool">
                                                <ion-icon name="trophy-outline"></ion-icon>
                                                <span><?php 
                                                    echo $tournament['prize_currency'] === 'USD' ? '$' : '₹';
                                                    echo number_format($tournament['prize_pool'], 2); 
                                                ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($tournament['mode'] !== 'Solo'): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Your Team</label>
                                            <div class="team-info">
                                                <h4><?php echo htmlspecialchars($team['name']); ?></h4>
                                                <span class="badge bg-primary">Captain</span>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Select <?php echo $required_members; ?> Teammate<?php echo $required_members > 1 ? 's' : ''; ?></label>
                                            <div class="team-members-grid">
                                                <?php foreach ($team_members as $member): ?>
                                                    <div class="member-card <?php echo $member['is_registered'] ? 'unavailable' : ''; ?>">
                                                        <input type="<?php echo $tournament['mode'] === 'Squad' ? 'checkbox' : 'radio'; ?>" 
                                                               name="teammates<?php echo $tournament['mode'] === 'Squad' ? '[]' : ''; ?>" 
                                                               value="<?php echo $member['id']; ?>"
                                                               class="member-checkbox"
                                                               <?php echo $member['is_registered'] ? 'disabled' : ''; ?>>
                                                        <?php
                                                        $profile_image = $member['profile_image'];
                                                        if (empty($profile_image)) {
                                                            $default_img_sql = "SELECT image_path FROM profile_images WHERE is_default = 1 AND is_active = 1 LIMIT 1";
                                                            $default_img_stmt = $db->prepare($default_img_sql);
                                                            $default_img_stmt->execute();
                                                            $default_img = $default_img_stmt->fetch(PDO::FETCH_ASSOC);
                                                            $profile_image = $default_img ? $default_img['image_path'] : '/KGX/assets/images/guest-icon.png';
                                                        }
                                                        if (!filter_var($profile_image, FILTER_VALIDATE_URL) && strpos($profile_image, '/KGX') !== 0) {
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
                                    <?php else: ?>
                                        <div class="confirmation-message">
                                            <p>You are about to register for this tournament as:</p>
                                            <div class="user-info">
                                                <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-grid gap-2 mt-4">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registrationForm');
    if (!form) return;

    <?php if ($tournament['mode'] === 'Squad'): ?>
    const checkboxes = form.querySelectorAll('.member-checkbox:not([disabled])');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const selectedCount = form.querySelectorAll('.member-checkbox:checked').length;
            if (selectedCount > <?php echo $required_members; ?>) {
                this.checked = false;
                alert('You can only select <?php echo $required_members; ?> teammates for squad mode.');
            }
            this.closest('.member-card').classList.toggle('selected', this.checked);
        });
    });

    form.addEventListener('submit', function(e) {
        const selectedCount = form.querySelectorAll('.member-checkbox:checked').length;
        if (selectedCount !== <?php echo $required_members; ?>) {
            e.preventDefault();
            alert('Please select exactly <?php echo $required_members; ?> teammates for squad mode.');
        }
    });
    <?php elseif ($tournament['mode'] === 'Duo'): ?>
    const radioButtons = form.querySelectorAll('.member-checkbox:not([disabled])');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.member-card').forEach(card => {
                card.classList.remove('selected');
            });
            if (this.checked) {
                this.closest('.member-card').classList.add('selected');
            }
        });
    });

    form.addEventListener('submit', function(e) {
        const selectedTeammate = form.querySelector('.member-checkbox:checked');
        if (!selectedTeammate) {
            e.preventDefault();
            alert('Please select one teammate for duo mode.');
        }
    });
    <?php endif; ?>
});
</script>

<?php 
require_once '../../includes/footer.php';
ob_end_flush();
?> 