<?php
session_start();

// 1. INCLUDE DATABASE CLASS
require_once "../classes/database.php"; 
require_once "../classes/user.php";

// 2. ESTABLISH CONNECTION
$database = new Database();
$pdo_conn = $database->connect();

// 3. PASS CONNECTION TO USER CLASS
$userObj = new User($pdo_conn); 

// --- SMART LOGIC: CHECK IF SYSTEM IS EMPTY ---
$total_users_count = $userObj->countAllUsers();
$is_first_run = ($total_users_count === 0);

// --- ACCESS CONTROL ---
if (!$is_first_run) {
    // If users exist, this page is restricted to logged-in Admins only
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../login.php");
        exit;
    }
}

// --- FORM HANDLING ---
$user = [
    "username" => "",
    "name" => "",
    "email" => "", 
    "role" => $is_first_run ? "admin" : "staff", // Default to admin if setup mode
    "password" => "",
    "confirm_password" => ""
];
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user["username"] = trim(htmlspecialchars($_POST["username"]));
    $user["name"] = trim(htmlspecialchars($_POST["name"]));
    $user["email"] = trim(htmlspecialchars($_POST["email"]));
    $user["password"] = $_POST["password"];
    $user["confirm_password"] = $_POST["confirm_password"];
    
    // Force 'admin' role if this is the first user
    if ($is_first_run) {
        $user["role"] = 'admin';
    } else {
        $user["role"] = trim(htmlspecialchars($_POST["role"]));
    }

    // Validation
    if (empty($user["username"])) $errors["username"] = "Username is required.";
    elseif ($userObj->isUsernameExist($user["username"])) $errors["username"] = "Username taken.";

    if (empty($user["name"])) $errors["name"] = "Full name is required.";

    if (empty($user["email"])) $errors["email"] = "Email is required.";
    elseif (!filter_var($user["email"], FILTER_VALIDATE_EMAIL)) $errors["email"] = "Invalid email format.";
    elseif ($userObj->isEmailExist($user["email"])) $errors["email"] = "Email already in use.";
    
    if (empty($user["password"]) || strlen($user["password"]) < 6) $errors["password"] = "Min 6 chars.";
    elseif ($user["password"] !== $user["confirm_password"]) $errors["confirm_password"] = "Passwords do not match.";

    if (empty(array_filter($errors))) {
        $userObj->username = $user["username"];
        $userObj->name = $user["name"];
        $userObj->email = $user["email"];
        $userObj->role = $user["role"];
        $userObj->password = $user["password"];

        if ($userObj->registerUser()) {
            // Redirect based on mode
            if ($is_first_run) {
                // Setup complete: Go to Login with success message
                header("Location: ../login.php?success=setup_complete");
            } else {
                // Normal add: Go back to list
                header("Location: view_users.php?success=created");
            }
            exit;
        } else {
            $errors["general"] = "Database error occurred.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_first_run ? 'System Setup' : 'Register User' ?></title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f4f6f9; 
            margin: 0; 
            padding: 0; 
        }

        /* --- MODE SPECIFIC LAYOUTS --- */
        <?php if ($is_first_run): ?>
            /* SETUP MODE: Centered, Single Box */
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                background-color: #e9ecef; /* Slightly darker for contrast */
            }
            .wrapper { width: 100%; display: flex; justify-content: center; }
            .content { width: 100%; max-width: 500px; padding: 20px; }
            .sidebar { display: none; } /* Hide sidebar in setup mode */
        <?php else: ?>
            /* NORMAL MODE: Dashboard Layout */
            .wrapper { display: flex; min-height: 100vh; }
            .sidebar { width: 250px; background-color: #343a40; color: #fff; padding-top: 20px; flex-shrink: 0; }
            .content { flex-grow: 1; padding: 40px; display: flex; justify-content: center; }
        <?php endif; ?>

        /* Container Styling */
        .container {
            width: 100%;
            max-width: 550px;
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        h1 { margin-top: 0; color: #343a40; font-size: 1.8rem; margin-bottom: 5px; }
        .subtitle { color: #6c757d; margin-bottom: 25px; font-size: 0.95rem; }

        /* Setup Banner */
        .setup-alert {
            background-color: #e3f2fd;
            border: 1px solid #90caf9;
            color: #0d47a1;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
        }
        .setup-alert strong { display: block; font-size: 1.1em; margin-bottom: 5px; }

        /* Form Elements */
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; font-size: 0.9rem; }
        input[type="text"], input[type="password"], input[type="email"], select {
            width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; font-size: 1rem;
        }
        
        .row { display: flex; gap: 15px; }
        .col { flex: 1; }

        /* Buttons */
        .btn-group { display: flex; gap: 10px; margin-top: 30px; }
        
        .btn-submit { 
            flex: 2; background-color: #28a745; color: white; 
            padding: 12px; border: none; border-radius: 6px; 
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; 
        }
        .btn-submit:hover { background-color: #218838; }
        
        .btn-cancel { 
            flex: 1; background-color: #6c757d; color: white; 
            padding: 12px; border: none; border-radius: 6px; 
            font-size: 1rem; font-weight: 600; text-decoration: none; text-align: center; transition: background 0.2s; 
        }
        .btn-cancel:hover { background-color: #5a6268; }

        .error-msg { color: #dc3545; font-size: 0.85em; margin-top: 5px; }
        .error-banner { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <div class="wrapper">
        <?php if (!$is_first_run): ?>
            <?php include "../includes/sidebar.php"; ?>
        <?php endif; ?>

        <div class="content">
            <div class="container">
                
                <?php if ($is_first_run): ?>
                    <div class="setup-alert">
                        <strong>🚀 System Initialization</strong>
                        Welcome! Please create the <b>First Administrator</b> account to set up the system.
                    </div>
                <?php else: ?>
                    <h1>Add New User</h1>
                    <p class="subtitle">Create a new account for a staff member or additional admin.</p>
                <?php endif; ?>

                <?php if (isset($errors["general"])): ?>
                    <div class="error-banner">❌ <?= htmlspecialchars($errors["general"]) ?></div>
                <?php endif; ?>

                <form action="" method="post">
                    
                    <div class="form-group">
                        <label>Full Name <span>*</span></label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user["name"]) ?>" placeholder="e.g. Juan Dela Cruz">
                        <div class="error-msg"><?= $errors["name"] ?? "" ?></div>
                    </div>

                    <div class="row">
                        <div class="col form-group">
                            <label>Username <span>*</span></label>
                            <input type="text" name="username" value="<?= htmlspecialchars($user["username"]) ?>" placeholder="e.g. juan23">
                            <div class="error-msg"><?= $errors["username"] ?? "" ?></div>
                        </div>
                        <div class="col form-group">
                            <label>Role</label>
                            <?php if ($is_first_run): ?>
                                <input type="text" value="Admin" disabled style="background-color: #e9ecef; color: #495057;">
                                <input type="hidden" name="role" value="admin">
                            <?php else: ?>
                                <select name="role">
                                    <option value="staff" <?= ($user["role"] == "staff") ? "selected" : "" ?>>Staff</option>
                                    <option value="admin" <?= ($user["role"] == "admin") ? "selected" : "" ?>>Admin</option>
                                </select>
                            <?php endif; ?>
                            <div class="error-msg"><?= $errors["role"] ?? "" ?></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address <span>*</span></label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user["email"]) ?>" placeholder="e.g. juan@laundryshop.com">
                        <div class="error-msg"><?= $errors["email"] ?? "" ?></div>
                    </div>

                    <div class="row">
                        <div class="col form-group">
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Min. 6 characters">
                            <div class="error-msg"><?= $errors["password"] ?? "" ?></div>
                        </div>
                        <div class="col form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Re-type password">
                            <div class="error-msg"><?= $errors["confirm_password"] ?? "" ?></div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <input type="submit" value="<?= $is_first_run ? 'Complete Setup' : 'Create Account' ?>" class="btn-submit">
                        
                        <a href="<?= $is_first_run ? '../login.php' : 'view_users.php' ?>" class="btn-cancel">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>