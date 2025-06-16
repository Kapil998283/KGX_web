<?php
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/database.php';

// Fetch statistics
$stats = [
    'total_users' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users"))['count'],
    'active_users' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status = 'active'"))['count'],
    'total_coins' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(coins) as total FROM user_coins"))['total'] ?? 0,
    'recent_users' => mysqli_fetch_all(mysqli_query($conn, "SELECT username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5"), MYSQLI_ASSOC)
];
?>

<div class="dashboard-container">
    <h1>Dashboard</h1>
    
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <div class="stat-info">
                <h3>Total Users</h3>
                <p><?php echo $stats['total_users']; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-user-check"></i>
            <div class="stat-info">
                <h3>Active Users</h3>
                <p><?php echo $stats['active_users']; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-coins"></i>
            <div class="stat-info">
                <h3>Total Coins</h3>
                <p><?php echo $stats['total_coins']; ?></p>
            </div>
        </div>
    </div>

    <div class="recent-users">
        <h2>Recent Users</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Joined Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($stats['recent_users'] as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <button class="admin-btn btn-primary action-btn" data-action="view" data-id="<?php echo $user['id']; ?>">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-card i {
    font-size: 2rem;
    color: var(--primary-color);
}

.stat-info h3 {
    margin: 0;
    font-size: 1rem;
    color: var(--text-color);
}

.stat-info p {
    margin: 5px 0 0;
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--secondary-color);
}

.recent-users {
    margin-top: 30px;
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?> 