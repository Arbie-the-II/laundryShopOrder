<?php
session_start();

// Access Control Check
if (!isset($_SESSION['user_id'])) {
    // Path correction: go up one level to find login.php
    header("Location: ../login.php");
    exit;
}

require_once "../classes/database.php";
require_once "../classes/laundryorder.php"; // Path correction: go up one level to find classes/

$database = new Database();
$pdo_conn = $database->connect();

$orderObj = new LaundryOrder($pdo_conn);

$search = "";
$selectedMonth = "";
$selectedYear = "";
$currentYear = date('Y');

if($_SERVER["REQUEST_METHOD"] == "GET"){
    // Allow searching by Order ID, Customer Name, or Phone Number
    $search = isset($_GET["search"]) ? trim(htmlspecialchars($_GET["search"])) : "";
    
    // --- Capture Month and Year filters ---
    $selectedMonth = isset($_GET["month"]) ? trim(htmlspecialchars($_GET["month"])) : "";
    $selectedYear = isset($_GET["year"]) ? trim(htmlspecialchars($_GET["year"])) : "";
}

// --- Data for Month/Year Selectors ---
$months = [
    1 => "January", 2 => "February", 3 => "March", 4 => "April", 5 => "May", 6 => "June",
    7 => "July", 8 => "August", 9 => "September", 10 => "October", 11 => "November", 12 => "December"
];
$years = range($currentYear, $currentYear - 5); // Show current year and previous 5 years

