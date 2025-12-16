<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../classes/database.php";
require_once "../classes/laundryorder.php"; 

$database = new Database();
$pdo_conn = $database->connect();
$orderObj = new LaundryOrder($pdo_conn);

$search = "";
$selectedMonth = "";
$selectedYear = "";
$currentYear = date('Y');

if($_SERVER["REQUEST_METHOD"] == "GET"){
    $search = isset($_GET["search"]) ? trim(htmlspecialchars($_GET["search"])) : "";
    $selectedMonth = isset($_GET["month"]) ? trim(htmlspecialchars($_GET["month"])) : "";
    $selectedYear = isset($_GET["year"]) ? trim(htmlspecialchars($_GET["year"])) : "";
}

$months = [
    1 => "January", 2 => "February", 3 => "March", 4 => "April", 5 => "May", 6 => "June",
    7 => "July", 8 => "August", 9 => "September", 10 => "October", 11 => "November", 12 => "December"
];
$years = range($currentYear, $currentYear - 5); 

$orders = $orderObj->viewAllOrders($search, $selectedMonth, $selectedYear);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Laundry Orders</title>
    <style>
        /* --- LAYOUT STYLES --- */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: #343a40; color: #fff; padding-top: 20px; flex-shrink: 0; }
        .content { flex-grow: 1; padding: 20px; }
        /* --------------------- */

        .header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #dee2e6; padding-bottom: 15px; }
        h1 { color: #343a40; margin: 0; font-size: 1.8em; }
        .controls { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; padding: 15px; background-color: #fff; border: 1px solid #ced4da; border-radius: 6px; flex-wrap: wrap; }
        .filter-header { width: 100%; font-size: 1.1em; font-weight: bold; color: #007bff; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #ced4da; }
        .filter-group { display: flex; align-items: center; margin-right: 20px; margin-bottom: 10px; }
        .filter-group label { font-weight: bold; margin-right: 10px; color: #495057; white-space: nowrap; }
        .filter-group input[type="search"], .filter-group select { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; width: 150px; margin-right: 10px; }
        .filter-group input[type="search"] { width: 300px; }
        .filter-group input[type="submit"] { padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap; }
        a.button-link { display: inline-block; background-color: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; transition: background-color 0.3s; white-space: nowrap; }
        
        table { border-collapse: collapse; width: 100%; background-color: #fff; box-shadow: 0 4px 8px rgba(0,0,0,0.08); border-radius: 6px; overflow: hidden; }
        th, td { border: 1px solid #dee2e6; padding: 12px; text-align: left; vertical-align: middle; }
        th { background-color: #007bff; color: white; font-weight: bold; }
        tr:nth-child(even) { background-color: #f1f6fb; }
        .status { font-weight: bold; padding: 4px 8px; border-radius: 3px; display: inline-block; }
        .status-Pending { background-color: #ffc107; color: #343a40; } 
        .status-Processing { background-color: #17a2b8; color: white; } 
        .status-Ready-for-Pickup { background-color: #28a745; color: white; } 
        .status-Completed { background-color: #6c757d; color: white; } 
        .deleted-note { font-style: italic; color: #dc3545; font-weight: 500; }

        /* --- BUTTON STYLES FOR ACTION COLUMN --- */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            margin-right: 5px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 0.85em;
            font-weight: bold;
            transition: background-color 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-action:last-child { margin-right: 0; }
        
        .btn-manage { background-color: #007bff; } /* Blue */
        .btn-manage:hover { background-color: #0056b3; }
        
        .btn-view { background-color: #17a2b8; } /* Teal */
        .btn-view:hover { background-color: #117a8b; }
        
        .btn-delete { background-color: #dc3545; } /* Red */
        .btn-delete:hover { background-color: #a71d2a; }
        
        .action-cell { white-space: nowrap; } /* Keep buttons on one line */
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "../includes/sidebar.php"; ?>
        <div class="content">
            <div class="header-controls">
                <h1>All Active Orders (Tracking)</h1>
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
                        <th style="width: 260px;">Action</th> </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    foreach($orders as $order){
                        $statusClass = "status-" . str_replace(' ', '-', $order["status"]);
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
                        <td><?= $displayName ?><br><?= $displayPhone ?></td>
                        <td><?= number_format($order["weight_lbs"], 2) ?></td>
                        <td><?= htmlspecialchars($order["service_type"]) ?></td>
                        <td><span class="status <?= $statusClass ?>"><?= htmlspecialchars($order["status"]) ?></span></td>
                        <td><?= date("Y-m-d H:i", strtotime($order["date_created"])) ?></td>
                        
                        <td class="action-cell">
                            <?php if ($isDeleted): ?>
                                <span class="btn-action" style="background-color:#6c757d; cursor:default;">🚫 Deleted Profile</span> 
                            <?php else: ?>
                                <a href="editorder.php?id=<?= $order["order_id"] ?>" class="btn-action btn-manage" title="Edit Order">
                                    ⚙️ Manage
                                </a> 
                            <?php endif; ?>
                            
                            <a href="order_summary.php?id=<?= $order["order_id"] ?>" class="btn-action btn-view" title="View Summary">
                                📄 View
                            </a>
                            
                            <a href="deleteorder.php?id=<?= $order["order_id"] ?>" 
                               class="btn-action btn-delete" 
                               title="Delete Order History"
                               onclick="return confirm('Are you sure you want to PERMANENTLY delete Order ID: <?= htmlspecialchars($order["order_id"]) ?>? This action cannot be undone.');">
                               🗑️ Delete
                            </a>
                        </td>
                    </tr>
                    <?php } ?>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 20px;">No orders match your search criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>