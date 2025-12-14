<?php 

$is_admin = ($_SESSION['role'] === 'admin');

// Function to check if a link is currently active
function isActive($fileName) {
    return (strpos($_SERVER['SCRIPT_NAME'], $fileName) !== false) ? 'active' : '';
}


// If the script is NOT in the root directory, path_prefix = "../"
$path_prefix = (basename(dirname($_SERVER['SCRIPT_NAME'])) === basename(getcwd())) ? "" : "../";
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>Laundry Shop <br>Order System</h2>
    </div>
    

    <ul class="nav-list">
        
        <li><a href="<?= $path_prefix ?>../dashboard.php" class="<?= isActive('dashboard.php') ?>">Dashboard</a></li>
        
        <li class="nav-heading">Orders & Customers</li>
        <li><a href="<?= $path_prefix ?>order/createcustomer.php" class="<?= isActive('createcustomer.php') ?>">Create New Order</a></li>
        <li><a href="<?= $path_prefix ?>order/vieworders.php" class="<?= isActive('vieworders.php') ?>">View All Orders</a></li>
        
        
        <?php if ($is_admin): ?>
        <li class="nav-heading">System Management</li>
            <li><a href="<?= $path_prefix ?>reports/index.php" class="<?= isActive('reports/index.php') ?>">Reporting & Analytics</a></li>
            <li><a href="<?= $path_prefix ?>admin/view_users.php" class="<?= isActive('view_users.php') ?>">User Management</a></li>
            <li><a href="<?= $path_prefix ?>admin/pricing.php" class="<?= isActive('pricing.php') ?>">Manage Prices</a></li>
            <?php endif; ?>
        
        <li class="nav-heading">User</li>
        <li><a href="<?= $path_prefix ?>logout.php">Logout &nbsp; </a></li>
    </ul>
</div>


