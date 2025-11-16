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

        <script>
        // Client-side validation (no HTML5 validation)
        (function(){
            function show(el, msg){ el.style.display='block'; el.textContent = msg; }
            function hide(el){ el.style.display='none'; el.textContent=''; }

            var form = document.getElementById('registerForm');
            var fname = document.getElementById('fname');
            var lname = document.getElementById('lname');
            var email = document.getElementById('email');
            var pwd = document.getElementById('password');
            var pwdc = document.getElementById('password_confirm');
            var contact = document.getElementById('contact_number');
            var address = document.getElementById('address_line');
            var photo = document.getElementById('photo');

            var fnameErr = document.getElementById('fnameError');
            var lnameErr = document.getElementById('lnameError');
            var emailErr = document.getElementById('emailError');
            var pwdErr = document.getElementById('passwordError');
            var pwdcErr = document.getElementById('passwordConfirmError');
            var contactErr = document.getElementById('contactError');
            var addressErr = document.getElementById('addressError');
            var photoErr = document.getElementById('photoError');

            function validateEmail(v){ return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v); }
            function validatePhone(v){ return /^\+?[0-9\-\s]{7,20}$/.test(v); }

            form.addEventListener('submit', function(e){
                var valid = true;
                // First/Last name
                if (fname.value.trim() === ''){ show(fnameErr, 'First name is required'); valid = false; } else { hide(fnameErr); }
                if (lname.value.trim() === ''){ show(lnameErr, 'Last name is required'); valid = false; } else { hide(lnameErr); }

                // Email
                var em = email.value.trim();
                if (em === ''){ show(emailErr, 'Email is required'); valid = false; }
                else if (!validateEmail(em)){ show(emailErr, 'Enter a valid email address'); valid = false; }
                else { hide(emailErr); }

                // Password rules
                if (pwd.value.length < 8){ show(pwdErr, 'Password must be at least 8 characters'); valid = false; } else { hide(pwdErr); }
                if (pwdc.value !== pwd.value){ show(pwdcErr, 'Passwords do not match'); valid = false; } else { hide(pwdcErr); }

                // Contact and address
                if (contact.value.trim() === ''){ show(contactErr, 'Contact number is required'); valid = false; }
                else if (!validatePhone(contact.value.trim())){ show(contactErr, 'Enter a valid contact number'); valid = false; }
                else { hide(contactErr); }

                if (address.value.trim() === ''){ show(addressErr, 'Address is required'); valid = false; } else { hide(addressErr); }

                // Photo (optional) - check type/size if provided
                if (photo.files && photo.files.length > 0) {
                    var f = photo.files[0];
                    var allowed = ['image/jpeg','image/png','image/gif'];
                    if (allowed.indexOf(f.type) === -1) { show(photoErr, 'Only JPG/PNG/GIF files allowed'); valid = false; }
                    else if (f.size > 2 * 1024 * 1024) { show(photoErr, 'Photo must be smaller than 2MB'); valid = false; }
                    else { hide(photoErr); }
                } else { hide(photoErr); }

                if (!valid) e.preventDefault();
            });
        })();
        </script>

        <p class="text-center mt-3">Already have an account? <a href="login.php">Login here</a></p>
      </div>
    </div>
  </div>
</div>

</body>
</html>