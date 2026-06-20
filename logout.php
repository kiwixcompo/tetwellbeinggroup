<?php
/**
 * Tet Wellbeing Group - Logout Handler (logout.php)
 * Clears session cookies, destroys session data, and redirects to the landing page.
 */
require_once 'db.php';

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to gateway index
header("Location: index.php");
exit;
?>
