<?php
session_start();

// 1. INCLUDE DATABASE CLASS
require_once "../classes/database.php"; 

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../classes/customer.php";
require_once "../classes/laundryorder.php"; 
require_once "../classes/pricingmanager.php"; 
// 2. ESTABLISH PDO CONNECTION
$db = new Database();
$pdo_conn = $db->connect(); 

// 3. INSTANTIATE PRICING MANAGER WITH PDO CONNECTION
$orderObj = new LaundryOrder($pdo_conn);
$pricingManager = new PricingManager($pdo_conn); // MODIFIED

if (!isset($_GET['id'])) {
    header("Location: vieworders.php?error=noid");
    exit;
}

$order_db_id = trim(htmlspecialchars($_GET['id']));
$order = $orderObj->fetchOrder($order_db_id); 

if (!$order) {
    header("Location: vieworders.php?error=notfound");
    exit;
}

// --- PRICE CALCULATION LOGIC (Fetches shop settings from DB) ---
$rates = $pricingManager->getPricing();

// 4. FETCH DYNAMIC RATES FROM DB
$RATE_PER_LB = $rates['rate_per_lb'];
$DRY_CLEANING_SURCHARGE = $rates['dry_cleaning_surcharge'];
$IRONING_ONLY_SURCHARGE = $rates['ironing_only_surcharge'];
$TAX_RATE = $rates['tax_rate']; 


$weight = (float)($order['weight_lbs'] ?? 0);
$service_type = $order['service_type'] ?? 'Wash & Fold';

// 5. Calculate Base Cost (using fetched rate)
$base_cost = $weight * $RATE_PER_LB;
$service_surcharge = 0.00;

// 6. Calculate Service Surcharge (using fetched surcharge)
if ($service_type == 'Dry Cleaning') {
    $service_surcharge = $DRY_CLEANING_SURCHARGE;
} elseif ($service_type == 'Ironing Only') {
    $service_surcharge = $IRONING_ONLY_SURCHARGE;
}

$subtotal = $base_cost + $service_surcharge;
$tax = $subtotal * $TAX_RATE;
$total_amount = $subtotal + $tax;

// Helper function for PHP currency formatting
function format_php($amount) {
    return '₱' . number_format($amount, 2);
}
// -----------------------------------------------------------

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Order #<?= htmlspecialchars($order['order_id']) ?></title>
    <style>

        body { font-family: 'Courier New', Courier, monospace; margin: 0; padding: 0; background-color: #f8f9fa; }
        .receipt-container { 
            width: 300px; margin: 50px auto; padding: 20px; 
            background: #fff; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,0.1); 
        }
        .header { text-align: center; border-bottom: 2px dashed #000; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 1.5em; }
        .details, .preferences, .items, .totals { font-size: 0.9em; margin-bottom: 15px; }
        .details p, .preferences p { margin: 5px 0; }
        .items table, .totals table { width: 100%; border-collapse: collapse; }
        .items th, .items td, .totals th, .totals td { padding: 5px 0; text-align: left; }
        .items td:last-child, .totals td:last-child { text-align: right; }
        .totals th { border-top: 1px dashed #000; }
        .final-total { font-size: 1.2em; font-weight: bold; border-top: 2px dashed #000; padding-top: 10px; }
        .footer { text-align: center; margin-top: 20px; font-size: 0.8em; }
        
 
        @media print {
            body { background: none; }
            .receipt-container { margin: 0; border: none; box-shadow: none; }
            .print-button { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h1>LAUNDRY SHOP</h1>
            <p>Order Receipt</p>
            <p>Date: <?= date("M d, Y H:i", strtotime($order['date_created'])) ?></p>
        </div>

        <div class="details">
            <p><strong>Order ID:</strong> <?= htmlspecialchars($order['order_id']) ?></p>
            <p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($order['phone_number'] ?? 'N/A') ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($order['status']) ?></p>
        </div>

        <div class="preferences">
            <p>--- Preferences ---</p>
            <p><strong>Detergent:</strong> <?= htmlspecialchars($order['detergent_type'] ?? 'Standard') ?></p>
            <p><strong>Softener:</strong> <?= htmlspecialchars($order['softener_type'] ?? 'Standard') ?></p>
            <p><strong>Starch:</strong> <?= htmlspecialchars($order['starch_level'] ?? 'None') ?></p>
            <p><strong>Instructions:</strong> <?= htmlspecialchars($order['special_instructions'] ?: 'None') ?></p>
            <p><strong>Defects Log:</strong> <?= htmlspecialchars($order['defect_log'] ?: 'None') ?></p>
        </div>

        <div class="items">
            <table>
                <tr>
                    <th style="width: 70%;">Service/Item</th>
                    <th style="width: 30%; text-align: right;">Amount (PHP)</th>
                </tr>
                <tr><td colspan="2"><hr style="border: 0; border-top: 1px dashed #000;"></td></tr>
                
                <tr>
                    <td><?= htmlspecialchars($order['weight_lbs']) ?> lbs @ <?= format_php($RATE_PER_LB) ?>/lb</td>
                    <td><?= format_php($base_cost) ?></td>
                </tr>
                
                <?php if ($service_surcharge > 0.00): ?>
                <tr>
                    <td><?= htmlspecialchars($order['service_type']) ?> Surcharge</td>
                    <td><?= format_php($service_surcharge) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="totals">
            <table>
                <tr><th>Subtotal:</th><td><?= format_php($subtotal) ?></td></tr>
                <tr><th>Tax (<?= $TAX_RATE * 100 ?>%):</th><td><?= format_php($tax) ?></td></tr>
                <tr><td colspan="2"><hr style="border: 0; border-top: 1px dashed #000;"></td></tr>
                <tr class="final-total"><th>TOTAL DUE:</th><td><?= format_php($total_amount) ?></td></tr>
            </table>
        </div>

        <div class="footer">
            <p>Thank you for choosing us! Sulit!</p>
            <p>---</p>
        </div>

        <div class="print-button" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px;">🖨️ Print Receipt</button>
            <a href="vieworders.php" style="margin-left: 10px; text-decoration: none; color: #333;">← Back</a>
        </div>
    </div>
</body>
</html>