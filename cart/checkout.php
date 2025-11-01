<?php
include '../includes/config.php';
include '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-warning text-center'>Please <a href='../user/login.php'>login</a> to proceed to checkout.</div>";
    include '../includes/footer.php';
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's cart items
$cart_query = "SELECT c.cart_id, c.product_id, c.quantity, p.price, p.stock
               FROM carts c
               JOIN products p ON c.product_id = p.product_id
               WHERE c.user_id = $user_id";
$cart_result = mysqli_query($conn, $cart_query);

if (mysqli_num_rows($cart_result) == 0) {
    echo "<div class='alert alert-info text-center'>Your cart is empty. <a href='../index.php'>Start shopping</a>.</div>";
    include '../includes/footer.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shipping_address = mysqli_real_escape_string($conn, $_POST['shipping_address']);
    $shipping_fee = 150.00; // Flat fee
    $order_date = date('Y-m-d');
    $status = 'Pending';

    // Insert into orders
    $order_query = "INSERT INTO orders (user_id, order_date, shipping_fee, status, shipping_address)
                    VALUES ('$user_id', '$order_date', '$shipping_fee', '$status', '$shipping_address')";
    if (mysqli_query($conn, $order_query)) {
        $order_id = mysqli_insert_id($conn);

        // Insert into orderline
        while ($item = mysqli_fetch_assoc($cart_result)) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $price = $item['price'];

            // Reduce product stock
            $new_stock = $item['stock'] - $quantity;
            mysqli_query($conn, "UPDATE products SET stock = $new_stock WHERE product_id = $product_id");

            $orderline_query = "INSERT INTO orderline (order_id, product_id, quantity)
                                VALUES ('$order_id', '$product_id', '$quantity')";
            mysqli_query($conn, $orderline_query);
        }

        // Clear user's cart
        mysqli_query($conn, "DELETE FROM carts WHERE user_id = $user_id");

        echo "<div class='alert alert-success text-center'>
                ✅ Order placed successfully! <a href='../index.php'>Continue Shopping</a>
              </div>";
    } else {
        echo "<div class='alert alert-danger text-center'>Error creating order. Please try again.</div>";
    }
} else {
    // Fetch user info for pre-filled shipping address
    $user_query = mysqli_query($conn, "SELECT address_line FROM users WHERE user_id = $user_id");
    $user_data = mysqli_fetch_assoc($user_query);
    $address_line = $user_data['address_line'];
    ?>

    <h2 class="text-center mb-4">🧾 Checkout</h2>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="shipping_address" class="form-label fw-bold">Shipping Address:</label>
            <textarea name="shipping_address" id="shipping_address" rows="3" class="form-control" required><?php echo htmlspecialchars($address_line); ?></textarea>
        </div>

        <div class="text-end">
            <a href="view_cart.php" class="btn btn-outline-secondary">← Back to Cart</a>
            <button type="submit" class="btn btn-primary">Confirm Order</button>
        </div>
    </form>

<?php
}

include '../includes/footer.php';
?>
