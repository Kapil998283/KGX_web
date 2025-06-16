<?php
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/database.php';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? 0;
    
    switch($action) {
        case 'update_coins':
            $coins = (int)$_POST['coins'];
            $sql = "UPDATE user_coins SET coins = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $coins, $user_id);
            mysqli_stmt_execute($stmt);
            log_admin_action('update_coins', "Updated coins for user $user_id to $coins");
            break;
            
        case 'delete_user':
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            log_admin_action('delete_user', "Deleted user $user_id");
            break;
    }
}

// Fetch all users with their coin balances
$sql = "SELECT u.id, u.username, u.email, u.created_at, uc.coins 
        FROM users u 
        LEFT JOIN user_coins uc ON u.id = uc.user_id 
        ORDER BY u.created_at DESC";
$result = mysqli_query($conn, $sql);
$users = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<div class="admin-container">
    <h1>User Management</h1>
    
    <div class="user-management">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Joined Date</th>
                    <th>Coins</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <form method="POST" class="coin-form">
                            <input type="hidden" name="action" value="update_coins">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <input type="number" name="coins" value="<?php echo $user['coins'] ?? 0; ?>" class="form-control">
                            <button type="submit" class="admin-btn btn-primary">Update</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" class="admin-btn btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.coin-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.coin-form input[type="number"] {
    width: 80px;
    padding: 5px;
}

.user-management {
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?> 