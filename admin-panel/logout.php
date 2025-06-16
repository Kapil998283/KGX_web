<?php
session_start();

// Clear admin session variables
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);

// Redirect to login page
header("Location: index.php");
exit();
?> 