<?php
session_start();
require_once '../../includes/user-auth.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: /KGX/pages/login.php");
    exit();
}

// Get database connection
$conn = getDbConnection();

$user_id = $_SESSION['user_id'];
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']); // Clear messages after displaying

// Get user's coin balance
$sql = "SELECT coins FROM user_coins WHERE user_id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user_coins = $stmt->fetch(PDO::FETCH_ASSOC);
$coin_balance = $user_coins ? $user_coins['coins'] : 0;

// Fetch items
$sql = "SELECT * FROM redeemable_items WHERE (stock > 0 OR is_unlimited = 1) AND is_active = 1"; // Only show active items
$stmt = $conn->prepare($sql);
$stmt->execute();
$redeemable_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle POST requests (Redemption or Conversion)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Handle Item Redemption
    if (isset($_POST['redeem'])) {
        $item_id = $_POST['item_id'];
        // Fetch item details (including requires_approval)
        $sql = "SELECT coin_cost, stock, is_unlimited, requires_approval FROM redeemable_items WHERE id = :item_id AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':item_id', $item_id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item && $coin_balance >= $item['coin_cost'] && ($item['stock'] > 0 || $item['is_unlimited'])) {
            // Start transaction
            $conn->beginTransaction();
            try {
                // Deduct coins
                $sql = "UPDATE user_coins SET coins = coins - :coin_cost WHERE user_id = :user_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':coin_cost', $item['coin_cost']);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();

                // Update stock only if not unlimited
                if (!$item['is_unlimited']) {
                    $sql = "UPDATE redeemable_items SET stock = stock - 1 WHERE id = :item_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':item_id', $item_id);
                    $stmt->execute();
                }

                // Set status based on whether approval is required
                $status = $item['requires_approval'] ? 'pending' : 'completed';
                
                // Record the redemption in the redemption_history table
                $sql = "INSERT INTO redemption_history (user_id, item_id, coins_spent, status) VALUES (:user_id, :item_id, :coins_spent, :status)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':item_id', $item_id);
                $stmt->bindParam(':coins_spent', $item['coin_cost']);
                $stmt->bindParam(':status', $status);
                $stmt->execute();

                // Commit transaction
                $conn->commit();

                if ($status == 'pending') {
                    $_SESSION['success_message'] = "Redemption request submitted successfully! Waiting for admin approval.";
                } else {
                    $_SESSION['success_message'] = "Item redeemed successfully!";
                }
            } catch (PDOException $e) {
                $conn->rollBack();
                $_SESSION['error_message'] = "An error occurred during redemption. Please try again.";
                error_log("PDO Exception: " . $e->getMessage());
            }
        } else {
             if (!$item) {
                 $_SESSION['error_message'] = "Item not found or inactive.";
             } elseif ($coin_balance < $item['coin_cost']){
                 $_SESSION['error_message'] = "Not enough coins.";
             } else {
                 $_SESSION['error_message'] = "Item out of stock.";
             }
        }
    } 
    // Handle Coin to Ticket Conversion
    elseif (isset($_POST['convert_ticket'])) {
        $conversion_cost = 200;
        if ($coin_balance >= $conversion_cost) {
            // Start transaction
            $conn->beginTransaction();
            try {
                // Deduct coins
                $sql_deduct = "UPDATE user_coins SET coins = coins - :cost WHERE user_id = :user_id";
                $stmt_deduct = $conn->prepare($sql_deduct);
                $stmt_deduct->bindParam(':cost', $conversion_cost);
                $stmt_deduct->bindParam(':user_id', $user_id);
                $stmt_deduct->execute();

                // Add ticket (handles insert or update)
                $sql_ticket = "INSERT INTO user_tickets (user_id, tickets) VALUES (:user_id, 1) ON DUPLICATE KEY UPDATE tickets = tickets + 1";
                $stmt_ticket = $conn->prepare($sql_ticket);
                $stmt_ticket->bindParam(':user_id', $user_id);
                $stmt_ticket->execute();

                // Create notification for the user
                $notificationMessage = "Successfully converted 200 coins to 1 ticket!";
                $notification_sql = "INSERT INTO notifications (
                    user_id,
                    type,
                    message,
                    related_id,
                    related_type,
                    created_at
                ) VALUES (
                    :user_id,
                    'coin_conversion',
                    :message,
                    NULL,
                    'ticket',
                    NOW()
                )";
                $notification_stmt = $conn->prepare($notification_sql);
                $notification_stmt->bindParam(':user_id', $user_id);
                $notification_stmt->bindParam(':message', $notificationMessage);
                $notification_stmt->execute();

                // Commit transaction
                $conn->commit();
                $_SESSION['success_message'] = "Successfully converted 200 coins to 1 ticket!";

            } catch (PDOException $e) {
                $conn->rollBack();
                $_SESSION['error_message'] = "An error occurred during conversion. Please try again.";
                error_log("PDO Exception: " . $e->getMessage());
            }
        } else {
            $_SESSION['error_message'] = "Not enough coins (200 required) to convert to a ticket.";
        }
    }

    // Redirect to the same page to prevent form resubmission and show messages
    header("Location: redeem.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Redeem Center</title>
    <link rel="stylesheet" href="../../assets/css/root.css">
    <link rel="stylesheet" href="../../assets/css/dashboard/redeem.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="page-header">
        <a href="./dashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <button class="history-toggle" onclick="toggleHistory()">
            <i class="fas fa-history"></i> View History
        </button>
    </div>

    <h2 class="page-title">Redeem Center</h2>

    <?php if($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="coin-balance">
        Your Balance: <strong><?php echo number_format($coin_balance); ?> Coins</strong>
    </div>

    <!-- Rest of your existing redeem cards code -->
    <div class="redeem-container">
        <!-- Coin to Ticket Conversion Card -->
        <div class="redeem-card conversion-card">
            <img src="../../assets/images/ticket-icon.png" alt="Ticket" /> <!-- Replace with actual ticket icon path -->
            <h3>Convert Coins to Ticket</h3>
            <p>Exchange 200 Coins for 1 Ticket</p>
            <p>Use tickets for special entries!</p>
            <form method="POST" onsubmit="return confirm('Convert 200 coins to 1 ticket?');">
                <button type="submit" name="convert_ticket" <?php echo ($coin_balance < 200) ? 'disabled' : ''; ?>>
                    Convert (200 Coins)
                </button>
            </form>
        </div>

        <?php foreach($redeemable_items as $item): ?>
            <div class="redeem-card">
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" />
                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                <p><?php echo htmlspecialchars($item['description']); ?></p>
                <p>Cost: <?php echo $item['coin_cost']; ?> Coins</p>
                <p>Stock: <?php echo $item['is_unlimited'] ? 'Unlimited' : $item['stock'] . ' left'; ?></p>
                <form method="POST" onsubmit="return confirm('Are you sure you want to redeem this item?');">
                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                    <input type="hidden" name="coin_cost" value="<?php echo $item['coin_cost']; ?>">
                    <button type="submit" name="redeem" <?php echo ($coin_balance < $item['coin_cost']) ? 'disabled' : ''; ?>>
                        Redeem
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- History Section -->
    <div class="redemption-history" id="historySection">
        <div class="cardHeader">
            <h2>Redemption History</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <td>Name</td>
                    <td>Price</td>
                    <td>Status</td>
                    <td>Date</td>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Fetch redeemed items
                $redemption_items = [];
                $sql_redemption = "SELECT ri.name, rh.coins_spent, rh.status, rh.redeemed_at
                                FROM redemption_history rh
                                JOIN redeemable_items ri ON rh.item_id = ri.id
                                WHERE rh.user_id = :user_id
                                ORDER BY rh.redeemed_at DESC";
                $stmt_redemption = $conn->prepare($sql_redemption);
                if ($stmt_redemption) {
                    $stmt_redemption->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    if (!$stmt_redemption->execute()) {
                        error_log("Redeem: Error executing redemption statement: " . $stmt_redemption->errorInfo()[2]);
                    } else {
                        $redemption_items = $stmt_redemption->fetchAll(PDO::FETCH_ASSOC);
                    }
                } else {
                    error_log("Redeem: Failed to prepare redemption statement: " . $conn->errorInfo()[2]);
                }

                if (count($redemption_items) > 0):
                    foreach ($redemption_items as $row): 
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo $row['coins_spent']; ?> Coins</td>
                        <td><span class="status <?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($row['redeemed_at'])); ?></td>
                    </tr>
                <?php 
                    endforeach; 
                else:
                ?>
                    <tr>
                        <td colspan="4" class="text-center">No redemption history found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        function toggleHistory() {
            const historySection = document.getElementById('historySection');
            const toggleButton = document.querySelector('.history-toggle');
            
            historySection.classList.toggle('active');
            
            // Update button text based on state
            if (historySection.classList.contains('active')) {
                toggleButton.innerHTML = '<i class="fas fa-times"></i> Hide History';
            } else {
                toggleButton.innerHTML = '<i class="fas fa-history"></i> View History';
            }
        }

        // Show success/error messages temporarily
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 3000);
        });
    </script>
</body>
</html>
