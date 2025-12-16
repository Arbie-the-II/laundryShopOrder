<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

function isActive($fileName) {
    return (strpos($_SERVER['SCRIPT_NAME'], $fileName) !== false) ? 'active' : '';
}

// Dynamic path logic
$path_prefix = "";
if (strpos($_SERVER['SCRIPT_NAME'], '/order/') !== false || 
    strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || 
    strpos($_SERVER['SCRIPT_NAME'], '/reports/') !== false || 
    strpos($_SERVER['SCRIPT_NAME'], '/classes/') !== false) {
    $path_prefix = "../";
}
?>

<style>
    /* Main Sidebar Container */
    .sidebar {
        width: 260px;
        height: 100vh;
        background-color: #2c3e50; /* Dark Blue-Grey Background */
        color: #ecf0f1;
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        flex-shrink: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Header Section */
    .sidebar-header {
        padding: 25px 20px;
        text-align: center;
        background-color: #1a252f; /* Darker header */
        border-bottom: 1px solid #34495e;
        margin-bottom: 20px;
    }

    .sidebar-header h2 {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
        letter-spacing: 1px;
        color: #fff;
    }

    /* Navigation List */
    .nav-list {
        list-style: none;
        padding: 0 15px; 
        margin: 0;
        flex-grow: 1;
    }

    /* Section Headings */
    .nav-heading {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #95a5a6;
        margin: 15px 0 5px 10px;
        font-weight: bold;
        letter-spacing: 1px;
    }

    /* The "Box" Design for Links */
    .nav-list li {
        margin-bottom: 8px; 
    }

    .nav-list li a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: #bdc3c7;
        text-decoration: none; 
        background-color: #34495e; 
        border-radius: 8px; 
        transition: all 0.3s ease;
        font-size: 0.95rem;
        font-weight: 500;
        border-left: 4px solid transparent;
    }

    /* Icon styling */
    .nav-list li a .icon {
        margin-right: 12px;
        font-size: 1.1rem;
        width: 25px;
        text-align: center;
    }

    /* Hover Effect */
    .nav-list li a:hover {
        background-color: #3e5871;
        color: #fff;
        transform: translateX(5px); 
        border-left-color: #3498db; 
    }

    /* Active State */
    .nav-list li a.active {
        background-color: #007bff; 
        color: #fff;
        box-shadow: 0 4px 6px rgba(0, 123, 255, 0.3);
    }

    /* Logout Specific Style */
    .logout-item {
        margin-top: auto; 
        border-top: 1px solid #34495e;
        padding-top: 15px;
    }
    
    .logout-link {
        background-color: #c0392b !important; 
        color: white !important;
    }
    .logout-link:hover {
        background-color: #e74c3c !important;
        border-left-color: #fff !important;
    }
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>🧺 Laundry<br><span style="font-size: 0.7em; font-weight: normal; color: #3498db;">Manager</span></h2>
    </div>

    <ul class="nav-list">
        
        <li>
            <a href="<?= $path_prefix ?>dashboard.php" class="<?= isActive('dashboard.php') ?>">
                <span class="icon">📊</span> Dashboard
            </a>
        </li>
        
        <li class="nav-heading">OPERATIONS</li>
        
        <li>
            <a href="<?= $path_prefix ?>order/createcustomer.php" class="<?= isActive('createcustomer.php') ?>">
                <span class="icon">👥</span> Manage Customers
            </a>
        </li>
        <li>
            <a href="<?= $path_prefix ?>order/vieworders.php" class="<?= isActive('vieworders.php') ?>">
                <span class="icon">📦</span> Manage Orders
            </a>
        </li>
        
        <?php if ($is_admin): ?>
        <li class="nav-heading">ADMINISTRATION</li>
            
            <li>
                <a href="<?= $path_prefix ?>reports/index.php" class="<?= isActive('reports/index.php') ?>">
                    <span class="icon">📈</span> Reports & Analytics
                </a>
            </li>
            <li>
                <a href="<?= $path_prefix ?>admin/view_users.php" class="<?= isActive('view_users.php') ?>">
                    <span class="icon">🛡️</span> Users (Staff/Admin)
                </a>
            </li>
            <li>
                <a href="<?= $path_prefix ?>admin/pricing.php" class="<?= isActive('pricing.php') ?>">
                    <span class="icon">💰</span> Manage Prices
                </a>
            </li>
        <?php endif; ?>
        
        <li class="nav-heading">ACCOUNT</li>
        <li class="logout-item">
            <a href="<?= $path_prefix ?>logout.php" class="logout-link">
                <span class="icon">🚪</span> Logout
            </a>
        </li>
    </ul>
</div>