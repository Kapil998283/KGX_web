<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /KGX/pages/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->connect();

    // Get all notifications for the user
    $sql = "SELECT * FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark all notifications as read
    $update_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
    $stmt = $conn->prepare($update_sql);
    $stmt->execute(['user_id' => $user_id]);

} catch (PDOException $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    $notifications = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="/KGX/assets/css/notifications.css">
</head>
<body>
    <main>
        <article>
            <section class="notifications-section">
                <div class="container">
                    <h2 class="section-title">Notifications</h2>
                    
                    <?php if (empty($notifications)): ?>
                        <div class="alert alert-info">
                            You have no notifications.
                        </div>
                    <?php else: ?>
                        <div class="notifications-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                    <div class="notification-content">
                                        <p class="message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <span class="time"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></span>
                                    </div>
                                    <?php if ($notification['type'] === 'join_request'): ?>
                                        <div class="notification-actions">
                                            <a href="/KGX/pages/teams/yourteams.php" class="btn btn-primary">View Team</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </article>
    </main>
</body>
</html>

<?php require_once '../includes/footer.php'; ?> 