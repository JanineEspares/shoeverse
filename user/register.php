<?php include '../includes/config.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShoeVerse | Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow-lg p-4">
        <h3 class="text-center mb-4">Create an Account</h3>

        <form action="store.php" method="POST">
          <div class="mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="fname" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="lname" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Contact Number</label>
            <input type="text" name="contact_number" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Address</label>
            <input type="text" name="address_line" class="form-control" required>
          </div>

          <input type="hidden" name="role" value="Customer">

          <div class="d-grid">
            <button type="submit" name="register" class="btn btn-primary">Register</button>
          </div>
        </form>

        <p class="text-center mt-3">Already have an account? <a href="login.php">Login here</a></p>
      </div>
    </div>
  </div>
</div>

</body>
</html>
