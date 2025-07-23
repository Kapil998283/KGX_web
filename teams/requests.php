<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

if (!$team_id) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$conn = $db->connect();

// Check if user is team captain
$stmt = $conn->prepare("
    SELECT t.* FROM teams t
    JOIN team_members tm ON t.id = tm.team_id
    WHERE t.id = ? AND tm.user_id = ? AND tm.role = 'captain'
");
$stmt->execute([$team_id, $_SESSION['user_id']]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    header('Location: index.php');
    exit;
}

// Get pending requests
$stmt = $conn->prepare("
    SELECT r.*, u.username, u.profile_image
    FROM team_join_requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.team_id = ? AND r.status = 'pending'
    ORDER BY r.created_at DESC
");
$stmt->execute([$team_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Requests - <?php echo htmlspecialchars($team['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #1a1a1a;
            color: #fff;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .request-card {
            background: #2a2a2a;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .request-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .request-info {
            flex-grow: 1;
        }

        .request-info h3 {
            margin: 0 0 5px 0;
            color: #fff;
        }

        .request-date {
            color: #888;
            font-size: 0.9em;
            margin: 0;
        }

        .request-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: opacity 0.2s;
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-accept {
            background: #4CAF50;
            color: white;
        }

        .btn-reject {
            background: #f44336;
            color: white;
        }

        .no-requests {
            text-align: center;
            padding: 40px;
            background: #2a2a2a;
            border-radius: 8px;
        }

        .no-requests i {
            font-size: 48px;
            color: #555;
            margin-bottom: 20px;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 5px;
            color: white;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }

        .notification.success {
            background: #4CAF50;
        }

        .notification.error {
            background: #f44336;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($team['name']); ?> - Join Requests</h1>
        
        <?php if (empty($requests)): ?>
            <div class="no-requests">
                <i class="fas fa-user-plus"></i>
                <h2>No Pending Requests</h2>
                <p>There are no pending join requests for your team at the moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
                <div class="request-card" id="request-<?php echo $request['id']; ?>">
                    <img src="<?php echo htmlspecialchars($request['profile_image'] ?: '../assets/images/default-avatar.png'); ?>" 
                         alt="<?php echo htmlspecialchars($request['username']); ?>"
                         class="request-avatar"
                         onerror="this.src='../assets/images/default-avatar.png'">
                    
                    <div class="request-info">
                        <h3><?php echo htmlspecialchars($request['username']); ?></h3>
                        <p class="request-date">
                            Requested: <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                        </p>
                    </div>
                    
                    <div class="request-actions">
                        <button class="btn btn-accept" onclick="handleRequest(<?php echo $request['id']; ?>, 'approve')">
                            <i class="fas fa-check"></i> Accept
                        </button>
                        <button class="btn btn-reject" onclick="handleRequest(<?php echo $request['id']; ?>, 'reject')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    function handleRequest(requestId, action) {
        // Find the request card and its buttons
        const card = document.getElementById(`request-${requestId}`);
        const buttons = card.querySelectorAll('button');
        
        // Disable all buttons
        buttons.forEach(btn => btn.disabled = true);
        
        // Show loading state on clicked button
        const clickedButton = action === 'approve' ? buttons[0] : buttons[1];
        const originalText = clickedButton.innerHTML;
        clickedButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        // Prepare form data
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('action', action);
        formData.append('team_id', <?php echo $team_id; ?>);
        
        // Send request
        fetch('handle_request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success notification
                showNotification(data.message, 'success');
                
                // Remove the request card with animation
                card.style.opacity = '0';
                card.style.transform = 'translateX(20px)';
                card.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    card.remove();
                    
                    // Check if there are no more requests
                    const remainingCards = document.querySelectorAll('.request-card');
                    if (remainingCards.length === 0) {
                        const container = document.querySelector('.container');
                        container.innerHTML += `
                            <div class="no-requests">
                                <i class="fas fa-user-plus"></i>
                                <h2>No Pending Requests</h2>
                                <p>There are no pending join requests for your team at the moment.</p>
                            </div>
                        `;
                    }
                }, 300);
            } else {
                // Show error notification
                showNotification(data.message, 'error');
                
                // Reset button state
                clickedButton.innerHTML = originalText;
                buttons.forEach(btn => btn.disabled = false);
            }
        })
        .catch(error => {
            // Show error notification
            showNotification('An error occurred while processing the request', 'error');
            
            // Reset button state
            clickedButton.innerHTML = originalText;
            buttons.forEach(btn => btn.disabled = false);
        });
    }

    function showNotification(message, type) {
        // Remove any existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(n => n.remove());
        
        // Create new notification
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            ${message}
        `;
        
        // Add to document
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    </script>
</body>
</html> 