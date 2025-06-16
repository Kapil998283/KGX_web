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
    ob_end_clean();
    header("Location: $url");
    exit();
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

    // Get tournament details and user information
    $stmt = $db->prepare("
        SELECT t.*, u.username 
        FROM tournaments t 
        CROSS JOIN users u 
        WHERE t.id = ? AND u.id = ?
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        redirect('index.php', 'Tournament or user not found.');
    }

    // Separate tournament and user data
    $tournament = [
        'id' => $result['id'],
        'name' => $result['name'],
        'game_name' => $result['game_name'],
        'mode' => $result['mode'],
        'entry_fee' => $result['entry_fee'],
        'prize_pool' => $result['prize_pool'],
        'prize_currency' => $result['prize_currency']
    ];
    $user = ['username' => $result['username']];

    // Verify it's a solo tournament
    if ($tournament['mode'] !== 'Solo') {
        redirect('details.php?id=' . $tournament['id'], 'This is not a solo tournament.');
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $db->beginTransaction();

            // For solo tournaments, we directly create the registration without a team
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

            // Deduct tickets
            $stmt = $db->prepare("
                UPDATE user_tickets 
                SET tickets = tickets - ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$tournament['entry_fee'], $_SESSION['user_id']]);

            // Update tournament count
            $stmt = $db->prepare("
                UPDATE tournaments 
                SET current_teams = current_teams + 1 
                WHERE id = ?
            ");
            $stmt->execute([$tournament['id']]);

            $db->commit();
            redirect('my-registrations.php', 'Successfully registered for the tournament!', 'success');

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
    <section class="registration-section" style="padding: 120px 0 60px;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="text-center mb-0">Solo Registration</h2>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger">
                                    <?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>

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

                            <div class="confirmation-message">
                                <p>You are about to register for this tournament as:</p>
                                <div class="user-info">
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </div>
                            </div>

                            <form method="POST" id="registrationForm">
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary">Register Now</button>
                                    <a href="details.php?id=<?php echo $tournament['id']; ?>" 
                                       class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
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
}

.card {
    background: var(--raisin-black-2);
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.card-header {
    background: var(--raisin-black-3);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 20px;
}

.card-body {
    padding: 30px;
}

.tournament-info {
    background: var(--raisin-black-3);
    padding: 20px;
    border-radius: 10px;
}

.tournament-info h4 {
    color: var(--white);
    margin-bottom: 5px;
}

.entry-fee, .prize-pool {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--quick-silver);
}

.confirmation-message {
    text-align: center;
    margin: 20px 0;
}

.user-info {
    background: var(--raisin-black-3);
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
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

ion-icon {
    font-size: 1.2em;
}
</style>

<?php 
require_once '../../includes/footer.php';
ob_end_flush();
?> 