<?php
session_start(); // Always start the session before any HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShoeVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/db_shoeverse/index.php">ShoeVerse</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <!-- Left side links -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="/db_shoeverse/index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="/db_shoeverse/item/index.php">Products</a></li>
        <li class="nav-item"><a class="nav-link" href="/db_shoeverse/cart/view_cart.php">Cart</a></li>
      </ul>

      <!-- Search (customers) -->
      <form class="d-flex me-3" action="/db_shoeverse/item/index.php" method="GET">
        <input class="form-control me-2" type="search" name="q" placeholder="Search products" aria-label="Search">
        <button class="btn btn-outline-success" type="submit">Search</button>
      </form>

      <!-- Right side user links -->
      <ul class="navbar-nav">
        <?php if (isset($_SESSION['user_id'])): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <?php echo htmlspecialchars($_SESSION['fname']); ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="/db_shoeverse/user/profile.php">Profile</a></li>
              <li><a class="dropdown-item" href="/db_shoeverse/user/logout.php">Logout</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/db_shoeverse/user/login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="/db_shoeverse/user/register.php">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Start main container -->
<div class="container mt-4">
  <?php
  // render flash alerts site-wide (helpers may not be loaded yet in every page)
  include __DIR__ . '/alert.php';
  ?>