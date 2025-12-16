<?php
session_start();

// ADMIN ACCESS CONTROL
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// 1. INCLUDE DATABASE CLASS
require_once "../classes/database.php"; 
require_once "../classes/user.php";

// 2. ESTABLISH CONNECTION
$database = new Database();
$pdo_conn = $database->connect();

// 3. PASS CONNECTION TO USER CLASS
$userObj = new User($pdo_conn); 

// Check for messages
$success_message = "";
if(isset($_GET['message']) && $_GET['message'] == 'User_Deleted'){
    $success_message = "User successfully deleted.";
} elseif(isset($_GET['error']) && $_GET['error'] == 'Cannot_Delete_Own_Account'){
    $success_message = "Error: You cannot delete your own account while logged in.";
}

// Fetch all users
$users = $userObj->fetchAllUsers(); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View System Users</title>
    <style>
        /* --- LAYOUT STYLES --- */
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
        .sidebar {
            width: 250px;
            background-color: #343a40; 
            color: #fff;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        /* --------------------- */

        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #343a40;
            margin-top: 0;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        a.button-link {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        a.button-link:hover {
            background-color: #218838;
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-top: 20px; 
        }
        th, td { 
            border: 1px solid #dee2e6; 
            padding: 12px; 
            text-align: left; 
            vertical-align: middle;
        }
        th { 
            background-color: #f2f2f2; 
            color: #495057;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .success { 
            color: #28a745; 
            font-weight: bold; 
            background-color: #d4edda;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
        }

        /* --- NEW BUTTON STYLES --- */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
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
        
        .btn-edit { 
            background-color: #007bff; /* Blue */
        } 
        .btn-edit:hover { 
            background-color: #0056b3; 
        }
        
        .btn-delete { 
            background-color: #dc3545; /* Red */
        } 
        .btn-delete:hover { 
            background-color: #a71d2a; 
        }
        
        .btn-disabled { 
            background-color: #6c757d; /* Grey */
            cursor: not-allowed; 
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "../includes/sidebar.php"; ?>
        
        <div class="content">
            <div class="container">
                <h1>System User List</h1>

                <?php if (!empty($success_message)): ?>
                    <p class="success"><?= $success_message ?></p>
                <?php endif; ?>

                <p><a href="register_user.php" class="button-link">+ Add New User</a></p>

                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th> 
                            <th>Role</th>
                            <th>Date Created</th>
                            <th style="width: 180px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if (!empty($users)) {
                            foreach($users as $user){
                                // Prevent admin from deleting their own account while logged in
                                $disable_delete = ($user['id'] == $_SESSION['user_id']);
                                $delete_message = $disable_delete 
                                    ? "You cannot delete your own account while logged in." 
                                    : "Are you sure you want to delete the user " . $user["username"] . "?";
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($user["username"]) ?></td>
                                <td><?= htmlspecialchars($user["name"]) ?></td>
                                <td><?= htmlspecialchars($user["email"] ?? 'N/A') ?></td> 
                                <td><span style="font-weight: bold; color: <?= ($user['role'] == 'admin' ? '#dc3545' : '#17a2b8') ?>;"><?= ucfirst($user["role"]) ?></span></td>
                                <td><?= date("Y-m-d", strtotime($user["date_created"])) ?></td>
                                <td>
                                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn-action btn-edit">
                                        ✏️ Edit
                                    </a>
                                    
                                    <a 
                                        href="delete_user.php?id=<?= $user["id"] ?>" 
                                        onclick="return confirm('<?= $delete_message ?>')"
                                        class="btn-action btn-delete <?= $disable_delete ? 'btn-disabled' : '' ?>"
                                    >
                                        🗑️ Delete
                                    </a>
                                </td>
                            </tr>
                            <?php 
                            } 
                        } else {
                        ?>
                            <tr><td colspan="7" style="text-align: center;">No system users found. Please create the first admin account.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>