// Fetch all orders using the updated method (must SELECT customer_name_snapshot and customer_id)
$orders = $orderObj->viewAllOrders($search, $selectedMonth, $selectedYear);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Laundry Orders</title>
    <style>
        /* Standalone Structural Styles (Full Width) */
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 30px; 
            background-color: #f8f9fa; 
        }
        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 15px;
        }
        h1 { 
            color: #343a40; 
            margin: 0; 
            font-size: 1.8em;
        }
        
        /* Dashboard Link Button */
        a.dashboard-link { 
            display: inline-block; 
            background-color: #6c757d; 
            color: white; 
            padding: 10px 15px; 
            text-decoration: none; 
            border-radius: 4px; 
            transition: background-color 0.3s; 
        }
        a.dashboard-link:hover { background-color: #5a6268; }

        /* Order Controls (Search & Add New) */
        .controls { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            margin-bottom: 25px; 
            padding: 15px;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            flex-wrap: wrap; 
        }
        
        /* Filter Header */
        .filter-header {
            width: 100%; 
            font-size: 1.1em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ced4da;
        }

        /* Grouping the forms/controls */
        .filter-group { 
            display: flex; 
            align-items: center; 
            margin-right: 20px; 
            margin-bottom: 10px; 
        }
        .filter-group label { 
            font-weight: bold; 
            margin-right: 10px; 
            color: #495057; 
            white-space: nowrap; 
        }
        .filter-group input[type="search"], 
        .filter-group select { 
            padding: 8px; 
            border: 1px solid #ced4da; 
            border-radius: 4px; 
            width: 150px; 
            margin-right: 10px; 
        }
        .filter-group input[type="search"] {
             width: 300px;
        }
        .filter-group input[type="submit"] { 
            padding: 8px 15px; 
            background-color: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            white-space: nowrap; 
        }
        .filter-group input[type="submit"]:hover { background-color: #0056b3; }
        
        a.button-link { 
            display: inline-block; 
            background-color: #28a745; 
            color: white; 
            padding: 10px 15px; 
            text-decoration: none; 
            border-radius: 4px; 
            transition: background-color 0.3s; 
            white-space: nowrap; 
        }
        a.button-link:hover { background-color: #218838; }

        /* Table Styling */
        table { 
            border-collapse: collapse; 
            width: 100%; 
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.08); 
            border-radius: 6px;
            overflow: hidden; 
        }
        th, td { 
            border: 1px solid #dee2e6; 
            padding: 12px; 
            text-align: left; 
        }
        th { 
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f1f6fb; 
        }
        tr:hover {
            background-color: #e9ecef;
        }
        /* Status Colors */
        .status {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 3px;
            display: inline-block;
        }
        .status-Pending { background-color: #ffc107; color: #343a40; } 
        .status-Processing { background-color: #17a2b8; color: white; } 
        .status-Ready-for-Pickup { background-color: #28a745; color: white; } 
        .status-Completed { background-color: #6c757d; color: white; } 

        .action-link {
            color: #007bff;
            margin-right: 10px;
            text-decoration: none;
        }
        .action-link.delete {
            color: #dc3545; /* Red color for Delete action */
            margin-right: 0;
        }
        .action-link:hover {
            text-decoration: underline;
        }
        .deleted-note {
            font-style: italic;
            color: #dc3545;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="header-controls">
        <h1>All Active Orders (Tracking)</h1>
        <a href="../dashboard.php" class="dashboard-link">← Back to Dashboard</a>
    </div>

    <div class="controls">
        
        <div class="filter-header">🔍 Order Filters</div>

        <form action="" method="get" style="display: flex; flex-wrap: wrap;">
            
            <div class="filter-group">
                <label for="search">Search:</label>
                <input type="search" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="ID, Name, or Phone Number">
            </div>

            <div class="filter-group">
                <label for="month">Month:</label>
                <select name="month" id="month" style="width: 120px;">
                    <option value="">All Months</option>
                    <?php foreach ($months as $num => $name): ?>
                        <option value="<?= $num ?>" <?= ($selectedMonth == $num) ? 'selected' : '' ?>>
                            <?= $name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="year">Year:</label>
                <select name="year" id="year" style="width: 100px;">
                    <option value="">All Years</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= $year ?>" <?= ($selectedYear == $year) ? 'selected' : '' ?>>
                            <?= $year ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <input type="submit" value="Apply Filter">
            </div>

        </form>
        
        <a href="createcustomer.php" class="button-link"> + Add New Order</a>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Order ID</th>
                <th>Customer Name</th>
                <th>Weight (lbs)</th>
                <th>Service Type</th>
                <th>Status</th>
                <th>Date Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            foreach($orders as $order){
                // Determine a status class for styling
                $statusClass = "status-" . str_replace(' ', '-', $order["status"]);
                
                // CRITICAL LOGIC: Determine display name/phone using the COALESCE logic from your model
                // The model (laundryorder.php) is already using COALESCE to give us `customer_name` and `phone_number`
                
                // If the original customer_id is empty, it means the profile was deleted.
                $isDeleted = empty($order["customer_id"]);

                if ($isDeleted) {
                    $displayName = '<span class="deleted-note">' . htmlspecialchars($order["customer_name"] ?? 'Deleted Customer') . '</span>';
                    $displayPhone = '<small>(' . htmlspecialchars($order["phone_number"] ?? 'N/A') . ')</small>';
                } else {
                    $displayName = htmlspecialchars($order["customer_name"]); 
                    $displayPhone = '<small>(' . htmlspecialchars($order["phone_number"]) . ')</small>'; 
                }
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><strong style="color:#007bff;"><?= htmlspecialchars($order["order_id"]) ?></strong></td>
                
                <td>
                    <?= $displayName ?><br>
                    <?= $displayPhone ?>
                </td>
                
                <td><?= number_format($order["weight_lbs"], 2) ?></td>
                <td><?= htmlspecialchars($order["service_type"]) ?></td>
                <td>
                    <span class="status <?= $statusClass ?>"><?= htmlspecialchars($order["status"]) ?></span>
                </td>
                <td><?= date("Y-m-d H:i", strtotime($order["date_created"])) ?></td>
                <td>
                    <?php if ($isDeleted): ?>
                        <span style="color:#6c757d; margin-right: 10px;">(Profile Deleted)</span> 
                    <?php else: ?>
                        <a href="editorder.php?id=<?= $order["order_id"] ?>" class="action-link">Manage</a> 
                        |
                    <?php endif; ?>
                    <a href="order_summary.php?id=<?= $order["order_id"] ?>" class="action-link" style="color:#17a2b8;">View Summary</a>
                    |
                    <a href="deleteorder.php?id=<?= $order["order_id"] ?>" 
                       class="action-link delete" 
                       onclick="return confirm('Are you sure you want to PERMANENTLY delete Order ID: <?= htmlspecialchars($order["order_id"]) ?>? This action cannot be undone.');">
                       Delete History
                    </a>
                </td>
            </tr>
            <?php } ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="8" style="text-align: center; padding: 20px;">No orders match your search criteria.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>