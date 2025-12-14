<?php
session_start();

// --- 1. INCLUDE DATABASE CLASS AND ESTABLISH CONNECTION ---
// Ensure you have a working Database class file in classes/database.php
require_once "classes/database.php"; 

// Helper function for PHP currency formatting
function format_php($amount) {
    return '₱' . number_format($amount, 2);
}

// Access Control Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Determine if the user is an Admin
$is_admin = ($_SESSION['role'] === 'admin');

// ------------------------------------
// --- 2. FETCH KPI DATA FROM DATABASE ---
// ------------------------------------
$db = new Database();
$pdo_conn = $db->connect();

$kpis = [
    'total_orders' => 0,
    'completed_orders' => 0,
    'ready_orders' => 0,
    'pending_orders' => 0,
    'processing_orders' => 0,
    'total_revenue_all_time' => 0.00,
    'total_revenue_30d' => 0.00,
    'total_revenue_this_month' => 0.00,
    'average_order_value' => 0.00,
];

try {
    // Total Orders (All Time)
    $stmt_total_orders = $pdo_conn->query("SELECT COUNT(id) FROM laundry_order");
    $kpis['total_orders'] = $stmt_total_orders->fetchColumn() ?? 0;

    // Completed Orders
    $stmt_completed = $pdo_conn->query("SELECT COUNT(id) FROM laundry_order WHERE status = 'Completed'");
    $kpis['completed_orders'] = $stmt_completed->fetchColumn() ?? 0;

    // Orders Ready for Pickup
    $stmt_ready_orders = $pdo_conn->query("SELECT COUNT(id) FROM laundry_order WHERE status = 'Ready for Pickup'");
    $kpis['ready_orders'] = $stmt_ready_orders->fetchColumn() ?? 0;

    // Pending Orders
    $stmt_pending_orders = $pdo_conn->query("SELECT COUNT(id) FROM laundry_order WHERE status = 'Pending'");
    $kpis['pending_orders'] = $stmt_pending_orders->fetchColumn() ?? 0;
    
    // Processing Orders
    $stmt_processing_orders = $pdo_conn->query("SELECT COUNT(id) FROM laundry_order WHERE status = 'Processing'");
    $kpis['processing_orders'] = $stmt_processing_orders->fetchColumn() ?? 0;
    
    // Total Revenue All Time (from completed orders only)
    $stmt_revenue_all = $pdo_conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM laundry_order WHERE status = 'Completed'");
    $kpis['total_revenue_all_time'] = (float)($stmt_revenue_all->fetchColumn() ?? 0.00);
    
    // Total Revenue Last 30 Days 
    $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
    $stmt_revenue_30d = $pdo_conn->prepare("
        SELECT COALESCE(SUM(total_amount), 0) 
        FROM laundry_order 
        WHERE date_created >= ? 
          AND status = 'Completed'
    ");
    $stmt_revenue_30d->execute([$thirty_days_ago]);
    $kpis['total_revenue_30d'] = (float)($stmt_revenue_30d->fetchColumn() ?? 0.00);
    
    // Total Revenue This Month
    $month_start = date('Y-m-01 00:00:00');
    $stmt_revenue_month = $pdo_conn->prepare("
        SELECT COALESCE(SUM(total_amount), 0) 
        FROM laundry_order 
        WHERE date_created >= ? 
          AND status = 'Completed'
    ");
    $stmt_revenue_month->execute([$month_start]);
    $kpis['total_revenue_this_month'] = (float)($stmt_revenue_month->fetchColumn() ?? 0.00);
    
    // Average Order Value
    $kpis['average_order_value'] = ($kpis['completed_orders'] > 0) ? ($kpis['total_revenue_all_time'] / $kpis['completed_orders']) : 0.00;

} catch (PDOException $e) {
    error_log("Dashboard KPI Error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Laundry System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa; 
        }
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        /* --- Sidebar Styling --- */
        .sidebar {
            width: 250px;
            background-color: #343a40; /* Dark color for sidebar */
            color: #fff;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar-header {
            padding: 0 20px 20px 20px;
            text-align: center;
            border-bottom: 1px solid #495057;
        }
        .user-profile {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            border-bottom: 1px solid #495057;
        }
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            background-color: #fff;
        }
        .nav-list {
            list-style: none;
            padding: 0;
        }
        .nav-list a {
            display: block;
            padding: 12px 20px;
            color: #ced4da;
            text-decoration: none;
            transition: background-color 0.3s, color 0.3s;
        }
        .nav-list a:hover, .nav-list a.active {
            background-color: #28a745; 
            color: #fff;
        }
        .nav-heading {
            color: #adb5bd;
            font-size: 0.85em;
            padding: 15px 20px 5px;
            text-transform: uppercase;
        }
        /* --- End Sidebar Styling --- */
        
        /* Main Content Area */
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
     
        .card {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .module-header {
            background-color: #007bff; 
            color: white;
            padding: 15px;
            margin: -20px -20px 20px -20px; 
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
            font-size: 1.2em;
        }
        .quick-actions li {
            padding: 5px 0;
        }

        /* --- KPI Styles (NEW) --- */
        .kpi-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }
        .kpi-card {
            flex: 1;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
            border-bottom: 4px solid; 
        }
        .kpi-card h4 {
            color: #6c757d;
            font-size: 0.85em;
            margin-top: 0;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        .kpi-value {
            font-size: 2.2em;
            font-weight: bold;
            color: #343a40;
            line-height: 1;
        }
        /* Color coding for KPIs */
        .kpi-blue { border-bottom-color: #007bff; }    
        .kpi-green { border-bottom-color: #28a745; }  
        .kpi-yellow { border-bottom-color: #ffc107; } 
        .kpi-red { border-bottom-color: #dc3545; }    

    </style>
</head>
<body>
    <div class="wrapper">
        
        <?php include "includes/sidebar.php"; ?>
        
        <div class="content">
            <div class="header-bar">
                <h1>Dashboard</h1>
            </div>

            <div class="kpi-container">
                
                <!-- Row 1: Order Counts -->
                <div class="kpi-card kpi-blue">
                    <h4><span style="font-size: 1.2em;">📦</span> Total Orders</h4>
                    <div class="kpi-value"><?= htmlspecialchars($kpis['total_orders']) ?></div>
                </div>
                
                <div class="kpi-card kpi-green">
                    <h4><span style="font-size: 1.2em;">✅</span> Completed Orders</h4>
                    <div class="kpi-value"><?= htmlspecialchars($kpis['completed_orders']) ?></div>
                </div>
                
                <div class="kpi-card kpi-yellow">
                    <h4><span style="font-size: 1.2em;">⏳</span> Processing Orders</h4>
                    <div class="kpi-value"><?= htmlspecialchars($kpis['processing_orders']) ?></div>
                </div>
                
                <div class="kpi-card kpi-blue">
                    <h4><span style="font-size: 1.2em;">⭐</span> Avg Order Value</h4>
                    <div class="kpi-value"><?= format_php($kpis['average_order_value']) ?></div>
                </div>
            </div>

            <!-- Revenue Section -->
            <div class="kpi-container">
                <div class="kpi-card kpi-green">
                    <h4><span style="font-size: 1.2em;">💰</span> Revenue (All Time)</h4>
                    <div class="kpi-value"><?= format_php($kpis['total_revenue_all_time']) ?></div>
                </div>
                
                <div class="kpi-card kpi-yellow">
                    <h4><span style="font-size: 1.2em;">📊</span> This Month's Revenue</h4>
                    <div class="kpi-value"><?= format_php($kpis['total_revenue_this_month']) ?></div>
                </div>
                
                <div class="kpi-card kpi-red">
                    <h4><span style="font-size: 1.2em;">🔴</span> Last 30 Days Revenue</h4>
                    <div class="kpi-value"><?= format_php($kpis['total_revenue_30d']) ?></div>
                </div>
            </div>
            <div class="card">
                <div class="module-header">
                    SYSTEM OVERVIEW
                </div>
                
                <h2>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>!</h2>
                <p>Your access role is: <strong><?= ucfirst($_SESSION['role']) ?></strong>.</p>
                
                <hr>

                <h3>🚀 Quick Actions</h3>
                <ul style="list-style: none; padding: 0;" class="quick-actions">
                    <li>&#10003; <a href="order/createcustomer.php">Start a New Walk-In Order</a></li>
                    <li>&#10003; <a href="order/vieworders.php">Check Order Tracking Status</a></li>
                </ul>

                <?php if ($is_admin): ?>
                    <hr>
                    <h3>👑 Admin Tools</h3>
                    <ul style="list-style: none; padding: 0;" class="quick-actions">
                        <li>&#10003; <a href="reports/index.php">View Reports and Analytics Dashboard</a></li>
                        <li>&#10003; <a href="admin/pricing.php">Manage Shop Pricing Rates</a></li>
                        <li>&#10003; <a href="admin/view_users.php">Manage System Users</a></li>
                        <li>&#10003; <a href="admin/register_user.php">Add New System User</a></li>
                    </ul>
                <?php endif; ?>
            </div>
            
        </div>
        </div>
</body>
</html>