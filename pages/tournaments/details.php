<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Function to get registration URL
function getRegistrationUrl($tournament) {
    return "register.php?id=" . $tournament['id'];
}

// Function to get tournament display status
function getTournamentDisplayStatus($tournament) {
    switch ($tournament['status']) {
        case 'draft':
            return ['status' => 'Draft', 'class' => 'draft'];
        case 'announced':
            return ['status' => 'Upcoming', 'class' => 'upcoming'];
        case 'registration_open':
            return ['status' => 'Registration Open', 'class' => 'registration-open'];
        case 'team_full':
            return ['status' => 'Teams Full', 'class' => 'team-full'];
        case 'registration_closed':
            return ['status' => 'Registration Closed', 'class' => 'registration-closed'];
        case 'in_progress':
            return ['status' => 'Playing', 'class' => 'playing'];
        case 'completed':
            return ['status' => 'Completed', 'class' => 'completed'];
        case 'archived':
            return ['status' => 'Archived', 'class' => 'archived'];
        case 'cancelled':
            return ['status' => 'Cancelled', 'class' => 'cancelled'];
        default:
            return ['status' => 'Unknown', 'class' => 'unknown'];
    }
}

// Add this function at the top of the file after database connection
function isUserRegistered($db, $tournament_id, $user_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM tournament_registrations tr
        LEFT JOIN team_members tm ON tr.team_id = tm.team_id
        WHERE tr.tournament_id = ? 
        AND (tr.user_id = ? OR (tm.user_id = ? AND tm.status = 'active'))
        AND tr.status IN ('pending', 'approved')
    ");
    $stmt->execute([$tournament_id, $user_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

// Check if tournament ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Fetch tournament details
$stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$_GET['id']]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

// If tournament doesn't exist, redirect
if (!$tournament) {
    header('Location: index.php');
    exit();
}

// Fetch registered teams count
$stmt = $db->prepare("SELECT COUNT(*) as team_count FROM tournament_registrations WHERE tournament_id = ? AND status = 'approved'");
$stmt->execute([$tournament['id']]);
$team_count = $stmt->fetch(PDO::FETCH_ASSOC)['team_count'];

// Update current teams count if different
if ($team_count != $tournament['current_teams']) {
    $stmt = $db->prepare("UPDATE tournaments SET current_teams = ? WHERE id = ?");
    $stmt->execute([$team_count, $tournament['id']]);
    $tournament['current_teams'] = $team_count;
}

// Check user's team status if logged in
$user_team_info = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("
        SELECT t.*, tm.role, tm.status as member_status
        FROM teams t
        INNER JOIN team_members tm ON t.id = tm.team_id
        WHERE tm.user_id = ? AND tm.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_team_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // If user has a team, check if already registered
    if ($user_team_info) {
        $stmt = $db->prepare("
            SELECT status 
            FROM tournament_registrations 
            WHERE tournament_id = ? AND 
            (team_id = ? OR (user_id = ? AND team_id IS NULL))
            AND status IN ('pending', 'approved')
            LIMIT 1
        ");
        $stmt->execute([$tournament['id'], $user_team_info['id'], $_SESSION['user_id']]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($registration) {
            $user_team_info['registration_status'] = $registration['status'];
        }
    }
}

$is_registered = isset($_SESSION['user_id']) ? isUserRegistered($db, $tournament['id'], $_SESSION['user_id']) : false;

?>

<link rel="stylesheet" href="../../assets/css/tournament/details.css">
<style>
/* Tournament Status Classes */
.status-draft {
    background-color: #f5f5f5;
    color: #757575;
}
.status-upcoming {
    background-color: #e3f2fd;
    color: #1976d2;
}
.status-registration-open {
    background-color: #e8f5e9;
    color: #2e7d32;
}
.status-team-full {
    background-color: #fff3e0;
    color: #e65100;
}
.status-registration-closed {
    background-color: #fce4ec;
    color: #c2185b;
}
.status-playing {
    background-color: #f3e5f5;
    color: #7b1fa2;
}
.status-completed {
    background-color: #e8eaf6;
    color: #3f51b5;
}
.status-archived {
    background-color: #eceff1;
    color: #455a64;
}
.status-cancelled {
    background-color: #ffebee;
    color: #c62828;
}
.status-unknown {
    background-color: #f5f5f5;
    color: #9e9e9e;
}

</style>

<main>
    <article>
        <div class="tournament-container">
            <div class="back-title">
                <a href="index.php"><span>&larr;</span> <?php echo htmlspecialchars($tournament['name']); ?></a>
            </div>

            <div class="tournament-card">
                <div class="image-section">
                    <img src="<?php echo htmlspecialchars($tournament['banner_image']); ?>" alt="tournament" />
                </div>
                <div class="info-section">
                    <h2><?php echo htmlspecialchars($tournament['game_name']); ?></h2>
                    <p class="subheading">Tournament <?php echo $tournament['status'] === 'completed' ? 'ended' : 'ending in'; ?></p>
                    
                    <?php if ($tournament['status'] !== 'completed'): ?>
                    <div class="countdown-grid" data-end-date="<?php echo $tournament['finish_date']; ?>">
                        <div class="hex-box"><span id="days">0</span><small>Days</small></div>
                        <div class="hex-box"><span id="hours">0</span><small>Hours</small></div>
                        <div class="hex-box"><span id="minutes">0</span><small>Minutes</small></div>
                        <div class="hex-box"><span id="seconds">0</span><small>Seconds</small></div>
                    </div>
                    <?php endif; ?>

                    <div class="tournament-meta">
                        <?php if ($tournament['status'] === 'registration_open' || $tournament['status'] === 'team_full'): ?>
                            <?php if (!isset($_SESSION['user_id'])): ?>
                                <button class="view-more" onclick="window.location.href='../../auth/login.php'">
                                    Login to Register
                                </button>
                            <?php elseif ($is_registered): ?>
                                <?php
                                // Fetch the registration status for the current user/team
                                $reg_status = null;
                                if ($tournament['mode'] === 'Solo') {
                                    $stmt = $db->prepare("SELECT status FROM tournament_registrations WHERE tournament_id = ? AND user_id = ? LIMIT 1");
                                    $stmt->execute([$tournament['id'], $_SESSION['user_id']]);
                                    $reg_status = $stmt->fetchColumn();
                                } else if ($user_team_info) {
                                    $stmt = $db->prepare("SELECT status FROM tournament_registrations WHERE tournament_id = ? AND team_id = ? LIMIT 1");
                                    $stmt->execute([$tournament['id'], $user_team_info['id']]);
                                    $reg_status = $stmt->fetchColumn();
                                }
                                ?>
                                <?php if ($reg_status === 'pending'): ?>
                                    <button class="view-more" disabled>
                                        Wait for Approval
                                    </button>
                                <?php else: ?>
                                    <button class="view-more" disabled>
                                        Already Registered
                                    </button>
                                <?php endif; ?>
                            <?php elseif ($tournament['mode'] === 'Solo'): ?>
                                <?php if ($tournament['status'] === 'team_full'): ?>
                                    <button class="view-more" disabled>
                                        Teams Full
                                    </button>
                                <?php else: ?>
                                    <button class="view-more" onclick="window.location.href='register.php?id=<?php echo $tournament['id']; ?>'">
                                        Register Now
                                    </button>
                                <?php endif; ?>
                            <?php elseif (!$user_team_info): ?>
                                <button class="view-more" onclick="window.location.href='../../teams/create_team.php?redirect=tournament&id=<?php echo $tournament['id']; ?>'">
                                    Create/Join Team
                                </button>
                            <?php elseif (isset($user_team_info['registration_status'])): ?>
                                <button class="view-more" disabled>
                                    <?php echo $user_team_info['registration_status'] === 'approved' ? 'Already Registered' : 'Registration Pending'; ?>
                                </button>
                            <?php else: ?>
                                <?php if ($tournament['status'] === 'team_full'): ?>
                                    <button class="view-more" disabled>
                                        Teams Full
                                    </button>
                                <?php else: ?>
                                    <button class="view-more" onclick="window.location.href='register.php?id=<?php echo $tournament['id']; ?>'">
                                        Register Now
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <span class="tournament-time"><?php echo date('M d, Y', strtotime($tournament['playing_start_date'])); ?></span>
                            <span class="players-count">👥 <?php echo $tournament['current_teams']; ?>/<?php echo $tournament['max_teams']; ?> Teams</span>
                        <?php else: ?>
                            <?php
                                $status_info = getTournamentDisplayStatus($tournament);
                                $status_text = $status_info['status'];
                                if ($tournament['status'] === 'announced') {
                                    $status_text .= ' - Registration opens ' . date('M d, Y', strtotime($tournament['registration_open_date']));
                                }
                            ?>
                            <button class="view-more" disabled>
                                <?php echo $status_text; ?>
                            </button>
                            <span class="tournament-time"><?php echo date('M d, Y', strtotime($tournament['playing_start_date'])); ?></span>
                            <span class="players-count">👥 <?php echo $tournament['current_teams']; ?>/<?php echo $tournament['max_teams']; ?> Teams</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tournament-details">
                <div class="info-box">
                    <i class="icon">₿</i>
                    <div>
                        <p>Prize Pool</p>
                        <strong><?php 
                            echo $tournament['prize_currency'] === 'USD' ? '$' : '₹';
                            echo number_format($tournament['prize_pool'], 2); 
                        ?></strong>
                    </div>
                </div>
                <div class="info-box">
                    <i class="icon">💰</i>
                    <div>
                        <p>Entry Fee</p>
                        <strong><?php echo $tournament['entry_fee']; ?> Tickets</strong>
                    </div>
                </div>
                <div class="info-box">
                    <i class="icon">👤</i>
                    <div>
                        <p>Mode</p>
                        <strong><?php echo htmlspecialchars($tournament['mode']); ?></strong>
                    </div>
                </div>
                <div class="info-box">
                    <i class="icon">🎮</i>
                    <div>
                        <p>Format</p>
                        <strong><?php echo htmlspecialchars($tournament['format']); ?></strong>
                    </div>
                </div>
                <div class="info-box">
                    <i class="icon">🏆</i>
                    <div>
                        <p>Match Type</p>
                        <strong><?php echo htmlspecialchars($tournament['match_type']); ?></strong>
                    </div>
                </div>
            </div>

            <div class="tournament-progress">
                <?php
                $steps = [
                    ['label' => 'Registration Open', 'date' => $tournament['registration_open_date'], 'desc' => 'Register now to play in the tournament.'],
                    ['label' => 'Registration Closed', 'date' => $tournament['registration_close_date'], 'desc' => 'Creating the brackets we\'ll start soon'],
                    ['label' => 'Playing', 'date' => $tournament['playing_start_date'], 'desc' => 'Tournament matches in progress'],
                    ['label' => 'Finished', 'date' => $tournament['finish_date'], 'desc' => 'Tournament finished. Prizes are on their way.'],
                    ['label' => 'Paid', 'date' => $tournament['payment_date'], 'desc' => 'Payments sent to the winners. Congrats!']
                ];

                $now = new DateTime();
                $currentStep = 1;
                foreach ($steps as $index => $step) {
                    $stepDate = new DateTime($step['date']);
                    if ($now >= $stepDate) {
                        $currentStep = $index + 1;
                    }
                    ?>
                    <div class="progress-step <?php echo $now >= $stepDate ? 'completed' : ''; ?>">
                        <div class="step-icon"><?php echo $now >= $stepDate ? '✔' : ($index + 1); ?></div>
                        <div class="progress-step-content">
                            <h4><?php echo $step['label']; ?></h4>
                            <p><?php echo $step['desc']; ?></p>
                            <small><?php echo date('M d, Y', strtotime($step['date'])); ?></small>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="tournament-tabs">
                <button class="tab-btn active" data-tab="brackets-section">Brackets</button>
                <button class="tab-btn" data-tab="players-section">Players</button>
                <button class="tab-btn" data-tab="winners-section">Winners</button>
                <button class="tab-btn" data-tab="rules-section">Rules</button>
            </div>

            <!-- Brackets Section -->
            <div id="brackets-section" class="tab-content active">
                <div class="rounds">
                    <div class="rounds-grid">
                        <?php
                        // Fetch tournament rounds
                        $stmt = $db->prepare("SELECT * FROM tournament_rounds WHERE tournament_id = ? ORDER BY round_number");
                        $stmt->execute([$tournament['id']]);
                        $rounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($rounds as $round): ?>
                            <div class="round-card">
                                <h4>Round <?php echo $round['round_number']; ?> ></h4>
                                <p><?php echo date('M d, g:i A', strtotime($round['start_time'])); ?></p>
                                <small><?php echo $round['total_teams']; ?> Teams</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Players Section -->
            <div id="players-section" class="tab-content">
                <div class="players-section">
                    <div class="players-branches">
                        <?php
                        if ($tournament['mode'] === 'Solo') {
                            // Fetch registered solo players
                            $stmt = $db->prepare("
                                SELECT u.id, u.username, u.profile_image, tr.registration_date
                                FROM tournament_registrations tr
                                INNER JOIN users u ON tr.user_id = u.id
                                WHERE tr.tournament_id = ? AND tr.status = 'approved'
                                ORDER BY tr.registration_date ASC
                            ");
                            $stmt->execute([$tournament['id']]);
                            $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($players)): ?>
                                <div class="branch">
                                    <h3>Registered Players</h3>
                                    <?php foreach ($players as $player): ?>
                                        <div class="player-card">
                                            <?php
                                            $profile_image = $player['profile_image'];
                                            if (empty($profile_image)) {
                                                $default_img_sql = "SELECT image_path FROM profile_images WHERE is_default = 1 AND is_active = 1 LIMIT 1";
                                                $default_img_stmt = $db->prepare($default_img_sql);
                                                $default_img_stmt->execute();
                                                $default_img = $default_img_stmt->fetch(PDO::FETCH_ASSOC);
                                                $profile_image = $default_img ? $default_img['image_path'] : '../../assets/images/guest-icon.png';
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                                                 alt="<?php echo htmlspecialchars($player['username']); ?>"
                                                 onerror="this.src='../../assets/images/guest-icon.png'">
                                            <span><?php echo htmlspecialchars($player['username']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif;
                        } else {
                            // Fetch registered teams and their members for Duo/Squad modes
                            $stmt = $db->prepare("
                                SELECT 
                                    t.id as team_id,
                                    t.name as team_name,
                                    t.logo as team_logo,
                                    u.id as user_id,
                                    u.username,
                                    u.profile_image,
                                    tm.role,
                                    tr.registration_date
                                FROM tournament_registrations tr
                                INNER JOIN teams t ON tr.team_id = t.id
                                INNER JOIN team_members tm ON t.id = tm.team_id
                                INNER JOIN users u ON tm.user_id = u.id
                                WHERE tr.tournament_id = ? 
                                AND tr.status = 'approved'
                                AND tm.status = 'active'
                                ORDER BY t.name, FIELD(tm.role, 'captain', 'member')
                            ");
                            $stmt->execute([$tournament['id']]);
                            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            $teams = [];
                            foreach ($results as $row) {
                                if (!isset($teams[$row['team_id']])) {
                                    $teams[$row['team_id']] = [
                                        'name' => $row['team_name'],
                                        'logo' => $row['team_logo'],
                                        'members' => []
                                    ];
                                }
                                $teams[$row['team_id']]['members'][] = [
                                    'username' => $row['username'],
                                    'profile_image' => $row['profile_image'],
                                    'role' => $row['role']
                                ];
                            }

                            if (!empty($teams)): 
                                foreach ($teams as $team): ?>
                                    <div class="branch">
                                        <div class="team-header">
                                            <?php if (!empty($team['logo'])): ?>
                                                <img src="<?php echo htmlspecialchars($team['logo']); ?>" 
                                                     alt="<?php echo htmlspecialchars($team['name']); ?> logo"
                                                     class="team-logo"
                                                     onerror="this.src='../../assets/images/default-team-logo.png'">
                                            <?php endif; ?>
                                            <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                                        </div>
                                        <?php foreach ($team['members'] as $member): 
                                            $profile_image = $member['profile_image'];
                                            if (empty($profile_image)) {
                                                $default_img_sql = "SELECT image_path FROM profile_images WHERE is_default = 1 AND is_active = 1 LIMIT 1";
                                                $default_img_stmt = $db->prepare($default_img_sql);
                                                $default_img_stmt->execute();
                                                $default_img = $default_img_stmt->fetch(PDO::FETCH_ASSOC);
                                                $profile_image = $default_img ? $default_img['image_path'] : '../../assets/images/guest-icon.png';
                                            }
                                        ?>
                                            <div class="player-card <?php echo $member['role']; ?>">
                                                <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                                                     alt="<?php echo htmlspecialchars($member['username']); ?>"
                                                     onerror="this.src='../../assets/images/guest-icon.png'">
                                                <span>
                                                    <?php echo htmlspecialchars($member['username']); ?>
                                                    <?php if ($member['role'] === 'captain'): ?>
                                                        <span class="captain-badge" title="Team Captain">👑</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach;
                            else: ?>
                                <div class="no-registrations">
                                    <p>No teams have registered for this tournament yet.</p>
                                </div>
                            <?php endif;
                        } ?>
                    </div>
                </div>
            </div>

            <!-- Winners Section -->
            <div id="winners-section" class="tab-content">
                <div class="winners-section">
                    <?php if ($tournament['status'] === 'completed'): ?>
                        <!-- Add winner display logic here -->
                        <img src="/assets/images/winner-trophy.png" alt="Winner Trophy" class="winner-trophy" />
                        <div class="winner-details">
                            <!-- Add winner details here -->
                        </div>
                    <?php else: ?>
                        <img src="/assets/images/winner-trophy.png" alt="Winner Trophy" class="winner-trophy" />
                        <p class="winner-msg">Once the tournament is over, the data takes<br>a few minutes to appear.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rules Section -->
            <div id="rules-section" class="tab-content">
                <div class="ruler">
                    <div class="rules-container">
                        <?php
                        $rules = explode("\n", $tournament['rules']);
                        foreach ($rules as $index => $rule):
                            if (trim($rule)):
                        ?>
                            <div class="rule-item">
                                <button class="rule-header" onclick="toggleAccordion('rule<?php echo $index; ?>')">
                                    <span class="icon">❯</span> Rule <?php echo $index + 1; ?>
                                </button>
                                <div id="rule<?php echo $index; ?>" class="rule-body">
                                    <?php echo htmlspecialchars($rule); ?>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </article>
</main>

<script>
// Countdown Timer
function updateCountdown() {
    const countdownElement = document.querySelector('.countdown-grid');
    if (!countdownElement) return;

    const endDate = new Date(countdownElement.dataset.endDate).getTime();
    
    function update() {
        const now = new Date().getTime();
        const distance = endDate - now;

        if (distance < 0) {
            document.getElementById('days').textContent = '0';
            document.getElementById('hours').textContent = '0';
            document.getElementById('minutes').textContent = '0';
            document.getElementById('seconds').textContent = '0';
            return;
        }

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        document.getElementById('days').textContent = days;
        document.getElementById('hours').textContent = hours;
        document.getElementById('minutes').textContent = minutes;
        document.getElementById('seconds').textContent = seconds;
    }

    update();
    setInterval(update, 1000);
}

// Tab Switching
const tabButtons = document.querySelectorAll('.tab-btn');
const tabContents = document.querySelectorAll('.tab-content');

tabButtons.forEach(button => {
    button.addEventListener('click', () => {
        tabButtons.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(tab => tab.classList.remove('active'));

        button.classList.add('active');
        const tabId = button.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});

// Rules Accordion
function toggleAccordion(id) {
    const body = document.getElementById(id);
    const item = body.parentElement;

    if (body.style.display === "block") {
        body.style.display = "none";
        item.classList.remove("open");
    } else {
        body.style.display = "block";
        item.classList.add("open");
    }
}

// Initialize countdown
document.addEventListener('DOMContentLoaded', updateCountdown);
</script>

<?php require_once '../../includes/footer.php'; ?> 