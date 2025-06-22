<?php
session_start();
require_once '../../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Handle approval/rejection of redemption requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redemption_id']) && isset($_POST['action'])) {
    $redemption_id = $_POST['redemption_id'];
    $action = $_POST['action'];
    
    try {
        $db->beginTransaction();
        
        if ($action == 'approve') {
            // Update redemption status to completed
            $sql = "UPDATE redemption_history SET status = 'completed' WHERE id = :redemption_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['redemption_id' => $redemption_id]);

            // Get redemption details for notification
            $sql = "SELECT rh.user_id, rh.coins_spent, ri.name as item_name 
                   FROM redemption_history rh 
                   JOIN redeemable_items ri ON rh.item_id = ri.id 
                   WHERE rh.id = :redemption_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['redemption_id' => $redemption_id]);
            $redemption = $stmt->fetch(PDO::FETCH_ASSOC);

            // Create approval notification
            $notificationMessage = "Your redemption request for {$redemption['item_name']} has been approved!";
            $notification_sql = "INSERT INTO notifications (
                user_id,
                type,
                message,
                related_id,
                related_type,
                created_at
            ) VALUES (
                :user_id,
                'redemption_approved',
                :message,
                :redemption_id,
                'redemption',
                NOW()
            )";
            $notification_stmt = $db->prepare($notification_sql);
            $notification_stmt->execute([
                'user_id' => $redemption['user_id'],
                'message' => $notificationMessage,
                'redemption_id' => $redemption_id
            ]);
            
            $_SESSION['success_message'] = "Redemption request approved successfully.";
        } elseif ($action == 'reject') {
            // Update redemption status to rejected
            $sql = "UPDATE redemption_history SET status = 'rejected' WHERE id = :redemption_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['redemption_id' => $redemption_id]);
            
            // Get redemption details to refund coins and create notification
            $sql = "SELECT rh.user_id, rh.coins_spent, ri.name as item_name 
                   FROM redemption_history rh 
                   JOIN redeemable_items ri ON rh.item_id = ri.id 
                   WHERE rh.id = :redemption_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['redemption_id' => $redemption_id]);
            $redemption = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Refund coins to user
            $sql = "UPDATE user_coins SET coins = coins + :coins_spent WHERE user_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'coins_spent' => $redemption['coins_spent'],
                'user_id' => $redemption['user_id']
            ]);

            // Create rejection notification
            $notificationMessage = "Your redemption request for {$redemption['item_name']} was rejected. {$redemption['coins_spent']} coins have been refunded to your account.";
            $notification_sql = "INSERT INTO notifications (
                user_id,
                type,
                message,
                related_id,
                related_type,
                created_at
            ) VALUES (
                :user_id,
                'redemption_rejected',
                :message,
                :redemption_id,
                'redemption',
                NOW()
            )";
            $notification_stmt = $db->prepare($notification_sql);
            $notification_stmt->execute([
                'user_id' => $redemption['user_id'],
                'message' => $notificationMessage,
                'redemption_id' => $redemption_id
            ]);
            
            $_SESSION['success_message'] = "Redemption request rejected and coins refunded.";
        }
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header("Location: redemption_request.php");
    exit();
}

// Get redemption requests that need approval
$sql = "SELECT rh.*, ri.name as item_name, ri.description, ri.image_url, u.username, u.email 
        FROM redemption_history rh 
        JOIN redeemable_items ri ON rh.item_id = ri.id 
        JOIN users u ON rh.user_id = u.id 
        WHERE ri.requires_approval = 1 AND rh.status = 'pending' 
        ORDER BY rh.redeemed_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute();
$redemption_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redemption Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
        }
        .item-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="./admin_dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="add_item.php">
                                <i class="bi bi-plus-circle"></i> Add Item
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="manage_items.php">
                                <i class="bi bi-list"></i> Manage Items
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="redemption_request.php">
                                <i class="bi bi-clock-history"></i> Redemption Request
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Redemption Requests</h1>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Pending Approval Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($redemption_requests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Coins</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($redemption_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($request['image_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($request['image_url']); ?>" alt="<?php echo htmlspecialchars($request['item_name']); ?>" class="item-image me-2">
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($request['item_name']); ?></strong>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($request['description']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['username']); ?></td>
                                        <td><?php echo htmlspecialchars($request['email']); ?></td>
                                        <td><?php echo $request['coins_spent']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($request['redeemed_at'])); ?></td>
                                        <td>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="redemption_id" value="<?php echo $request['id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            No pending redemption requests at this time.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 