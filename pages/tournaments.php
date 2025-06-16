<?php
require_once '../config/database.php';
require_once '../includes/header.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

try {
    $query = "SELECT 
        t.id,
        t.name,
        COALESCE(t.game_name, 'Unknown Game') as game_name,
        COALESCE(t.banner_image, '/assets/images/tournaments/default-banner.png') as banner_image,
        COALESCE(t.status, 'upcoming') as status,
        COALESCE(t.registration_phase, 'closed') as registration_phase,
        t.start_date,
        t.prize_pool,
        t.entry_fee,
        t.max_teams,
        COALESCE((SELECT COUNT(*) FROM tournament_registrations tr WHERE tr.tournament_id = t.id), 0) as current_teams
    FROM tournaments t 
    ORDER BY t.start_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching tournaments: " . $e->getMessage());
    $tournaments = [];
}
?><!-- Add your CSS file -->
<link rel="stylesheet" href="/KGX/assets/css/tournament/tournaments.css">


<div class="container mt-4">
    <h2 class="mb-4">Tournaments</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($tournaments as $tournament): ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <img src="<?= htmlspecialchars($tournament['banner_image']) ?>" 
                         class="card-img-top" 
                         alt="<?= htmlspecialchars($tournament['name']) ?>"
                         style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($tournament['name']) ?></h5>
                        <p class="card-text">
                            <small class="text-muted"><?= htmlspecialchars($tournament['game_name']) ?></small>
                        </p>
                        <div class="mb-2">
                            <span class="badge bg-info">$<?= number_format($tournament['prize_pool'], 2) ?> Prize Pool</span>
                        </div>
                        <div class="mb-2">
                            <span class="badge bg-secondary"><?= $tournament['current_teams'] ?>/<?= $tournament['max_teams'] ?> Teams</span>
                        </div>
                        <?php
                        $statusClass = match($tournament['status']) {
                            'upcoming' => 'bg-primary',
                            'ongoing' => 'bg-success',
                            'completed' => 'bg-secondary',
                            default => 'bg-info'
                        };
                        ?>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($tournament['status'])) ?></span>
                            <a href="tournaments/index.php?id=<?= $tournament['id'] ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>