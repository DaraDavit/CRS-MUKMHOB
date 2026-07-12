<?php
// Initialize session tracking
session_start();

// Unset all active session variables pool mapping array registers
$_SESSION = array();

// If the session cookie exists, destroy it permanently
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy the data file container on the hosting server instance
session_destroy();

// Safely redirect back to your login gate sequence panel
header("Location: login.php");
exit();