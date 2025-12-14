<?php
session_start();

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Ensure the necessary classes are included
require_once "../classes/database.php"; 
require_once "../classes/customer.php";
require_once "../classes/laundryorder.php"; 
require_once "../classes/pricingmanager.php"; 

$db = new Database();
$pdo_conn = $db->connect(); // Get the PDO connection object

$customerObj = new Customer($pdo_conn);
$orderObj = new LaundryOrder($pdo_conn);
$pricingManager = new PricingManager($pdo_conn); // Instantiate PricingManager
$rates = $pricingManager->getPricing(); // Make rates available for client-side calculations

$order = [];
$errors = [];
$status_message = ""; 

// --- PRICING FUNCTION (MATCHES order_summary.php LOGIC) ---
function calculateOrderTotal($weight, $service_type, $pricingManager) {
    
    $rates = $pricingManager->getPricing();
    
    $RATE_PER_LB = $rates['rate_per_lb'];
    $DRY_CLEANING_SURCHARGE = $rates['dry_cleaning_surcharge'];
    $IRONING_ONLY_SURCHARGE = $rates['ironing_only_surcharge'];
    $TAX_RATE = $rates['tax_rate'];
    
    $weight = (float)$weight;
    if ($weight <= 0) return 0.00;

    // 1. Calculate Base Cost (Wash & Fold)
    $base_cost = $weight * $RATE_PER_LB;
    $service_surcharge = 0.00;

    // 2. Calculate Service Surcharge
    if ($service_type == 'Dry Cleaning') {
        $service_surcharge = $DRY_CLEANING_SURCHARGE;
    } elseif ($service_type == 'Ironing Only') {
        $service_surcharge = $IRONING_ONLY_SURCHARGE;
    }

    $subtotal = $base_cost + $service_surcharge;
    $tax = $subtotal * $TAX_RATE;
    $total_amount = $subtotal + $tax;

    return round($total_amount, 2);
}
// -----------------------------------------------------------


// --- CONFIGURATION FOR DROPDOWNS ---
$service_options = ['Wash and Fold', 'Dry Cleaning', 'Ironing Only'];
$status_options = ['Pending', 'Processing', 'Ready for Pickup', 'Completed'];
$detergent_options = ['Standard', 'Hypoallergenic', 'Scented Deluxe'];
$softener_options = ['Standard', 'No Softener', 'Extra Scented'];
$starch_options = ['None', 'Light', 'Medium', 'Heavy'];


if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['id'])) {
        $order_db_id = trim(htmlspecialchars($_GET['id']));
        
        // Fetch the order and related customer data
        $order = $orderObj->fetchOrder($order_db_id);
        
        error_log("DEBUG: Fetched order from GET: " . json_encode($order));
        
        if (!$order) {
            header("Location: vieworders.php?error=notfound");
            exit;
        }
    } else {
        header("Location: vieworders.php?error=noid");
        exit;
    }
} 

elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $order_db_id = trim(htmlspecialchars($_POST['id'] ?? ''));
    
    // Fetch the original order data to repopulate fields if an error occurs
    $order = $orderObj->fetchOrder($order_db_id); 
    
    // --- 1. Sanitize and collect ALL updated data ---
    $order['weight_lbs'] = trim(htmlspecialchars($_POST['weight_lbs'] ?? ''));
    $order['service_type'] = trim(htmlspecialchars($_POST['service_type'] ?? ''));
    $order['status'] = trim(htmlspecialchars($_POST['status'] ?? ''));

    $order['special_instructions'] = trim(htmlspecialchars($_POST['special_instructions'] ?? ''));
    $order_db_id = trim(htmlspecialchars($_POST['id'] ?? ''));
    // Fetch the original order data to repopulate fields if an error occurs
    $order = $orderObj->fetchOrder($order_db_id);

    // --- 1. Sanitize and collect ALL updated data ---
    $order['weight_lbs'] = trim(htmlspecialchars($_POST['weight_lbs'] ?? ''));
    $order['service_type'] = trim(htmlspecialchars($_POST['service_type'] ?? ''));
    $order['status'] = trim(htmlspecialchars($_POST['status'] ?? ''));
    $order['special_instructions'] = trim(htmlspecialchars($_POST['special_instructions'] ?? ''));
    $order['defect_log'] = trim(htmlspecialchars($_POST['defect_log'] ?? ''));
    $order['detergent_type'] = trim(htmlspecialchars($_POST['detergent_type'] ?? 'Standard'));
    $order['softener_type'] = trim(htmlspecialchars($_POST['softener_type'] ?? 'Standard'));
    $order['starch_level'] = trim(htmlspecialchars($_POST['starch_level'] ?? 'None'));

    // --- 2. Validation ---
    if (!is_numeric($order['weight_lbs']) || (float)$order['weight_lbs'] <= 0) {
        $errors['weight_lbs'] = "Weight must be a positive number.";
    }
    if (empty($order['service_type'])) {
        $errors['service_type'] = "Please select a service type.";
    }
    if (empty($order['status'])) {
        $errors['status'] = "Please select an order status.";
    }

    if (empty(array_filter($errors))) {
        $final_weight = (float)$order['weight_lbs'];
        $final_service = $order['service_type'];
        // Calculate the total amount using the centralized pricing logic
        $calculated_total = calculateOrderTotal($final_weight, $final_service, $pricingManager);
        // Assign the calculated total to the object property
        $orderObj->total_amount = $calculated_total;
        // Also update the local array for display persistence
        $order['total_amount'] = $calculated_total;

        // --- 3. Prepare data for the update method ---
        $orderObj->weight_lbs = $final_weight;
        $orderObj->service_type = $final_service;
        $orderObj->status = $order['status'];
        $orderObj->special_instructions = $order['special_instructions'];
        $orderObj->defect_log = $order['defect_log'];
        $orderObj->detergent_type = $order['detergent_type'];
        $orderObj->softener_type = $order['softener_type'];
        $orderObj->starch_level = $order['starch_level'];
        $orderObj->order_id = $order_db_id;

        // Debug log: values before updating order_details (comprehensive)
        error_log("DEBUG: About to update order_details for order_id: " . $order_db_id);
        error_log("DEBUG: Washing preferences from POST: " . json_encode([
            'detergent_type' => $order['detergent_type'],
            'softener_type' => $order['softener_type'],
            'starch_level' => $order['starch_level']
        ]));
        error_log("DEBUG: updateOrderDetails input (all fields): " . json_encode([
            'order_id' => $orderObj->order_id,
            'detergent_type' => $orderObj->detergent_type,
            'softener_type' => $orderObj->softener_type,
            'starch_level' => $orderObj->starch_level,
            'special_instructions' => $orderObj->special_instructions,
            'defect_log' => $orderObj->defect_log
        ]));

        // Execute the update - single transactional method updates both tables and ensures details row exists
        $update_success = $orderObj->updateOrderWithDetails();
        
        if ($update_success) {
            error_log("SUCCESS: Order updated successfully - order_id: " . $order_db_id);
            $status_message = "Order #{$order['order_id']} successfully updated! New Total: PHP " . number_format($calculated_total, 2);
            // Re-fetch the updated data to display it
            $order = $orderObj->fetchOrder($order_db_id);
            error_log("DEBUG: After update, fetched order preferences: " . json_encode([
                'detergent_type' => $order['detergent_type'] ?? 'NULL',
                'softener_type' => $order['softener_type'] ?? 'NULL',
                'starch_level' => $order['starch_level'] ?? 'NULL'
            ]));
        } else {
            // When the transactional update fails, return a general error message for the UI
            $errors['general'] = "Failed to update order in the database. See logs for details.";
        }
    }
}

// --- LOGIC FOR DISPLAYING CUSTOMER NAME ---
$customer_display_name = htmlspecialchars($order['customer_name'] ?? 'N/A');
$customer_display_phone = htmlspecialchars($order['phone_number'] ?? 'N/A');
$deleted_customer_note = "";

