<?php
// shoeverse/item/index.php
// Handles both product listing and single product detail view

include '../includes/config.php';
include '../includes/header.php';

// If an ID is provided, show detail view
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $product_id = intval($_GET['id']);

    $stmt = mysqli_prepare($conn,
        "SELECT p.*, b.brand_name, c.category_name
         FROM products p
         JOIN brands b ON p.brand_id = b.brand_id
         JOIN category c ON p.category_id = c.category_id
         WHERE p.product_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res && mysqli_num_rows($res) > 0) {
        $product = mysqli_fetch_assoc($res);
    // --- Reviews handling: add/update and display ---
    // Helper: mask bad words using regex (customize $badWords as needed)
    function mask_bad_words($text) {
      $badWords = ['damn','crap','stupid','idiot','hell','shit','fuck'];
      $pattern = '/\b(' . implode('|', array_map('preg_quote', $badWords)) . ')\b/i';
      return preg_replace_callback($pattern, function($m){
        return str_repeat('*', mb_strlen($m[1]));
      }, $text);
    }

    // If user posts a review (create or update)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_review']) || isset($_POST['update_review']))) {
      // require login
      if (!isset($_SESSION['user_id'])) {
        echo "<div class='alert alert-warning'>Please <a href='../user/login.php'>login</a> to submit a review.</div>";
      } else {
        $user_id = $_SESSION['user_id'];
        $product_id = $product['product_id'];

  // Check if user received (Delivered) this product
  $pstmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM orderline ol JOIN orders o ON ol.order_id = o.order_id WHERE o.user_id = ? AND ol.product_id = ? AND o.status = 'Delivered'");
        mysqli_stmt_bind_param($pstmt, 'ii', $user_id, $product_id);
        mysqli_stmt_execute($pstmt);
        mysqli_stmt_bind_result($pstmt, $pcount);
        mysqli_stmt_fetch($pstmt);
        mysqli_stmt_close($pstmt);

        if (empty($pcount) || $pcount == 0) {
          echo "<div class='alert alert-danger'>Only customers who have received (Delivered) this product can leave a review.</div>";
        } else {
          $rating = intval($_POST['rating']);
          if ($rating < 1) $rating = 1; if ($rating > 5) $rating = 5;
          $review_text = trim($_POST['review_text']);
          // mask bad words
          $review_text = mask_bad_words($review_text);

          if (isset($_POST['save_review'])) {
            $stmt = mysqli_prepare($conn, "INSERT INTO reviews (user_id, product_id, rating, review_text, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            mysqli_stmt_bind_param($stmt, 'iiis', $user_id, $product_id, $rating, $review_text);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "<div class='alert alert-success'>Review submitted.</div>";
          } elseif (isset($_POST['update_review'])) {
            $review_id = intval($_POST['review_id']);
            // ensure review belongs to user
            $check = mysqli_prepare($conn, "SELECT review_id FROM reviews WHERE review_id = ? AND user_id = ? AND product_id = ?");
            mysqli_stmt_bind_param($check, 'iii', $review_id, $user_id, $product_id);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);
            if (mysqli_stmt_num_rows($check) > 0) {
              mysqli_stmt_close($check);
              $ustmt = mysqli_prepare($conn, "UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE review_id = ?");
              mysqli_stmt_bind_param($ustmt, 'isi', $rating, $review_text, $review_id);
              mysqli_stmt_execute($ustmt);
              mysqli_stmt_close($ustmt);
              echo "<div class='alert alert-success'>Review updated.</div>";
            } else {
              mysqli_stmt_close($check);
              echo "<div class='alert alert-danger'>Cannot update review.</div>";
            }
          }
        }
      }
    }

    // Fetch reviews for this product
    $reviews = [];
    $rstmt = mysqli_prepare($conn, "SELECT r.review_id, r.user_id, r.rating, r.review_text, r.created_at, u.fname, u.lname FROM reviews r JOIN users u ON r.user_id = u.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC");
    mysqli_stmt_bind_param($rstmt, 'i', $product['product_id']);
    mysqli_stmt_execute($rstmt);
    $rres = mysqli_stmt_get_result($rstmt);
    if ($rres) {
      while ($row = mysqli_fetch_assoc($rres)) { $reviews[] = $row; }
    }
    mysqli_stmt_close($rstmt);

    // Check if current user has a review already
    $userReview = null;
    if (isset($_SESSION['user_id'])) {
      $urstmt = mysqli_prepare($conn, "SELECT review_id, rating, review_text FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
      mysqli_stmt_bind_param($urstmt, 'ii', $product['product_id'], $_SESSION['user_id']);
      mysqli_stmt_execute($urstmt);
      $urres = mysqli_stmt_get_result($urstmt);
      if ($urres && mysqli_num_rows($urres) > 0) { $userReview = mysqli_fetch_assoc($urres); }
      mysqli_stmt_close($urstmt);
    }

    // Determine if current logged-in user is eligible to leave a review (has a Delivered order for this product)
    $canReview = false;
    if (isset($_SESSION['user_id'])) {
      $crstmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM orderline ol JOIN orders o ON ol.order_id = o.order_id WHERE o.user_id = ? AND ol.product_id = ? AND o.status = 'Delivered'");
      mysqli_stmt_bind_param($crstmt, 'ii', $_SESSION['user_id'], $product['product_id']);
      mysqli_stmt_execute($crstmt);
      mysqli_stmt_bind_result($crstmt, $crcount);
      mysqli_stmt_fetch($crstmt);
      mysqli_stmt_close($crstmt);
      if (!empty($crcount) && $crcount > 0) { $canReview = true; }
    }

    ?>
        <div class="row">
          <div class="col-md-6 text-center">
            <?php if (!empty($product['image'])): ?>
              <img src="images/<?php echo htmlspecialchars($product['image']); ?>" 
                   alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                   class="img-fluid rounded shadow-sm" 
                   style="max-height: 450px; object-fit: cover;">
            <?php else: ?>
              <img src="https://via.placeholder.com/450x450?text=ShoeVerse" class="img-fluid rounded shadow-sm">
            <?php endif; ?>
          </div>

          <div class="col-md-6">
            <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>
            <p class="text-muted mb-1">
              Brand: <strong><?php echo htmlspecialchars($product['brand_name']); ?></strong><br>
              Category: <strong><?php echo htmlspecialchars($product['category_name']); ?></strong>
            </p>
            <h4 class="text-primary mb-3">₱<?php echo number_format($product['price'], 2); ?></h4>
            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

            <p class="text-muted">
              Available Size: <strong><?php echo htmlspecialchars($product['size']); ?></strong><br>
              In Stock: <strong><?php echo htmlspecialchars($product['stock']); ?></strong>
            </p>

            <form action="../cart/cart_update.php" method="GET" class="mt-4">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="id" value="<?php echo $product['product_id']; ?>">
              <div class="input-group mb-3" style="max-width: 200px;">
                <input type="number" name="quantity" class="form-control" value="1" min="1" max="<?php echo (int)$product['stock']; ?>">
                <button type="submit" class="btn btn-primary">Add to Cart</button>
              </div>
            </form>

            <a href="../index.php" class="btn btn-outline-secondary">← Back to Shop</a>
          </div>
        </div>
        
        <hr>
        <div id="reviews">
          <h4>Customer Reviews</h4>

          <?php if (!empty($reviews)): ?>
            <div class="mb-3">
              <?php foreach ($reviews as $rev): ?>
                <div class="border rounded p-2 mb-2">
                  <strong><?php echo htmlspecialchars($rev['fname'] . ' ' . $rev['lname']); ?></strong>
                  <span class="text-muted"> — <?php echo htmlspecialchars($rev['created_at']); ?></span>
                  <div>Rating: <?php echo intval($rev['rating']); ?> / 5</div>
                  <div><?php echo nl2br(htmlspecialchars($rev['review_text'])); ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted">No reviews yet.</p>
          <?php endif; ?>

          <?php if ($canReview): ?>
            <hr>
            <h5><?php echo $userReview ? 'Update Your Review' : 'Leave a Review'; ?></h5>
            <form method="POST">
              <?php if ($userReview): ?>
                <input type="hidden" name="review_id" value="<?php echo intval($userReview['review_id']); ?>">
              <?php endif; ?>

              <div class="mb-2">
                <label class="form-label">Rating</label>
                <select name="rating" class="form-select" required>
                  <?php for ($i=1;$i<=5;$i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($userReview && intval($userReview['rating'])==$i)?'selected':''; ?>><?php echo $i; ?></option>
                  <?php endfor; ?>
                </select>
              </div>

              <div class="mb-2">
                <label class="form-label">Review</label>
                <textarea name="review_text" class="form-control" rows="4"><?php echo $userReview ? htmlspecialchars($userReview['review_text']) : ''; ?></textarea>
              </div>

              <div class="mb-3">
                <?php if ($userReview): ?>
                  <button type="submit" name="update_review" class="btn btn-primary">Update Review</button>
                <?php else: ?>
                  <button type="submit" name="save_review" class="btn btn-success">Submit Review</button>
                <?php endif; ?>
              </div>
            </form>
          <?php else: ?>
            <?php if (!isset($_SESSION['user_id'])): ?>
              <p class="text-muted">Please <a href="../user/login.php">login</a> to leave a review.</p>
            <?php else: ?>
              <p class="text-muted">Only customers who have received (Delivered) this product may leave a review.</p>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php
  } else {
    echo "<div class='alert alert-warning text-center'>Product not found.</div>";
  }

    mysqli_stmt_close($stmt);
    include '../includes/footer.php';
    exit();
}

