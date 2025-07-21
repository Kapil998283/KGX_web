<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Function to get registration URL
function getRegistrationUrl($tournament) {
    return "/KGX/pages/tournaments/register.php?id=" . $tournament['id'];
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

// Function to get registration status class
function getStatusClass($tournament) {
    $now = new DateTime();
    $regOpen = new DateTime($tournament['registration_open_date']);
    $regClose = new DateTime($tournament['registration_close_date']);
    $playStart = new DateTime($tournament['playing_start_date']);
    $finishDate = new DateTime($tournament['finish_date']);
    
    if ($tournament['status'] === 'cancelled') {
        return 'cancelled';
    } elseif ($now >= $playStart && $now <= $finishDate) {
        return 'playing';
    } elseif ($now >= $regOpen && $now <= $regClose) {
        return 'registration-open';
    } elseif ($now < $regOpen) {
        return 'upcoming';
    } else {
        return 'completed';
    }
}
?>

<link rel="stylesheet" href="../../assets/css/tournament/details.css">

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
                    <?php if ($tournament['registration_phase'] === 'open'): ?>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <button class="view-more" onclick="window.location.href='../../register/login.php'">
                                Login to Register
                            </button>
                        <?php elseif ($tournament['mode'] === 'Solo'): ?>
                            <button class="view-more" onclick="window.location.href='<?php echo getRegistrationUrl($tournament); ?>'">
                                Register Now
                            </button>
                        <?php elseif (!$user_team_info): ?>
                            <button class="view-more" onclick="window.location.href='../../teams/create_team.php?redirect=tournament&id=<?php echo $tournament['id']; ?>'">
                                Create/Join Team
                            </button>
                        <?php elseif (isset($user_team_info['registration_status'])): ?>
                            <button class="view-more" disabled>
                                <?php echo $user_team_info['registration_status'] === 'approved' ? 'Already Registered' : 'Registration Pending'; ?>
                            </button>
                        <?php else: ?>
                            <button class="view-more" onclick="window.location.href='<?php echo getRegistrationUrl($tournament); ?>'">
                                Register Now
                            </button>
                        <?php endif; ?>
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
                        // Fetch registered teams and their members
                        $stmt = $db->prepare("
                            SELECT t.*, tr.status as registration_status, tm.user_id, tm.role, u.username
                            FROM tournament_registrations tr
                            INNER JOIN teams t ON tr.team_id = t.id
                            INNER JOIN team_members tm ON t.id = tm.team_id
                            INNER JOIN users u ON tm.user_id = u.id
                            WHERE tr.tournament_id = ? AND tr.status = 'approved'
                            ORDER BY t.name, tm.role DESC
                        ");
                        $stmt->execute([$tournament['id']]);
                        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $teams = [];
                        foreach ($players as $player) {
                            if (!isset($teams[$player['team_id']])) {
                                $teams[$player['team_id']] = [
                                    'name' => $player['name'],
                                    'members' => []
                                ];
                            }
                            $teams[$player['team_id']]['members'][] = [
                                'username' => $player['username'],
                                'role' => $player['role']
                            ];
                        }

                        foreach ($teams as $teamId => $team): ?>
                            <div class="branch">
                                <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                                <?php foreach ($team['members'] as $member): ?>
                                    <div class="player-card">
                                        <img src="/assets/images/team-member-1.png" alt="Player">
                                        <span>
                                            <?php echo htmlspecialchars($member['username']); ?>
                                            <?php if ($member['role'] === 'captain'): ?>
                                                <i class="fas fa-crown" style="color: gold;"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
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