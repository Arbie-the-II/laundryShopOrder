<?php
// FIX: Corrected path from '../laundryShopOrd/classes/...' to '../classes/...'
require_once "../classes/database.php"; 
require_once "../classes/customer.php";
require_once "../classes/notification.php";

$database = new Database();
$pdo_conn = $database->connect();
$customerObj = new Customer($pdo_conn);
$notifyObj = new Notification($pdo_conn);

$message = "";
$status_type = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // 1. Find customer BEFORE verifying (to get name for notification)
    $stmt = $pdo_conn->prepare("SELECT id, name FROM customer WHERE email_verification_token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Perform Verification
    // Assuming the customerObj->verifyByToken() method also handles marking the email as verified
    $result = $customerObj->verifyByToken($token);

    if ($result === true || $result > 0) {
        $status_type = "success";
        $message = "🎉 Success! Your email has been verified. You will now receive order updates.";
        
        // --- 🔔 NOTIFY STAFF: SUCCESS ---
        if ($customer) {
            $notifyObj->create(
                "Customer Verified", 
                "<strong>{$customer['name']}</strong> has verified their email address.", 
                "../order/createcustomer.php?customer_search=" . urlencode($customer['name']),
                "success"
            );
        }

    } elseif ($result === 'expired') {
        $status_type = "error";
        $message = "⏳ This confirmation link has expired. Please contact the shop if you need a new one.";
        
        // --- 🔔 NOTIFY STAFF: EXPIRED ---
        if ($customer) {
            $notifyObj->create(
                "Verification Expired", 
                "Verification token for <strong>{$customer['name']}</strong> has expired.", 
                "../order/createcustomer.php?customer_search=" . urlencode($customer['name']),
                "warning"
            );
        }

    } else {
        $status_type = "error";
        $message = "❌ Invalid confirmation link. It may have already been used.";
    }
} else {
    $status_type = "error";
    $message = "❌ Access denied. No verification token was provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification Status</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 50px; background-color: #f8f9fa; text-align: center; }
        .box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; }
        h2 { color: #343a40; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Email Confirmation</h2>
        <div class="<?= $status_type ?>">
            <?= $message ?>
        </div>
        <p style="margin-top: 20px; color: #666;">Thank you. You can now close this window.</p>
    </div>
</body>
</html>