<?php
session_start();

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../classes/database.php";
require_once "../classes/customer.php";
require_once "../classes/laundryorder.php";

$database = new Database();
$pdo_conn = $database->connect();

$customerObj = new Customer($pdo_conn);
$orderObj = new LaundryOrder($pdo_conn);

if (!isset($_GET['customer_id'])) {
    // If no ID is passed, redirect back to customer selection 
    header("Location: createcustomer.php");
    exit;
}

$customer_id = trim(htmlspecialchars($_GET['customer_id']));
$customer = $customerObj->fetchCustomer($customer_id);

if (!$customer) {
    // If ID is invalid or customer doesn't exist
    header("Location: createcustomer.php");
    exit;
}

// --- SETUP DROPDOWN OPTIONS (Configuration) ---
$service_options = ['Wash and Fold', 'Dry Cleaning', 'Ironing Only'];
$detergent_options = ['Standard', 'Hypoallergenic', 'Scented Deluxe'];
$softener_options = ['Standard', 'No Softener', 'Extra Scented'];
$starch_options = ['None', 'Light', 'Medium', 'Heavy'];


// --- INITIALIZE & PROCESS FORM ---
$order_data = [
    'weight_lbs' => '',
    'service_type' => '',
    'special_instructions' => '', 
    'defect_log' => '',
    'detergent_type' => 'Standard', // Set default
    'softener_type' => 'Standard',  // Set default
    'starch_level' => 'None'   // Set default
];
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and collect data
    $order_data['weight_lbs'] = trim(htmlspecialchars($_POST['weight_lbs'] ?? ''));
    $order_data['service_type'] = trim(htmlspecialchars($_POST['service_type'] ?? ''));
    
    // --- COLLECT ALL NEW FIELDS ---
    $order_data['special_instructions'] = trim(htmlspecialchars($_POST['special_instructions'] ?? ''));
    $order_data['defect_log'] = trim(htmlspecialchars($_POST['defect_log'] ?? ''));
    $order_data['detergent_type'] = trim(htmlspecialchars($_POST['detergent_type'] ?? 'Standard'));
    $order_data['softener_type'] = trim(htmlspecialchars($_POST['softener_type'] ?? 'Standard'));
    $order_data['starch_level'] = trim(htmlspecialchars($_POST['starch_level'] ?? 'None'));
    
    // Validation
    if (!is_numeric($order_data['weight_lbs']) || (float)$order_data['weight_lbs'] <= 0) {
        $errors['weight_lbs'] = "Weight must be a positive number.";
    }
    if (empty($order_data['service_type'])) {
        $errors['service_type'] = "Please select a service type.";
    }

    if (empty(array_filter($errors))) {
        // Prepare data for the LaundryOrder class
        $orderObj->customer_id = $customer['id'];
        $orderObj->customer_name_at_order = $customer['name'];
        $orderObj->customer_phone_at_order = $customer['phone_number'];
        $orderObj->weight_lbs = (float)$order_data['weight_lbs'];
        $orderObj->service_type = $order_data['service_type'];
        $orderObj->status = "Pending"; // Set default status
        $orderObj->total_amount = 0.00; // Will be calculated later, set to 0 for now

        // --- ASSIGN ALL PROPERTIES TO $orderObj ---
        $orderObj->special_instructions = $order_data['special_instructions'];
        $orderObj->defect_log = $order_data['defect_log'];
        $orderObj->detergent_type = $order_data['detergent_type'];
        $orderObj->softener_type = $order_data['softener_type'];
        $orderObj->starch_level = $order_data['starch_level'];

        // Debug log: washing preferences being saved
        error_log("DEBUG: Saving order preferences: " . json_encode([
            'customer_id' => $orderObj->customer_id,
            'detergent_type' => $orderObj->detergent_type,
            'softener_type' => $orderObj->softener_type,
            'starch_level' => $orderObj->starch_level,
            'special_instructions' => $orderObj->special_instructions,
            'defect_log' => $orderObj->defect_log
        ]));

        if ($orderObj->createOrder()) {
            error_log("SUCCESS: Order created with ID: " . $orderObj->order_id);
            // Redirect to the main order viewing page
            header("Location: vieworders.php?order_status=created");
            exit;
        } else {
            error_log("ERROR: Failed to create order for customer: " . $customer['id']);
            $errors['general'] = "Failed to create order in the database. Please check connection/logs.";
        }
    }
}

