<?php
session_start();

// 1. If already logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once "classes/database.php";
require_once "classes/user.php";

$db = new Database();
$conn = $db->connect();
$userObj = new User($conn);

// --- 2. AUTO-REDIRECT IF NO USERS EXIST ---
// If the system has 0 users, force redirect to the setup/register page
if ($userObj->countAllUsers() === 0) {
    header("Location: admin/register_user.php");
    exit;
}

$error = "";
$success_msg = "";

// Check for success message from registration redirect
if (isset($_GET['success']) && $_GET['success'] === 'setup_complete') {
    $success_msg = "System initialized successfully! You may now login.";
}

// 3. LOGIN LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_input = trim($_POST['username']); 
    $password = $_POST['password'];

    if ($userObj->login($login_input, $password)) {
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username/email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Login | Laundry Manager</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #e9ecef;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-wrapper {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 25px;
            color: #343a40;
        }
        .brand-logo h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .brand-logo span {
            color: #007bff;
        }

        .login-container {
            background-color: #ffffff;
            padding: 40px 30px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .login-header {
            margin-bottom: 30px;
            text-align: center;
        }
        .login-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #495057;
        }
        .login-header p {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box; 
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        button:hover {
            background-color: #0056b3;
        }

        /* Alert Styles */
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #adb5bd;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        
        <div class="brand-logo">
            <h1>🧺 Laundry<span>Manager</span></h1>
        </div>

        <div class="login-container">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Please enter your credentials to login</p>
            </div>

            <?php if($success_msg): ?>
                <div class="alert alert-success">
                    ✅ <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-error">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username or Email</label> 
                    <input type="text" id="username" name="username" required placeholder="e.g. admin or admin@laundry.com">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                
                <button type="submit">Sign In</button>
            </form>
        </div>

        <div class="footer-text">
            &copy; <?= date('Y') ?> Laundry Shop System. All rights reserved.
        </div>
    </div>

</body>
</html>