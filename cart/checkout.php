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
// Detect whether the `variant_id` column exists; some DBs may not have it yet
$variantColumnExists = false;
$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM carts LIKE 'variant_id'");
if ($colCheck && mysqli_num_rows($colCheck) > 0) {
    $variantColumnExists = true;
}

// Fetch cart items using prepared statements to avoid direct interpolation
if ($variantColumnExists) {
    $stmt_cart = mysqli_prepare($conn, "SELECT c.cart_id, c.product_id, c.variant_id, c.quantity, p.price, v.stock AS variant_stock
                   FROM carts c
                   JOIN products p ON c.product_id = p.product_id
                   LEFT JOIN product_variants v ON c.variant_id = v.variant_id
                   WHERE c.user_id = ?");
} else {
    $stmt_cart = mysqli_prepare($conn, "SELECT c.cart_id, c.product_id, NULL AS variant_id, c.quantity, p.price, NULL AS variant_stock
                   FROM carts c
                   JOIN products p ON c.product_id = p.product_id
                   WHERE c.user_id = ?");
}
mysqli_stmt_bind_param($stmt_cart, 'i', $user_id);
mysqli_stmt_execute($stmt_cart);
$cart_result = mysqli_stmt_get_result($stmt_cart);

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
    // Calculate cart subtotal and grand total
    mysqli_data_seek($cart_result, 0);
    $subtotal = 0.0;
    while ($it = mysqli_fetch_assoc($cart_result)) {
        $price = isset($it['price']) ? floatval($it['price']) : 0.0;
        $qty = isset($it['quantity']) ? intval($it['quantity']) : 0;
        $subtotal += $price * $qty;
    }
    $grand_total = $subtotal + $shipping_fee;
    // reset pointer for later inserts
    mysqli_data_seek($cart_result, 0);
    // Payment method handling
    $payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, $_POST['payment_method']) : 'COD';
    $payment_info = NULL;
    if ($payment_method === 'Online') {
        // For this flow user enters the payment amount only (no card details)
        $payment_amount_raw = isset($_POST['payment_amount']) ? $_POST['payment_amount'] : '';
        // normalize and validate
        $payment_amount = str_replace(',', '', $payment_amount_raw);
        if (!is_numeric($payment_amount)) {
            echo "<div class='alert alert-danger text-center'>Please enter a valid payment amount for Online Payment.</div>";
            include '../includes/footer.php';
            exit();
        }
        $payment_amount = floatval($payment_amount);
        // require at least the grand total
        if ($payment_amount < $grand_total - 0.001) {
            echo "<div class='alert alert-danger text-center'>Entered payment is less than the order total (\u20B1" . number_format($grand_total,2) . ").</div>";
            include '../includes/footer.php';
            exit();
        }
        $payment_info = 'Paid: ' . number_format($payment_amount, 2);
        // mark as paid
        $status = 'Paid';
    }

    // Insert into orders using prepared statement. Detect payment-related columns separately
    $hasPaymentMethod = false;
    $hasPaymentInfo = false;
    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'payment_method'");
    if ($colCheck && mysqli_num_rows($colCheck) > 0) { $hasPaymentMethod = true; }
    $colCheck2 = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'payment_info'");
    if ($colCheck2 && mysqli_num_rows($colCheck2) > 0) { $hasPaymentInfo = true; }

    // Build dynamic INSERT depending on available columns
    $cols = ['user_id','order_date','shipping_fee','status','shipping_address'];
    $placeholders = ['?','?','?','?','?'];
    $types = 'isdss';
    $values = [$user_id, $order_date, $shipping_fee, $status, $shipping_address];

    if ($hasPaymentMethod) {
        $cols[] = 'payment_method';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $payment_method;
    }
    if ($hasPaymentInfo) {
        $cols[] = 'payment_info';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $payment_info;
    }

    $sql = 'INSERT INTO orders (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        // bind params dynamically
        $bind_names = [];
        $bind_names[] = $stmt;
        $bind_names[] = $types;
        foreach ($values as $i => $v) $bind_names[] = & $values[$i];
        call_user_func_array('mysqli_stmt_bind_param', $bind_names);
    }

    if (mysqli_stmt_execute($stmt)) {
        $order_id = mysqli_insert_id($conn);

        // Insert into orderline (use prepared statements)
        mysqli_data_seek($cart_result, 0); // reset pointer
        // Detect whether the orderline table has a variant_id column in this database
        $orderlineHasVariant = false;
        $colCheck2 = mysqli_query($conn, "SHOW COLUMNS FROM orderline LIKE 'variant_id'");
        if ($colCheck2 && mysqli_num_rows($colCheck2) > 0) { $orderlineHasVariant = true; }

        if ($orderlineHasVariant) {
            $stmt_line_with_variant = mysqli_prepare($conn, "INSERT INTO orderline (order_id, product_id, variant_id, quantity) VALUES (?, ?, ?, ?)");
        }
        $stmt_line_no_variant = mysqli_prepare($conn, "INSERT INTO orderline (order_id, product_id, quantity) VALUES (?, ?, ?)");

        // prepare a text block of items to include in the confirmation email
        $email_items = "";
        while ($item = mysqli_fetch_assoc($cart_result)) {
            $product_id = $item['product_id'];
            $variant_id = $item['variant_id'] ? intval($item['variant_id']) : NULL;
            $quantity = $item['quantity'];

            $price = isset($item['price']) ? floatval($item['price']) : 0.0;
            $email_items .= "Product ID: " . intval($product_id) . " - Qty: " . intval($quantity) . " - Price: ₱" . number_format($price,2) . "<br>";

            // Reduce stock (variant if present, else product)
            if ($variant_id && $orderlineHasVariant) {
                $currentStock = $item['variant_stock'];
                $newStock = $currentStock - $quantity;
                $u = mysqli_prepare($conn, "UPDATE product_variants SET stock = ? WHERE variant_id = ?");
                mysqli_stmt_bind_param($u, 'ii', $newStock, $variant_id);
                mysqli_stmt_execute($u);
                mysqli_stmt_close($u);
                if ($orderlineHasVariant) {
                    mysqli_stmt_bind_param($stmt_line_with_variant, 'iiii', $order_id, $product_id, $variant_id, $quantity);
                    mysqli_stmt_execute($stmt_line_with_variant);
                } else {
                    // If DB doesn't store variant in orderline, fall back to inserting without variant
                    mysqli_stmt_bind_param($stmt_line_no_variant, 'iii', $order_id, $product_id, $quantity);
                    mysqli_stmt_execute($stmt_line_no_variant);
                }
            } else {
                // Reduce product stock
                $u2 = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE product_id = ?");
                mysqli_stmt_bind_param($u2, 'ii', $quantity, $product_id);
                mysqli_stmt_execute($u2);
                mysqli_stmt_close($u2);
                mysqli_stmt_bind_param($stmt_line_no_variant, 'iii', $order_id, $product_id, $quantity);
                mysqli_stmt_execute($stmt_line_no_variant);
            }
        }

                // Clear user's cart
                $del = mysqli_prepare($conn, "DELETE FROM carts WHERE user_id = ?");
                mysqli_stmt_bind_param($del, 'i', $user_id);
                mysqli_stmt_execute($del);

                // Send confirmation email using centralized mailer helper
                require_once __DIR__ . '/../includes/mailer.php';
                $mailSent = sendOrderEmail($conn, $user_id, $order_id, $order_date, $subtotal, $grand_total, $email_items, $shipping_address, $payment_method, $payment_info);
                if (!$mailSent) {
                    error_log("sendOrderEmail failed for order_id={$order_id} user_id={$user_id}");
                }

                echo "<div class='alert alert-success text-center'>
                                ✅ Order placed successfully! <a href='../index.php'>Continue Shopping</a>
                            </div>";
    } else {
        echo "<div class='alert alert-danger text-center'>Error creating order. Please try again.</div>";
    }
    mysqli_stmt_close($stmt);
} else {
    // Fetch user info for pre-filled shipping address
    // Fetch user address using prepared statement
    $stmt_user = mysqli_prepare($conn, "SELECT address_line FROM users WHERE user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
    mysqli_stmt_execute($stmt_user);
    $res_user = mysqli_stmt_get_result($stmt_user);
    $user_data = $res_user ? mysqli_fetch_assoc($res_user) : null;
    $address_line = $user_data['address_line'] ?? '';
    if ($stmt_user) mysqli_stmt_close($stmt_user);
    ?>

    <h2 class="text-center mb-4">🧾 Checkout</h2>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="shipping_address" class="form-label fw-bold">Shipping Address:</label>
            <textarea name="shipping_address" id="shipping_address" rows="3" class="form-control" required><?php echo htmlspecialchars($address_line); ?></textarea>
        </div>
        <?php
        // calculate subtotal and grand total for display
        mysqli_data_seek($cart_result, 0);
        $subtotal_display = 0.0;
        while ($it = mysqli_fetch_assoc($cart_result)) {
            $subtotal_display += (isset($it['price']) ? floatval($it['price']) : 0.0) * (isset($it['quantity']) ? intval($it['quantity']) : 0);
        }
        $shipping_fee = 150.00; // flat fee used in checkout
        $grand_total_display = $subtotal_display + $shipping_fee;
        // reset pointer for potential future use
        mysqli_data_seek($cart_result, 0);
        ?>

        <div class="mb-3">
            <label class="form-label fw-bold">Order Summary:</label>
            <div class="border rounded p-2">
                <div>Subtotal: ₱<?php echo number_format($subtotal_display,2); ?></div>
                <div>Shipping: ₱<?php echo number_format($shipping_fee,2); ?></div>
                <div class="fw-bold">Grand Total: ₱<?php echo number_format($grand_total_display,2); ?></div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Payment Method:</label>
            <!-- Radios and online fields are siblings so we can toggle visibility with CSS only -->
            <div class="payment-block p-2 border rounded">
                <input type="radio" name="payment_method" id="pm_cod" value="COD" checked>
                <label for="pm_cod" class="me-3">Cash on Delivery</label>

                <input type="radio" name="payment_method" id="pm_online" value="Online">
                <label for="pm_online">Online Payment</label>

                <div id="onlinePaymentFields" class="mt-3">
                    <div class="mb-3">
                        <label class="form-label">Enter payment amount (₱)</label>
                        <input type="number" step="0.01" min="0" name="payment_amount" class="form-control" placeholder="<?php echo number_format($grand_total_display,2); ?>">
                        <div class="form-text">Type the amount you paid online (must be at least the order total).</div>
                    </div>
                </div>
            </div>
            <style>
                /* Hide online fields unless Online is selected (CSS-only toggle) */
                #onlinePaymentFields { display: none; }
                /* When the Online radio is checked, show the fields (they are siblings inside .payment-block) */
                #pm_online:checked ~ #onlinePaymentFields { display: block; }
                /* Minor spacing for radio labels */
                .payment-block input[type=radio] { margin-right: .25rem; }
            </style>
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