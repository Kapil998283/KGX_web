<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

// Get user's current balance
$user_id = $_SESSION['user_id'];
$db = new Database();
$conn = $db->connect();

$sql = "SELECT c.coins, t.tickets 
        FROM user_coins c 
        LEFT JOIN user_tickets t ON c.user_id = t.user_id 
        WHERE c.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$balance = $stmt->fetch(PDO::FETCH_ASSOC);

$current_coins = $balance['coins'] ?? 0;
$current_tickets = $balance['tickets'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&display=swap" rel="stylesheet" />
    <title>KGX Shop - Buy Coins & Tickets</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <a href="../dashboard.php" class="back-button">
        <ion-icon name="arrow-back-outline"></ion-icon>
        Back to Dashboard
    </a>

    <main>
        <div class="container">
            <!-- Current Balance Section -->
            <div class="balance-container">
                <div class="balance-item">
                    <div class="amount">
                        <ion-icon name="wallet-outline"></ion-icon>
                        <?php echo number_format($current_coins); ?>
                    </div>
                    <div class="label">Current Coins</div>
                </div>
                <div class="balance-item">
                    <div class="amount">
                        <ion-icon name="ticket-outline"></ion-icon>
                        <?php echo number_format($current_tickets); ?>
                    </div>
                    <div class="label">Current Tickets</div>
                </div>
            </div>

            <!-- Pricing Header -->
            <header>
                <h1>Purchase Coins & Tickets</h1>
                <div class="toggle">
                    <label>Coins</label>
                    <div class="toggle-btn">
                        <input type="checkbox" class="checkbox" id="checkbox">
                        <label class="sub" id="sub" for="checkbox">
                            <div class="circle"></div>
                        </label>
                    </div>
                    <label>Tickets</label>
                </div>
            </header>

            <!-- Pricing Cards -->
            <div class="cards">
                <div class="card shadow">
                    <ul>
                        <li class="pack">Starter</li>
                        <li id="basic" class="price bottom-bar" data-coins="1000" data-tickets="100">₹199</li>
                        <li class="bottom-bar">1,000 Coins</li>
                        <li class="bottom-bar">100 Tickets</li>
                        <li class="bottom-bar">Valid for 30 days</li>
                        <li><button class="btn" onclick="showPaymentModal('Starter', 199, 1000, 100)">Purchase Now</button></li>
                    </ul>
                </div>

                <div class="card active">
                    <ul>
                        <li class="pack">Popular</li>
                        <li id="professional" class="price bottom-bar" data-coins="2500" data-tickets="250">₹499</li>
                        <li class="bottom-bar">2,500 Coins</li>
                        <li class="bottom-bar">250 Tickets</li>
                        <li class="bottom-bar">Valid for 60 days</li>
                        <li><button class="btn active-btn" onclick="showPaymentModal('Popular', 499, 2500, 250)">Purchase Now</button></li>
                    </ul>
                </div>

                <div class="card shadow">
                    <ul>
                        <li class="pack">Premium</li>
                        <li id="master" class="price bottom-bar" data-coins="5000" data-tickets="500">₹999</li>
                        <li class="bottom-bar">5,000 Coins</li>
                        <li class="bottom-bar">500 Tickets</li>
                        <li class="bottom-bar">Valid for 90 days</li>
                        <li><button class="btn" onclick="showPaymentModal('Premium', 999, 5000, 500)">Purchase Now</button></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <div class="payment-modal" id="paymentModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Confirm Purchase</h2>
                    <span class="close-modal" onclick="closePaymentModal()">&times;</span>
                </div>
                <div class="payment-details">
                    <div class="payment-row">
                        <span>Package</span>
                        <span id="packageName"></span>
                    </div>
                    <div class="payment-row">
                        <span>Coins</span>
                        <span id="packageCoins"></span>
                    </div>
                    <div class="payment-row">
                        <span>Tickets</span>
                        <span id="packageTickets"></span>
                    </div>
                    <div class="payment-row total">
                        <span>Total Amount</span>
                        <span class="payment-amount" id="packageAmount"></span>
                    </div>
                </div>
                <button class="btn" onclick="initializePayment()">Proceed to Payment</button>
            </div>
        </div>
    </main>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script src="js/payment.js"></script>
</body>
</html>