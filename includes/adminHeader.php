<?php
session_start();
include __DIR__ . '/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['auth_message'] = 'Please login as an administrator to access the admin panel.';
    header('Location: /db_shoeverse/user/login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | ShoeVerse</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" 
        integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" 
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link href="/db_shoeverse/includes/style/style.css" rel="stylesheet" type="text/css">

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</head>
<body>

<nav class="navbar navbar-light bg-light justify-content-between px-3">
    <a class="navbar-brand">Admin Panel</a>
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="GET" class="d-flex">
        <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search" name="search">
        <button class="btn btn-outline-success" type="submit">Search</button>
    </form>
</nav>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mt-2">
    <div class="container-fluid">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
                <a class="nav-link" href="orders.php">Orders</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="products.php">Products</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="categories.php">Categories</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="brands.php">Brands</a>
            </li>
        </ul>
        <span class="navbar-text me-3">
            Welcome, <?php echo htmlspecialchars($_SESSION['fname']); ?>
        </span>
        <a class="btn btn-outline-light" href="../user/logout.php">Logout</a>
    </div>
</nav>

<div class="container mt-4">