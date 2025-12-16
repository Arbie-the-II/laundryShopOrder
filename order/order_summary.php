<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

require_once "../classes/database.php"; 
require_once "../classes/customer.php";
require_once "../classes/laundryorder.php"; 
require_once "../classes/pricingmanager.php";

$db = new Database();
$pdo_conn = $db->connect(); 
$orderObj = new LaundryOrder($pdo_conn); 
$pricingManager = new PricingManager($pdo_conn); 

if (!isset($_GET['id'])) { header("Location: vieworders.php?error=noid"); exit; }

$order_db_id = trim(htmlspecialchars($_GET['id']));
$order = $orderObj->fetchOrder($order_db_id); 

if (!$order) { header("Location: vieworders.php?error=notfound"); exit; }

// --- Calculation ---
$rates = $pricingManager->getPricing();
$weight = (float)($order['weight_lbs'] ?? 0);
$service_type = $order['service_type'] ?? 'Wash & Fold';

$base_cost = $weight * $rates['rate_per_lb'];
$service_surcharge = 0.00;
if ($service_type == 'Dry Cleaning') $service_surcharge = $rates['dry_cleaning_surcharge'];
elseif ($service_type == 'Ironing Only') $service_surcharge = $rates['ironing_only_surcharge'];

$subtotal = $base_cost + $service_surcharge;
$tax = $subtotal * $rates['tax_rate'];
$total_amount = $subtotal + $tax;

// Update DB
$sql_update_total = "UPDATE laundry_order SET total_amount = :total WHERE order_id = :id";
$stmt_update = $pdo_conn->prepare($sql_update_total);
$stmt_update->execute([':total' => round($total_amount, 2), ':id' => $order_db_id]);

function format_php($amount) { return '₱' . number_format($amount, 2); }

