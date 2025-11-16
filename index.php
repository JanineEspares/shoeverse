<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/includes/config.php';
include __DIR__ . '/includes/header.php';

// Fetch products (with brand and category names)
$query = "SELECT p.*, b.brand_name, c.category_name 
          FROM products p
          JOIN brands b ON p.brand_id = b.brand_id
          JOIN category c ON p.category_id = c.category_id
          ORDER BY p.date_added DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}
?>

<h2 class="text-center mb-4">Welcome to ShoeVerse 👟</h2>
<p class="text-center text-muted mb-5">Find your perfect pair of shoes today!</p>

<div class="row g-4">
  <?php if (mysqli_num_rows($result) > 0): ?>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
      <div class="col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm">
          <img src="item/images/<?php echo htmlspecialchars($row['image']); ?>" 
               class="card-img-top" 
               alt="<?php echo htmlspecialchars($row['product_name']); ?>" 
               style="height: 250px; object-fit: cover;">

          <div class="card-body d-flex flex-column">
            <h5 class="card-title"><?php echo htmlspecialchars($row['product_name']); ?></h5>
            <p class="text-muted mb-1">
              <?php echo htmlspecialchars($row['brand_name']); ?> - 
              <?php echo htmlspecialchars($row['category_name']); ?>
            </p>
            <p class="fw-bold text-primary mb-2">₱<?php echo number_format($row['price'], 2); ?></p>

            <p class="text-muted small flex-grow-1"><?php echo htmlspecialchars($row['description']); ?></p>

            <div class="mt-auto">
              <a href="item/index.php?id=<?php echo $row['product_id']; ?>#reviews" class="btn btn-outline-primary btn-sm w-100 mb-2">
                View Details
              </a>
              <a href="cart/cart_update.php?action=add&id=<?php echo $row['product_id']; ?>" class="btn btn-primary btn-sm w-100">
                Add to Cart
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p class="text-center text-muted">No products available at the moment.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>