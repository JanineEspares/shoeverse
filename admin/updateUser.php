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

    // Use prepared statement for safety
    $stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "si", $role, $user_id);
    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('User role updated successfully!'); window.location.href='users.php';</script>";
    } else {
        echo "<script>alert('Error updating role.'); window.location.href='users.php';</script>";
    }
}

// Handle activate/deactivate actions
if (isset($_POST['action']) && in_array($_POST['action'], ['activate','deactivate'])) {
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];

    // Prevent admin from deactivating themselves
    if ($user_id == $_SESSION['user_id'] && $action === 'deactivate') {
        echo "<script>alert('You cannot deactivate your own account.'); window.location.href='users.php';</script>";
        exit();
    }

    $newVal = ($action === 'activate') ? 1 : 0;
    $stmt = mysqli_prepare($conn, "UPDATE users SET is_active = ? WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $newVal, $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $msg = ($newVal) ? 'User activated successfully!' : 'User deactivated successfully!';
        echo "<script>alert('" . $msg . "'); window.location.href='users.php';</script>";
    } else {
        echo "<script>alert('Error updating user status.'); window.location.href='users.php';</script>";
    }
}
?>