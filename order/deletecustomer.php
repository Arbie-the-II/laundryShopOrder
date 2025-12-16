// File: deletecustomer.php

<?php
session_start();
// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// --- START FIX: INCLUDE Database and Customer classes ---
require_once "../classes/database.php"; 
require_once "../classes/customer.php"; 

$database = new Database();
$pdo_conn = $database->connect(); // <-- This line correctly defines $pdo_conn
// --- END FIX ---


// Check if customer ID was passed (Submitted via POST from createcustomer.php)
if (!isset($_POST['id']) || empty($_POST['id'])) {
    header("Location: createcustomer.php?error=noid");
    exit;
}

$customer_id = trim(htmlspecialchars($_POST['id']));

// Now, the Customer object can be instantiated correctly:
$customerObj = new Customer($pdo_conn); 

// 1. Fetch customer name before deletion (for the success message)
$customer = $customerObj->fetchCustomer($customer_id);

if (!$customer) {
    header("Location: createcustomer.php?error=notfound");
    exit;
}

$customer_name = $customer['name'];

// 2. Attempt to delete the customer record
if ($customerObj->deleteCustomer($customer_id)) {
    // Customer deleted successfully. Redirect with success status.
    header("Location: createcustomer.php?status=deleted&name=" . urlencode($customer_name));
    exit;
} else {
    // Deletion failed.
    header("Location: createcustomer.php?error=deletefailed");
    exit;
}