// Otherwise: product listing (grid)

// Add New Product button for Admins
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    echo '<div class="mb-3 text-end">
            <a href="create.php" class="btn btn-success">+ Add New Product</a>
          </div>';
}

$query = "SELECT p.*, b.brand_name, c.category_name 
          FROM products p
          JOIN brands b ON p.brand_id = b.brand_id
          JOIN category c ON p.category_id = c.category_id
          ORDER BY p.date_added DESC";
$result = mysqli_query($conn, $query);
?>

<h2 class="text-center mb-4">All Products</h2>

<div class="row g-4">
  <?php if ($result && mysqli_num_rows($result) > 0): ?>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
      <div class="col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm">
          <?php if (!empty($row['image'])): ?>
            <img src="images/<?php echo htmlspecialchars($row['image']); ?>" 
                 class="card-img-top" 
                 alt="<?php echo htmlspecialchars($row['product_name']); ?>" 
                 style="height: 250px; object-fit: cover;">
          <?php else: ?>
            <img src="https://via.placeholder.com/250x250?text=ShoeVerse" class="card-img-top" alt="No image">
          <?php endif; ?>

          <div class="card-body d-flex flex-column">
            <h5 class="card-title"><?php echo htmlspecialchars($row['product_name']); ?></h5>
            <p class="text-muted mb-1 small">
              <?php echo htmlspecialchars($row['brand_name']); ?> — <?php echo htmlspecialchars($row['category_name']); ?>
            </p>
            <p class="fw-bold text-primary mb-2">₱<?php echo number_format($row['price'], 2); ?></p>

            <p class="text-muted small flex-grow-1">
              <?php echo htmlspecialchars(substr($row['description'], 0, 90)); ?>
              <?php echo (strlen($row['description'])>90)?'...':''; ?>
            </p>

            <div class="mt-auto">
              <a href="index.php?id=<?php echo $row['product_id']; ?>#reviews" class="btn btn-outline-primary btn-sm w-100 mb-2">
                View Details
              </a>
              <a href="../cart/cart_update.php?action=add&id=<?php echo $row['product_id']; ?>" class="btn btn-primary btn-sm w-100 mb-2">
                Add to Cart
              </a>

              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <a href="edit.php?id=<?php echo $row['product_id']; ?>" class="btn btn-warning btn-sm w-100 mb-2">
                  Edit
                </a>
                <a href="delete.php?id=<?php echo $row['product_id']; ?>" class="btn btn-danger btn-sm w-100"
                   onclick="return confirm('Are you sure you want to delete this product?');">
                   Delete
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p class="text-center text-muted">No products available at the moment.</p>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>