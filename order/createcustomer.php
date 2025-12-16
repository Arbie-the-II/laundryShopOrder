<?php
session_start();

// Access Control Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Ensure Database and necessary classes are included 
require_once "../classes/database.php";
require_once "../classes/customer.php";
require_once "../classes/mailer.php"; 
require_once "../classes/notification.php"; // Added for TopBar/Bell

$database = new Database();
$pdo_conn = $database->connect();
$customerObj = new Customer($pdo_conn);
// Note: $notifyObj is initialized in includes/topbar.php, but the PDO connection is needed here.

// --- Initialization ---
$new_customer = [
    "name" => "",
    "phone_number" => "",
    "email" => ""
];
$new_customer_errors = [];
$search_term = "";
$existing_customers = [];
$status_message = "";


// --- LOGIC FOR NEW CUSTOMER CREATION (POST) ---
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'new_customer'){
    
    // 1. Sanitize and Collect Data
    $new_customer["name"] = trim(htmlspecialchars($_POST["name"]));
    $new_customer["phone_number"] = trim(htmlspecialchars($_POST["phone_number"]));
    $new_customer["email"] = trim(htmlspecialchars($_POST["email"] ?? ""));

    // 2. Validation Checks
    if(empty($new_customer["name"])){
        $new_customer_errors["name"] = "Customer name is required.";
    }

    if(empty($new_customer["phone_number"])){
        $new_customer_errors["phone_number"] = "Phone number is required.";
    } elseif ($customerObj->isCustomerExist($new_customer["phone_number"])) {
        $new_customer_errors["phone_number"] = "A customer with this phone number already exists. Please select them below.";
    }
    
    // Email Validation
    if(!empty($new_customer["email"])){
        if (!filter_var($new_customer["email"], FILTER_VALIDATE_EMAIL)){
            $new_customer_errors["email"] = "Please provide a valid email address.";
        }
        elseif($customerObj->isEmailExist($new_customer["email"])){
            $new_customer_errors["email"] = "A customer with this email already exists. Please try again";
        }
    }

    // 3. Execution (Customer Creation)
    if(empty(array_filter($new_customer_errors))){
        $customerObj->name = $new_customer["name"];
        $customerObj->phone_number = $new_customer["phone_number"];
        
        $verification_token = null;

        // Handle Email & Token Generation
        if (!empty($new_customer["email"])) {
            $customerObj->email = $new_customer["email"];
            
            // Generate secure token (32-character hex)
            $verification_token = bin2hex(random_bytes(16));
            $customerObj->email_verification_token = $verification_token;
            $customerObj->email_verification_sent_at = date('Y-m-d H:i:s');
        } else {
            $customerObj->email = null;
            $customerObj->email_verification_token = null;
            $customerObj->email_verification_sent_at = null;
        }

        if($customerObj->addCustomer()){
            $new_customer_id = $pdo_conn->lastInsertId();
            
            // --- Send Verification Email ---
            if ($verification_token && !empty($customerObj->email)) {
                $mailer = new Mailer();
                $mailer->sendVerificationEmail($customerObj->email, $customerObj->name, $verification_token);
            }
            // -------------------------------

            // Redirect to the order creation page
            header("Location: createorder.php?customer_id=" . $new_customer_id . "&status=customer_created"); 
            exit;
        }else{
            $new_customer_errors["general"] = "Error creating the customer in the database.";
        }
    }
}

// --- LOGIC FOR EXISTING CUSTOMER SEARCH (GET or POST) ---
if($_SERVER["REQUEST_METHOD"] == "GET" || $_SERVER["REQUEST_METHOD"] == "POST"){
    $search_term = isset($_REQUEST["customer_search"]) ? trim(htmlspecialchars($_REQUEST["customer_search"])) : "";
    // Note: viewCustomers() must select the email_verified_at column for the table display to work.
    $existing_customers = $customerObj->viewCustomers($search_term);
}

// --- Status/Error Message Handling from Redirects ---
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'deleted') {
        $status_message = "✅ Customer \"" . htmlspecialchars($_GET['name'] ?? 'ID ' . ($_GET['id'] ?? '')) . "\" has been successfully deleted.";
    } elseif ($_GET['status'] === 'email_resent') {
        $status_message = "📧 Verification email successfully resent to <strong>" . htmlspecialchars($_GET['name']) . "</strong>.";
    }
}

