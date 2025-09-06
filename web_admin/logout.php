<?php
session_start();
require_once 'includes/auth.php';

// Logout the admin
logoutAdmin();

// Redirect to login page
header('Location: login.php');
exit();
?>
