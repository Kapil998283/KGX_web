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
    <link rel="stylesheet" href="../ui/assets/css/style.css">
    <link rel="stylesheet" href="css/styles.css">
    
    <!-- Add custom shop styles -->
    <style>
        body {
            background: var(--eerie-black-1);
            min-height: 100vh;
        }

        main {
            padding: 20px;
            min-height: 100vh;
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--raisin-black-1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 25px;
            color: var(--quick-silver);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .back-button:hover {
            background: var(--raisin-black-2);
            color: var(--orange);
            transform: translateX(-5px);
        }

        .back-button ion-icon {
            font-size: 1.2em;
        }

        /* Reset header styles */
        .navbar {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            padding: 20px 0 !important;
        }

        .navbar-list {
            display: flex !important;
            flex-direction: row !important;
            gap: 30px !important;
            margin-left: 50px !important;
        }

        .navbar-link {
            color: var(--quick-silver) !important;
            transition: color 0.3s !important;
            text-transform: uppercase !important;
            font-size: 15px !important;
            font-weight: 600 !important;
        }

        .navbar-link:hover {
            color: var(--orange) !important;
        }

        /* Shop specific styles */
        .balance-container {
            background: var(--raisin-black-1);
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            display: flex;
            justify-content: space-around;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .balance-item {
            text-align: center;
            color: var(--white);
        }

        .balance-item .amount {
            font-size: 2em;
            color: var(--orange);
            margin: 10px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .balance-item .label {
            color: var(--quick-silver);
            font-size: 0.9em;
            text-transform: uppercase;
        }

        .cards {
            margin-top: 40px;
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            padding-bottom: 50px;
        }

        .card {
            background: var(--raisin-black-1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            width: 300px;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card.active {
            background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dark) 100%);
        }

        .card ul {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: center;
        }

        .card ul li {
            padding: 10px 0;
            color: var(--quick-silver);
        }

        .card ul li.pack {
            font-size: 1.5em;
            color: var(--white);
            margin-bottom: 20px;
        }

        .card ul li.price {
            font-size: 2em;
            color: var(--orange);
            margin: 20px 0;
        }

        .card.active ul li.price {
            color: var(--white);
        }

        .btn {
            background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dark) 100%);
            color: var(--white);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 165, 0, 0.3);
        }

        .active-btn {
            background: var(--white);
            color: var(--orange);
        }

        .active-btn:hover {
            background: var(--raisin-black-2);
            color: var(--white);
        }

        /* Payment Modal Styles */
        .payment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--raisin-black-1);
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: var(--white);
            font-size: 1.5em;
        }

        .close-modal {
            color: var(--quick-silver);
            font-size: 24px;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--orange);
        }

        .payment-details {
            margin: 20px 0;
            padding: 15px;
            background: var(--raisin-black-2);
            border-radius: 10px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            color: var(--quick-silver);
        }

        .payment-row.total {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 15px;
            padding-top: 15px;
            font-weight: bold;
            color: var(--white);
        }

        .payment-amount {
            color: var(--orange);
            font-weight: bold;
        }

        /* Header styles */
        header {
            text-align: center;
            margin-bottom: 40px;
        }

        header h1 {
            color: var(--white);
            font-size: 2.5em;
            margin-bottom: 20px;
        }

        .toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            color: var(--quick-silver);
        }

        .toggle-btn {
            position: relative;
            width: 50px;
            height: 25px;
            border-radius: 25px;
            background: var(--raisin-black-2);
            cursor: pointer;
        }

        .toggle-btn .circle {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 21px;
            height: 21px;
            border-radius: 50%;
            background: var(--orange);
            transition: transform 0.3s;
        }

        .checkbox:checked + .sub .circle {
            transform: translateX(25px);
        }

        .checkbox {
            display: none;
        }
    </style>
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
                <button class="btn" onclick="initializePayment()" style="width: 100%;">Proceed to Payment</button>
            </div>
        </div>
    </main>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script src="js/payment.js"></script>
    <script>
        // Toggle between coins and tickets pricing
        const checkbox = document.getElementById("checkbox");
        const professional = document.getElementById("professional");
        const master = document.getElementById("master");
        const basic = document.getElementById("basic");

        // Payment modal functionality
        function showPaymentModal(packageName, amount, coins, tickets) {
            const modal = document.getElementById('paymentModal');
            document.getElementById('packageName').textContent = packageName;
            document.getElementById('packageCoins').textContent = coins.toLocaleString();
            document.getElementById('packageTickets').textContent = tickets.toLocaleString();
            document.getElementById('packageAmount').textContent = '₹' + amount.toLocaleString();
            
            modal.style.display = 'flex';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target == modal) {
                closePaymentModal();
            }
        }
    </script>
</body>
</html>