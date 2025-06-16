<?php
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../../config/database.php';

// Handle table operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'drop_tables') {
        // Get all tables
        $tables = [];
        $result = mysqli_query($conn, "SHOW TABLES");
        while ($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }
        
        // Drop each table
        foreach ($tables as $table) {
            mysqli_query($conn, "DROP TABLE IF EXISTS `$table`");
        }
        
        log_admin_action('drop_tables', 'Dropped all database tables');
        $message = "All tables have been dropped successfully.";
    }
}
?>

<div class="admin-container">
    <h1>Database Management</h1>
    
    <div class="database-management">
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Warning: This action cannot be undone!</h3>
            <p>Dropping tables will permanently delete all data in the database.</p>
        </div>
        
        <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to drop all tables? This action cannot be undone!');">
            <input type="hidden" name="action" value="drop_tables">
            <button type="submit" class="admin-btn btn-danger">
                <i class="fas fa-trash"></i> Drop All Tables
            </button>
        </form>
        
        <?php if (isset($message)): ?>
        <div class="message-box success">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.warning-box {
    background-color: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.warning-box i {
    font-size: 1.5rem;
}

.message-box {
    margin-top: 20px;
    padding: 15px;
    border-radius: 5px;
}

.message-box.success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.database-management {
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?> 