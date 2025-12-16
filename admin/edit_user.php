<?php
session_start();

// 1. ACCESS CONTROL
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once "../classes/database.php";
require_once "../classes/user.php";

$db = new Database();
$pdo = $db->connect();
$userObj = new User($pdo);

$user = [];
$errors = [];
$success = "";

// 2. FETCH USER DATA
if (isset($_GET['id'])) {
    $user_id = trim($_GET['id']);
    
    // This calls the function you just fixed in Step 1
    $user = $userObj->fetchUser($user_id);

    if (!$user) {
        header("Location: view_users.php?error=notfound");
        exit;
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "GET") {
    header("Location: view_users.php");
    exit;
}

// 3. HANDLE UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];

    // Validation
    if (empty($name)) $errors['name'] = "Full name is required";
    if (empty($username)) $errors['username'] = "Username is required";
    elseif ($userObj->isUsernameExist($username, $id)) $errors['username'] = "Username taken";
    
    if (empty($email)) $errors['email'] = "Email is required";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "Invalid email";
    elseif ($userObj->isEmailExist($email, $id)) $errors['email'] = "Email taken";

    if (empty($errors)) {
        $userObj->name = $name;
        $userObj->username = $username;
        $userObj->email = $email;
        $userObj->role = $role;
        
        if ($userObj->updateUser($id)) {
            $success = "User updated successfully!";
            $user = $userObj->fetchUser($id); // Refresh data
        } else {
            $errors['general'] = "Database update failed.";
        }
    } else {
        // Persist data on error
        $user = ['id' => $id, 'name' => $name, 'username' => $username, 'email' => $email, 'role' => $role];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit System User</title>
    <style>
        /* Focused Layout - No Sidebar */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f4f6f9; 
            margin: 0; 
            padding: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
        }
        
        .container {
            width: 100%; 
            max-width: 550px;
            background-color: #fff; 
            padding: 40px;
            border-radius: 12px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        /* Header Row with Back Button */
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        h1 { 
            color: #343a40; 
            margin: 0; 
            font-size: 1.5rem; 
        }
        
        .btn-back { 
            background-color: #6c757d; 
            color: white; 
            padding: 8px 16px; 
            text-decoration: none; 
            border-radius: 6px; 
            font-size: 0.9rem; 
            font-weight: 600;
            transition: background 0.2s; 
        }
        .btn-back:hover { background-color: #5a6268; }
        
        /* Form Styles */
        label { display: block; margin-top: 15px; font-weight: 600; color: #495057; font-size: 0.95rem; }
        input[type="text"], input[type="email"], select {
            width: 100%; padding: 12px; margin-top: 5px;
            border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; font-size: 1rem;
        }
        
        /* Save Button */
        .btn-save {
            background-color: #28a745; color: white;
            padding: 12px; width: 100%; margin-top: 25px;
            border: none; border-radius: 6px; cursor: pointer;
            font-size: 1rem; font-weight: 600; transition: background 0.2s;
        }
        .btn-save:hover { background-color: #218838; }
        
        /* Alerts */
        .error-msg { color: #dc3545; font-size: 0.85em; margin-top: 4px; }
        .success-box {
            background-color: #d4edda; color: #155724;
            padding: 12px; border-radius: 6px; margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .error-box {
            background-color: #f8d7da; color: #721c24;
            padding: 12px; border-radius: 6px; margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

    <div class="container">
        
        <div class="header-row">
            <h1>Edit User Details</h1>
            <a href="view_users.php" class="btn-back">← Back to List</a>
        </div>
        
        <?php if ($success): ?>
            <div class="success-box">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($errors['general'])): ?>
            <div class="error-box">❌ <?= htmlspecialchars($errors['general']) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']) ?>">
            
            <label>Full Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
            <div class="error-msg"><?= $errors['name'] ?? '' ?></div>

            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
            <div class="error-msg"><?= $errors['username'] ?? '' ?></div>

            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            <div class="error-msg"><?= $errors['email'] ?? '' ?></div>

            <label>Role</label>
            <?php if ($_SESSION['user_id'] == $user['id']): ?>
                <input type="hidden" name="role" value="<?= $user['role'] ?>">
                <input type="text" value="<?= ucfirst($user['role']) ?> (You cannot change your own role)" disabled style="background-color:#e9ecef; color:#6c757d;">
            <?php else: ?>
                <select name="role">
                    <option value="staff" <?= (isset($user['role']) && $user['role'] == 'staff') ? 'selected' : '' ?>>Staff</option>
                    <option value="admin" <?= (isset($user['role']) && $user['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                </select>
            <?php endif; ?>

            <button type="submit" class="btn-save">💾 Save Changes</button>
        </form>
    </div>

</body>
</html>