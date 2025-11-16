<?php
session_start();
include '../includes/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShoeVerse | Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow-lg p-4">
        <h3 class="text-center mb-4">Welcome Back!</h3>

        <form id="loginForm" action="" method="POST" novalidate>
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

            <div class="d-grid">
              <button type="submit" name="login" class="btn btn-primary">Login</button>
            </div>
            <div class="mt-2 text-end">
              <a href="change_password.php">Forgot Password?</a>
            </div>
          </form>

        <script>
        // Client-side validation (no HTML5 validation)
        (function(){
            function show(el, msg){ el.style.display='block'; el.textContent = msg; }
            function hide(el){ el.style.display='none'; el.textContent=''; }

            var form = document.getElementById('loginForm');
            var emailIn = document.getElementById('email');
            var pwdIn = document.getElementById('password');
            var emailErr = document.getElementById('emailError');
            var pwdErr = document.getElementById('passwordError');

            function validateEmail(v){ return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v); }

            form.addEventListener('submit', function(e){
                var valid = true;
                var em = emailIn.value.trim();
                var pw = pwdIn.value;
                if (em === ''){ show(emailErr, 'Email is required'); valid = false; } else if (!validateEmail(em)){ show(emailErr, 'Enter a valid email address'); valid = false; } else { hide(emailErr); }
                if (pw === ''){ show(pwdErr, 'Password is required'); valid = false; } else { hide(pwdErr); }
                if (!valid) e.preventDefault();
            });
        })();
        </script>

          <p class="text-center mt-3">
            Don’t have an account? <a href="register.php">Register here</a>
          </p>
      </div>
    </div>
  </div>
</div>

<?php
// Handle form submission
if (isset($_POST['login'])) {
  // Basic server-side validation (mirrors client-side rules)
  if (empty($_POST['email']) || empty($_POST['password'])) {
    echo "<script>alert('Please provide both email and password.');</script>";
  } else {
    $email_raw = trim($_POST['email']);
    $password_raw = $_POST['password'];

    if (!filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
      echo "<script>alert('Invalid email format.');</script>";
    } else {
      $email = mysqli_real_escape_string($conn, $email_raw);
      $password = mysqli_real_escape_string($conn, $password_raw);

      $query = "SELECT * FROM users WHERE email='$email'";
      $result = mysqli_query($conn, $query);

      if ($result && mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
          // Check active status if column exists
          if (isset($user['is_active']) && !$user['is_active']) {
            echo "<script>alert('Account deactivated. Contact administrator.');</script>";
          } else {
            // Create session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] == 'Admin') {
              echo "<script>alert('Welcome Admin!'); window.location='../admin/orders.php';</script>";
            } else {
              echo "<script>alert('Login successful!'); window.location='../index.php';</script>";
            }
          }
        } else {
          echo "<script>alert('Incorrect password!');</script>";
        }
      } else {
        echo "<script>alert('Email not found!');</script>";
      }
    }
  }
}
?>

</body>
</html>