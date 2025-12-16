<?php
session_start();

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../classes/database.php";
require_once "../classes/customer.php";
require_once "../classes/mailer.php";

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: createcustomer.php?error=noid");
    exit;
}

$customer_id = trim(htmlspecialchars($_GET['id']));

$database = new Database();
$pdo_conn = $database->connect();
$customerObj = new Customer($pdo_conn);
$mailer = new Mailer();

// 1. Fetch Customer Details
$customer = $customerObj->fetchCustomer($customer_id);

if (!$customer) {
    header("Location: createcustomer.php?error=notfound");
    exit;
}

// 2. Check if already verified
if (!empty($customer['email_verified_at'])) {
    header("Location: createcustomer.php?error=already_verified");
    exit;
}

// 3. Check if email exists
if (empty($customer['email'])) {
    header("Location: createcustomer.php?error=no_email");
    exit;
}

// 4. Generate New Token & Send
$new_token = $customerObj->refreshVerificationToken($customer_id);

if ($new_token) {
    // Send the email
    $sent = $mailer->sendVerificationEmail($customer['email'], $customer['name'], $new_token);
    
    if ($sent) {
        header("Location: createcustomer.php?status=email_resent&name=" . urlencode($customer['name']));
    } else {
        header("Location: createcustomer.php?error=email_failed");
    }
} else {
    header("Location: createcustomer.php?error=db_error");
}
exit;
?>