<?php
// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Format date
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

// Get file extension
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Check if string is valid URL
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// Get user avatar URL
function getUserAvatar($avatar = null) {
    if ($avatar && isValidUrl($avatar)) {
        return $avatar;
    }
    return '/newapp/assets/images/default_avatar.png';
}

// Get team logo URL
function getTeamLogo($logo = null) {
    if ($logo && isValidUrl($logo)) {
        return $logo;
    }
    return '/newapp/assets/images/default_logo.png';
}

// Get team banner URL
function getTeamBanner($banner = null) {
    if ($banner && isValidUrl($banner)) {
        return $banner;
    }
    return '/newapp/assets/images/default_banner.jpg';
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

// Check if user is team captain
function isTeamCaptain($conn, $userId, $teamId) {
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND user_id = ? AND role = 'captain'");
    $stmt->bind_param('ii', $teamId, $userId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Check if user is team member
function isTeamMember($conn, $userId, $teamId) {
    $stmt = $conn->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $teamId, $userId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Get team details
function getTeamDetails($conn, $teamId) {
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get team members
function getTeamMembers($conn, $teamId) {
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar, tm.role, tm.joined_date 
        FROM users u 
        INNER JOIN team_members tm ON u.id = tm.user_id 
        WHERE tm.team_id = ?
    ");
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get team join requests
function getTeamRequests($conn, $teamId) {
    $stmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar, tr.request_date 
        FROM users u 
        INNER JOIN team_requests tr ON u.id = tr.user_id 
        WHERE tr.team_id = ? AND tr.status = 'pending'
    ");
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} 