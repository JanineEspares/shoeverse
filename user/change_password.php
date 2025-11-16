<?php
session_start();
include '../includes/config.php';

// If coming from profile, user is logged in
// If coming from login, user may not be logged in

// Get user id from session if available
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// If not logged in, ask for email
if (!$user_id && isset($_POST['email'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $query = "SELECT user_id FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $user_id = $row['user_id'];
        $_SESSION['reset_user_id'] = $user_id;
    } else {
        $error = "Email not found.";
    }
}
if (!$user_id && isset($_SESSION['reset_user_id'])) {
    $user_id = $_SESSION['reset_user_id'];
}

// Handle password change
if ($user_id && isset($_POST['change'])) {
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    if ($new === $confirm && strlen($new) >= 6) {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $update = "UPDATE users SET password='$hashed' WHERE user_id=$user_id";
        if (mysqli_query($conn, $update)) {
            echo "<script>alert('Password changed successfully!'); window.location='login.php';</script>";
            unset($_SESSION['reset_user_id']);
            exit;
        } else {
            $error = "Failed to change password.";
        }
    } else {
        $error = "Passwords do not match or too short.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg p-4">
                <h3 class="text-center mb-4">Change Password</h3>
                <?php if (isset($error)) echo '<div class="alert alert-danger">'.$error.'</div>'; ?>
                <form method="POST">
                    <?php if (!$user_id) { ?>
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    <?php } ?>
                    <div class="mb-3">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="change" class="btn btn-success">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
