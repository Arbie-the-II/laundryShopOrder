<?php
// 1. Start the session (necessary to access session variables)
session_start();

// 2. Unset all session variables (clears the $_SESSION array)
$_SESSION = array();

// Alternatively, use session_unset() if you prefer:
// session_unset();

// 3. Destroy the session (removes the session data from the server and kills the session)
session_destroy();

// 4. Redirect the user back to the login page
header("Location: login.php");
exit;