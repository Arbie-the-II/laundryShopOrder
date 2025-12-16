<?php
session_start();
require_once "../classes/database.php";
require_once "../classes/notification.php";

// Check if ID is provided
if (isset($_GET['id'])) {
    $db = new Database();
    $pdo = $db->connect();
    $notify = new Notification($pdo);
    
    // Mark as read
    $notify->markAsRead($_GET['id']);
}

// Redirect to the destination
if (isset($_GET['redirect'])) {
    header("Location: " . urldecode($_GET['redirect']));
} else {
    header("Location: ../dashboard.php");
}
exit;
?>