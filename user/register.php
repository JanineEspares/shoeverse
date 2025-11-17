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

  <form id="registerForm" action="store.php" method="POST" enctype="multipart/form-data" novalidate>
          <div class="mb-3">
            <label class="form-label">First Name</label>
            <input type="text" id="fname" name="fname" class="form-control">
            <div id="fnameError" class="text-danger small mt-1" style="display:none;"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" id="lname" name="lname" class="form-control">
            <div id="lnameError" class="text-danger small mt-1" style="display:none;"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="text" id="email" name="email" class="form-control">
            <div id="emailError" class="text-danger small mt-1" style="display:none;"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-control">
            <div id="passwordError" class="text-danger small mt-1" style="display:none;"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" id="password_confirm" name="password_confirm" class="form-control">
            <div id="passwordConfirmError" class="text-danger small mt-1" style="display:none;"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Contact Number</label>
            <input type="text" id="contact_number" name="contact_number" class="form-control">
            <div id="contactError" class="text-danger small mt-1" style="display:none;"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Address</label>
            <input type="text" id="address_line" name="address_line" class="form-control">
            <div id="addressError" class="text-danger small mt-1" style="display:none;"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Profile Photo</label>
            <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
            <div id="photoError" class="text-danger small mt-1" style="display:none;"></div>
          </div>

          <input type="hidden" name="role" value="Customer">

          <div class="d-grid">
            <button type="submit" name="register" class="btn btn-primary">Register</button>
          </div>
        </form>

        <!-- Server-side PHP validation is used instead of JavaScript validation per course rules -->

        <p class="text-center mt-3">Already have an account? <a href="login.php">Login here</a></p>
      </div>
    </div>
  </div>
</div>

</body>
</html>