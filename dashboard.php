<?php
session_start();

// --- 1. INCLUDE DATABASE CLASS AND ESTABLISH CONNECTION ---
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

    // Processing Orders
    $stmt_processing_orders = $pdo_conn->query("SELECT COUNT(id) FROM laundry_order WHERE status = 'Processing'");
    $kpis['processing_orders'] = $stmt_processing_orders->fetchColumn() ?? 0;
    
    // Total Revenue All Time (from completed orders only)
    $stmt_revenue_all = $pdo_conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM laundry_order WHERE status = 'Completed'");
    $kpis['total_revenue_all_time'] = (float)($stmt_revenue_all->fetchColumn() ?? 0.00);
    
    // Total Revenue Last 30 Days 
    $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
    $stmt_revenue_30d = $pdo_conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM laundry_order WHERE date_created >= ? AND status = 'Completed'");
    $stmt_revenue_30d->execute([$thirty_days_ago]);
    $kpis['total_revenue_30d'] = (float)($stmt_revenue_30d->fetchColumn() ?? 0.00);
    
    // Total Revenue This Month
    $month_start = date('Y-m-01 00:00:00');
    $stmt_revenue_month = $pdo_conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM laundry_order WHERE date_created >= ? AND status = 'Completed'");
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
        /* --- LAYOUT STYLES --- */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f4f6f9; color: #333; }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background-color: #343a40; color: #fff; padding-top: 20px; flex-shrink: 0; }
        .content { flex-grow: 1; padding: 30px; }
        /* --------------------- */

        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        h1 { margin: 0; color: #343a40; font-size: 2rem; font-weight: 600; }

        /* --- KPI Cards --- */
        .kpi-container { display: flex; justify-content: space-between; gap: 20px; margin-bottom: 30px; }
        .kpi-card { 
            flex: 1; 
            background-color: #fff; 
            padding: 25px; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
            text-align: center; 
            border-bottom: 5px solid #ddd;
            transition: transform 0.2s;
        }
        .kpi-card:hover { transform: translateY(-5px); }
        .kpi-card h4 { color: #6c757d; font-size: 0.9em; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-value { font-size: 2.2em; font-weight: 700; color: #212529; }
        
        .kpi-blue { border-bottom-color: #007bff; }    
        .kpi-green { border-bottom-color: #28a745; }  
        .kpi-yellow { border-bottom-color: #ffc107; } 
        .kpi-red { border-bottom-color: #dc3545; }    

        /* --- Main Card --- */
        .card { background-color: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 30px; }
        .welcome-text { font-size: 1.2em; margin-bottom: 20px; }
        .role-badge { background-color: #e9ecef; padding: 5px 10px; border-radius: 4px; font-size: 0.9em; font-weight: bold; color: #495057; }

        /* --- Action Buttons Grid (Replaces List) --- */
        h3 { color: #495057; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px; margin-top: 30px; margin-bottom: 20px; }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); /* Responsive columns */
            gap: 20px;
            padding: 0;
            list-style: none;
        }

        .action-grid li a {
            display: flex;
            align-items: center;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            text-decoration: none;
            color: #495057;
            font-weight: 600;
            font-size: 1.05rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .action-grid li a:hover {
            background-color: #f8f9fa;
            border-color: #007bff;
            color: #007bff;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.1);
        }

        .action-grid li a .icon {
            font-size: 1.8rem;
            margin-right: 15px;
            width: 40px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "../laundryShopOrd/includes/sidebar.php"; ?>
        
        <div class="content">
            <div class="header-bar">
                <h1>Dashboard</h1>
            </div>

    <div class="content">
        <?php include "includes/topbar.php"; ?> 

            <div class="kpi-container">
                <div class="kpi-card kpi-blue">
                    <h4>📦 Total Orders</h4>
                    <div class="kpi-value"><?= htmlspecialchars($kpis['total_orders']) ?></div>
                </div>
                <div class="kpi-card kpi-green">
                    <h4>✅ Completed</h4>
                    <div class="kpi-value"><?= htmlspecialchars($kpis['completed_orders']) ?></div>
                </div>
                <div class="kpi-card kpi-yellow">
                    <h4>⏳ Processing</h4>
                    <div class="kpi-value"><?= htmlspecialchars($kpis['processing_orders']) ?></div>
                </div>
                <div class="kpi-card kpi-blue">
                    <h4>⭐ Avg Value</h4>
                    <div class="kpi-value"><?= format_php($kpis['average_order_value']) ?></div>
                </div>
            </div>

            <div class="kpi-container">
                <div class="kpi-card kpi-green">
                    <h4>💰 Revenue (Total)</h4>
                    <div class="kpi-value"><?= format_php($kpis['total_revenue_all_time']) ?></div>
                </div>
                <div class="kpi-card kpi-yellow">
                    <h4>📊 Revenue (This Month)</h4>
                    <div class="kpi-value"><?= format_php($kpis['total_revenue_this_month']) ?></div>
                </div>
                <div class="kpi-card kpi-red">
                    <h4>🔴 Revenue (30 Days)</h4>
                    <div class="kpi-value"><?= format_php($kpis['total_revenue_30d']) ?></div>
                </div>
            </div>

            <div class="card">
                <div class="welcome-text">
                    Welcome back, <strong><?= htmlspecialchars($_SESSION['name']) ?></strong>! 
                    <span class="role-badge"><?= ucfirst($_SESSION['role']) ?> Access</span>
                </div>

                <h3>🚀 Quick Actions</h3>
                <ul class="action-grid">
                    <li>
                        <a href="order/createcustomer.php">
                            <span class="icon">🛒</span> Start New Walk-In Order
                        </a>
                    </li>
                    <li>
                        <a href="order/vieworders.php">
                            <span class="icon">📋</span> Order Tracking & Status
                        </a>
                    </li>
                </ul>

                <?php if ($is_admin): ?>
                    <h3>👑 Admin Tools</h3>
                    <ul class="action-grid">
                        <li>
                            <a href="reports/index.php">
                                <span class="icon">📈</span> Reports & Analytics
                            </a>
                        </li>
                        <li>
                            <a href="admin/pricing.php">
                                <span class="icon">💲</span> Manage Prices
                            </a>
                        </li>
                        <li>
                            <a href="admin/view_users.php">
                                <span class="icon">👥</span> Manage Users
                            </a>
                        </li>
                        <li>
                            <a href="admin/register_user.php">
                                <span class="icon">➕</span> Add System User
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>