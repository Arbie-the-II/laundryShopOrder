<?php
session_start();

require_once "../classes/user.php";
$userObj = new User();

// --- ACCESS CONTROL FOR FIRST-TIME SETUP ---
$total_users_count = $userObj->countAllUsers();

if ($total_users_count > 0) {
    // Users exist: Require logged-in 'admin' role
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../login.php");
        exit;
    }
}

$user = [
    "username" => "",
    "name" => "",
    "role" => ($total_users_count > 0) ? "staff" : "admin",
    "password" => "",
    "confirm_password" => ""
];
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize and Collect Data
    $user["username"] = trim(htmlspecialchars($_POST["username"]));
    $user["name"] = trim(htmlspecialchars($_POST["name"]));
    $user["password"] = $_POST["password"];
    $user["confirm_password"] = $_POST["confirm_password"];
    
    if ($total_users_count === 0) {
        $user["role"] = 'admin';
    } else {
        $user["role"] = trim(htmlspecialchars($_POST["role"]));
    }

    // 2. Validation Checks
    if (empty($user["username"])) {
        $errors["username"] = "Username is required.";
    } elseif ($userObj->isUserExist($user["username"])) {
        $errors["username"] = "Username already exists.";
    }

    if (empty($user["name"])) {
        $errors["name"] = "Full name is required.";
    }
    
    if (empty($user["role"]) || !in_array($user["role"], ['staff', 'admin'])) {
        $errors["role"] = "Invalid role selected.";
    }

    if (empty($user["password"]) || strlen($user["password"]) < 6) {
        $errors["password"] = "Password must be at least 6 characters.";
    } elseif ($user["password"] !== $user["confirm_password"]) {
        $errors["confirm_password"] = "Passwords do not match.";
    }

    // 3. Execution (Registration)
    if (empty(array_filter($errors))) {
        $userObj->username = $user["username"];
        $userObj->name = $user["name"];
        $userObj->role = $user["role"];
        $userObj->password = $user["password"];

        if ($userObj->registerUser()) {
            // Redirect to login page after creating the first account, or dashboard otherwise
            $redirect_page = ($total_users_count === 0) ? "../login.php?success=Account_Created_Successfully" : "../dashboard.php?success=User_Created_Successfully";
            header("Location: " . $redirect_page);
            exit;
        } else {
            $errors["general"] = "Error creating user account in the database.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New System User</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 500px;
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
        }
        h2 {
            color: #dc3545;
            text-align: center;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: #495057;
        }
        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            background-color: #28a745;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        input[type="submit"]:hover {
            background-color: #218838;
        }
        a {
            color: #007bff;
            text-decoration: none;
            margin-left: 15px;
        }
        a:hover {
            text-decoration: underline;
        }
        span {
            color: red;
        }
        p.error {
            color: red;
            margin: 5px 0 0 0;
            font-size: 0.9em;
        }
        fieldset {
            margin-top: 25px;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        legend {
            font-weight: bold;
            color: #007bff;
            padding: 0 10px;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($total_users_count === 0): ?>
            <h2 style="color: #007bff;">&#9888; Initial System Setup</h2>
            <p style="text-align: center; font-weight: bold;">Create the first **Admin** account to proceed.</p>
        <?php else: ?>
            <h1>Register New System User</h1>
            <p>Admin: **<?= htmlspecialchars($_SESSION['name']) ?>**</p>
        <?php endif; ?>

        <label>Fields with <span>*</span> are required</label>
        <p class="error"><?= $errors["general"] ?? "" ?></p>

        <form action="" method="post">
            <fieldset>
                <legend>User Details</legend>
                <label for="name">Full Name <span>*</span></label>
                <input type="text" name="name" id="name" value="<?= htmlspecialchars($user["name"]) ?>" required>
                <p class="error"><?= $errors["name"] ?? "" ?></p>

                <label for="username">Username <span>*</span></label>
                <input type="text" name="username" id="username" value="<?= htmlspecialchars($user["username"]) ?>" required>
                <p class="error"><?= $errors["username"] ?? "" ?></p>

                <label for="role">User Role <span>*</span></label>
                <?php if ($total_users_count === 0): ?>
                    <input type="text" value="Admin" disabled style="font-weight: bold; background-color: #e9ecef;">
                    <input type="hidden" name="role" value="admin">
                <?php else: ?>
                    <select name="role" id="role">
                        <option value="staff" <?= ($user["role"] == "staff") ? "selected" : "" ?>>Staff</option>
                        <option value="admin" <?= ($user["role"] == "admin") ? "selected" : "" ?>>Admin</option>
                    </select>
                <?php endif; ?>
                <p class="error"><?= $errors["role"] ?? "" ?></p>
            </fieldset>
            
            <fieldset>
                <legend>Security</legend>
                <label for="password">Password (min 6 chars) <span>*</span></label>
                <input type="password" name="password" id="password" required>
                <p class="error"><?= $errors["password"] ?? "" ?></p>

                <label for="confirm_password">Confirm Password <span>*</span></label>
                <input type="password" name="confirm_password" id="confirm_password" required>
                <p class="error"><?= $errors["confirm_password"] ?? "" ?></p>
            </fieldset>

            <input type="submit" value="Create Account">
            <?php if ($total_users_count > 0): ?>
                <a href="../dashboard.php">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>