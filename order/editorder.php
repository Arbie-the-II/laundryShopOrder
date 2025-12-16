<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

require_once "../classes/database.php"; 
require_once "../classes/customer.php";
require_once "../classes/laundryorder.php"; 
require_once "../classes/pricingmanager.php"; 
require_once "../classes/mailer.php"; 

$db = new Database();
$pdo_conn = $db->connect(); 
$customerObj = new Customer($pdo_conn);
$orderObj = new LaundryOrder($pdo_conn);
$pricingManager = new PricingManager($pdo_conn); 
$rates = $pricingManager->getPricing(); 

$order = [];
$errors = [];
$status_message = ""; 

// --- PRICING FUNCTION ---
function calculateOrderTotal($weight, $service_type, $pricingManager) {
    $rates = $pricingManager->getPricing();
    $weight = (float)$weight;
    if ($weight <= 0) return 0.00;
    
    $base_cost = $weight * $rates['rate_per_lb'];
    $service_surcharge = 0.00;
    if ($service_type == 'Dry Cleaning') $service_surcharge = $rates['dry_cleaning_surcharge'];
    elseif ($service_type == 'Ironing Only') $service_surcharge = $rates['ironing_only_surcharge'];

    $subtotal = $base_cost + $service_surcharge;
    return round($subtotal + ($subtotal * $rates['tax_rate']), 2);
}

$service_options = ['Wash and Fold', 'Dry Cleaning', 'Ironing Only'];
$status_options = ['Pending', 'Processing', 'Ready for Pickup', 'Completed'];
$detergent_options = ['Standard', 'Hypoallergenic', 'Scented Deluxe'];
$softener_options = ['Standard', 'No Softener', 'Extra Scented'];
$starch_options = ['None', 'Light', 'Medium', 'Heavy'];

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['id'])) {
        $order = $orderObj->fetchOrder(trim(htmlspecialchars($_GET['id'])));
        if (!$order) { header("Location: vieworders.php?error=notfound"); exit; }
    } else { header("Location: vieworders.php?error=noid"); exit; }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $order_db_id = trim(htmlspecialchars($_POST['id'] ?? ''));
    $order = $orderObj->fetchOrder($order_db_id); 
    $old_status = $order['status']; // Capture status BEFORE update

    $order['weight_lbs'] = trim(htmlspecialchars($_POST['weight_lbs'] ?? ''));
    $order['service_type'] = trim(htmlspecialchars($_POST['service_type'] ?? ''));
    $order['status'] = trim(htmlspecialchars($_POST['status'] ?? ''));
    $order['special_instructions'] = trim(htmlspecialchars($_POST['special_instructions'] ?? ''));
    $order['defect_log'] = trim(htmlspecialchars($_POST['defect_log'] ?? ''));
    $order['detergent_type'] = trim(htmlspecialchars($_POST['detergent_type'] ?? 'Standard'));
    $order['softener_type'] = trim(htmlspecialchars($_POST['softener_type'] ?? 'Standard'));
    $order['starch_level'] = trim(htmlspecialchars($_POST['starch_level'] ?? 'None'));

    if (!is_numeric($order['weight_lbs']) || (float)$order['weight_lbs'] <= 0) $errors['weight_lbs'] = "Invalid weight.";
    if (empty($order['service_type'])) $errors['service_type'] = "Select service type.";
    
    if (empty(array_filter($errors))) {
        $calculated_total = calculateOrderTotal($order['weight_lbs'], $order['service_type'], $pricingManager);
        $orderObj->total_amount = $calculated_total;
        $orderObj->weight_lbs = (float)$order['weight_lbs'];
        $orderObj->service_type = $order['service_type'];
        $orderObj->status = $order['status']; 
        $orderObj->special_instructions = $order['special_instructions'];
        $orderObj->defect_log = $order['defect_log'];
        $orderObj->detergent_type = $order['detergent_type'];
        $orderObj->softener_type = $order['softener_type'];
        $orderObj->starch_level = $order['starch_level'];
        $orderObj->order_id = $order_db_id;

        if ($orderObj->updateOrderWithDetails()) {
            
            // --- NOTIFICATION LOGIC WITH FEEDBACK ---
            $email_msg = "";
            
            // Only send if status CHANGED and customer has EMAIL
            if ($old_status !== $orderObj->status && !empty($order['customer_email'])) {
                $mailer = new Mailer();
                $sent = $mailer->sendOrderStatusUpdate(
                    $order['customer_email'], 
                    $order['customer_name'], 
                    $order['order_id'], 
                    $orderObj->status 
                );
                
                if ($sent) {
                    $email_msg = " <br>📧 <strong>Email notification sent to customer.</strong>";
                } else {
                    $email_msg = " <br>⚠️ <strong>Warning: Failed to send email notification.</strong>";
                }
            } elseif ($old_status !== $orderObj->status && empty($order['customer_email'])) {
                 $email_msg = " <br>ℹ️ Status updated, but no email on file for notification.";
            }
            // ----------------------------------------

            $status_message = "Order updated! New Total: PHP " . number_format($calculated_total, 2) . $email_msg;
            $order = $orderObj->fetchOrder($order_db_id);
        } else {
            $errors['general'] = "Failed to update order.";
        }
    }
}

