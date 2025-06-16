<?php
require_once '../includes/admin-auth.php';
require_once '../../config/database.php';
include '../includes/admin-header.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_match':
                $game_id = $_POST['game_id'];
                $match_type = $_POST['match_type'];
                $match_date = $_POST['match_date'] . ' ' . $_POST['match_time'];
                $entry_type = $_POST['entry_type'];
                $entry_fee = $_POST['entry_fee'] ?: 0;
                $prize_pool = $_POST['prize_pool'] ?: 0;
                $prize_type = $_POST['prize_type'] ?? 'INR';
                $max_participants = $_POST['max_participants'];
                $tournament_id = $_POST['tournament_id'] ?: null;
                $team1_id = $_POST['team1_id'] ?: null;
                $team2_id = $_POST['team2_id'] ?: null;
                $map_name = isset($_POST['map_name']) ? $_POST['map_name'] : 'Erangel';
                
                // New fields for website currency and prize distribution
                $website_currency_type = isset($_POST['website_currency_type']) ? $_POST['website_currency_type'] : null;
                $website_currency_amount = isset($_POST['website_currency_amount']) ? $_POST['website_currency_amount'] : 0;
                $prize_distribution = isset($_POST['prize_distribution']) ? $_POST['prize_distribution'] : 'single';
                $coins_per_kill = isset($_POST['coins_per_kill']) ? $_POST['coins_per_kill'] : 0;

                try {
                    $db->beginTransaction();
                    
                    // Check if this is an update or new match
                    $match_id = isset($_POST['match_id']) && !empty($_POST['match_id']) ? $_POST['match_id'] : null;
                    
                    if ($match_id) {
                        // Update existing match
                        $stmt = $db->prepare("UPDATE matches SET 
                            game_id = ?, 
                            tournament_id = ?, 
                            team1_id = ?, 
                            team2_id = ?, 
                            match_type = ?, 
                            match_date = ?, 
                            entry_type = ?, 
                            entry_fee = ?, 
                            prize_pool = ?, 
                            prize_type = ?, 
                            max_participants = ?, 
                            map_name = ?,
                            website_currency_type = ?, 
                            website_currency_amount = ?, 
                            prize_distribution = ?, 
                            coins_per_kill = ?
                            WHERE id = ?");
                        
                        $stmt->execute([
                            $game_id, $tournament_id, $team1_id, $team2_id, $match_type, $match_date,
                            $entry_type, $entry_fee, $prize_pool, $prize_type, $max_participants, $map_name,
                            $website_currency_type, $website_currency_amount, $prize_distribution, $coins_per_kill,
                            $match_id
                        ]);
                        
                        // Update team participants
                        $stmt = $db->prepare("DELETE FROM match_participants WHERE match_id = ? AND user_id IS NULL");
                        $stmt->execute([$match_id]);
                        
                        if ($team1_id) {
                            $stmt = $db->prepare("INSERT IGNORE INTO match_participants (match_id, team_id) VALUES (?, ?)");
                            $stmt->execute([$match_id, $team1_id]);
                        }
                        if ($team2_id) {
                            $stmt = $db->prepare("INSERT IGNORE INTO match_participants (match_id, team_id) VALUES (?, ?)");
                            $stmt->execute([$match_id, $team2_id]);
                        }
                    } else {
                        // Insert new match
                        $stmt = $db->prepare("INSERT INTO matches (
                            game_id, tournament_id, team1_id, team2_id, match_type, match_date, 
                            entry_type, entry_fee, prize_pool, prize_type, max_participants, map_name,
                            website_currency_type, website_currency_amount, prize_distribution, coins_per_kill,
                            status
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming'
                        )");
                        
                        $stmt->execute([
                            $game_id, $tournament_id, $team1_id, $team2_id, $match_type, $match_date,
                            $entry_type, $entry_fee, $prize_pool, $prize_type, $max_participants, $map_name,
                            $website_currency_type, $website_currency_amount, $prize_distribution, $coins_per_kill
                        ]);
                        
                        $match_id = $db->lastInsertId();
                        
                        // Add initial participants (teams)
                        if ($team1_id) {
                            $stmt = $db->prepare("INSERT INTO match_participants (match_id, team_id) VALUES (?, ?)");
                            $stmt->execute([$match_id, $team1_id]);
                        }
                        if ($team2_id) {
                            $stmt = $db->prepare("INSERT INTO match_participants (match_id, team_id) VALUES (?, ?)");
                            $stmt->execute([$match_id, $team2_id]);
                        }
                    }
                    
                    $db->commit();
                    header("Location: bgmi.php");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error creating/updating match: " . $e->getMessage());
                }
                break;

            case 'start_match':
                $match_id = $_POST['match_id'];
                $room_code = $_POST['room_code'];
                $room_password = $_POST['room_password'];
                
                try {
                    $db->beginTransaction();
                    
                    $stmt = $db->prepare("UPDATE matches SET status = 'in_progress', started_at = NOW(), room_code = ?, room_password = ?, room_details_added_at = NOW() WHERE id = ?");
                    $stmt->execute([$room_code, $room_password, $match_id]);
                    
                    $db->commit();
                    header("Location: bgmi.php");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error starting match: " . $e->getMessage());
                }
                break;

            case 'complete_match':
                $match_id = $_POST['match_id'];
                try {
                    $db->beginTransaction();
                    
                    // Get match details
                    $stmt = $db->prepare("SELECT team1_id, team2_id, score_team1, score_team2 FROM matches WHERE id = ?");
                    $stmt->execute([$match_id]);
                    $match = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Determine winner
                    $winner_id = null;
                    if ($match['score_team1'] > $match['score_team2']) {
                        $winner_id = $match['team1_id'];
                    } elseif ($match['score_team2'] > $match['score_team1']) {
                        $winner_id = $match['team2_id'];
                    }
                    
                    // Update match status
                    $stmt = $db->prepare("UPDATE matches SET status = 'completed', completed_at = NOW(), winner_id = ? WHERE id = ?");
                    $stmt->execute([$winner_id, $match_id]);
                    
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error completing match: " . $e->getMessage());
                }
                break;

            case 'delete_match':
                $match_id = $_POST['match_id'];
                try {
                    $db->beginTransaction();
                    
                    // Delete match participants first
                    $stmt = $db->prepare("DELETE FROM match_participants WHERE match_id = ?");
                    $stmt->execute([$match_id]);
                    
                    // Then delete the match
                    $stmt = $db->prepare("DELETE FROM matches WHERE id = ?");
                    $stmt->execute([$match_id]);
                    
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error deleting match: " . $e->getMessage());
                }
                break;

            case 'cancel_match':
                $match_id = $_POST['match_id'];
                try {
                    // Redirect to match_scoring.php to handle the cancellation
                    header("Location: match_scoring.php?action=cancel_match&match_id=" . $match_id);
                    exit;
                } catch (Exception $e) {
                    error_log("Error redirecting to cancel match: " . $e->getMessage());
                }
                break;
        }
    }
}

