<?php
require_once '../../includes/header.php';
require_once '../../config/database.php';

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /KGX/pages/auth/login.php");
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
                (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count,
                t.total_score
                FROM teams t
                JOIN team_members tm ON t.id = tm.team_id
                WHERE tm.user_id = :user_id AND t.is_active = 1";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Teams</title>
    <link rel="stylesheet" href="/KGX/assets/css/yourteam.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<main>
  <article>
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

        <style>
        .no-teams-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 60vh;
            padding: 2rem;
        }

        .no-teams-content {
            text-align: center;
            background: var(--raisin-black-2);
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .no-teams-icon {
            font-size: 4rem;
            color: var(--orange);
            margin-bottom: 1.5rem;
        }

        .no-teams-content h2 {
            color: var(--white);
            margin-bottom: 1rem;
            font-size: 2rem;
        }

        .no-teams-content p {
            color: var(--quick-silver);
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        .no-teams-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .join-btn {
            background-color: var(--orange);
            color: var(--white);
        }

        .create-btn {
            background-color: var(--raisin-black-1);
            color: var(--white);
            border: 2px solid var(--orange);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .join-btn:hover {
            background-color: var(--orange-dark);
        }

        .create-btn:hover {
            background-color: var(--orange);
        }
        </style>
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
                        <a href="/KGX/pages/teams/captain.php?id=<?php echo $team['id']; ?>" class="edit-btn">
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
                        $sql = "SELECT u.*, tm.role, tm.joined_at,
                               CASE 
                                   WHEN tm.role = 'captain' THEN 1 
                                   ELSE 2 
                               END as role_order
                               FROM team_members tm 
                               JOIN users u ON tm.user_id = u.id 
                               WHERE tm.team_id = :team_id
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
                                         class="player-avatar"
                                         onerror="this.src='/KGX/ui/assets/images/guest-icon.png'">
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
                                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                        <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                        <button type="submit" class="remove-member-btn" onclick="return confirm('Are you sure you want to remove this member?');">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <style>
                .player-list {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    gap: 20px;
                    padding: 20px;
                }

                .player-card {
                    position: relative;
                    background: #1a1a1a;
                    border-radius: 10px;
                    padding: 20px;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    border: 1px solid #333;
                    transition: all 0.3s ease;
                }

                .player-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                }

                .captain-card {
                    background: linear-gradient(145deg, #1a1a1a, #2a2a2a);
                    border: 1px solid #ff4655;
                    order: -1;
                }

                .member-number {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    background: #ff4655;
                    color: white;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 0.8em;
                    font-weight: bold;
                }

                .player-avatar-wrapper {
                    position: relative;
                    width: 80px;
                    height: 80px;
                }

                .player-avatar {
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    object-fit: cover;
                    border: 2px solid #333;
                }

                .captain-card .player-avatar {
                    border-color: #ff4655;
                }

                .captain-badge {
                    position: absolute;
                    bottom: -5px;
                    right: -5px;
                    background: #ff4655;
                    border-radius: 50%;
                    width: 25px;
                    height: 25px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 0.8em;
                }

                .player-info {
                    flex-grow: 1;
                }

                .player-name {
                    margin: 0;
                    font-size: 1.2em;
                    color: #fff;
                }

                .role {
                    margin: 5px 0;
                    font-size: 0.9em;
                    color: #888;
                }

                .role.captain {
                    color: #ff4655;
                    font-weight: bold;
                }

                .join-date {
                    margin: 5px 0;
                    font-size: 0.8em;
                    color: #666;
                }

                .remove-member-btn {
                    background: transparent;
                    border: none;
                    color: #ff4655;
                    cursor: pointer;
                    padding: 5px;
                    font-size: 1.2em;
                    transition: all 0.3s ease;
                }

                .remove-member-btn:hover {
                    color: #ff6b76;
                    transform: scale(1.1);
                }
                </style>

                <!-- Tournament Content -->
                <div class="tab-content <?php echo $active_tab === 'tournament' ? 'active' : ''; ?>" id="tournamentContent">
                    <div class="tournaments-section">
                        <?php
                        // Fetch team's tournaments
                        $stmt = $conn->prepare("
                            SELECT 
                                t.*,
                                tr.registration_date,
                                tr.status as registration_status
                            FROM tournaments t
                            INNER JOIN tournament_registrations tr ON t.id = tr.tournament_id
                            WHERE tr.team_id = ?
                            ORDER BY 
                                CASE 
                                    WHEN t.registration_phase = 'playing' THEN 1
                                    WHEN t.registration_phase = 'closed' THEN 2
                                    ELSE 3
                                END,
                                t.playing_start_date DESC
                        ");
                        $stmt->execute([$team['id']]);
                        $tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <?php if (!empty($tournaments)): ?>
                            <div class="row g-4">
                                <?php foreach ($tournaments as $tournament): ?>
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

                                                <div class="tournament-phase">
                                                    <?php
                                                    $phase_class = '';
                                                    $phase_text = '';
                                                    
                                                    switch ($tournament['registration_phase']) {
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
                            <?php foreach ($pending_requests as $request): ?>
                                <?php if ($request['team_id'] == $team['id']): ?>
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
                                             onerror="this.src='/KGX/ui/assets/images/guest-icon.png'">
                                        <div class="request-info">
                                            <h3><?php echo htmlspecialchars($request['username']); ?></h3>
                                            <p class="request-date">Requested: <?php echo date('M d, Y', strtotime($request['created_at'])); ?></p>
                                        </div>
                                        <div class="request-actions">
                                            <form action="handle_request.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <button type="submit" name="submit" class="accept-btn">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                            </form>
                                            <form action="handle_request.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                <button type="submit" name="submit" class="reject-btn">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if (empty($pending_requests)): ?>
                                <div class="no-requests">
                                    <i class="fas fa-user-plus"></i>
                                    <h3>No Pending Requests</h3>
                                    <p>There are no pending join requests for your team at the moment.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <style>
                        .requests-list {
                            padding: 20px;
                            max-width: 800px;
                            margin: 0 auto;
                        }

                        .request-card {
                            background: var(--raisin-black-2);
                            border-radius: 10px;
                            padding: 15px;
                            margin-bottom: 15px;
                            display: flex;
                            align-items: center;
                            gap: 15px;
                            transition: transform 0.3s ease;
                        }

                        .request-card:hover {
                            transform: translateY(-2px);
                        }

                        .request-avatar {
                            width: 60px;
                            height: 60px;
                            border-radius: 50%;
                            object-fit: cover;
                            border: 2px solid var(--orange);
                        }

                        .request-info {
                            flex-grow: 1;
                        }

                        .request-info h3 {
                            color: var(--white);
                            margin: 0;
                            font-size: 1.1rem;
                        }

                        .request-date {
                            color: var(--quick-silver);
                            font-size: 0.9rem;
                            margin: 5px 0 0 0;
                        }

                        .request-actions {
                            display: flex;
                            gap: 10px;
                        }

                        .accept-btn, .reject-btn {
                            padding: 8px 15px;
                            border: none;
                            border-radius: 5px;
                            cursor: pointer;
                            font-size: 0.9rem;
                            display: flex;
                            align-items: center;
                            gap: 5px;
                            transition: all 0.3s ease;
                        }

                        .accept-btn {
                            background-color: var(--orange);
                            color: var(--white);
                        }

                        .reject-btn {
                            background-color: var(--raisin-black-1);
                            color: var(--quick-silver);
                            border: 1px solid var(--quick-silver);
                        }

                        .accept-btn:hover {
                            background-color: var(--orange-dark);
                        }

                        .reject-btn:hover {
                            background-color: #ff4655;
                            color: var(--white);
                            border-color: #ff4655;
                        }

                        .no-requests {
                            text-align: center;
                            padding: 40px 20px;
                            color: var(--quick-silver);
                        }

                        .no-requests i {
                            font-size: 3rem;
                            margin-bottom: 15px;
                            color: var(--orange);
                        }

                        .no-requests h3 {
                            color: var(--white);
                            margin-bottom: 10px;
                        }

                        .no-requests p {
                            color: var(--quick-silver);
                        }
                        </style>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
  </article>
</main>

<?php require_once '../../includes/footer.php'; ?>

<style>
/* Tournament Section Styles */
.tournaments-section {
    padding: 20px 0;
}

.tournament-card {
    background: var(--raisin-black-2);
    border-radius: 15px;
    overflow: hidden;
    margin-bottom: 20px;
    transition: transform 0.3s ease;
}

.tournament-card:hover {
    transform: translateY(-5px);
}

.card-banner {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.tournament-banner {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tournament-meta {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: space-between;
    padding: 10px;
    background: rgba(0, 0, 0, 0.7);
    color: var(--white);
}

.prize-pool, .registration-status {
    display: flex;
    align-items: center;
    gap: 5px;
}

.card-content {
    padding: 20px;
}

.tournament-title {
    font-size: 1.25rem;
    margin-bottom: 5px;
    color: var(--white);
}

.game-name {
    color: var(--quick-silver);
    margin-bottom: 15px;
}

.tournament-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--quick-silver);
}

.tournament-dates {
    font-size: 0.9rem;
    color: var(--quick-silver);
    margin-bottom: 15px;
}

.registration-date {
    color: var(--orange);
    margin-bottom: 5px;
}

.tournament-phase {
    margin-bottom: 15px;
    text-align: center;
}

.card-actions {
    display: flex;
    gap: 10px;
}

.card-actions .btn {
    flex: 1;
    text-align: center;
    padding: 8px 16px;
    border-radius: 5px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-primary {
    background: var(--orange);
    color: var(--white);
}

.btn:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.no-tournaments {
    text-align: center;
    padding: 40px 20px;
}

.no-tournaments .large-icon {
    font-size: 48px;
    color: var(--quick-silver);
    margin-bottom: 20px;
}

.no-tournaments h3 {
    color: var(--white);
    margin-bottom: 10px;
}

.no-tournaments p {
    color: var(--quick-silver);
    margin-bottom: 20px;
}
</style>