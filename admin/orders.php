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

    // fetch current status
    $curS = mysqli_prepare($conn, "SELECT status FROM orders WHERE order_id = ? LIMIT 1");
    mysqli_stmt_bind_param($curS, 'i', $order_id);
    mysqli_stmt_execute($curS);
    $resS = mysqli_stmt_get_result($curS);
    $rowS = $resS ? mysqli_fetch_assoc($resS) : null;
    mysqli_stmt_close($curS);

    $oldStatus = $rowS['status'] ?? null;

    // perform update; if the orders table has a date_shipped column and status becomes Shipped,
    // store the current datetime in that column.
    $hasDateShipped = false;
    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'date_shipped'");
    if ($colCheck && mysqli_num_rows($colCheck) > 0) { $hasDateShipped = true; }

    if ($hasDateShipped && $status === 'Shipped') {
        $now = date('Y-m-d H:i:s');
        $stmtU = mysqli_prepare($conn, "UPDATE orders SET status = ?, date_shipped = ? WHERE order_id = ?");
        mysqli_stmt_bind_param($stmtU, 'ssi', $status, $now, $order_id);
    } else {
        $stmtU = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE order_id = ?");
        mysqli_stmt_bind_param($stmtU, 'si', $status, $order_id);
    }
    $exec = mysqli_stmt_execute($stmtU);
    mysqli_stmt_close($stmtU);

    if ($exec) {
        // if newly cancelled and previously not cancelled, restore stock for this order
        if ($status === 'Cancelled' && $oldStatus !== 'Cancelled') {
            // restore stock for each orderline
            $ol = mysqli_prepare($conn, "SELECT product_id, variant_id, quantity FROM orderline WHERE order_id = ?");
            mysqli_stmt_bind_param($ol, 'i', $order_id);
            mysqli_stmt_execute($ol);
            $olres = mysqli_stmt_get_result($ol);
            mysqli_stmt_close($ol);

            while ($line = mysqli_fetch_assoc($olres)) {
                $pid = intval($line['product_id']);
                $vid = $line['variant_id'] !== null ? intval($line['variant_id']) : null;
                $qty = intval($line['quantity']);
                if ($vid) {
                    $upv = mysqli_prepare($conn, "UPDATE product_variants SET stock = stock + ? WHERE variant_id = ?");
                    mysqli_stmt_bind_param($upv, 'ii', $qty, $vid);
                    mysqli_stmt_execute($upv);
                    mysqli_stmt_close($upv);
                } else {
                    $upp = mysqli_prepare($conn, "UPDATE products SET stock = stock + ? WHERE product_id = ?");
                    mysqli_stmt_bind_param($upp, 'ii', $qty, $pid);
                    mysqli_stmt_execute($upp);
                    mysqli_stmt_close($upp);
                }
            }
        }

        // After updating status, send an email notification to the customer with order details
        // Fetch order header info (detect optional columns to avoid referencing missing ones)
        function _col_exists($conn, $table, $col) {
            $q = mysqli_query($conn, "SHOW COLUMNS FROM `" . mysqli_real_escape_string($conn, $table) . "` LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
            return ($q && mysqli_num_rows($q) > 0);
        }

        $selectCols = [ 'user_id', 'order_date', 'shipping_fee', 'shipping_address' ];
        if (_col_exists($conn, 'orders', 'payment_method')) $selectCols[] = 'payment_method';
        if (_col_exists($conn, 'orders', 'payment_info')) $selectCols[] = 'payment_info';

        $sqlOH = "SELECT " . implode(', ', $selectCols) . " FROM orders WHERE order_id = ? LIMIT 1";
        $oh = mysqli_prepare($conn, $sqlOH);
        mysqli_stmt_bind_param($oh, 'i', $order_id);
        mysqli_stmt_execute($oh);
        $ohres = mysqli_stmt_get_result($oh);
        $orderHeader = $ohres ? mysqli_fetch_assoc($ohres) : null;
        mysqli_stmt_close($oh);

        if ($orderHeader) {
            $user_id_for_email = intval($orderHeader['user_id']);
            $order_date_for_email = $orderHeader['order_date'];
            $shipping_fee_for_email = floatval($orderHeader['shipping_fee'] ?? 0);
            $shipping_address_for_email = $orderHeader['shipping_address'];
            $payment_method_for_email = $orderHeader['payment_method'] ?? '';
            $payment_info_for_email = $orderHeader['payment_info'] ?? null;

            // Build items HTML and compute subtotal. Prefer the view if present; otherwise fall back to a direct join query.
            $items_html = '';
            $subtotal_calc = 0.0;

            $viewExists = false;
            $vc = mysqli_query($conn, "SHOW TABLES LIKE 'order_transaction_details'");
            if ($vc && mysqli_num_rows($vc) > 0) { $viewExists = true; }

            if ($viewExists) {
                $vt = mysqli_prepare($conn, "SELECT product_name, variant_color, variant_size, quantity, unit_price, line_total FROM order_transaction_details WHERE order_id = ?");
                if ($vt) {
                    mysqli_stmt_bind_param($vt, 'i', $order_id);
                    mysqli_stmt_execute($vt);
                    $vres = mysqli_stmt_get_result($vt);
                    mysqli_stmt_close($vt);
                } else {
                    $vres = false;
                }
            } else {
                // fallback: join orderline -> products -> product_variants
                $stmtFallback = mysqli_prepare($conn, "SELECT p.product_name, pv.color_name AS variant_color, pv.size_value AS variant_size, ol.quantity, p.price AS unit_price, (ol.quantity * p.price) AS line_total FROM orderline ol JOIN products p ON ol.product_id = p.product_id LEFT JOIN product_variants pv ON ol.variant_id = pv.variant_id WHERE ol.order_id = ?");
                if ($stmtFallback) {
                    mysqli_stmt_bind_param($stmtFallback, 'i', $order_id);
                    mysqli_stmt_execute($stmtFallback);
                    $vres = mysqli_stmt_get_result($stmtFallback);
                    mysqli_stmt_close($stmtFallback);
                } else {
                    $vres = false;
                }
            }

            if ($vres) {
                $items_html .= '<table style="width:100%;border-collapse:collapse;">';
                $items_html .= '<tr><th style="text-align:left;border-bottom:1px solid #ddd;padding:4px;">Product</th><th style="text-align:left;border-bottom:1px solid #ddd;padding:4px;">Variant</th><th style="text-align:right;border-bottom:1px solid #ddd;padding:4px;">Qty</th><th style="text-align:right;border-bottom:1px solid #ddd;padding:4px;">Line Total</th></tr>';
                while ($r = mysqli_fetch_assoc($vres)) {
                    $pname = htmlspecialchars($r['product_name']);
                    $variant = '';
                    if (!empty($r['variant_color'])) $variant .= htmlspecialchars($r['variant_color']);
                    if (!empty($r['variant_size'])) $variant .= ($variant? ' / ':'') . htmlspecialchars($r['variant_size']);
                    if ($variant === '') $variant = '-';
                    $qty = intval($r['quantity']);
                    $line_total = floatval($r['line_total']);
                    $subtotal_calc += $line_total;
                    $items_html .= '<tr><td style="padding:4px;border-bottom:1px solid #eee;">' . $pname . '</td><td style="padding:4px;border-bottom:1px solid #eee;">' . $variant . '</td><td style="padding:4px;border-bottom:1px solid #eee;text-align:right;">' . $qty . '</td><td style="padding:4px;border-bottom:1px solid #eee;text-align:right;">₱' . number_format($line_total,2) . '</td></tr>';
                }
                $items_html .= '</table>';
            }

            $grand_total_calc = $subtotal_calc + $shipping_fee_for_email;

            // send email (use centralized mailer)
            require_once __DIR__ . '/../includes/mailer.php';
            try {
                // prepare a short status-specific intro message to prepend to the email
                $status_intro = null;
                if (isset($status)) {
                    switch ($status) {
                        case 'Pending':
                            $status_intro = 'Thank you — your order has been placed successfully.';
                            break;
                        case 'Shipped':
                            $status_intro = 'Good news — your parcel has been shipped.';
                            break;
                        case 'Delivered':
                            $status_intro = 'Your order has been delivered. We hope you enjoy your purchase!';
                            break;
                        case 'Cancelled':
                            $status_intro = 'Your order has been cancelled. If this was a mistake, please contact support.';
                            break;
                        default:
                            $status_intro = null;
                    }
                }
                $emailOk = sendOrderEmail($conn, $user_id_for_email, $order_id, $order_date_for_email, $subtotal_calc, $grand_total_calc, $items_html, $shipping_address_for_email, $payment_method_for_email, $payment_info_for_email, $status_intro);
            } catch (Exception $e) {
                error_log('Error sending order update email for order ' . $order_id . ': ' . $e->getMessage());
            }

        }

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