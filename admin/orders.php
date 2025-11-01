<?php
include '../includes/config.php';
include '../includes/adminHeader.php';

// Restrict access to Admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    echo "<div class='alert alert-danger text-center'>Access denied. Admins only.</div>";
    include '../includes/footer.php';
    exit();
}

// Handle order status update
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $update = mysqli_query($conn, "UPDATE orders SET status = '$status' WHERE order_id = '$order_id'");

    if ($update) {
        echo "<script>alert('Order status updated successfully!'); window.location.href='orders.php';</script>";
    } else {
        echo "<div class='alert alert-danger text-center'>Error updating order: " . mysqli_error($conn) . "</div>";
    }
}

// Fetch all orders with user info
$query = "
    SELECT orders.*, users.fname, users.lname, users.email 
    FROM orders 
    INNER JOIN users ON orders.user_id = users.user_id 
    ORDER BY order_id DESC
";
$orders = mysqli_query($conn, $query);
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">📦 Manage Orders</h2>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
            <span>Order List</span>
            <small class="text-light">Total Orders: <?= mysqli_num_rows($orders) ?></small>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle text-center">
                <thead class="table-secondary">
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Order Date</th>
                        <th>Shipping Address</th>
                        <th>Shipping Fee</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($orders) > 0): ?>
                        <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                            <tr>
                                <td><?= $order['order_id'] ?></td>
                                <td><?= htmlspecialchars($order['fname'] . " " . $order['lname']) ?></td>
                                <td><?= htmlspecialchars($order['email']) ?></td>
                                <td><?= $order['order_date'] ?></td>
                                <td><?= htmlspecialchars($order['shipping_address']) ?></td>
                                <td>₱<?= number_format($order['shipping_fee'], 2) ?></td>
                                <td>
                                    <?php
                                    $statusColor = match ($order['status']) {
                                        'Pending' => 'warning',
                                        'Shipped' => 'info',
                                        'Delivered' => 'success',
                                        'Cancelled' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusColor ?>"><?= $order['status'] ?></span>
                                </td>
                                <td>
                                    <a href="orderDetails.php?id=<?= $order['order_id'] ?>" class="btn btn-secondary btn-sm">🔍 View</a>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateOrder<?= $order['order_id'] ?>">✏️ Update</button>
                                </td>
                            </tr>

                            <!-- Update Status Modal -->
                            <div class="modal fade" id="updateOrder<?= $order['order_id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header bg-dark text-white">
                                                <h5 class="modal-title">Update Order Status</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select" required>
                                                        <option value="Pending" <?= ($order['status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
                                                        <option value="Shipped" <?= ($order['status'] == 'Shipped') ? 'selected' : '' ?>>Shipped</option>
                                                        <option value="Delivered" <?= ($order['status'] == 'Delivered') ? 'selected' : '' ?>>Delivered</option>
                                                        <option value="Cancelled" <?= ($order['status'] == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="update_status" class="btn btn-success" onclick="return confirm('Are you sure you want to update this order status?')">💾 Save</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8">No orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
