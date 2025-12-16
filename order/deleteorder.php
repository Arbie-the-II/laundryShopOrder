<?php
session_start();
// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../classes/database.php";
require_once "../classes/laundryorder.php";

$database = new Database();
$pdo_conn = $database->connect();

// Check if an order ID was passed
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect back to the order list if no ID is provided
    header("Location: vieworders.php");
    exit;
}

$order_id = trim(htmlspecialchars($_GET['id']));
$orderObj = new LaundryOrder($pdo_conn);

// Attempt to delete the order record
if ($orderObj->deleteOrder($order_id)) {
    // Order deleted successfully. Add a success message to session if needed.
    $_SESSION['message'] = "Order history (ID: {$order_id}) has been permanently deleted.";
} else {
    // Deletion failed.
    $_SESSION['error'] = "Failed to delete order history (ID: {$order_id}). Please try again.";
}

// Redirect back to the order listing page
header("Location: vieworders.php");
exit;
?>