$customer_display_name = htmlspecialchars($order['customer_name'] ?? 'N/A');
$deleted_customer_note = (isset($order['customer_id']) && $order['customer_id'] === NULL) ? " (Profile Deleted)" : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .form-container { width: 100%; max-width: 650px; background-color: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        
        .header-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 25px; }
        h1 { color: #343a40; margin: 0; font-size: 1.8em; }
        
        .btn-back { background-color: #6c757d; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-size: 0.9em; transition: background 0.3s; }
        .btn-back:hover { background-color: #5a6268; }

        .customer-info { background-color: #e9ecef; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .deleted { background-color: #fce4e4; border: 1px solid #fcc0c0; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input[type="text"], select, textarea { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        
        .buttons { margin-top: 30px; }
        input[type="submit"] { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 1.1em; }
        input[type="submit"]:hover { background-color: #0056b3; }
        
        .total-amount-display { background-color: #e9f7e9; border: 1px solid #a6e4a6; padding: 10px; border-radius: 4px; margin-top: 10px; font-weight: bold; color: #218838; text-align: right; }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="header-row">
            <h1>Edit Order #<?= htmlspecialchars($order['order_id'] ?? 'N/A') ?></h1>
            <a href="vieworders.php" class="btn-back">← Back to View Orders</a>
        </div>

        <?php if ($status_message): ?> 
            <div style="color:#155724; background-color:#d4edda; border:1px solid #c3e6cb; padding:15px; border-radius: 4px; margin-bottom: 20px;">
                ✅ <?= $status_message ?>
            </div>
        <?php endif; ?>
        
        <div class="customer-info <?= $deleted_customer_note ? 'deleted' : '' ?>">
            Customer: <strong><?= $customer_display_name ?></strong> <?= $deleted_customer_note ?>
        </div>

        <form action="" method="post">
            <input type="hidden" name="id" value="<?= htmlspecialchars($order['order_id'] ?? '') ?>">
            
            <div style="display:flex; gap:20px;">
                <div style="flex:1;">
                    <label>Status</label>
                    <select name="status" required>
                        <?php foreach ($status_options as $status): ?>
                            <option value="<?= $status ?>" <?= (($order['status'] ?? '') == $status) ? 'selected' : '' ?>><?= $status ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;">
                    <label>Weight (lbs)</label>
                    <input type="text" id="weight_lbs" name="weight_lbs" value="<?= htmlspecialchars($order['weight_lbs'] ?? '') ?>" required>
                </div>
            </div>

            <label>Service Type</label>
            <select name="service_type" id="service_type" required>
                <?php foreach ($service_options as $service): ?>
                    <option value="<?= $service ?>" <?= (($order['service_type'] ?? '') == $service) ? 'selected' : '' ?>><?= $service ?></option>
                <?php endforeach; ?>
            </select>
            
            <div class="total-amount-display">Total: PHP <?= number_format($order['total_amount'] ?? 0, 2) ?></div>

            <h3 style="border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 25px;">Preferences</h3>
            <div style="display:flex; gap:20px;">
                <div style="flex:1;"><label>Detergent</label><select name="detergent_type"><?php foreach($detergent_options as $o) echo "<option value='$o' ".(($order['detergent_type']??'')==$o?'selected':'').">$o</option>"; ?></select></div>
                <div style="flex:1;"><label>Softener</label><select name="softener_type"><?php foreach($softener_options as $o) echo "<option value='$o' ".(($order['softener_type']??'')==$o?'selected':'').">$o</option>"; ?></select></div>
                <div style="flex:1;"><label>Starch</label><select name="starch_level"><?php foreach($starch_options as $o) echo "<option value='$o' ".(($order['starch_level']??'')==$o?'selected':'').">$o</option>"; ?></select></div>
            </div>

            <label>Instructions</label><textarea name="special_instructions"><?= htmlspecialchars($order['special_instructions'] ?? '') ?></textarea>
            <label>Defect Log</label><textarea name="defect_log"><?= htmlspecialchars($order['defect_log'] ?? '') ?></textarea>

            <div class="buttons">
                <input type="submit" value="💾 Save Changes">
            </div>
        </form>
    </div>

    <script>
        (function(){
            const rates = <?= json_encode($rates) ?: '{}' ?>;
            function getRate(key) { return parseFloat(rates[key]) || 0.00; }

            const weightInput = document.getElementById('weight_lbs');
            const serviceSelect = document.getElementById('service_type');
            const totalDisplay = document.querySelector('.total-amount-display');

            function calculateTotalJS(){
                const weight = parseFloat(weightInput.value);
                if (isNaN(weight) || weight <= 0) {
                    totalDisplay.textContent = 'Current Total Amount: PHP 0.00';
                    return;
                }
                const ratePerLb = getRate('rate_per_lb');
                const dryCleanCharge = getRate('dry_cleaning_surcharge');
                const ironingCharge = getRate('ironing_only_surcharge');
                const taxRate = getRate('tax_rate');

                const baseCost = weight * ratePerLb;
                let serviceSurcharge = 0.00;
                if(serviceSelect.value === 'Dry Cleaning') serviceSurcharge = dryCleanCharge;
                else if(serviceSelect.value === 'Ironing Only') serviceSurcharge = ironingCharge;

                const subtotal = baseCost + serviceSurcharge;
                const total = subtotal + (subtotal * taxRate);
                totalDisplay.textContent = 'Current Total Amount: PHP ' + total.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            if (weightInput && serviceSelect) {
                weightInput.addEventListener('input', calculateTotalJS);
                serviceSelect.addEventListener('change', calculateTotalJS);
                calculateTotalJS();
            }
        })();
    </script>
</body>
</html>