if (isset($_GET['error'])) {
    $error_type = htmlspecialchars($_GET['error']);
    switch ($error_type) {
        case 'noid': 
        case 'notfound': 
        case 'deletefailed': 
        case 'invalidmethod': 
            // Handle deletion errors from this page
            $status_message = "❌ Deletion Error: Could not process request."; 
            break;
        case 'already_verified': 
            $status_message = "❌ Error: Customer is already verified."; 
            break;
        case 'no_email': 
            $status_message = "❌ Error: Cannot resend, customer has no email address."; 
            break;
        case 'email_failed': 
            $status_message = "❌ Error: Failed to send email. Check server/SMTP settings."; 
            break;
        default: 
            $status_message = "❌ An unknown error occurred."; 
            break;
    }
    // Force error styling
    $status_type = 'error';
} else {
    // Force success styling for status messages unless error occurred
    $status_type = 'success'; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers</title>
    <style>
        /* --- LAYOUT STYLES --- */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; padding-top: 20px; flex-shrink: 0; box-shadow: 4px 0 15px rgba(0,0,0,0.1); }
        .content { flex-grow: 1; padding: 20px; display: flex; flex-direction: column; }
        /* --------------------- */
        
        /* Local styles */
        .main-container { width: 100%; max-width: 900px; margin: 0 auto; }
        h1 { color: #343a40; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 30px; width: 100%; }
        .card { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); margin-bottom: 30px; width: 100%; box-sizing: border-box; }
        .new-customer-card h2 { color: #28a745; margin-top: 0; padding-bottom: 10px; border-bottom: 1px dashed #ced4da; }
        label { display: block; margin-top: 15px; font-weight: bold; color: #495057; text-align: left; }
        input[type="text"] { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        .form-buttons { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; }
        input[type="submit"] { background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.3s; }
        p.error { color: red; margin: 5px 0 0 0; font-size: 0.9em; }
        .existing-customer-card h2 { color: #007bff; margin-top: 0; padding-bottom: 10px; border-bottom: 1px dashed #ced4da; }
        .search-controls { display: flex; margin-bottom: 20px; }
        .search-controls input[type="search"] { flex-grow: 1; padding: 10px; border: 1px solid #ced4da; border-radius: 4px 0 0 4px; }
        .search-controls input[type="submit"] { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 0 4px 4px 0; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #dee2e6; padding: 10px; text-align: left; }
        th { background-color: #f1f6fb; color: #343a40; }
        .action-button { background-color: #007bff; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px; font-size: 0.9em; }
        .action-buttonTwo-submit { background-color: #dc3545; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; }
        .status-alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; width: 100%; box-sizing: border-box;}
        .status-alert.success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
        .status-alert.error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }

        /* Verification Badges */
        .badge-verified { color: #28a745; font-weight: bold; font-size: 1.1em; margin-left: 5px; }
        .badge-unverified { color: #ffc107; font-weight: bold; font-size: 1.1em; margin-left: 5px; cursor: help; }

        /* RESEND BUTTON STYLES (NEW) */
        .btn-resend {
            background-color: #17a2b8; 
            color: white;
            padding: 8px 10px; /* Slightly larger padding to match other buttons */
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.85em;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: background-color 0.3s;
        }
        .btn-resend:hover {
            background-color: #138496;
        }
        .spin-icon { /* Optional: keep styles for icon alignment */ }
        
        .action-button-group {
            display: flex;
            gap: 5px;
            align-items: center; /* Align buttons and resend link vertically */
        }
        .action-table-cell {
            white-space: nowrap; /* Prevent buttons from wrapping */
        }
    </style>
    </head>
<body>
    <div class="wrapper">
        <?php include "../includes/sidebar.php"; ?>
        
        <div class="content">
            <div class="main-container">
                <h1>Manage Customers & New Order</h1>
                
                <?php if (!empty($status_message)): ?>
                    <div class="status-alert <?= $status_type ?>">
                        <?= $status_message ?>
                    </div>
                <?php endif; ?>

                <div class="card new-customer-card">
                    <h2>1. Add New Customer</h2>
                    <p>Use this form to register a new customer into the system.</p>
                    <p class="error"><?= $new_customer_errors["general"] ?? "" ?></p>

                    <form action="" method="post">
                        <input type="hidden" name="action" value="new_customer">
                        
                        <label for="name">Customer Name <span>*</span></label>
                        <input type="text" name="name" id="name" value="<?= htmlspecialchars($new_customer["name"]) ?>" required>
                        <p class="error"><?= $new_customer_errors["name"] ?? "" ?></p>

                        <label for="phone_number">Phone Number <span>*</span></label>
                        <input type="text" name="phone_number" id="phone_number" value="<?= htmlspecialchars($new_customer["phone_number"]) ?>" required>
                        <p class="error"><?= $new_customer_errors["phone_number"] ?? "" ?></p>

                        <label for="email">Email</label>
                        <input type="text" name="email" id="email" value="<?= htmlspecialchars($new_customer["email"]) ?>">
                        <p class="error"><?= $new_customer_errors["email"] ?? "" ?></p>

                        <div class="form-buttons">
                            <input type="submit" value="Add Customer & Start Order">
                        </div>
                    </form>
                </div>

                <div class="card existing-customer-card">
                    <h2>2. Customer List</h2>
                    <p>Search and manage existing customers.</p>

                    <form action="" method="get" class="search-controls">
                        <input type="search" name="customer_search" placeholder="Enter name or phone number..." value="<?= htmlspecialchars($search_term) ?>">
                        <input type="submit" value="Search">
                    </form>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Phone Number</th>
                                <th>Email Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($existing_customers)): ?>
                                <?php foreach($existing_customers as $customer): ?>
                                <tr>
                                    <td><?= htmlspecialchars($customer["id"]) ?></td>
                                    <td><?= htmlspecialchars($customer["name"]) ?></td>
                                    <td><?= htmlspecialchars($customer["phone_number"]) ?></td>
                                    <td>
                                        <?php 
                                            $email = $customer["email"] ?? ''; 
                                            // Assuming viewCustomers SELECTS email_verified_at, which is needed for this logic
                                            $is_verified = !empty($customer['email_verified_at']);
                                            
                                            if (!empty($email)) {
                                                echo htmlspecialchars($email);
                                                if ($is_verified) {
                                                    echo '<span class="badge-verified" title="Verified">✅</span>';
                                                } else {
                                                    echo '<span class="badge-unverified" title="Pending Verification">⚠️</span>';
                                                }
                                            } else {
                                                echo '<span style="color: #999; font-style: italic;">N/A</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="action-table-cell">
                                        <div class="action-button-group">
                                            <a href="createorder.php?customer_id=<?= $customer["id"] ?>" class="action-button">New Order</a>
                                            
                                            <button 
                                                type="button" 
                                                class="action-buttonTwo-submit js-delete-btn"
                                                data-customer-id="<?= $customer['id'] ?>"
                                                data-customer-name="<?= htmlspecialchars($customer['name']) ?>"
                                            >Delete</button>
                                            
                                            <?php 
                                            // RESEND BUTTON LOGIC IN ACTION COLUMN
                                            if (!empty($email) && !$is_verified) {
                                                echo '<a href="resend_verification.php?id=' . $customer['id'] . '" 
                                                         class="btn-resend" 
                                                         title="Resend Verification Email"
                                                         onclick="return confirm(\'Resend verification email to ' . htmlspecialchars($email) . '?\')">
                                                         🔄 Resend
                                                      </a>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px;">
                                        No customers found. Use the form above to add a new one.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.js-delete-btn');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const customerId = this.getAttribute('data-customer-id');
                const customerName = this.getAttribute('data-customer-name');
                const message = `Are you sure you want to permanently delete the customer: ${customerName}? This will remove their profile but their order history will remain, displayed as 'Deleted Customer'.`;
                
                if (confirm(message)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'deletecustomer.php';
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = customerId;
                    form.appendChild(idInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
    </script>
</body>
</html>