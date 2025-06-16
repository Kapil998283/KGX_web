<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'tournaments.php' ? 'active' : ''; ?>" href="tournaments.php">
                    <i class="bi bi-trophy"></i> Tournaments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="bi bi-people"></i> Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'teams.php' ? 'active' : ''; ?>" href="teams.php">
                    <i class="bi bi-people-fill"></i> Teams
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'matches.php' ? 'active' : ''; ?>" href="matches.php">
                    <i class="bi bi-controller"></i> Matches
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Reports</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="bi bi-file-earmark-text"></i> Tournament Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'activity-log.php' ? 'active' : ''; ?>" href="activity-log.php">
                    <i class="bi bi-clock-history"></i> Activity Log
                </a>
            </li>
        </ul>
    </div>
</nav> 