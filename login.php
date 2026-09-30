<?php
session_start(); // Must be the very first line!
require 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // 1. Fetch user safely using PDO
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    // 2. Verify plain-text password directly
    // CHANGED: Removed password_verify() and replaced with raw string comparison (===)
    if ($user && $password === $user['password']) {
        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id']; // Essential for your user tasks workspace!
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; 

        // 3. The Traffic Cop (Redirection)
        if ($user['role'] === 'admin') {
            header("Location: admin.php");
            exit();
        } else {
            header("Location: user.php"); 
            exit();
        }
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
   <div class="login-page">

    <div class="login-image">
        <div class="image-overlay"></div>

        <div class="system-title">
            RENE
            <span>OS</span>
        </div>
    </div>

    <div class="login-panel">

        <div class="login-logo">
            RENE
        </div>

        <p class="system-status">
            ● SYSTEM ONLINE
        </p>

        <h1>Welcome Back</h1>

        <p class="login-description">
            Initialize your workspace.
        </p>

        <form method="POST">

            <label>Username</label>
            <input
                type="text"
                name="username"
                required
            >

            <label>Password</label>
            <input
                type="password"
                name="password"
                required
            >

            <button
                type="submit"
                class="btn btn-primary"
            >
                INITIALIZE SYSTEM
            </button>

        </form>

        <p class="register-link">
            Don't have an account?
            <a href="register.php">Create Account</a>
        </p>

    </div>
    

</div>
</body>
</html>