// If validation fails, use $_POST values (now stored in $order_data) to repopulate the form
$form_weight = $order_data['weight_lbs'];
$form_service = $order_data['service_type'];
$form_special_instructions = $order_data['special_instructions']; 
$form_defect_log = $order_data['defect_log'];
$form_detergent = $order_data['detergent_type'];
$form_softener = $order_data['softener_type'];
$form_starch_level = $order_data['starch_level'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Order</title>
    <style>
        /* Existing styles maintained */
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; padding: 0; background-color: #f8f9fa; 
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
            background-color: #f1f6fb; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: left;
        }
        .customer-info strong { color: #007bff; }
        label { display: block; margin-top: 15px; font-weight: bold; color: #495057; text-align: left; }
        input[type="text"], select, textarea { 
            width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; 
        }
        textarea { resize: vertical; height: 80px; }
        .buttons { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; }
        input[type="submit"] { background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background-color 0.3s; flex-grow: 1; margin-right: 15px; }
        input[type="submit"]:hover { background-color: #218838; }
        .cancel-link { color: #6c757d; text-decoration: none; padding: 12px 20px; border: 1px solid #ced4da; border-radius: 4px; transition: color 0.3s, border-color 0.3s; }
        .cancel-link:hover { color: #dc3545; border-color: #dc3545; }
        span { color: red; }
        p.error { color: red; margin: 5px 0 0 0; font-size: 0.9em; text-align: left;}
        /* status alert styles */
        .status-alert { padding: 12px; margin-bottom: 12px; border-radius: 6px; font-weight: 700; }
        .status-alert.success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
        .status-alert.error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        
        /* New row styling for washing preferences */
        .form-row { 
            display: flex; 
            gap: 20px; 
            margin-top: 15px;
        }
        .form-row > div {
            flex: 1;
        }
        .form-row label {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Create Laundry Order</h1>
        
        <div class="customer-info">
            Order for: 
            <strong><?= htmlspecialchars($customer['name']) ?></strong> 
            (<?= htmlspecialchars($customer['phone_number']) ?>)
            <small style="float: right;">Customer ID: <?= htmlspecialchars($customer['id']) ?></small>
        </div>

        <form action="" method="post">
            <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer['id']) ?>">
            
            <h2>Primary Details</h2>

            <label for="weight_lbs">Weight (in lbs) <span>*</span></label>
            <input type="text" name="weight_lbs" id="weight_lbs" value="<?= htmlspecialchars($form_weight) ?>" required>
            <p class="error"><?= $errors["weight_lbs"] ?? "" ?></p>

            <label for="service_type">Service Type <span>*</span></label>
            <select name="service_type" id="service_type" required>
                <option value="">-- Select Service --</option>
                <?php foreach ($service_options as $service): ?>
                    <option value="<?= $service ?>" <?= ($form_service == $service) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($service) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="error"><?= $errors["service_type"] ?? "" ?></p>
            
            <h2>Washing Preferences & Logs</h2>

            <div class="form-row"> 
                <div>
                    <label for="detergent_type">Detergent</label>
                    <select name="detergent_type" id="detergent_type">
                        <?php foreach ($detergent_options as $option): ?>
                            <option value="<?= $option ?>" <?= ($form_detergent == $option) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="softener_type">Softener</label>
                    <select name="softener_type" id="softener_type">
                        <?php foreach ($softener_options as $option): ?>
                            <option value="<?= $option ?>" <?= ($form_softener == $option) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="starch_level">Starch Level</label>
                    <select name="starch_level" id="starch_level">
                        <?php foreach ($starch_options as $option): ?>
                            <option value="<?= $option ?>" <?= ($form_starch_level == $option) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <label for="special_instructions">Special Instructions (e.g., Cold wash, delicate)</label>
            <textarea name="special_instructions" id="special_instructions"><?= htmlspecialchars($form_special_instructions) ?></textarea>

            <label for="defect_log">Defect Log (e.g., Existing stain on collar, torn hem)</label>
            <textarea name="defect_log" id="defect_log"><?= htmlspecialchars($form_defect_log) ?></textarea>
            
            <div class="buttons">
                <input type="submit" value="Confirm & Create Order">
                <a href="createcustomer.php" class="cancel-link">Cancel Order</a>
            </div>
        </form>
    </div>
</body>
</html>