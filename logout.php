<?php
session_start();

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php'; // Add this line
require_once __DIR__ . '/includes/auth.php';

// Logout the user
logoutUser();

// Redirect to login page
header('Location: ' . SITE_URL . 'index.php?logout=1');
exit();
?>