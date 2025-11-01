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
              <a href="index.php?id=<?php echo $row['product_id']; ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
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
