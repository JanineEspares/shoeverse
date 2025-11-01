<?php
include '../includes/config.php';

if (isset($_POST['register'])) {
    $fname = mysqli_real_escape_string($conn, $_POST['fname']);
    $lname = mysqli_real_escape_string($conn, $_POST['lname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $address_line = mysqli_real_escape_string($conn, $_POST['address_line']);
    $role = 'Customer';

    // Encrypt password before storing
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists
    $check_query = "SELECT * FROM users WHERE email='$email'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo "<script>alert('Email already registered!'); window.location='register.php';</script>";
    } else {
        $insert_query = "INSERT INTO users (fname, lname, email, password, contact_number, address_line, role)
                         VALUES ('$fname', '$lname', '$email', '$hashed_password', '$contact_number', '$address_line', '$role')";
        if (mysqli_query($conn, $insert_query)) {
            echo "<script>alert('Registration successful! You can now login.'); window.location='login.php';</script>";
        } else {
            echo "<script>alert('Error: Could not register user.'); window.location='register.php';</script>";
        }
    }
} else {
    header("Location: register.php");
    exit();
}
?>
