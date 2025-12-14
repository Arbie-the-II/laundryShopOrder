<?php
session_start();


// Access Control Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../classes/customer.php";
$customerObj = new Customer();

// --- Initialization ---
$new_customer = [
    "name" => "",
    "phone_number" => ""
];
$new_customer_errors = [];
$search_term = "";
$existing_customers = [];


// --- LOGIC FOR NEW CUSTOMER CREATION (POST) ---
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'new_customer'){
    
    // 1. Sanitize and Collect Data
    $new_customer["name"] = trim(htmlspecialchars($_POST["name"]));
    $new_customer["phone_number"] = trim(htmlspecialchars($_POST["phone_number"]));

    // 2. Validation Checks
    if(empty($new_customer["name"])){
        $new_customer_errors["name"] = "Customer name is required.";
    }

    if(empty($new_customer["phone_number"])){
        $new_customer_errors["phone_number"] = "Phone number is required.";
    } elseif ($customerObj->isCustomerExist($new_customer["phone_number"])) {
        $new_customer_errors["phone_number"] = "A customer with this phone number already exists. Please select them below.";
    }

    // 3. Execution (Customer Creation)
    if(empty(array_filter($new_customer_errors))){
        $customerObj->name = $new_customer["name"];
        $customerObj->phone_number = $new_customer["phone_number"];

        if($customerObj->addCustomer()){
            $new_customer_id = $customerObj->connect()->lastInsertId();

            // Redirect to the order creation page (sibling file)
            header("Location: createorder.php?customer_id=" . $new_customer_id); 
            exit;
        }else{
            $new_customer_errors["general"] = "Error creating the customer in the database.";
        }
    }
}

// --- LOGIC FOR EXISTING CUSTOMER SEARCH (GET or POST) ---
if($_SERVER["REQUEST_METHOD"] == "GET" || $_SERVER["REQUEST_METHOD"] == "POST"){
    // Handle search query for existing customers
    $search_term = isset($_REQUEST["customer_search"]) ? trim(htmlspecialchars($_REQUEST["customer_search"])) : "";
    
    // ASSUMPTION: viewCustomers() method exists in ../classes/customer.php
    $existing_customers = $customerObj->viewCustomers($search_term);
}

