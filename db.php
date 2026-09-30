<?php
$host = 'localhost';
$db   = 'toDoDB'; // kun ano ginpangalan nyo sa database nyo!
$user = 'root'; // XAMPP default
$pass = '';     // XAMPP default is blank

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   // echo "Database connection is good! Connected successfully to " . $db;
} catch (PDOException $e) {
    die("Database connection failed.");
}
?>