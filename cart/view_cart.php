<?php
include '../includes/config.php';
include '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-warning text-center'>Please <a href='../user/login.php'>login</a> to view your cart.</div>";
    include '../includes/footer.php';
    exit();
}

$user_id = $_SESSION['user_id'];

// Get cart items for this user
$variantColumnExists = false;
$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM carts LIKE 'variant_id'");
if ($colCheck && mysqli_num_rows($colCheck) > 0) {
  $variantColumnExists = true;
}

if ($variantColumnExists) {
  $query = "SELECT c.cart_id, c.quantity, c.variant_id,
           p.product_id, p.product_name, p.price, p.image,
           v.color_name, v.size_value, v.stock AS variant_stock
        FROM carts c
        JOIN products p ON c.product_id = p.product_id
        LEFT JOIN product_variants v ON c.variant_id = v.variant_id
        WHERE c.user_id = $user_id";
} else {
  // Return NULL placeholders for variant-related fields so the page doesn't break
  $query = "SELECT c.cart_id, c.quantity, NULL AS variant_id,
           p.product_id, p.product_name, p.price, p.image,
           NULL AS color_name, NULL AS size_value, NULL AS variant_stock
        FROM carts c
        JOIN products p ON c.product_id = p.product_id
        WHERE c.user_id = $user_id";
}

$result = mysqli_query($conn, $query);

$total = 0;
?>

<h2 class="text-center mb-4">🛒 Your Shopping Cart</h2>

<?php if (isset($_GET['error']) && $_GET['error'] === 'out_of_stock'): ?>
  <div class="container">
    <div class="alert alert-warning text-center">Sorry — one or more items you tried to add are out of stock or the requested quantity exceeds current availability.</div>
  </div>
<?php endif; ?>

<?php if (mysqli_num_rows($result) > 0): ?>
  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead class="table-dark text-center">
        <tr>
          <th>Product</th>
          <th>Price</th>
          <th>Quantity</th>
          <th>Subtotal</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <?php
            $subtotal = $row['price'] * $row['quantity'];
            $total += $subtotal;
            $availStock = !empty($row['variant_stock']) ? (int)$row['variant_stock'] : 9999;
            $variantInfo = '';
            if (!empty($row['color_name']) || !empty($row['size_value'])) {
                $variantInfo = '<small class="text-muted d-block">Color: ' . htmlspecialchars($row['color_name'] ?? 'N/A') . ' | Size: ' . htmlspecialchars($row['size_value'] ?? 'N/A') . '</small>';
            }
          ?>
          <tr>
            <td class="d-flex align-items-center">
              <img src="../item/images/<?php echo htmlspecialchars($row['image']); ?>" 
                   alt="<?php echo htmlspecialchars($row['product_name']); ?>" 
                   class="me-3 rounded" style="width: 70px; height: 70px; object-fit: cover;">
              <div>
                <?php echo htmlspecialchars($row['product_name']); ?>
                <?php echo $variantInfo; ?>
              </div>
            </td>
            <td class="text-center">₱<?php echo number_format($row['price'], 2); ?></td>
            <td class="text-center">
              <form action="cart_update.php" method="GET" class="d-inline">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="cart_id" value="<?php echo $row['cart_id']; ?>">
                <input type="number" name="quantity" 
                       value="<?php echo $row['quantity']; ?>" 
                       min="1" max="<?php echo $availStock; ?>" 
                       class="form-control d-inline text-center" style="width: 80px; display:inline-block;">
                <button type="submit" class="btn btn-sm btn-success mt-1">Update</button>
              </form>
            </td>
            <td class="text-center fw-bold text-primary">₱<?php echo number_format($subtotal, 2); ?></td>
            <td class="text-center">
              <a href="cart_update.php?action=delete&cart_id=<?php echo $row['cart_id']; ?>" 
                 class="btn btn-sm btn-danger"
                 onclick="return confirm('Remove this item from your cart?');">
                Remove
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
      <tfoot class="table-light">
        <tr>
          <td colspan="3" class="text-end fw-bold">Total:</td>
          <td colspan="2" class="fw-bold text-primary">₱<?php echo number_format($total, 2); ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="text-end">
    <form action="cart_update.php" method="GET" class="d-inline">
      <input type="hidden" name="action" value="clear">
      <button type="submit" class="btn btn-danger me-2" onclick="return confirm('Clear your entire cart?');">Clear Cart</button>
    </form>
    <a href="../index.php" class="btn btn-outline-secondary me-2">← Continue Shopping</a>
    <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
  </div>

<?php else: ?>
  <div class="alert alert-info text-center">
    Your cart is empty. <a href="../index.php">Start shopping now!</a>
  </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>