$customer_display_name = htmlspecialchars($order['customer_name'] ?? 'N/A');
$customer_display_phone = htmlspecialchars($order['phone_number'] ?? 'N/A');
$is_customer_deleted = false;
if (isset($order['customer_id']) && $order['customer_id'] === NULL) {
    $customer_display_name = '<span style="color:#dc3545; font-weight: bold;">' . htmlspecialchars($order['customer_name_at_order'] ?? 'Deleted Customer') . '</span>';
    $customer_display_phone = "N/A";
    $is_customer_deleted = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Summary - #<?= htmlspecialchars($order['order_id']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 30px; display: flex; justify-content: center; }
        .container { max-width: 800px; width: 100%; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 6px 12px rgba(0,0,0,0.15); }
        
        .header-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #007bff; padding-bottom: 15px; margin-bottom: 25px; }
        h2 { margin: 0; color: #007bff; }
        
        /* BACK BUTTON STYLE */
        .btn-back { background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background 0.3s; }
        .btn-back:hover { background-color: #5a6268; }

        h4 { color: #343a40; margin-top: 25px; padding-bottom: 5px; border-bottom: 1px dashed #ced4da; }
        .detail-group { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .detail-group div { flex: 1; padding: 0 15px; }
        .price-summary { border: 2px solid #007bff; padding: 20px; border-radius: 8px; background-color: #e9f5ff; margin-top: 20px; }
        .summary-table { width: 100%; border-collapse: collapse; margin-top: 15px; background-color: #fff; }
        .summary-table th, .summary-table td { padding: 10px; border: 1px solid #ddd; text-align: right; }
        .summary-table th { background-color: #f1f1f1; text-align: left; }
        .summary-table td:first-child { text-align: left; }
        .price-row { display: flex; justify-content: space-between; padding: 5px 0; }
        .price-row.total { font-weight: bold; font-size: 1.4em; color: #d9534f; border-top: 2px solid #000; margin-top: 15px; padding-top: 15px; }
        .action-button { margin-top: 30px; text-align: center; }
        .action-button a { display: inline-block; padding: 12px 25px; text-decoration: none; color: white; border-radius: 5px; font-weight: bold; transition: background-color 0.3s; }
        .action-button a:first-child { background-color: #28a745; }
        .action-button a:first-child:hover { background-color: #218838; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-row">
            <h2>Order Summary - #<?= htmlspecialchars($order['order_id']) ?></h2>
            <a href="vieworders.php" class="btn-back">← Back to View Orders</a>
        </div>

        <div class="detail-group">
            <div>
                <h4>👤 Customer Details</h4>
                <p><strong>Name:</strong> <?= $customer_display_name ?></p>
                <p><strong>Phone:</strong> <?= $customer_display_phone ?></p>
                <?php if ($is_customer_deleted): ?>
                    <p style="color:#dc3545; font-size: 0.9em; margin-top: 5px;">* Customer profile has been deleted.</p>
                <?php endif; ?>
                <p><strong>Date:</strong> <?= date("M d, Y H:i", strtotime($order['date_created'])) ?></p>
            </div>
            <div>
                <h4>📑 Order Information</h4>
                <p><strong>Service Type:</strong> <?= htmlspecialchars($order['service_type']) ?></p>
                <p><strong>Weight:</strong> <?= number_format($weight, 2) ?> lbs</p>
                <p><strong>Status:</strong> <strong><?= htmlspecialchars($order['status']) ?></strong></p>
            </div>
        </div>

        <h4>📝 Preferences & Instructions</h4>
        <div class="detail-group">
            <div>
                <p><strong>Detergent:</strong> <?= htmlspecialchars($order['detergent_type'] ?? 'Standard') ?></p>
                <p><strong>Softener:</strong> <?= htmlspecialchars($order['softener_type'] ?? 'Standard') ?></p>
                <p><strong>Starch:</strong> <?= htmlspecialchars($order['starch_level'] ?? 'None') ?></p>
            </div>
            <div>
                <p><strong>Instructions:</strong> <em><?= htmlspecialchars($order['special_instructions'] ?: 'None') ?></em></p>
                <p><strong>Defect Log:</strong> <em><?= htmlspecialchars($order['defect_log'] ?: 'None') ?></em></p>
            </div>
        </div>
        
        <h4 style="margin-top: 30px;">💰 Financial Breakdown</h4>
        <div class="price-summary">
            <table class="summary-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Description</th>
                        <th style="width: 20%;">Rate / Unit</th>
                        <th style="width: 15%;">Quantity</th>
                        <th style="width: 25%;">Amount (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Weight-Based Service</td>
                        <td><?= format_php($rates['rate_per_lb']) ?>/lb</td>
                        <td><?= htmlspecialchars($order['weight_lbs']) ?></td>
                        <td><?= format_php($base_cost) ?></td>
                    </tr>
                    <?php if ($service_surcharge > 0.00): ?>
                    <tr>
                        <td>Surcharge: <?= htmlspecialchars($order['service_type']) ?></td>
                        <td>Flat Fee</td>
                        <td>1</td>
                        <td><?= format_php($service_surcharge) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="padding: 10px 0;">
                <div class="price-row"><span>Subtotal:</span><span><?= format_php($subtotal) ?></span></div>
                <div class="price-row"><span>Tax (<?= $rates['tax_rate'] * 100 ?>%):</span><span><?= format_php($tax) ?></span></div>
                <div class="price-row total"><span>FINAL TOTAL DUE:</span><span><?= format_php($total_amount) ?></span></div>
            </div>
        </div>

        <div class="action-button">
            <a href="printreceipt.php?id=<?= htmlspecialchars($order_db_id) ?>">✅ Generate Printable Receipt</a>
            <?php if (!$is_customer_deleted): ?>
                <a href="editorder.php?id=<?= htmlspecialchars($order_db_id) ?>" style="background-color: #ffc107; margin-left: 10px;">✍️ Edit Details</a>
            <?php else: ?>
                 <span style="display:inline-block; padding: 12px 25px; margin-left: 10px; background-color: #ced4da; color: #6c757d; border-radius: 5px; font-weight: bold;">Cannot Edit (Deleted)</span>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>