<?php
include '../includes/config.php';

if (isset($_POST['register'])) {
    // Basic server-side validation
    $fname = isset($_POST['fname']) ? trim($_POST['fname']) : '';
    $lname = isset($_POST['lname']) ? trim($_POST['lname']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    $contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
    $address_line = isset($_POST['address_line']) ? trim($_POST['address_line']) : '';
    $role = 'Customer';

    if ($fname === '' || $lname === '' || $email === '' || $password === '' || $password_confirm === '' || $contact_number === '' || $address_line === '') {
        echo "<script>alert('Please fill in all required fields.'); window.location='register.php';</script>";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email address.'); window.location='register.php';</script>";
        exit;
    }

    if (strlen($password) < 8) {
        echo "<script>alert('Password must be at least 8 characters.'); window.location='register.php';</script>";
        exit;
    }

    if ($password !== $password_confirm) {
        echo "<script>alert('Passwords do not match.'); window.location='register.php';</script>";
        exit;
    }

    // Optionally validate contact number
    if (!preg_match('/^\+?[0-9\-\s]{7,20}$/', $contact_number)) {
        echo "<script>alert('Invalid contact number.'); window.location='register.php';</script>";
        exit;
    }

    // Handle profile photo upload (optional)
    $photo = '';
    if (isset($_FILES['photo']) && isset($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/images';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $tmpName = $_FILES['photo']['tmp_name'];
        $origName = basename($_FILES['photo']['name']);
        $ext = pathinfo($origName, PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','gif'];
        if (in_array(strtolower($ext), $allowed)) {
            $newName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = $uploadDir . '/' . $newName;
            if (move_uploaded_file($tmpName, $dest)) {
                $photo = $newName;
            }
        }
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists using prepared statement
    $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
    if (!$stmt) {
        echo "<script>alert('Database error.'); window.location='register.php';</script>";
        exit;
    }
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        echo "<script>alert('Email already registered!'); window.location='register.php';</script>";
        exit;
    }
    mysqli_stmt_close($stmt);

    // Detect if photo column exists
    $photoColumnExists = false;
    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'photo'");
    if ($colCheck && mysqli_num_rows($colCheck) > 0) { $photoColumnExists = true; }

    // Prepare insert with or without photo column
    if ($photoColumnExists && $photo !== '') {
        $insert_sql = "INSERT INTO users (fname, lname, email, password, contact_number, address_line, role, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssssss', $fname, $lname, $email, $hashed_password, $contact_number, $address_line, $role, $photo);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else { $ok = false; }
    } else {
        $insert_sql = "INSERT INTO users (fname, lname, email, password, contact_number, address_line, role) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sssssss', $fname, $lname, $email, $hashed_password, $contact_number, $address_line, $role);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else { $ok = false; }
    }

    if ($ok) {
        echo "<script>alert('Registration successful! You can now login.'); window.location='login.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error: Could not register user.'); window.location='register.php';</script>";
        exit;
    }
} else {
    header("Location: register.php");
    exit();
}
?>