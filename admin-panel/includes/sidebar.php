<?php
// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="../dashboard/index.php">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo $current_page === 'hero-settings.php' ? 'active' : ''; ?>" href="../dashboard/hero-settings.php">
                    <i class="bi bi-image"></i>
                    Hero Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo $current_page === 'users.php' ? 'active' : ''; ?>" href="../dashboard/users.php">
                    <i class="bi bi-people"></i>
                    Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo $current_page === 'tournaments.php' ? 'active' : ''; ?>" href="../dashboard/tournaments.php">
                    <i class="bi bi-trophy"></i>
                    Tournaments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo $current_page === 'teams.php' ? 'active' : ''; ?>" href="../dashboard/teams.php">
                    <i class="bi bi-people-fill"></i>
                    Teams
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="../dashboard/profile.php">
                    <i class="bi bi-person"></i>
                    Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="../logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
.sidebar {
    height: 100vh;
    position: fixed;
}

.sidebar .nav-link {
    padding: .5rem 1rem;
    color: #fff;
    opacity: 0.8;
}

.sidebar .nav-link:hover {
    opacity: 1;
}

.sidebar .nav-link.active {
    background-color: rgba(255,255,255,0.1);
    opacity: 1;
}

.sidebar .bi {
    margin-right: 8px;
}
</style> 