// Fetch all matches with game details
$stmt = $db->query("SELECT m.*, g.name as game_name, g.image_url as game_image,
                           t1.name as team1_name, t2.name as team2_name,
                           (SELECT COUNT(*) FROM match_participants WHERE match_id = m.id) as current_participants,
                           CASE 
                             WHEN m.winner_id = m.team1_id THEN t1.name
                             WHEN m.winner_id = m.team2_id THEN t2.name
                             ELSE NULL 
                           END as winner_name,
                           DATE(m.match_date) as match_date,
                           TIME(m.match_date) as match_time,
                           t.name as tournament_name,
                           m.website_currency_type,
                           m.website_currency_amount,
                           m.prize_distribution,
                           m.coins_per_kill
                    FROM matches m 
                    LEFT JOIN games g ON m.game_id = g.id 
                    LEFT JOIN teams t1 ON m.team1_id = t1.id
                    LEFT JOIN teams t2 ON m.team2_id = t2.id
                    LEFT JOIN tournaments t ON m.tournament_id = t.id
                    WHERE g.name = 'BGMI'
                    ORDER BY m.match_date DESC");
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active games
$stmt = $db->query("SELECT id, name FROM games WHERE status = 'active' ORDER BY name");
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all teams
$stmt = $db->query("SELECT id, name FROM teams ORDER BY name");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all tournaments
$stmt = $db->query("SELECT id, name FROM tournaments ORDER BY name");
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="bgmi.php">
                            <i class="bi bi-controller"></i> BGMI Matches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pubg.php">
                            <i class="bi bi-trophy"></i> PUBG Matches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="freefire.php">
                            <i class="bi bi-people"></i> Free Fire Matches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cod.php">
                            <i class="bi bi-joystick"></i> COD Matches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../users.php">
                            <i class="bi bi-person"></i> Users
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h3 mb-0">BGMI Match Management</h2>
                    <button type="button" class="btn btn-primary" id="addMatchButton">
                        <i class="bi bi-plus-circle"></i> Add New Match
                    </button>
                </div>

                <div class="matches-grid">
                    <?php foreach ($matches as $match): ?>
                        <div class="match-card">
                            <div class="match-header">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($match['game_image']): ?>
                                        <img src="../<?= htmlspecialchars($match['game_image']) ?>" alt="<?= htmlspecialchars($match['game_name']) ?>" class="game-icon">
                                    <?php endif; ?>
                                    <div>
                                        <h3><?= htmlspecialchars($match['game_name']) ?></h3>
                                        <div class="match-subtitle">
                                            <span class="map-badge">
                                                <i class="bi bi-map"></i> <?= htmlspecialchars($match['map_name']) ?>
                                            </span>
                                            <span class="match-type-badge">
                                                <i class="bi bi-controller"></i> <?= htmlspecialchars(ucfirst($match['match_type'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php if (isset($match['tournament_name']) && $match['tournament_name']): ?>
                                    <div class="tournament-name">
                                        <i class="bi bi-trophy"></i> <?= htmlspecialchars($match['tournament_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="match-info">
                                <div class="info-group">
                                    <div class="info-item">
                                        <i class="bi bi-calendar"></i>
                                        <?= date('M j, Y', strtotime($match['match_date'])) ?>
                                    </div>
                                    <div class="info-item">
                                        <i class="bi bi-clock"></i>
                                        <?= date('g:i A', strtotime($match['match_time'])) ?>
                                    </div>
                                </div>
                                <div class="info-group">
                                    <div class="info-item">
                                        <i class="bi bi-people"></i>
                                        <?= $match['current_participants'] ?>/<?= $match['max_participants'] ?>
                                    </div>
                                    <div class="info-item prize-pool">
                                        <i class="bi bi-trophy-fill"></i> 
                                        <?php 
                                            if ($match['website_currency_type'] && $match['website_currency_amount'] > 0) {
                                                echo number_format($match['website_currency_amount']) . ' ' . ucfirst($match['website_currency_type']);
                                            } else {
                                                $currency_symbol = isset($match['prize_type']) && $match['prize_type'] === 'USD' ? '$' : '₹';
                                                echo $currency_symbol . ' ' . number_format($match['prize_pool']); 
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="info-item entry-fee">
                                    <i class="bi bi-ticket"></i> 
                                    <?php if ($match['entry_type'] === 'free'): ?>
                                        Free Entry
                                    <?php else: ?>
                                        <?= number_format($match['entry_fee']) ?> <?= ucfirst($match['entry_type']) ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($match['prize_distribution'] || $match['coins_per_kill'] > 0): ?>
                                <div class="prize-details">
                                    <?php if ($match['prize_distribution']): ?>
                                        <div class="info-item distribution-info">
                                            <i class="bi bi-diagram-3"></i>
                                            Distribution: <?= ucfirst(str_replace('_', ' ', $match['prize_distribution'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($match['coins_per_kill'] > 0): ?>
                                        <div class="info-item kill-reward">
                                            <i class="bi bi-star"></i>
                                            <?= number_format($match['coins_per_kill']) ?> Coins per Kill
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($match['team1_id'] && $match['team2_id']): ?>
                            <div class="teams-container">
                                <div class="team team1">
                                    <div class="team-info">
                                        <?php if (isset($match['team1_logo'])): ?>
                                            <img src="<?= htmlspecialchars($match['team1_logo']) ?>" alt="<?= htmlspecialchars($match['team1_name']) ?>" class="team-logo">
                                        <?php endif; ?>
                                        <span class="team-name"><?= htmlspecialchars($match['team1_name']) ?></span>
                                    </div>
                                    <span class="team-score"><?= $match['score_team1'] ?? '0' ?></span>
                                </div>
                                <div class="vs">VS</div>
                                <div class="team team2">
                                    <span class="team-score"><?= $match['score_team2'] ?? '0' ?></span>
                                    <div class="team-info">
                                        <?php if (isset($match['team2_logo'])): ?>
                                            <img src="<?= htmlspecialchars($match['team2_logo']) ?>" alt="<?= htmlspecialchars($match['team2_name']) ?>" class="team-logo">
                                        <?php endif; ?>
                                        <span class="team-name"><?= htmlspecialchars($match['team2_name']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($match['status'] === 'in_progress' && $match['room_code'] && $match['room_password']): ?>
                            <div class="room-details">
                                <div class="info-item room-info">
                                    <i class="bi bi-door-open"></i>
                                    <span>Room Code: <strong><?= htmlspecialchars($match['room_code']) ?></strong></span>
                                </div>
                                <div class="info-item room-info">
                                    <i class="bi bi-key"></i>
                                    <span>Password: <strong><?= htmlspecialchars($match['room_password']) ?></strong></span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="match-status">
                                <div class="status-badge <?= $match['status'] ?>">
                                    <i class="bi bi-circle-fill"></i>
                                    <?= ucfirst(str_replace('_', ' ', $match['status'])) ?>
                                </div>
                                <?php if ($match['winner_name']): ?>
                                    <div class="winner-badge">
                                        <i class="bi bi-trophy-fill"></i> Winner: <?= htmlspecialchars($match['winner_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="match-actions">
                                <a href="match_details.php?id=<?= $match['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="bi bi-people"></i> View Participants
                                </a>
                                <?php if ($match['status'] === 'upcoming'): ?>
                                    <button class="btn btn-sm btn-primary" onclick="editMatch(<?= $match['id'] ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="startMatch(<?= $match['id'] ?>)">
                                        <i class="bi bi-play-fill"></i> Start
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="cancelMatch(<?= $match['id'] ?>)">
                                        <i class="bi bi-x-circle"></i> Cancel Match
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($match['status'] === 'in_progress'): ?>
                                    <a href="match_scoring.php?id=<?= $match['id'] ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> Update Score
                                    </a>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-danger" onclick="deleteMatch(<?= $match['id'] ?>)">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add/Edit Match Modal -->
<div class="modal fade" id="addMatchModal" tabindex="-1" aria-labelledby="addMatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMatchModalLabel">Create New Match</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="matchForm" method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_match">
                    <input type="hidden" name="match_id" id="match_id">
                    <input type="hidden" name="game_id" value="1"> <!-- BGMI game ID -->

                    <div class="row g-3">
                        <!-- Team Toggle -->
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enableTeams" onchange="toggleTeamSection()">
                                <label class="form-check-label" for="enableTeams">Add Teams to Match</label>
                            </div>
                        </div>

                        <!-- Team Selections (Hidden by default) -->
                        <div id="teamSection" style="display: none;">
                            <div class="col-md-6">
                                <label for="team1_id" class="form-label">Team 1</label>
                                <select class="form-select" id="team1_id" name="team1_id">
                                    <option value="">Select Team 1</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="team2_id" class="form-label">Team 2</label>
                                <select class="form-select" id="team2_id" name="team2_id">
                                    <option value="">Select Team 2</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Match Type -->
                        <div class="col-md-6">
                            <label for="match_type" class="form-label">Match Type</label>
                            <select class="form-select" id="match_type" name="match_type" required>
                                <option value="">Select Match Type</option>
                                <option value="solo">Solo</option>
                                <option value="duo">Duo</option>
                                <option value="squad">Squad</option>
                                <option value="tdm">Team Deathmatch</option>
                            </select>
                            <div class="invalid-feedback">Please select a match type.</div>
                        </div>

                        <!-- Entry Type -->
                        <div class="col-md-6">
                            <label for="entry_type" class="form-label">Entry Type</label>
                            <select class="form-select" id="entry_type" name="entry_type" required onchange="toggleEntryFee()">
                                <option value="">Select Entry Type</option>
                                <option value="free">Free</option>
                                <option value="coins">Coins</option>
                                <option value="tickets">Tickets</option>
                            </select>
                            <div class="invalid-feedback">Please select an entry type.</div>
                        </div>

                        <!-- Entry Fee -->
                        <div class="col-md-6" id="entryFeeContainer" style="display: none;">
                            <label for="entry_fee" class="form-label">Entry Fee</label>
                            <input type="number" class="form-control" id="entry_fee" name="entry_fee" min="0">
                            <div class="invalid-feedback">Please enter a valid entry fee.</div>
                        </div>

                        <!-- Max Participants -->
                        <div class="col-md-6">
                            <label for="max_participants" class="form-label">Max Participants</label>
                            <input type="number" class="form-control" id="max_participants" name="max_participants" required min="2">
                            <div class="invalid-feedback">Please enter the maximum number of participants.</div>
                        </div>

                        <!-- Prize Pool Section -->
                        <div class="col-12">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="useWebsiteCurrency" onchange="togglePrizeCurrency()">
                                <label class="form-check-label" for="useWebsiteCurrency">Use Website Currency for Prize Pool</label>
                            </div>
                        </div>

                        <!-- Real Currency Prize Section -->
                        <div id="realCurrencySection">
                            <div class="col-md-6">
                                <label for="prize_type" class="form-label">Prize Currency</label>
                                <select class="form-select" id="prize_type" name="prize_type">
                                    <option value="INR">₹ (INR)</option>
                                    <option value="USD">$ (USD)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="prize_pool" class="form-label">Prize Pool Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="prize-currency">₹</span>
                                    <input type="number" class="form-control" id="prize_pool" name="prize_pool" min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Website Currency Prize Section -->
                        <div id="websiteCurrencySection" style="display: none;">
                            <div class="col-md-6">
                                <label for="website_currency_type" class="form-label">Website Currency Type</label>
                                <select class="form-select" id="website_currency_type" name="website_currency_type">
                                    <option value="coins">Coins</option>
                                    <option value="tickets">Tickets</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="website_currency_amount" class="form-label">Prize Amount</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="website_currency_amount" name="website_currency_amount" min="0">
                                    <span class="input-group-text website-currency-label">Coins</span>
                                </div>
                            </div>
                        </div>

                        <!-- Prize Distribution -->
                        <div class="col-md-6">
                            <label for="prize_distribution" class="form-label">Prize Distribution</label>
                            <select class="form-select" id="prize_distribution" name="prize_distribution" required>
                                <option value="single">Winner Takes All</option>
                                <option value="top3">Top 3 Positions</option>
                                <option value="top5">Top 5 Positions</option>
                            </select>
                            <div class="form-text">Select how the prize pool will be distributed among winners</div>
                        </div>

                        <!-- Coins per Kill -->
                        <div class="col-md-6">
                            <label for="coins_per_kill" class="form-label">Coins per Kill</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="coins_per_kill" name="coins_per_kill" min="0" value="0">
                                <span class="input-group-text">Coins</span>
                            </div>
                            <div class="form-text">Set how many coins a player earns for each kill (0 to disable)</div>
                        </div>

                        <!-- Map Selection -->
                        <div class="col-md-6">
                            <label for="map_name" class="form-label">Map</label>
                            <select class="form-select" id="map_name" name="map_name" required>
                                <option value="">Select Map</option>
                                <option value="Erangel">Erangel</option>
                                <option value="Miramar">Miramar</option>
                                <option value="Sanhok">Sanhok</option>
                                <option value="Vikendi">Vikendi</option>
                                <option value="Karakin">Karakin</option>
                            </select>
                            <div class="invalid-feedback">Please select a map.</div>
                        </div>

                        <!-- Match Date -->
                        <div class="col-md-6">
                            <label for="match_date" class="form-label">Match Date</label>
                            <input type="date" class="form-control" id="match_date" name="match_date" required>
                            <div class="invalid-feedback">Please select a match date.</div>
                        </div>

                        <!-- Match Time -->
                        <div class="col-md-6">
                            <label for="match_time" class="form-label">Match Time</label>
                            <input type="time" class="form-control" id="match_time" name="match_time" required>
                            <div class="invalid-feedback">Please select a match time.</div>
                        </div>

                        <!-- Rules -->
                        <div class="col-12">
                            <label for="rules" class="form-label">Match Rules</label>
                            <textarea class="form-control" id="rules" name="rules" rows="3" placeholder="Enter match rules and guidelines..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Match</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Room Details Modal -->
<div class="modal fade" id="roomDetailsModal" tabindex="-1" aria-labelledby="roomDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roomDetailsModalLabel">Add Room Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="roomDetailsForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="start_match">
                    <input type="hidden" name="match_id" id="room_match_id">
                    
                    <div class="mb-3">
                        <label for="room_code" class="form-label">Room Code</label>
                        <input type="text" class="form-control" id="room_code" name="room_code" required>
                        <div class="form-text">Enter the room code for participants to join.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="room_password" class="form-label">Room Password</label>
                        <input type="text" class="form-control" id="room_password" name="room_password" required>
                        <div class="form-text">Enter the room password for participants to join.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Start Match</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    const forms = document.querySelectorAll('.needs-validation')
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Initialize Bootstrap modals
const addMatchModal = new bootstrap.Modal(document.getElementById('addMatchModal'));
const roomDetailsModal = new bootstrap.Modal(document.getElementById('roomDetailsModal'));

// Toggle entry fee based on entry type
function toggleEntryFee() {
    const entryType = document.getElementById('entry_type').value;
    const entryFeeContainer = document.getElementById('entryFeeContainer');
    const entryFeeInput = document.getElementById('entry_fee');
    
    if (entryType === 'free') {
        entryFeeContainer.style.display = 'none';
        entryFeeInput.value = '0';
        entryFeeInput.required = false;
    } else {
        entryFeeContainer.style.display = 'block';
        entryFeeInput.required = true;
    }
}

// Toggle team section
function toggleTeamSection() {
    const enableTeams = document.getElementById('enableTeams');
    const teamSection = document.getElementById('teamSection');
    teamSection.style.display = enableTeams.checked ? 'block' : 'none';
    
    // Clear team selections when disabled
    if (!enableTeams.checked) {
        document.getElementById('team1_id').value = '';
        document.getElementById('team2_id').value = '';
    }
}

// Toggle between real currency and website currency
function togglePrizeCurrency() {
    const useWebsiteCurrency = document.getElementById('useWebsiteCurrency').checked;
    const realCurrencySection = document.getElementById('realCurrencySection');
    const websiteCurrencySection = document.getElementById('websiteCurrencySection');
    
    if (useWebsiteCurrency) {
        realCurrencySection.style.display = 'none';
        websiteCurrencySection.style.display = 'block';
        // Only reset real currency values if they haven't been set yet
        if (!document.getElementById('prize_pool').value) {
            document.getElementById('prize_pool').value = '0';
            document.getElementById('prize_type').value = 'INR';
        }
    } else {
        realCurrencySection.style.display = 'block';
        websiteCurrencySection.style.display = 'none';
        // Only reset website currency values if they haven't been set yet
        if (!document.getElementById('website_currency_amount').value) {
            document.getElementById('website_currency_amount').value = '0';
            document.getElementById('website_currency_type').value = 'coins';
        }
    }
}

// Update website currency label when type changes
document.getElementById('website_currency_type').addEventListener('change', function() {
    const label = this.value.charAt(0).toUpperCase() + this.value.slice(1);
    document.querySelector('.website-currency-label').textContent = label;
});

// Initialize date and time inputs with current values
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    const dateInput = document.getElementById('match_date');
    const timeInput = document.getElementById('match_time');
    
    // Set minimum date to today
    const today = now.toISOString().split('T')[0];
    dateInput.min = today;
    dateInput.value = today;
    
    // Set default time to next hour
    now.setHours(now.getHours() + 1);
    now.setMinutes(0);
    timeInput.value = now.toTimeString().slice(0, 5);

    // Add click handler for Add New Match button
    document.getElementById('addMatchButton').addEventListener('click', function() {
        resetMatchForm();
        addMatchModal.show();
    });
});

// Handle match actions
function startMatch(matchId) {
    document.getElementById('room_match_id').value = matchId;
    roomDetailsModal.show();
}

function completeMatch(matchId) {
    if (confirm('Are you sure you want to mark this match as completed?')) {
        submitForm('complete_match', { match_id: matchId });
    }
}

function deleteMatch(matchId) {
    if (confirm('Are you sure you want to delete this match? This action cannot be undone and will remove all related data including participant registrations and scores.')) {
        const formData = new FormData();
        formData.append('action', 'delete_match');
        formData.append('match_id', matchId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (response.ok) {
                window.location.reload();
            }
        }).catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the match. Please try again.');
        });
    }
}

// Handle room details form submission
document.getElementById('roomDetailsForm').addEventListener('submit', function(event) {
    event.preventDefault();
    
    if (confirm('Are you sure you want to start this match?')) {
        const formData = new FormData(this);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (response.ok) {
                roomDetailsModal.hide();
                window.location.reload();
            }
        }).catch(error => {
            console.error('Error:', error);
            alert('An error occurred while starting the match. Please try again.');
        });
    }
});

// Helper function to submit forms
function submitForm(action, data) {
    const form = document.createElement('form');
    form.method = 'POST';
    
    // Add action
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);
    
    // Add other data
    Object.keys(data).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = data[key];
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
}

// Reset match form
function resetMatchForm() {
    const form = document.getElementById('matchForm');
    const modalTitle = document.getElementById('addMatchModalLabel');
    const submitButton = form.querySelector('button[type="submit"]');
    
    // Reset form
    form.reset();
    form.classList.remove('was-validated');
    
    // Update form for adding
    modalTitle.textContent = 'Create New Match';
    submitButton.textContent = 'Create Match';
    form.elements['action'].value = 'add_match';
    form.elements['match_id'].value = '';
    
    // Initialize date and time
    const now = new Date();
    const today = now.toISOString().split('T')[0];
    form.elements['match_date'].value = today;
    now.setHours(now.getHours() + 1);
    now.setMinutes(0);
    form.elements['match_time'].value = now.toTimeString().slice(0, 5);
    
    // Reset entry fee
    toggleEntryFee();
}

function editMatch(matchId) {
    // Fetch match details via AJAX
    fetch(`get_match.php?id=${matchId}`)
        .then(response => response.json())
        .then(match => {
            // Populate form fields
            const form = document.getElementById('matchForm');
            form.elements['match_id'].value = match.id;
            form.elements['game_id'].value = match.game_id;
            form.elements['match_type'].value = match.match_type;
            form.elements['entry_type'].value = match.entry_type;
            form.elements['entry_fee'].value = match.entry_fee;
            form.elements['max_participants'].value = match.max_participants;
            form.elements['prize_type'].value = match.prize_type;
            form.elements['prize_pool'].value = match.prize_pool;
            form.elements['map_name'].value = match.map_name;
            form.elements['prize_distribution'].value = match.prize_distribution || 'single';
            form.elements['coins_per_kill'].value = match.coins_per_kill || 0;
            
            // Set date and time
            const matchDateTime = new Date(match.match_date);
            form.elements['match_date'].value = matchDateTime.toISOString().split('T')[0];
            form.elements['match_time'].value = matchDateTime.toTimeString().slice(0,5);
            
            // Handle teams
            if (match.team1_id || match.team2_id) {
                document.getElementById('enableTeams').checked = true;
                document.getElementById('teamSection').style.display = 'block';
                form.elements['team1_id'].value = match.team1_id || '';
                form.elements['team2_id'].value = match.team2_id || '';
            }
            
            // Handle website currency
            if (match.website_currency_type) {
                document.getElementById('useWebsiteCurrency').checked = true;
                form.elements['website_currency_type'].value = match.website_currency_type;
                form.elements['website_currency_amount'].value = match.website_currency_amount || 0;
                togglePrizeCurrency();
            }
            
            // Update form for editing
            document.getElementById('addMatchModalLabel').textContent = 'Edit Match';
            form.querySelector('button[type="submit"]').textContent = 'Update Match';
            form.elements['action'].value = 'add_match';
            
            addMatchModal.show();
        })
        .catch(error => {
            console.error('Error fetching match details:', error);
            alert('Failed to load match details. Please try again.');
        });
}

// Update form submission to handle both add and edit
document.getElementById('matchForm').addEventListener('submit', function(event) {
    event.preventDefault();
    
    if (!this.checkValidity()) {
        event.stopPropagation();
        this.classList.add('was-validated');
        return;
    }
    
    const formData = new FormData(this);
    const isEdit = formData.get('match_id') !== '';
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(response => {
        if (response.ok) {
            addMatchModal.hide();
            window.location.reload();
        }
    }).catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
});

function cancelMatch(matchId) {
    if (confirm('Are you sure you want to cancel this match? This will refund all participants and cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="cancel_match">
            <input type="hidden" name="match_id" value="${matchId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<style>
body {
    font-size: .875rem;
}

.sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 48px 0 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}

.sidebar .nav-link {
    font-weight: 500;
    color: #333;
    padding: 0.75rem 1rem;
}

.sidebar .nav-link i {
    margin-right: 0.5rem;
}

.sidebar .nav-link.active {
    color: #0d6efd;
}

main {
    padding-top: 1.5rem;
}

.matches-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    padding: 1rem 0;
}

.match-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 1.5rem;
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #eee;
}

.match-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.match-header {
    border-bottom: 1px solid #eee;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
}

.match-header h3 {
    margin: 0;
    color: #333;
    font-size: 1.25rem;
    font-weight: 600;
}

.match-subtitle {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.map-badge, .match-type-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.map-badge {
    background-color: #e3f2fd;
    color: #0d47a1;
}

.match-type-badge {
    background-color: #f3e5f5;
    color: #7b1fa2;
}

.tournament-name {
    color: #666;
    font-size: 0.875rem;
    margin-top: 0.75rem;
    padding: 0.5rem;
    background: #fff8e1;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.match-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 12px;
}

.info-group {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #555;
    font-size: 0.875rem;
    background: white;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.prize-pool {
    color: #2e7d32;
    background-color: #e8f5e9;
    border-color: #c8e6c9;
    font-weight: 500;
}

.entry-fee {
    width: 100%;
    justify-content: center;
    color: #0d47a1;
    background-color: #e3f2fd;
    border-color: #bbdefb;
}

.teams-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 1.5rem 0;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.team {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.team1 {
    text-align: right;
    justify-content: flex-end;
}

.team2 {
    text-align: left;
    justify-content: flex-start;
}

.team-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.team-name {
    font-weight: 600;
    color: #333;
}

.team-score {
    font-size: 2rem;
    font-weight: 700;
    color: #0d6efd;
    min-width: 2.5rem;
    text-align: center;
    padding: 0.25rem 0.75rem;
    background: white;
    border-radius: 8px;
    border: 2px solid #e9ecef;
}

.vs {
    font-weight: 600;
    color: #6c757d;
    padding: 0 1.5rem;
    font-size: 1.25rem;
}

.match-status {
    margin: 1rem 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.status-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge.upcoming {
    background: #e8eaed;
    color: #3c4043;
}

.status-badge.in_progress {
    background: #e3f2fd;
    color: #0d47a1;
}

.status-badge.completed {
    background: #e8f5e9;
    color: #2e7d32;
}

.status-badge.cancelled {
    background: #ffebee;
    color: #c62828;
}

.winner-badge {
    background: #fff8e1;
    color: #f57f17;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid #ffe082;
}

.match-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

.match-actions .btn {
    flex: 1;
    min-width: max-content;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 500;
}

.game-icon {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 12px;
    border: 2px solid #eee;
}

.team-logo {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid #dee2e6;
    background: white;
    padding: 2px;
}

/* Modal styles */
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.modal-header {
    border-bottom: 2px solid #f8f9fa;
    padding: 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 2px solid #f8f9fa;
    padding: 1.5rem;
}

.form-label {
    font-weight: 500;
    color: #495057;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border-radius: 8px;
    padding: 0.75rem 1rem;
    border-color: #dee2e6;
}

.form-control:focus, .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25);
}

@media (max-width: 768px) {
    .matches-grid {
        grid-template-columns: 1fr;
    }
    
    .teams-container {
        padding: 1rem;
    }
    
    .team-score {
        font-size: 1.5rem;
    }
    
    .vs {
        padding: 0 1rem;
    }
    
    .match-actions {
        flex-direction: column;
    }
    
    .match-actions .btn {
        width: 100%;
    }
}

.room-details {
    margin: 1rem 0;
    padding: 1rem;
    background: #fff3e0;
    border-radius: 12px;
    border: 1px solid #ffe0b2;
}

.room-details .room-info {
    background: white;
    margin-bottom: 0.5rem;
    border-color: #ffe0b2;
    color: #e65100;
}

.room-details .room-info:last-child {
    margin-bottom: 0;
}

.room-details .room-info strong {
    font-family: monospace;
    background: #fff3e0;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    margin-left: 0.25rem;
}

/* New styles for the prize section */
.form-switch {
    padding-left: 2.5em;
}

.form-switch .form-check-input {
    width: 3em;
}

#realCurrencySection,
#websiteCurrencySection {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.website-currency-label {
    min-width: 70px;
    justify-content: center;
}

/* Prize details styles */
.prize-details {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 0.75rem;
}

.distribution-info {
    color: #5c6bc0;
    background-color: #e8eaf6;
    border-color: #c5cae9;
}

.kill-reward {
    color: #f9a825;
    background-color: #fff8e1;
    border-color: #ffe082;
}

.prize-details .info-item {
    flex: 1;
    justify-content: center;
    min-width: 150px;
}
</style>

<?php include '../includes/admin-footer.php'; ?>
