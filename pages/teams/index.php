<?php
require_once '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';
require_once 'check_team_status.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Check user's team status if logged in
$user_status = ['is_member' => false];
if (isset($_SESSION['user_id'])) {
    $user_status = checkTeamStatus($conn, $_SESSION['user_id']);
}

// Get all active teams with member count
$sql = "SELECT 
            t.*, 
            u.username as captain_name,
            (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as current_members
        FROM teams t 
        LEFT JOIN users u ON t.captain_id = u.id 
        WHERE t.is_active = 1 
        ORDER BY t.created_at DESC";
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching teams: " . $e->getMessage());
    $teams = [];
}

// Get user's pending requests if logged in
$pending_requests = [];
if (isset($_SESSION['user_id'])) {
    $sql = "SELECT team_id FROM team_join_requests 
            WHERE user_id = :user_id AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $pending_requests = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Check for success message
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
}
?>

<!-- Add Teams CSS -->
<link rel="stylesheet" href="../../assets/css/teams.css">

<main>
    <article>
        <!-- Team Section -->
        <section class="teams-section">
            <div class="container">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo htmlspecialchars($_SESSION['success_message']); 
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo htmlspecialchars($_SESSION['error_message']); 
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['info_message'])): ?>
                    <div class="alert alert-info">
                        <?php 
                        echo htmlspecialchars($_SESSION['info_message']); 
                        unset($_SESSION['info_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title">Find Teams</h2>
                    <?php if (isset($_SESSION['user_id']) && !$user_status['is_member']): ?>
                        <a href="create_team.php" class="rc-btn">
                            <i class="fas fa-plus"></i> Create Team
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Search Bar -->
                <div class="search-bar mb-4">
                    <input type="text" id="team-search" placeholder="Search for a team...">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                </div>

                <div class="team-cards-container">
                    <?php if (empty($teams)): ?>
                        <div class="alert alert-info">
                            No teams found. Be the first to create one!
                        </div>
                    <?php else: ?>
                        <?php foreach ($teams as $team): ?>
                            <div class="team-card">
                                <div class="team-logo">
                                    <?php if (!empty($team['logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($team['logo']); ?>" 
                                             alt="<?php echo htmlspecialchars($team['name']); ?> Logo"
                                             onerror="this.src='../assets/images/default-team.png'">
                                    <?php else: ?>
                                        <img src="../assets/images/default-team.png" alt="Default Team Logo">
                                    <?php endif; ?>
                                </div>
                                <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                                <p class="team-description"><?php echo htmlspecialchars($team['description']); ?></p>
                                <p><i class="fas fa-users"></i> <?php echo $team['current_members']; ?>/<?php echo $team['max_members']; ?> players</p>
                                <p><i class="fas fa-globe"></i> <?php echo htmlspecialchars($team['language']); ?></p>
                                <p><i class="fas fa-user-shield"></i> Captain: <?php echo htmlspecialchars($team['captain_name']); ?></p>
                                
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <?php if ($team['captain_id'] == $_SESSION['user_id']): ?>
                                        <button class="rc-btn disabled" disabled>Your Team</button>
                                    <?php elseif ($user_status['is_member']): ?>
                                        <button class="rc-btn disabled" disabled>Already in a Team</button>
                                    <?php elseif (in_array($team['id'], $pending_requests)): ?>
                                        <button class="rc-btn disabled" disabled>Request Pending</button>
                                    <?php elseif ($team['current_members'] >= $team['max_members']): ?>
                                        <button class="rc-btn disabled" disabled>Team Full</button>
                                    <?php else: ?>
                                        <form action="send_join_request.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                            <button type="submit" class="rc-btn">Join Team</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="../../pages/login.php" class="rc-btn">Login to Join</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </article>
</main>

<!-- Add Teams JS -->
<script src="../assets/js/teams.js"></script>

<?php require_once '../../includes/footer.php'; ?> 