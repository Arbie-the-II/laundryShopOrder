<?php
session_start();

require_once "classes/user.php";
$userObj = new User();

// --- If a user is already logged in, redirect them to the dashboard ---
if(isset($_SESSION['user_id'])){
    header("Location: dashboard.php");
    exit;
}

$username = "";
$error_message = "";
$success_message = "";

// Check for success or error messages passed via GET parameters
if (isset($_GET['success'])) {
    $success_message = "Account created successfully! Please log in.";
} elseif (isset($_GET['error']) && $_GET['error'] == 'Access_Denied') {
    $error_message = "Login required to access that page.";
}

// Check if any users exist for initial setup prompt
if ($userObj->countAllUsers() == 0) {
    header("Location: admin/register_user.php");
    exit;
}


if($_SERVER["REQUEST_METHOD"] == "POST"){
    $username = trim(htmlspecialchars($_POST["username"]));
    $password = $_POST["password"];

    if(empty($username) || empty($password)){
        $error_message = "Please enter both username and password.";
    } else {
        $user = $userObj->loginUser($username, $password);

        if($user){
            // Success! Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role']; // 'admin' or 'staff'
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error_message = "Invalid username or password.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Laundry System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #343a40; /* Dark background like the sidebar */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background-color: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            text-align: center;
        }
        h1 {
            color: #007bff; /* Primary blue color */
            margin-bottom: 5px;
            font-size: 2.2em;
        }
        h2 {
            color: #6c757d;
            font-size: 1.1em;
            margin-top: 0;
            margin-bottom: 25px;
        }
        label {
            display: block;
            text-align: left;
            margin-top: 15px;
            font-weight: bold;
            color: #495057;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-top: 5px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            width: 100%;
            background-color: #28a745; /* Green for action */
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 30px;
            transition: background-color 0.3s;
        }
        input[type="submit"]:hover {
            background-color: #218838;
        }
        p.error {
            color: #dc3545; /* Red for errors */
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin-top: 15px;
        }
        p.success {
            color: #155724; /* Dark green for success */
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            border-radius: 4px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Laundry Shop <br> Order System</h1>
        <h2><br>User Login</h2>
        
        <?php if (!empty($error_message)): ?>
            <p class="error"><?= $error_message ?></p>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <p class="success"><?= $success_message ?></p>
        <?php endif; ?>

        <form action="" method="post">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" value="<?= htmlspecialchars($username) ?>" required>

            <label for="password">Password</label>
            <input type="password" name="password" id="password" required>

            <input type="submit" value="Log In">
        </form>
    </div>
</body>
</html>