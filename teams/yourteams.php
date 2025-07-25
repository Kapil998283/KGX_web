<?php
require_once '../includes/header.php';
require_once '../config/database.php';
require_once '../includes/user-auth.php';

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /KGX/register/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$database = new Database();
$conn = $database->connect();

// Check if user is in any team
$check_team_sql = "SELECT COUNT(*) as team_count FROM team_members WHERE user_id = :user_id";
$stmt = $conn->prepare($check_team_sql);
$stmt->execute(['user_id' => $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$has_teams = $result['team_count'] > 0;

// Only proceed with team data if user has teams
$teams = [];
if ($has_teams) {
    // Get user's teams and their roles
    try {
        $sql = "SELECT t.*, tm.role, 
                (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count,
                t.total_score
                FROM teams t
                JOIN team_members tm ON t.id = tm.team_id
                WHERE tm.user_id = :user_id AND t.is_active = 1 AND tm.status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching teams: " . $e->getMessage());
    }
}

// Get active tab from URL parameter or default to players
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'players';
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// Get pending join requests for teams where user is captain
$pending_requests = [];
if (!empty($teams)) {
    $team_ids = array_column($teams, 'id');
    $placeholders = str_repeat('?,', count($team_ids) - 1) . '?';
    
    $sql = "SELECT tjr.*, u.username, u.profile_image, t.name as team_name
            FROM team_join_requests tjr
            JOIN users u ON tjr.user_id = u.id
            JOIN teams t ON tjr.team_id = t.id
            WHERE tjr.team_id IN ($placeholders) AND tjr.status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->execute($team_ids);
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check for messages
$success_message = '';
$error_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Teams</title>
    <link rel="stylesheet" href="../assets/css/teams/team.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<main>
  <article>
    <!-- Success/Error Message Display -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success" style="margin: 20px auto; max-width: 800px; padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" style="margin: 20px auto; max-width: 800px; padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$has_teams): ?>
        <div class="no-teams-container">
            <div class="no-teams-content">
                <i class="fas fa-users-slash no-teams-icon"></i>
                <h2>No Teams Found</h2>
                <p>You haven't joined or created any teams yet.</p>
                <div class="no-teams-actions">
                    <a href="index.php" class="action-btn join-btn">
                        <i class="fas fa-user-plus"></i> Join a Team
                    </a>
                    <a href="create_team.php" class="action-btn create-btn">
                        <i class="fas fa-plus-circle"></i> Create New Team
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($teams as $team): ?>
            <section class="team-banner">
                <div class="banner-container">
                    <?php
                    // Get banner path from team_banners table
                    $banner_sql = "SELECT image_path FROM team_banners WHERE id = :banner_id";
                    $banner_stmt = $conn->prepare($banner_sql);
                    $banner_stmt->execute(['banner_id' => $team['banner_id']]);
                    $banner = $banner_stmt->fetch(PDO::FETCH_ASSOC);
                    $banner_path = $banner ? $banner['image_path'] : '/KGX/assets/images/hero-banner1.png';
                    ?>
                    <img src="<?php echo htmlspecialchars($banner_path); ?>" 
                         alt="Team Background" class="banner-bg" />
                    
                    <div class="team-content">
                        <div class="team-avatar">
                            <img src="<?php echo htmlspecialchars($team['logo']); ?>" 
                                 alt="Team Avatar"
                                 onerror="this.src='/KGX/assets/images/character-2.png'" />
                        </div>
                
                        <div class="team-details">
                            <h2><?php echo htmlspecialchars($team['name']); ?></h2>
                            <div class="team-meta">
                                <span><i class="fas fa-users"></i> <?php echo $team['member_count']; ?> players</span>
                                <span><i class="fas fa-language"></i> <?php echo htmlspecialchars($team['language']); ?></span>
                                <span><i class="fas fa-id-card"></i> Team ID: <?php echo $team['id']; ?></span>
                                <?php
                                // Check if user is a member or captain of this team
                                $is_team_member = false;
                                foreach ($teams as $user_team) {
                                    if ($user_team['id'] === $team['id']) {
                                        $is_team_member = true;
                                        break;
                                    }
                                }
                                if ($is_team_member):
                                ?>
                                <span><i class="fas fa-trophy"></i> Score: <?php echo number_format($team['total_score']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                
                    <?php if ($team['role'] === 'captain'): ?>
                        <a href="/KGX/teams/captain.php?id=<?php echo $team['id']; ?>" class="edit-btn">
                            <i class="fas fa-edit"></i> Edit Team
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Tournament Section -->
            <section class="tournament-section">
                <div class="tabs">
                    <a href="yourteams.php?tab=players&team_id=<?php echo $team['id']; ?>" 
                       class="tab <?php echo $active_tab === 'players' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Players
                    </a>
                    <a href="yourteams.php?tab=tournament&team_id=<?php echo $team['id']; ?>" 
                       class="tab <?php echo $active_tab === 'tournament' ? 'active' : ''; ?>">
                        <i class="fas fa-trophy"></i> Tournament
                    </a>
                    <?php if ($team['role'] === 'captain'): ?>
                        <a href="yourteams.php?tab=requests&team_id=<?php echo $team['id']; ?>" 
                           class="tab <?php echo $active_tab === 'requests' ? 'active' : ''; ?>">
                            <i class="fas fa-user-plus"></i> Requests 
                            <?php if (!empty($pending_requests)): ?>
                                <span class="badge"><?php echo count($pending_requests); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Player Content -->
                <div class="tab-content <?php echo $active_tab === 'players' ? 'active' : ''; ?>" id="playersContent">
                    <div class="player-list">
                        <?php
                        // Get team members with proper ordering (captain first, then by join date)
                        $sql = "SELECT 
                            u.id as user_id,
                            u.username,
                            u.profile_image,
                            tm.role,
                            tm.joined_at,
                            CASE 
                                WHEN tm.role = 'captain' THEN 1 
                                ELSE 2 
                            END as role_order
                            FROM team_members tm 
                            JOIN users u ON tm.user_id = u.id 
                            WHERE tm.team_id = :team_id AND tm.status = 'active'
                            ORDER BY role_order ASC, tm.joined_at ASC";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute(['team_id' => $team['id']]);
                        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $member_number = 0; // Initialize counter
                        foreach ($members as $member):
                            $member_number++; // Increment counter for each member
                        ?>
                            <div class="player-card <?php echo $member['role'] === 'captain' ? 'captain-card' : ''; ?>">
                                <div class="member-number">#<?php echo $member_number; ?></div>
                                <div class="player-avatar-wrapper">
                                    <?php
                                    $profile_image = $member['profile_image'];
                                    
                                    // If user has no profile image, get the default one from profile_images table
                                    if (empty($profile_image)) {
                                        $default_img_sql = "SELECT image_path FROM profile_images WHERE is_default = 1 AND is_active = 1 LIMIT 1";
                                        $default_img_stmt = $conn->prepare($default_img_sql);
                                        $default_img_stmt->execute();
                                        $default_img = $default_img_stmt->fetch(PDO::FETCH_ASSOC);
                                        $profile_image = $default_img ? $default_img['image_path'] : '/KGX/assets/images/guest-icon.png';
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                                         alt="<?php echo htmlspecialchars($member['username']); ?>" 
                                         class="player-avatar"
                                         onerror="this.src='/KGX/assets/images/guest-icon.png'">
                                    <?php if ($member['role'] === 'captain'): ?>
                                        <div class="captain-badge">
                                            <i class="fas fa-crown"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="player-info">
                                    <h3 class="player-name">
                                        <?php echo htmlspecialchars($member['username']); ?>
                                    </h3>
                                    <p class="role <?php echo $member['role']; ?>">
                                        <?php echo ucfirst($member['role']); ?>
                                    </p>
                                    <p class="join-date">
                                        <i class="fas fa-calendar"></i>
                                        Joined: <?php echo date('M d, Y', strtotime($member['joined_at'])); ?>
                                    </p>
                                </div>
                                <?php if ($team['role'] === 'captain' && $member['role'] !== 'captain'): ?>
                                    <form action="remove_member.php" method="POST" class="remove-member-form">
                                        <input type="hidden" name="member_id" value="<?php echo $member['user_id']; ?>">
                                        <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                        <button type="submit" class="remove-member-btn" onclick="return confirm('Are you sure you want to remove this member?');">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button class="details-btn" onclick="showGameProfile(<?php echo $member['user_id']; ?>)">
                                    Details
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Game Profile Modal -->
                <div id="gameProfileModal" class="modal">
                    <div class="modal-content">
                        <span class="close-modal">&times;</span>
                        <div id="gameProfileContent">
                            <!-- Content will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Tournament Content -->
                <div class="tab-content <?php echo $active_tab === 'tournament' ? 'active' : ''; ?>" id="tournamentContent">
                    <div class="tournaments-section">
                        <?php
                        // Fetch tournaments where the team is registered (duo/squad)
                        $stmt = $conn->prepare("
                            SELECT 
                                t.*, 
                                tr.registration_date, 
                                tr.status as registration_status, 
                                NULL as solo_user_id, 
                                NULL as solo_username
                            FROM tournaments t
                            INNER JOIN tournament_registrations tr ON t.id = tr.tournament_id
                            WHERE tr.team_id = ?
                            ORDER BY t.playing_start_date DESC
                        ");
                        $stmt->execute([$team['id']]);
                        $team_tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Fetch all team members
                        $stmt = $conn->prepare("SELECT u.id, u.username FROM team_members tm INNER JOIN users u ON tm.user_id = u.id WHERE tm.team_id = ? AND tm.status = 'active'");
                        $stmt->execute([$team['id']]);
                        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $member_ids = array_column($members, 'id');

                        // Fetch solo tournaments for all team members
                        $solo_tournaments = [];
                        if (!empty($member_ids)) {
                            $in = str_repeat('?,', count($member_ids) - 1) . '?';
                            $sql = "
                                SELECT t.*, tr.registration_date, tr.status as registration_status, tr.user_id as solo_user_id, u.username as solo_username
                                FROM tournaments t
                                INNER JOIN tournament_registrations tr ON t.id = tr.tournament_id
                                INNER JOIN users u ON tr.user_id = u.id
                                WHERE tr.user_id IN ($in) AND t.mode = 'Solo'
                                ORDER BY t.playing_start_date DESC
                            ";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute($member_ids);
                            $solo_tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        }

                        // Merge and deduplicate tournaments (avoid showing the same tournament twice if registered both as team and solo)
                        $all_tournaments = $team_tournaments;
                        foreach ($solo_tournaments as $solo) {
                            // Only add if not already in team_tournaments
                            $already = false;
                            foreach ($team_tournaments as $tt) {
                                if ($tt['id'] == $solo['id']) {
                                    $already = true;
                                    break;
                                }
                            }
                            if (!$already) {
                                $all_tournaments[] = $solo;
                            }
                        }
                        ?>

                        <?php if (!empty($all_tournaments)): ?>
                            <div class="row g-4">
                                <?php foreach ($all_tournaments as $tournament): ?>
                                    <div class="col-md-6">
                                        <div class="tournament-card">
                                            <div class="card-banner">
                                                <img src="<?php echo htmlspecialchars($tournament['banner_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($tournament['name']); ?>" 
                                                     class="tournament-banner">
                                                <div class="tournament-meta">
                                                    <div class="prize-pool">
                                                        <ion-icon name="trophy-outline"></ion-icon>
                                                        <span><?php 
                                                            echo $tournament['prize_currency'] === 'USD' ? '$' : '₹';
                                                            echo number_format($tournament['prize_pool'], 2); 
                                                        ?></span>
                                                    </div>
                                                    <div class="registration-status">
                                                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                                                        <span><?php echo ucfirst($tournament['registration_status']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-content">
                                                <h3 class="tournament-title"><?php echo htmlspecialchars($tournament['name']); ?></h3>
                                                <p class="game-name"><?php echo htmlspecialchars($tournament['game_name']); ?></p>
                                                <div class="tournament-info">
                                                    <div class="info-item">
                                                        <ion-icon name="people-outline"></ion-icon>
                                                        <span><?php echo $tournament['current_teams']; ?>/<?php echo $tournament['max_teams']; ?> Teams</span>
                                                    </div>
                                                    <div class="info-item">
                                                        <ion-icon name="game-controller-outline"></ion-icon>
                                                        <span><?php echo htmlspecialchars($tournament['mode']); ?></span>
                                                    </div>
                                                </div>
                                                <div class="tournament-dates">
                                                    <div class="registration-date">
                                                        Registered: <?php echo date('M d, Y', strtotime($tournament['registration_date'])); ?>
                                                    </div>
                                                    <div class="tournament-starts">
                                                        Starts: <?php echo date('M d, Y', strtotime($tournament['playing_start_date'])); ?>
                                                    </div>
                                                </div>
                                                <?php if ($tournament['mode'] === 'Solo' && !empty($tournament['solo_username'])): ?>
                                                    <div class="solo-registered-by">
                                                        <ion-icon name="person-outline"></ion-icon>
                                                        <span>Registered by: <strong><?php echo htmlspecialchars($tournament['solo_username']); ?></strong> (Solo)</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="tournament-phase">
                                                    <?php
                                                    $phase_class = '';
                                                    $phase_text = '';
                                                    switch ($tournament['registration_phase'] ?? $tournament['phase']) {
                                                        case 'open':
                                                            $phase_class = 'bg-success';
                                                            $phase_text = 'Registration Open';
                                                            break;
                                                        case 'closed':
                                                            $phase_class = 'bg-warning';
                                                            $phase_text = 'Registration Closed';
                                                            break;
                                                        case 'playing':
                                                            $phase_class = 'bg-primary';
                                                            $phase_text = 'Tournament Active';
                                                            break;
                                                        default:
                                                            $phase_class = 'bg-secondary';
                                                            $phase_text = 'Tournament Ended';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $phase_class; ?>"><?php echo $phase_text; ?></span>
                                                </div>
                                                <div class="card-actions">
                                                    <a href="../tournaments/details.php?id=<?php echo $tournament['id']; ?>" 
                                                       class="btn btn-primary">View Details</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-tournaments">
                                <ion-icon name="trophy-outline" class="large-icon"></ion-icon>
                                <h3>No Tournaments Yet</h3>
                                <p>Your team hasn't registered for any tournaments.</p>
                                <a href="../tournaments/index.php" class="btn btn-primary mt-3">Browse Tournaments</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Requests Content -->
                <?php if ($team['role'] === 'captain'): ?>
                    <div class="tab-content <?php echo $active_tab === 'requests' ? 'active' : ''; ?>" id="requestsContent">
                        <div class="requests-list">
                            <?php
                            // Get pending requests for this team with user details
                            $pending_requests_sql = "SELECT tjr.*, u.username, u.profile_image, tjr.created_at
                                   FROM team_join_requests tjr
                                   JOIN users u ON tjr.user_id = u.id
                                   WHERE tjr.team_id = :team_id 
                                   AND tjr.status = 'pending'
                                   ORDER BY tjr.created_at DESC";
                            $stmt = $conn->prepare($pending_requests_sql);
                            $stmt->execute(['team_id' => $team['id']]);
                            $team_pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>

                            <?php if (!empty($team_pending_requests)): ?>
                                <?php foreach ($team_pending_requests as $request): ?>
                                    <?php
                                    // Handle profile image
                                    $profile_image = $request['profile_image'];
                                    
                                    // If user has no profile image, get the default one from profile_images table
                                    if (empty($profile_image)) {
                                        $default_img_sql = "SELECT image_path FROM profile_images WHERE is_default = 1 AND is_active = 1 LIMIT 1";
                                        $default_img_stmt = $conn->prepare($default_img_sql);
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
                                    <div class="request-card">
                                        <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                                             alt="<?php echo htmlspecialchars($request['username']); ?>" 
                                             class="request-avatar"
                                             onerror="this.src='/KGX/assets/images/guest-icon.png'">
                                        <div class="request-info">
                                            <h3><?php echo htmlspecialchars($request['username']); ?></h3>
                                            <p class="request-date">Requested: <?php echo date('M d, Y', strtotime($request['created_at'])); ?></p>
                                        </div>
                                        <div class="request-actions">
                                            <form action="handle_request.php" method="POST">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <input type="hidden" name="active_tab" value="requests">
                                                <button type="submit" class="accept-btn">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                            </form>
                                            <form action="handle_request.php" method="POST">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <input type="hidden" name="active_tab" value="requests">
                                                <button type="submit" class="reject-btn">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-requests">
                                    <i class="fas fa-user-plus"></i>
                                    <h3>No Pending Requests</h3>
                                    <p>There are no pending join requests for your team at the moment.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
  </article>
</main>

<?php require_once '../includes/footer.php'; ?>

<script>
function showGameProfile(userId) {
    const modal = document.getElementById('gameProfileModal');
    const content = document.getElementById('gameProfileContent');
    const closeBtn = document.querySelector('.close-modal');

    // Show loading state
    content.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    modal.style.display = 'block';

    // Fetch game profile data
    fetch(`get_game_profile.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = `
                    <h3 style="margin-bottom: 20px; color: #fff;">Game Profile</h3>
                    <div style="margin-bottom: 15px;">
                        <strong style="color: #25d366;">Game:</strong>
                        <span style="color: #fff;">${data.game_name || 'Not specified'}</span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong style="color: #25d366;">In-Game Name:</strong>
                        <span style="color: #fff;">${data.in_game_name || 'Not specified'}</span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong style="color: #25d366;">Game ID:</strong>
                        <span style="color: #fff;">${data.game_id || 'Not specified'}</span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong style="color: #25d366;">Experience Level:</strong>
                        <span style="color: #fff;">${data.experience_level || 'Not specified'}</span>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #fff;">
                        <i class="fas fa-exclamation-circle" style="color: #ff4655; font-size: 24px; margin-bottom: 10px;"></i>
                        <p>${data.message || 'No game profile found'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            content.innerHTML = `
                <div style="text-align: center; padding: 20px; color: #ff4655;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error loading game profile</p>
                </div>
            `;
            console.error('Error:', error);
        });

    // Close modal when clicking the close button
    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
}
</script>