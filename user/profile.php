<?php
// Show all errors for learning
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../includes/config.php';
include __DIR__ . '/../includes/header.php';

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /shoeverse/user/login.php");
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) == 1) {
    $user = mysqli_fetch_assoc($result);
} else {
    echo "<p class='text-danger'>User not found.</p>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Handle form submission
if (isset($_POST['update'])) {
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
    $address_line = $_POST['address_line'];

    $updateQuery = "UPDATE users SET 
                        fname='$fname',
                        lname='$lname',
                        email='$email',
                        contact_number='$contact_number',
                        address_line='$address_line'
                    WHERE user_id=$user_id";

    if (mysqli_query($conn, $updateQuery)) {
        $_SESSION['fname'] = $fname; // update session name
        echo "<div class='alert alert-success'>Profile updated successfully.</div>";
        // Update $user array so form shows latest values
        $user['fname'] = $fname;
        $user['lname'] = $lname;
        $user['email'] = $email;
        $user['contact_number'] = $contact_number;
        $user['address_line'] = $address_line;
    } else {
        echo "<div class='alert alert-danger'>Failed to update profile.</div>";
    }
}
?>

<h2 class="text-center mb-4">My Profile</h2>

<div class="row justify-content-center">
    <div class="col-md-6">
        <form method="POST">
            <div class="mb-3">
                <label>First Name</label>
                <input type="text" name="fname" class="form-control" value="<?php echo $user['fname']; ?>" required>
            </div>
            <div class="mb-3">
                <label>Last Name</label>
                <input type="text" name="lname" class="form-control" value="<?php echo $user['lname']; ?>" required>
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required>
            </div>
            <div class="mb-3">
                <label>Contact Number</label>
                <input type="text" name="contact_number" class="form-control" value="<?php echo $user['contact_number']; ?>" required>
            </div>
            <div class="mb-3">
                <label>Address</label>
                <textarea name="address_line" class="form-control" rows="2" required><?php echo $user['address_line']; ?></textarea>
            </div>
            <button type="submit" name="update" class="btn btn-primary w-100">Update Profile</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