if (isset($order['customer_id']) && $order['customer_id'] === NULL) {
    $deleted_customer_note = " (Customer Profile Deleted)";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order #<?= htmlspecialchars($order['order_id'] ?? 'N/A') ?></title>
    <style>
        
        body { 
            font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; 
            display: flex; justify-content: center; align-items: center; min-height: 100vh;
        }
        .form-container {
            width: 100%; max-width: 650px; 
            background-color: #fff; padding: 40px; border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); text-align: center;
        }
        h1 { color: #343a40; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 25px; text-align: left; }
        h2 { color: #007bff; border-bottom: 1px solid #ced4da; padding-bottom: 5px; margin-top: 30px; margin-bottom: 15px; text-align: left; font-size: 1.25em;}
        .customer-info { 
            background-color: #e9ecef; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: left;
        }
        /* Highlight deleted status */
        .customer-info.deleted {
            background-color: #fce4e4; 
            border: 1px solid #fcc0c0;
        }
        .customer-info strong { color: #007bff; }
        .customer-info.deleted strong { color: #b73a3a; }

        label { display: block; margin-top: 15px; font-weight: bold; color: #495057; text-align: left; }
        input[type="text"], select, textarea { 
            width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; 
        }
        textarea { resize: vertical; height: 80px; }
        .buttons { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; }
        input[type="submit"] { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background-color 0.3s; flex-grow: 1; margin-right: 15px; }
        input[type="submit"]:hover { background-color: #0056b3; }
        .cancel-link { color: #6c757d; text-decoration: none; padding: 12px 20px; border: 1px solid #ced4da; border-radius: 4px; transition: color 0.3s, border-color 0.3s; }
        .cancel-link:hover { color: #343a40; border-color: #343a40; }
        span { color: red; }
        p.error { color: red; margin: 5px 0 0 0; font-size: 0.9em; text-align: left;}
        .success-message { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .form-row { 
            display: flex; 
            gap: 20px; 
            margin-top: 15px;
        }
        .form-row > div {
            flex: 1;
        }
        .total-amount-display {
            background-color: #e9f7e9; 
            border: 1px solid #a6e4a6; 
            padding: 10px; 
            border-radius: 4px; 
            margin-top: 10px;
            font-size: 1.1em;
            font-weight: bold;
            color: #218838;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Edit Order #<?= htmlspecialchars($order['order_id'] ?? 'N/A') ?></h1>
        
        <?php if ($status_message): ?>
            <p class="success-message">✅ <?= htmlspecialchars($status_message) ?></p>
        <?php endif; ?>

        <div class="customer-info <?= $deleted_customer_note ? 'deleted' : '' ?>">
            Customer: 
            <strong><?= $customer_display_name ?></strong> 
            (<?= $customer_display_phone ?>)
            <small style="float: right;">Date: <?= date("Y-m-d H:i", strtotime($order['date_created'] ?? '')) ?></small>
            <?php if ($deleted_customer_note): ?>
                <p style="margin: 5px 0 0 0; font-size: 0.9em; color: #b73a3a; font-weight: bold;">(Customer Profile Deleted)</p>
            <?php endif; ?>
        </div>
        <p class="error"><?= $errors["general"] ?? "" ?></p>

        <form action="" method="post">
            <input type="hidden" name="id" value="<?= htmlspecialchars($order['order_id'] ?? '') ?>">
            
            <h2>Primary Details</h2>

            <div class="form-row">
                <div>
                    <label for="status">Order Status <span>*</span></label>
                    <select name="status" id="status" required>
                        <option value="">-- Change Status --</option>
                        <?php foreach ($status_options as $status): ?>
                            <option value="<?= $status ?>" <?= (($order['status'] ?? '') == $status) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="error"><?= $errors["status"] ?? "" ?></p>
                </div>

                <div>
                    <label for="weight_lbs">Weight (in lbs) <span>*</span></label>
                    <input type="text" name="weight_lbs" id="weight_lbs" value="<?= htmlspecialchars($order['weight_lbs'] ?? '') ?>" required>
                    <p class="error"><?= $errors["weight_lbs"] ?? "" ?></p>
                </div>
            </div>

            <label for="service_type">Service Type <span>*</span></label>
            <select name="service_type" id="service_type" required>
                <option value="">-- Select Service --</option>
                <?php foreach ($service_options as $service): ?>
                    <option value="<?= $service ?>" <?= (($order['service_type'] ?? '') == $service) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($service) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="error"><?= $errors["service_type"] ?? "" ?></p>
            
            <div class="total-amount-display">
                Current Total Amount: PHP <?= number_format($order['total_amount'] ?? 0.00, 2) ?>
            </div>


            <h2>Washing Preferences & Logs</h2>

            <div class="form-row"> 
                <div>
                    <label for="detergent_type">Detergent</label>
                    <select name="detergent_type" id="detergent_type">
                        <?php foreach ($detergent_options as $option): ?>
                            <option value="<?= $option ?>" <?= (($order['detergent_type'] ?? 'Standard') == $option) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="softener_type">Softener</label>
                    <select name="softener_type" id="softener_type">
                        <?php foreach ($softener_options as $option): ?>
                            <option value="<?= $option ?>" <?= (($order['softener_type'] ?? 'Standard') == $option) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="starch_level">Starch Level</label>
                    <select name="starch_level" id="starch_level">
                        <?php foreach ($starch_options as $option): ?>
                            <option value="<?= $option ?>" <?= (($order['starch_level'] ?? 'None') == $option) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <label for="special_instructions">Special Instructions (e.g., Cold wash, delicate)</label>
            <textarea name="special_instructions" id="special_instructions"><?= htmlspecialchars($order['special_instructions'] ?? '') ?></textarea>

            <label for="defect_log">Defect Log (e.g., Existing stain on collar, torn hem)</label>
            <textarea name="defect_log" id="defect_log"><?= htmlspecialchars($order['defect_log'] ?? '') ?></textarea>

            <div class="buttons">
                <input type="submit" value="💾 Save Order Changes">
                <a href="vieworders.php" class="cancel-link">Back to Orders</a>
            </div>
        </form>
    </div>
    <script>
        // Client-side mirror of calculateOrderTotal to keep UI responsive
        (function(){
            const rates = <?= json_encode($rates) ?>;
            const weightInput = document.getElementById('weight_lbs');
            const serviceSelect = document.getElementById('service_type');
            const totalDisplay = document.querySelector('.total-amount-display');

            function formatCurrency(n){
                return Number(n).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            function calculateTotalJS(){
                const rawWeight = weightInput.value.trim();
                const weight = parseFloat(rawWeight);
                if (isNaN(weight) || weight <= 0) {
                    totalDisplay.textContent = 'Current Total Amount: PHP ' + formatCurrency(0.00);
                    return;
                }

                const service = serviceSelect.value;
                const RATE_PER_LB = parseFloat(rates.rate_per_lb || 0);
                const DRY_CLEANING_SURCHARGE = parseFloat(rates.dry_cleaning_surcharge || 0);
                const IRONING_ONLY_SURCHARGE = parseFloat(rates.ironing_only_surcharge || 0);
                const TAX_RATE = parseFloat(rates.tax_rate || 0);

                const base_cost = weight * RATE_PER_LB;
                let service_surcharge = 0.00;
                if (service === 'Dry Cleaning') {
                    service_surcharge = DRY_CLEANING_SURCHARGE;
                } else if (service === 'Ironing Only') {
                    service_surcharge = IRONING_ONLY_SURCHARGE;
                }

                const subtotal = base_cost + service_surcharge;
                const tax = subtotal * TAX_RATE;
                const total_amount = Math.round((subtotal + tax) * 100) / 100;

                totalDisplay.textContent = 'Current Total Amount: PHP ' + formatCurrency(total_amount);
            }

            // Attach listeners
            weightInput.addEventListener('input', calculateTotalJS);
            serviceSelect.addEventListener('change', calculateTotalJS);

            // Initialize display
            document.addEventListener('DOMContentLoaded', calculateTotalJS);
            // Also call immediately to mirror server value if present
            calculateTotalJS();
        })();
    </script>
</body>
</html>