// End of PHP logic block - start of HTML presentation
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Selection for New Order</title>
    <style>
        /* Standalone Page Styles */
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 30px; 
            background-color: #f8f9fa; 
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .main-container {
            width: 100%;
            max-width: 900px; 
            margin: auto;
        }
        h1 { color: #343a40; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 30px; }

        /* General Card/Form Styling */
        .card {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        /* New Customer Form Specifics */
        .new-customer-card h2 { color: #28a745; margin-top: 0; padding-bottom: 10px; border-bottom: 1px dashed #ced4da; }
        label { display: block; margin-top: 15px; font-weight: bold; color: #495057; text-align: left; }
        input[type="text"] { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        .form-buttons { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; }
        input[type="submit"] { background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background-color 0.3s; }
        input[type="submit"]:hover { background-color: #218838; }
        .cancel-link { color: #6c757d; text-decoration: none; padding: 12px 20px; border: 1px solid #ced4da; border-radius: 4px; transition: color 0.3s, border-color 0.3s; }
        .cancel-link:hover { color: #dc3545; border-color: #dc3545; }
        p.error { color: red; margin: 5px 0 0 0; font-size: 0.9em; }

        /* Existing Customer Table Specifics */
        .existing-customer-card h2 { color: #007bff; margin-top: 0; padding-bottom: 10px; border-bottom: 1px dashed #ced4da; }
        .search-controls { display: flex; margin-bottom: 20px; }
        .search-controls input[type="search"] { flex-grow: 1; padding: 10px; border: 1px solid #ced4da; border-radius: 4px 0 0 4px; }
        .search-controls input[type="submit"] { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 0 4px 4px 0; cursor: pointer; }

        /* Table and Action Button Styles */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #dee2e6; padding: 10px; text-align: left; }
        th { background-color: #f1f6fb; color: #343a40; }
        .action-button { background-color: #007bff; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px; transition: background-color 0.3s; font-size: 0.9em; }
        .action-button:hover { background-color: #0056b3; }
        
        /* Styles for the Delete button */
        .action-button-group {
            display: flex;
            gap: 5px; /* Spacing between the buttons/forms */
        }
        .action-buttonTwo-submit { 
            background-color: #dc3545; 
            color: white; 
            padding: 8px 12px; 
            border: none;
            border-radius: 4px; 
            transition: background-color 0.3s; 
            font-size: 0.9em; 
            cursor: pointer;
        }
        .action-buttonTwo-submit:hover { 
            background-color: #a71d2a; 
        }
        
        /* New Alert Styles for Status Messages */
        .status-alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-alert.success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .status-alert.error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="main-container">
        
        <div class="header-controls" style="width:100%; display:flex; justify-content: space-between; align-items:center; margin-bottom: 30px;">
            <h1>Customer Selection for New Order</h1>
            <a href="../dashboard.php" class="cancel-link">← Back to Dashboard</a>
        </div>
        
        <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
            <div class="status-alert success">
                ✅ Customer "<?= htmlspecialchars($_GET['name']) ?>" has been successfully deleted.
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="status-alert error">
                ❌ Deletion Error: 
                <?php
                    switch ($_GET['error']) {
                        case 'noid': echo 'No customer ID was provided for deletion.'; break;
                        case 'notfound': echo 'The customer profile could not be found.'; break;
                        case 'deletefailed': echo 'An error occurred while attempting to delete the customer.'; break;
                        case 'invalidmethod': echo 'Deletion must be performed via a secure form submission.'; break;
                        default: echo 'An unknown error occurred.'; break;
                    }
                ?>
            </div>
        <?php endif; ?>
        <div class="card new-customer-card">
            <h2>1. New Walk-in Customer</h2>
            <p>Use this form to add a brand new customer and continue to order details.</p>
            
            <p class="error"><?= $new_customer_errors["general"] ?? "" ?></p>

            <form action="" method="post">
                <input type="hidden" name="action" value="new_customer">
                
                <label for="name">Customer Name <span>*</span></label>
                <input type="text" name="name" id="name" value="<?= htmlspecialchars($new_customer["name"] ?? '') ?>" required>
                <p class="error"><?= $new_customer_errors["name"] ?? "" ?></p>

                <label for="phone_number">Phone Number <span>*</span></label>
                <input type="text" name="phone_number" id="phone_number" value="<?= htmlspecialchars($new_customer["phone_number"] ?? '') ?>" required>
                <p class="error"><?= $new_customer_errors["phone_number"] ?? "" ?></p>

                <div class="form-buttons">
                    <input type="submit" value="Add Customer" style="margin-right:0;">
                </div>
            </form>
        </div>

        <div class="card existing-customer-card">
            <h2>2. Existing Customer</h2>
            <p>Search for a returning customer below by name or phone number to start a fast order.</p>

            <form action="" method="get" class="search-controls">
                <input type="search" name="customer_search" placeholder="Enter name or phone number..." value="<?= htmlspecialchars($search_term) ?>">
                <input type="submit" value="Search">
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Name</th>
                        <th>Phone Number</th>
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
                                <div class="action-button-group">
                                    <a href="createorder.php?customer_id=<?= $customer["id"] ?>" class="action-button">Start New Order</a>
                                    
                                    <button 
                                        type="button" 
                                        class="action-buttonTwo-submit js-delete-btn"
                                        data-customer-id="<?= $customer['id'] ?>"
                                        data-customer-name="<?= htmlspecialchars($customer['name']) ?>"
                                    >Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 20px;">
                                No customers found. Use the form above to add a new walk-in.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.js-delete-btn');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const customerId = this.getAttribute('data-customer-id');
                const customerName = this.getAttribute('data-customer-name');
                
                // Construct the confirmation message
                const message = `Are you sure you want to permanently delete the customer: ${customerName}? This will remove their profile but their order history will remain, displayed as 'Deleted Customer'.`;
                
                if (confirm(message)) {
                    // If confirmed, dynamically create and submit the form to deletecustomer.php
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'deletecustomer.php';

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = customerId;

                    form.appendChild(idInput);
                    // Append form to body and submit it
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
    </script>
</body>
</html>