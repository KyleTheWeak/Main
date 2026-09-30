
<?php
session_start();
require 'db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user) {
        $error = "Username already exists!";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $emailExists = $stmt->fetch();

        if ($emailExists) {
            $error = "Email already exists!";
        } else {
            // Insert user
            $stmt = $pdo->prepare(
                "INSERT INTO Users (username, email, password, role)
                 VALUES (:username, :email, :password, :role)"
            );

            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => 'user'
            ]);

            $success = "Registration successful! You can now log in.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
    <head>   <link rel="stylesheet" href="styles.css"></head>

<body>

    <form method="POST">
        <h2>System Registration</h2>

        <?php if ($error): ?>
            <p style="color: red;">
                <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p style="color: green;">
                <?php echo htmlspecialchars($success); ?>
            </p>
        <?php endif; ?>

        <label>Username:</label><br>
        <input type="text" name="username" required><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Register</button>
    </form>

    <p>
        Already have an account?
        <a href="login.php">Log In</a>
    </p>

</body>
</html>

