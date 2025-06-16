<?php
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/database.php';

// Fetch all users
$sql = "SELECT id, username, email, coins FROM users LEFT JOIN user_coins ON users.id = user_coins.user_id ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
$users = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<div class="admin-container">
    <h1>User Management</h1>
    
    <div class="search-box">
        <input type="text" id="search-input" placeholder="Search users...">
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
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
                <td><?php echo $user['coins'] ?? 0; ?></td>
                <td>
                    <button class="admin-btn btn-primary action-btn" data-action="edit" data-id="<?php echo $user['id']; ?>">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="admin-btn btn-danger action-btn" data-action="delete" data-id="<?php echo $user['id']; ?>">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.search-box {
    margin: 20px 0;
}

.search-box input {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary-color);
}
</style>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?> 