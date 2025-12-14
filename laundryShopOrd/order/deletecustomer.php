<?php
session_start();

// 1. Security Check (Access Control)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// 2. Class Dependencies
require_once "../classes/customer.php";
$customerObj = new Customer();

// 3. Method Check (Ensure it's a POST request from the form)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: createcustomer.php?error=invalidmethod");
    exit;
}

// 4. Get and Validate Customer ID
$customer_id = $_POST['id'] ?? null;

if (!$customer_id) {
    header("Location: createcustomer.php?error=noid");
    exit;
}

// 5. Fetch Customer Name BEFORE Deletion (for the success message)
$customer_to_delete = $customerObj->fetchCustomer($customer_id);

if (!$customer_to_delete) {
    // If the customer doesn't exist, we can't delete them.
    header("Location: createcustomer.php?error=notfound");
    exit;
}

// Store the name for the success redirect message
$deleted_name = $customer_to_delete['name'];


// 6. Execute Deletion
// This delete operation will set the customer_id in the laundry_order table to NULL 
// because of the ON DELETE SET NULL foreign key constraint you established.
if ($customerObj->deleteCustomer($customer_id)) {
    // 7. Success Redirect
    // Use the name of the deleted customer in the URL for the success alert
    header("Location: createcustomer.php?status=deleted&name=" . urlencode($deleted_name));
    exit;
} else {
    // 8. Failure Redirect
    header("Location: createcustomer.php?error=deletefailed");
    exit;
}
?>