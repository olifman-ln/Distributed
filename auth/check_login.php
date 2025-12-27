<?php
session_start();
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: login.php");
        exit();
    }

    // Function to check login in a table
    function checkLogin($conn, $table, $username, $email, $password)
    {
        $stmt = $conn->prepare("SELECT * FROM $table WHERE username = ? AND email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                return $user;
            }
        }
        return false;
    }

    // Check admin
    $user = checkLogin($conn, 'admin', $username, $email, $password);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = 'admin';
        header("Location: ../index.php");
        exit();
    }

    // Check owners
    $user = checkLogin($conn, 'owners', $username, $email, $password);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = 'owner';
        header("Location: ../owners/dashboard.php");
        exit();
    }

    // Invalid login
    $_SESSION['error'] = "Invalid username, email, or password.";
    header("Location: login.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}
