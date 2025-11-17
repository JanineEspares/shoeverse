<?php
include '../includes/config.php';
include '../includes/adminHeader.php';

// Restrict access to Admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    echo "<div class='alert alert-danger text-center'>Access denied. Admins only.</div>";
    include '../includes/footer.php';
    exit();
}

// Validate order ID
if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger text-center'>Invalid order ID.</div>";
    include '../includes/footer.php';
    exit();
}

$order_id = intval($_GET['id']);

// Fetch order details
$order_query = "
    SELECT o.*, u.fname, u.lname, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.user_id 
    WHERE o.order_id = $order_id
";
$order_result = mysqli_query($conn, $order_query);
$order = mysqli_fetch_assoc($order_result);

if (!$order) {
    echo "<div class='alert alert-danger text-center'>Order not found.</div>";
    include '../includes/footer.php';
    exit();
}

// Fetch order items
$order_items_query = "
    SELECT ol.*, p.product_name, p.price, p.image, pv.color_name, pv.size_value
    FROM orderline ol
    JOIN products p ON ol.product_id = p.product_id
    LEFT JOIN product_variants pv ON ol.variant_id = pv.variant_id
    WHERE ol.order_id = $order_id
";
$order_items = mysqli_query($conn, $order_items_query);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="m-0">📦 Order #<?= $order['order_id'] ?> Details</h2>
        <a href="orders.php" class="btn btn-secondary">⬅️ Back to Orders</a>
    </div>

    <!-- Customer Info -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">Customer Information</div>
        <div class="card-body">
            <p><strong>Customer:</strong> <?= htmlspecialchars($order['fname'] . " " . $order['lname']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></p>
            <p><strong>Order Date:</strong> <?= $order['order_date'] ?></p>
            <p><strong>Shipping Address:</strong> <?= htmlspecialchars($order['shipping_address']) ?></p>
            <p><strong>Shipping Fee:</strong> ₱<?= number_format($order['shipping_fee'], 2) ?></p>
            <p><strong>Status:</strong>
                <span class="badge 
                    <?php
                    echo match ($order['status']) {
                        'Pending' => 'bg-warning',
                        'Shipped' => 'bg-info',
                        'Delivered' => 'bg-success',
                        'Cancelled' => 'bg-danger',
                        default => 'bg-secondary'
                    };
                    ?>">
                    <?= $order['status'] ?>
                </span>
            </p>
        </div>
    </div>

    <!-- Order Items -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">Order Items</div>
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle text-center">
                <thead class="table-secondary">
                    <tr>
                        <th>Image</th>
                        <th>Product Name</th>
                        <th>Color</th>
                        <th>Size</th>
                        <th>Price (₱)</th>
                        <th>Quantity</th>
                        <th>Subtotal (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total = 0;
                    if (mysqli_num_rows($order_items) > 0):
                        while ($item = mysqli_fetch_assoc($order_items)):
                            $subtotal = $item['price'] * $item['quantity'];
                            $total += $subtotal;
                    ?>
                            <tr>
                                <td><img src="../item/images/<?= htmlspecialchars($item['image']) ?>" alt="Product" width="70"></td>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= $item['color_name'] ? htmlspecialchars($item['color_name']) : '-' ?></td>
                                <td><?= $item['size_value'] ? htmlspecialchars($item['size_value']) : '-' ?></td>
                                <td><?= number_format($item['price'], 2) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= number_format($subtotal, 2) ?></td>
                            </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <tr><td colspan="5">No products found for this order.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="fw-bold table-dark text-white">
                    <tr>
                        <td colspan="6" class="text-end">Subtotal:</td>
                        <td>₱<?= number_format($total, 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="6" class="text-end">Shipping Fee:</td>
                        <td>₱<?= number_format($order['shipping_fee'], 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="6" class="text-end">Total Amount:</td>
                        <td>₱<?= number_format($total + $order['shipping_fee'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="orders.php" class="btn btn-secondary">⬅️ Back to Orders</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>