<?php
include '../includes/config.php';
session_start();

// ✅ Only Admins can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../user/login.php");
    exit();
}

if (isset($_POST['update_role'])) {
    $user_id = intval($_POST['user_id']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    // Prevent admin from demoting themselves
    if ($user_id == $_SESSION['user_id'] && $role != 'Admin') {
        echo "<script>alert('You cannot change your own role.'); window.location.href='users.php';</script>";
        exit();
    }

    $update_query = mysqli_query($conn, "UPDATE users SET role = '$role' WHERE user_id = $user_id");

    if ($update_query) {
        echo "<script>alert('User role updated successfully!'); window.location.href='users.php';</script>";
    } else {
        echo "<script>alert('Error updating role.'); window.location.href='users.php';</script>";
    }
}
?>
