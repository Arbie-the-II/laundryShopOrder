<?php
session_start();

//ADMIN ACCESS CONTROL
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once "../classes/user.php";
$userObj = new User();

// Check for messages
$success_message = "";
if(isset($_GET['message']) && $_GET['message'] == 'User_Deleted'){
    $success_message = "User successfully deleted.";
} elseif(isset($_GET['error']) && $_GET['error'] == 'Cannot_Delete_Own_Account'){
    $success_message = "Error: You cannot delete your own account while logged in.";
}

// Fetch all users
$users = $userObj->viewUsers();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View System Users</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
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
        .action-link {
            color: #007bff;
            margin-right: 10px;
            text-decoration: none;
        }
        .delete-link {
            color: #dc3545;
            text-decoration: none;
        }
        .disabled-delete {
            color: #6c757d !important;
            pointer-events: none;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>System User List <a href="../dashboard.php" style="font-size: 0.7em; color: #007bff;">← Back to Dashboard</a></h1>

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
                    <th>Role</th>
                    <th>Date Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
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
                    <td><span style="font-weight: bold; color: <?= ($user['role'] == 'admin' ? '#dc3545' : '#17a2b8') ?>;"><?= ucfirst($user["role"]) ?></span></td>
                    <td><?= date("Y-m-d", strtotime($user["date_created"])) ?></td>
                    <td>
                        <a 
                            href="delete_user.php?id=<?= $user["id"] ?>" 
                            onclick="return confirm('<?= $delete_message ?>')"
                            class="delete-link <?= $disable_delete ? 'disabled-delete' : '' ?>"
                        >
                            Delete
                        </a>
                    </td>
                </tr>
                <?php } ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" style="text-align: center;">No system users found. Please create the first admin account.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>