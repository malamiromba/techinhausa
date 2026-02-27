<?php
// admin/logout.php
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/auth.php";

// Logout the admin
logout_admin();

// Redirect to login page
header('Location: login.php');
exit();