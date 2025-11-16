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

// Completed state is shown automatically — no manual Complete handler needed

// Handle delete review from profile page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    $review_id = intval($_POST['review_id']);
    $user_id = $_SESSION['user_id'];
    if ($review_id > 0) {
        // ensure review belongs to user
        $chk = mysqli_prepare($conn, "SELECT review_id FROM reviews WHERE review_id = ? AND user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($chk, 'ii', $review_id, $user_id);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) > 0) {
            mysqli_stmt_close($chk);
            $del = mysqli_prepare($conn, "DELETE FROM reviews WHERE review_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($del, 'ii', $review_id, $user_id);
            if (mysqli_stmt_execute($del)) {
                mysqli_stmt_close($del);
                header('Location: /db_shoeverse/user/profile.php'); exit();
            } else {
                mysqli_stmt_close($del);
            }
        } else {
            mysqli_stmt_close($chk);
        }
    }
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

    // Handle profile photo upload (optional)
    $uploadedPhoto = '';
    if (isset($_FILES['photo']) && isset($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/images';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $tmpName = $_FILES['photo']['tmp_name'];
        $origName = basename($_FILES['photo']['name']);
        $ext = pathinfo($origName, PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','gif'];
        if (in_array(strtolower($ext), $allowed)) {
            $newName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = $uploadDir . '/' . $newName;
            if (move_uploaded_file($tmpName, $dest)) {
                $uploadedPhoto = $newName;
                // Optionally remove old photo file if exists
                if (!empty($user['photo'])) {
                    $oldPath = __DIR__ . '/images/' . $user['photo'];
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
            }
        }
    }

    // Build update query; include photo only if column exists and a new photo was uploaded
    $photoColumnExists = false;
    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'photo'");
    if ($colCheck && mysqli_num_rows($colCheck) > 0) { $photoColumnExists = true; }

    $updateQuery = "UPDATE users SET 
                        fname='$fname',
                        lname='$lname',
                        email='$email',
                        contact_number='$contact_number',
                        address_line='$address_line'";

    if ($photoColumnExists && $uploadedPhoto !== '') {
        $updateQuery .= ", photo='$uploadedPhoto'";
        // update $user array so the latest photo displays below
        $user['photo'] = $uploadedPhoto;
    }

    $updateQuery .= " WHERE user_id=$user_id";

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

<div class="container-xl px-4 mt-4">
    <?php include(__DIR__ . '/../includes/alert.php'); ?>
    <nav class="nav nav-borders mb-3">
        <a class="nav-link active ms-0" href="#">Profile</a>
    </nav>
    <div class="row">
        <div class="col-xl-4 d-flex">
            <!-- Profile picture card-->
            <div class="card mb-4 mb-xl-0 h-100 w-100">
                <div class="card-header">Profile Picture</div>
                <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                    <?php $photoSrc = !empty($user['photo']) ? '/db_shoeverse/user/images/' . htmlspecialchars($user['photo']) : 'http://bootdey.com/img/Content/avatar/avatar1.png'; ?>
                    <!-- Profile picture image-->
                    <img id="profilePreview" class="img-account-profile rounded-circle mb-2" src="<?php echo $photoSrc; ?>" alt="" style="width:180px;height:180px;object-fit:cover;">
                    <!-- Profile picture help block-->
                    <div class="small font-italic text-muted mb-4">JPG or PNG no larger than 5 MB</div>
                    <!-- Profile picture upload button-->
                    <div class="d-flex justify-content-center">
                        <button class="btn btn-primary me-2" type="button" onclick="document.getElementById('profilePhotoInput').click();">Upload new image</button>
                        <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('profilePreview').src='<?php echo $photoSrc; ?>'; document.getElementById('profilePhotoInput').value='';">Reset</button>
                    </div>
                    <input id="profilePhotoInput" type="file" name="photo" accept="image/*" style="display:none;" onchange="previewProfilePhoto(this);">
                </div>
            </div>
        </div>
        <div class="col-xl-8 d-flex">
            <!-- Account details card-->
            <div class="card mb-4 h-100 w-100">
                <div class="card-header">Account Details</div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1" for="inputFirstName">First name</label>
                                <input class="form-control" id="inputFirstName" type="text" placeholder="Enter your first name" name="fname" value="<?php echo htmlspecialchars($user['fname']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1" for="inputLastName">Last name</label>
                                <input class="form-control" id="inputLastName" type="text" placeholder="Enter your last name" name="lname" value="<?php echo htmlspecialchars($user['lname']); ?>">
                            </div>
                        </div>

                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1" for="address">Address</label>
                                <input class="form-control" id="address" type="text" placeholder="Enter your address" name="address_line" value="<?php echo htmlspecialchars($user['address_line']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small mb-1" for="town">Phone</label>
                                <input class="form-control" id="town" type="text" placeholder="Enter your phone" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>">
                            </div>
                        </div>

                        <div class="row gx-3 mb-3">
                            <div class="col-md-6">
                                <label class="small mb-1" for="inputEmail">Email</label>
                                <input class="form-control" id="inputEmail" type="email" placeholder="Enter your email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                            </div>
                            <div class="col-md-6">
                                <!-- title removed as requested -->
                            </div>
                        </div>

                        <button class="btn btn-primary" type="submit" name="update">Save changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Edit review modal markup is injected below via HTML; attach handlers after DOM ready
document.addEventListener('DOMContentLoaded', function(){
        // attach edit buttons
        document.querySelectorAll('.btn-edit-review').forEach(function(btn){
                btn.addEventListener('click', function(e){
                        var reviewId = this.getAttribute('data-review-id');
                        var productId = this.getAttribute('data-product-id');
                        var rating = this.getAttribute('data-rating') || '5';
                        var reviewText = this.getAttribute('data-review-text') || '';

                        // populate modal fields
                        document.getElementById('edit_review_id').value = reviewId;
                        document.getElementById('edit_product_id').value = productId;
                        document.getElementById('edit_rating').value = rating;
                        document.getElementById('edit_review_text').value = reviewText;

                        // show modal (Bootstrap 5)
                        var modalEl = document.getElementById('editReviewModal');
                        if (modalEl) {
                                var modal = new bootstrap.Modal(modalEl);
                                modal.show();
                        }
                });
        });
});

// Modal HTML appended to body for editing reviews
(function(){
        var modalHtml = `
        <div class="modal fade" id="editReviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Review</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="/db_shoeverse/user/submit_review.php">
                    <div class="modal-body">
                            <input type="hidden" id="edit_review_id" name="review_id" value="">
                            <input type="hidden" id="edit_product_id" name="product_id" value="">
                            <div class="mb-3">
                                <label class="form-label">Rating</label>
                                <select id="edit_rating" name="rating" class="form-select">
                                    <option value="5">5</option>
                                    <option value="4">4</option>
                                    <option value="3">3</option>
                                    <option value="2">2</option>
                                    <option value="1">1</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Review</label>
                                <textarea id="edit_review_text" name="review_text" class="form-control" rows="4"></textarea>
                            </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="update_review" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                    </form>
                </div>
            </div>
        </div>`;

        var wrapper = document.createElement('div');
        wrapper.innerHTML = modalHtml;
        document.body.appendChild(wrapper);
})();

function previewProfilePhoto(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<!-- Orders by status: To Ship / To Receive / To Rate -->
<div class="row justify-content-center mt-5">
    <div class="col-md-10">
        <h3 class="mb-3">My Orders</h3>
        <div class="mb-3">
            <button id="btnToShip" class="btn btn-outline-primary me-2">To Ship</button>
            <button id="btnToReceive" class="btn btn-outline-primary me-2">To Receive</button>
            <button id="btnToRate" class="btn btn-outline-primary me-2">To Rate</button>
            <button id="btnCompleted" class="btn btn-outline-primary">Completed</button>
        </div>

        <div id="sectionToShip" class="order-section">
            <h5>To Ship (Awaiting seller to ship)</h5>
            <?php
            $q = "SELECT o.order_id, o.order_date, ol.product_id, ol.quantity, p.product_name, p.image
                  FROM orders o
                  JOIN orderline ol ON o.order_id = ol.order_id
                  JOIN products p ON ol.product_id = p.product_id
                  WHERE o.user_id = $user_id AND o.status = 'Pending'
                  ORDER BY o.order_date DESC";
            $res = mysqli_query($conn, $q);
            if ($res && mysqli_num_rows($res) > 0): ?>
                <div class="list-group">
                <?php while ($r = mysqli_fetch_assoc($res)): ?>
                    <div class="list-group-item d-flex align-items-center">
                        <img src="/db_shoeverse/item/images/<?php echo htmlspecialchars($r['image']); ?>" style="width:80px;height:60px;object-fit:cover;margin-right:12px;">
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?php echo htmlspecialchars($r['product_name']); ?></div>
                            <div>Quantity: <?php echo (int)$r['quantity']; ?></div>
                            <div class="text-muted">Order: #<?php echo (int)$r['order_id']; ?> | <?php echo htmlspecialchars($r['order_date']); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No orders in this status.</p>
            <?php endif; ?>
        </div>

        <div id="sectionToReceive" class="order-section" style="display:none;">
            <h5>To Receive (Shipped)</h5>
            <?php
            $q = "SELECT o.order_id, o.order_date, ol.product_id, ol.quantity, p.product_name, p.image
                  FROM orders o
                  JOIN orderline ol ON o.order_id = ol.order_id
                  JOIN products p ON ol.product_id = p.product_id
                  WHERE o.user_id = $user_id AND o.status = 'Shipped'
                  ORDER BY o.order_date DESC";
            $res = mysqli_query($conn, $q);
            if ($res && mysqli_num_rows($res) > 0): ?>
                <div class="list-group">
                <?php while ($r = mysqli_fetch_assoc($res)): ?>
                    <div class="list-group-item d-flex align-items-center">
                        <img src="/db_shoeverse/item/images/<?php echo htmlspecialchars($r['image']); ?>" style="width:80px;height:60px;object-fit:cover;margin-right:12px;">
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?php echo htmlspecialchars($r['product_name']); ?></div>
                            <div>Quantity: <?php echo (int)$r['quantity']; ?></div>
                            <div class="text-muted">Order: #<?php echo (int)$r['order_id']; ?> | <?php echo htmlspecialchars($r['order_date']); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No orders in this status.</p>
            <?php endif; ?>
        </div>

        <div id="sectionToRate" class="order-section" style="display:none;">
            <h5>To Rate (Delivered)</h5>
            <?php
            // Show delivered orders that have NOT been reviewed by this user yet
            $q = "SELECT o.order_id, o.order_date, ol.product_id, ol.quantity, p.product_name, p.image
                FROM orders o
                JOIN orderline ol ON o.order_id = ol.order_id
                JOIN products p ON ol.product_id = p.product_id
                LEFT JOIN reviews r ON r.product_id = ol.product_id AND r.user_id = $user_id
                WHERE o.user_id = $user_id AND o.status = 'Delivered' AND r.review_id IS NULL
                ORDER BY o.order_date DESC";
            $res = mysqli_query($conn, $q);
            if ($res && mysqli_num_rows($res) > 0): ?>
                <div class="list-group">
                <?php while ($r = mysqli_fetch_assoc($res)): ?>
                    <div class="list-group-item d-flex align-items-start">
                        <img src="/db_shoeverse/item/images/<?php echo htmlspecialchars($r['image']); ?>" style="width:80px;height:60px;object-fit:cover;margin-right:12px;">
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?php echo htmlspecialchars($r['product_name']); ?></div>
                            <div>Quantity: <?php echo (int)$r['quantity']; ?></div>
                            <div class="text-muted">Order: #<?php echo (int)$r['order_id']; ?> | <?php echo htmlspecialchars($r['order_date']); ?></div>

                            <!-- Review form inline -->
                            <div class="mt-2">
                                <form method="POST" action="/db_shoeverse/user/submit_review.php">
                                    <input type="hidden" name="product_id" value="<?php echo (int)$r['product_id']; ?>">
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <select name="rating" class="form-select" required>
                                                <option value="5">5</option>
                                                <option value="4">4</option>
                                                <option value="3">3</option>
                                                <option value="2">2</option>
                                                <option value="1">1</option>
                                            </select>
                                        </div>
                                        <div class="col-md-7">
                                            <input type="text" name="review_text" class="form-control" placeholder="Write a short review (optional)">
                                        </div>
                                        <div class="col-md-2 d-grid">
                                            <button type="submit" name="save_review" class="btn btn-primary">Rate</button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="mt-2">
                                <!-- Completed handled automatically; no manual button -->
                            </div>

                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No orders in this status.</p>
            <?php endif; ?>
        </div>

        <div id="sectionCompleted" class="order-section" style="display:none;">
            <h5>Completed Orders</h5>
            <?php
            $q = "SELECT o.order_id, o.order_date, ol.product_id, ol.quantity, p.product_name, p.image
                FROM orders o
                JOIN orderline ol ON o.order_id = ol.order_id
                JOIN products p ON ol.product_id = p.product_id
                WHERE o.user_id = $user_id AND o.status IN ('Delivered','Completed')
                ORDER BY o.order_date DESC";
            $res = mysqli_query($conn, $q);
            if ($res && mysqli_num_rows($res) > 0): ?>
                <div class="list-group">
                <?php while ($r = mysqli_fetch_assoc($res)): ?>
                    <div class="list-group-item d-flex align-items-center">
                        <img src="/db_shoeverse/item/images/<?php echo htmlspecialchars($r['image']); ?>" style="width:80px;height:60px;object-fit:cover;margin-right:12px;">
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?php echo htmlspecialchars($r['product_name']); ?></div>
                            <div>Quantity: <?php echo (int)$r['quantity']; ?></div>
                            <div class="text-muted">Order: #<?php echo (int)$r['order_id']; ?> | <?php echo htmlspecialchars($r['order_date']); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No completed orders.</p>
            <?php endif; ?>
        </div>

        <div id="sectionReviewed" class="order-section" style="display:none;">
            <h5>Reviewed Products</h5>
            <?php
            // Show unique products that this user has reviewed (use reviews as base to avoid duplicates)
            $q = "SELECT r.review_id, r.product_id, r.rating, r.review_text, r.created_at, p.product_name, p.image
                  FROM reviews r
                  JOIN products p ON r.product_id = p.product_id
                  WHERE r.user_id = $user_id
                  ORDER BY r.created_at DESC";
            $res = mysqli_query($conn, $q);
            if ($res && mysqli_num_rows($res) > 0): ?>
                <div class="list-group">
                <?php while ($rev = mysqli_fetch_assoc($res)): ?>
                    <div class="list-group-item d-flex align-items-start">
                        <img src="/db_shoeverse/item/images/<?php echo htmlspecialchars($rev['image']); ?>" style="width:80px;height:60px;object-fit:cover;margin-right:12px;">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($rev['product_name']); ?></div>
                                    <div class="text-muted small">Product ID: <?php echo (int)$rev['product_id']; ?></div>
                                </div>
                                <div class="ms-3">
                                    <!-- Edit button opens modal -->
                                    <button class="btn btn-sm btn-outline-secondary me-2 btn-edit-review"
                                        data-review-id="<?php echo (int)$rev['review_id']; ?>"
                                        data-product-id="<?php echo (int)$rev['product_id']; ?>"
                                        data-rating="<?php echo (int)$rev['rating']; ?>"
                                        data-review-text="<?php echo htmlspecialchars($rev['review_text'], ENT_QUOTES); ?>">Edit</button>
                                    <!-- Delete form -->
                                    <form method="POST" action="" style="display:inline-block;">
                                        <input type="hidden" name="review_id" value="<?php echo (int)$rev['review_id']; ?>">
                                        <button type="submit" name="delete_review" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this review?');">Delete</button>
                                    </form>
                                </div>
                            </div>

                            <div class="mt-2">
                                <strong>Rating:</strong> <?php echo intval($rev['rating']); ?> / 5
                            </div>
                            <div class="mt-1 text-muted small">
                                <?php echo nl2br(htmlspecialchars($rev['review_text'])); ?>
                            </div>
                            <div class="mt-2 text-muted small">Reviewed on: <?php echo htmlspecialchars($rev['created_at']); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No reviewed products yet.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
document.getElementById('btnToShip').addEventListener('click', function(){
    document.getElementById('sectionToShip').style.display='block';
    document.getElementById('sectionToReceive').style.display='none';
    document.getElementById('sectionToRate').style.display='none';
    document.getElementById('sectionReviewed').style.display='none';
    document.getElementById('sectionCompleted').style.display='none';
});
document.getElementById('btnToReceive').addEventListener('click', function(){
    document.getElementById('sectionToShip').style.display='none';
    document.getElementById('sectionToReceive').style.display='block';
    document.getElementById('sectionToRate').style.display='none';
    document.getElementById('sectionReviewed').style.display='none';
    document.getElementById('sectionCompleted').style.display='none';
});
document.getElementById('btnToRate').addEventListener('click', function(){
    document.getElementById('sectionToShip').style.display='none';
    document.getElementById('sectionToReceive').style.display='none';
    document.getElementById('sectionToRate').style.display='block';
    // Show reviewed products below To Rate
    document.getElementById('sectionReviewed').style.display='block';
    document.getElementById('sectionCompleted').style.display='none';
});
document.getElementById('btnCompleted').addEventListener('click', function(){
    document.getElementById('sectionToShip').style.display='none';
    document.getElementById('sectionToReceive').style.display='none';
    document.getElementById('sectionToRate').style.display='none';
    document.getElementById('sectionReviewed').style.display='none';
    document.getElementById('sectionCompleted').style.display='block';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>