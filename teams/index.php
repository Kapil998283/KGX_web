<?php
require_once '../includes/header.php';
require_once '../config/database.php';
require_once '../includes/user-auth.php';
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
<link rel="stylesheet" href="../assets/css/teams/index.css">

<main>
    <article>
        <!-- Team Section -->
        <section class="teams-section">
            <div class="container">
                <h2 class="section-title">Find Teams</h2>

                <!-- Search Bar -->
                <div class="search-bar">
                    <input type="text" id="team-search" placeholder="Search for a team...">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                </div>

                <div class="team-cards-container">
                    <?php if (isset($_SESSION['user_id']) && !$user_status['is_member']): ?>
                        <!-- Create Team Box -->
                        <div class="team-card create-box" onclick="window.location.href='create_team.php'">
                            <div class="create-image">
                                <i class="fas fa-plus"></i>
                            </div>
                            <h3>Create your team and become the captain</h3>
                            <button class="rc-btn">Create Now</button>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($teams)): ?>
                        <div class="no-teams">
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
                                <p><i class="fas fa-users"></i> <?php echo $team['current_members']; ?>/<?php echo $team['max_members']; ?> players</p>
                                <p><i class="fas fa-globe"></i> <?php echo htmlspecialchars($team['language']); ?></p>
                                
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <?php if ($team['captain_id'] == $_SESSION['user_id']): ?>
                                        <button class="rc-btn disabled" disabled>Your Team</button>
                                    <?php elseif ($user_status['is_member']): ?>
                                        <button class="rc-btn disabled" disabled>Already in a Team</button>
                                    <?php elseif (in_array($team['id'], $pending_requests)): ?>
                                        <form action="cancel_request.php" method="POST" style="width: 100%;">
                                            <?php
                                            // Get the request ID for this team
                                            $request_sql = "SELECT id FROM team_join_requests 
                                                          WHERE user_id = :user_id 
                                                          AND team_id = :team_id 
                                                          AND status = 'pending'";
                                            $request_stmt = $conn->prepare($request_sql);
                                            $request_stmt->execute([
                                                'user_id' => $_SESSION['user_id'],
                                                'team_id' => $team['id']
                                            ]);
                                            $request = $request_stmt->fetch(PDO::FETCH_ASSOC);
                                            ?>
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" class="rc-btn cancel-btn">Cancel Request</button>
                                        </form>
                                    <?php elseif ($team['current_members'] >= $team['max_members']): ?>
                                        <button class="rc-btn disabled" disabled>Team Full</button>
                                    <?php else: ?>
                                        <form action="send_join_request.php" method="POST" style="width: 100%;">
                                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                            <button type="submit" class="rc-btn">Request to Join</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="../register/login.php" class="rc-btn">Login to Join</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </article>
</main>

<script>
// Add search functionality
document.getElementById('team-search').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase().trim();
    const teamCards = document.querySelectorAll('.team-card:not(.create-box)');
    const createTeamCard = document.querySelector('.create-box');
    
    // Hide/show create team card based on search
    if (createTeamCard) {
        createTeamCard.style.display = searchTerm === '' ? '' : 'none';
    }

    let hasResults = false;
    
    teamCards.forEach(card => {
        const teamName = card.querySelector('h3').textContent.toLowerCase();
        const teamLang = card.querySelector('.fa-globe').parentNode.textContent.toLowerCase();
        
        if (teamName.includes(searchTerm) || teamLang.includes(searchTerm)) {
            card.style.display = '';
            hasResults = true;
        } else {
            card.style.display = 'none';
        }
    });

    // Show/hide no results message
    let noResultsMsg = document.querySelector('.no-results-message');
    if (!noResultsMsg) {
        noResultsMsg = document.createElement('div');
        noResultsMsg.className = 'no-results-message';
        noResultsMsg.style.cssText = `
            grid-column: 1 / -1;
            text-align: center;
            padding: 2rem;
            color: var(--light-gray);
            font-size: 1.1rem;
        `;
        document.querySelector('.team-cards-container').appendChild(noResultsMsg);
    }
    
    if (!hasResults && searchTerm !== '') {
        noResultsMsg.textContent = 'No teams found matching your search.';
        noResultsMsg.style.display = '';
    } else {
        noResultsMsg.style.display = 'none';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>

</body>
</html> 