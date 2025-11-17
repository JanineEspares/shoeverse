<?php
// Centralized mailer helper. Uses PHPMailer if available, falls back to mail().
// Usage: sendOrderEmail($conn, $user_id, $order_id, $order_date, $subtotal, $grand_total, $items_html, $shipping_address, $payment_method, $payment_info)
// New optional parameter $status_intro: a short message to prepend depending on order status
function sendOrderEmail($conn, $user_id, $order_id, $order_date, $subtotal, $grand_total, $items_html, $shipping_address, $payment_method, $payment_info = null, $status_intro = null) {
    // fetch user email and name
    $stmt = mysqli_prepare($conn, "SELECT fname, email FROM users WHERE user_id = ? LIMIT 1");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cust = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$cust || empty($cust['email'])) return false;

    $to = $cust['email'];
    $cust_name = !empty($cust['fname']) ? $cust['fname'] : '';
    $subject = "Order Confirmation - Order #" . intval($order_id);

    // build HTML body
    $body = "<p>Hi " . htmlspecialchars($cust_name) . ",</p>";
    if (!empty($status_intro)) {
        $body .= "<p>" . htmlspecialchars($status_intro) . "</p>";
    }
    $body .= "<p>Thanks for your order. Here are the details for your transaction:</p>";
    $body .= "<p><strong>Order ID:</strong> " . intval($order_id) . "<br>";
    $body .= "<strong>Order Date:</strong> " . htmlspecialchars($order_date) . "<br>";
    $body .= "<strong>Subtotal:</strong> Php" . number_format($subtotal,2) . "<br>";
    $body .= "<strong>Grand Total:</strong> Php" . number_format($grand_total,2) . "</p>";
    $body .= "<p><strong>Items:</strong><br>" . $items_html . "</p>";
    $body .= "<p><strong>Shipping Address:</strong><br>" . nl2br(htmlspecialchars($shipping_address)) . "</p>";
    $body .= "<p><strong>Payment Method:</strong> " . htmlspecialchars($payment_method);
    if (!empty($payment_info)) { $body .= " - " . htmlspecialchars($payment_info); }
    $body .= "</p>";
    $body .= "<p>You can view your orders by logging into your account: <a href='http://localhost/db_shoeverse/user/profile.php'>My Profile</a></p>";
    $body .= "<p>Regards,<br>Shoeverse</p>";

    // Try PHPMailer
    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') || class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = defined('MAILTRAP_HOST') ? MAILTRAP_HOST : 'smtp.mailtrap.io';
            $mail->SMTPAuth = true;
            $mail->Username = defined('MAILTRAP_USER') ? MAILTRAP_USER : '';
            $mail->Password = defined('MAILTRAP_PASS') ? MAILTRAP_PASS : '';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = defined('MAILTRAP_PORT') ? MAILTRAP_PORT : 2525;

            $mail->setFrom(defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@shoeverse.local', defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Shoeverse');
            $mail->addAddress($to, $cust_name);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (\Exception $e) {
            // log and fall back
            $err = 'PHPMailer error: ' . $e->getMessage();
            error_log($err);
            $logdir = __DIR__ . '/../logs'; if (!is_dir($logdir)) @mkdir($logdir,0755,true);
            @file_put_contents($logdir . '/mail_error.log', date('c') . " - " . $err . "\n", FILE_APPEND);
        }
    }

    // Fallback to PHP mail()
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@shoeverse.local') . "\r\n";
    $sent = @mail($to, $subject, $body, $headers);
    $logdir = __DIR__ . '/../logs'; if (!is_dir($logdir)) @mkdir($logdir,0755,true);
    @file_put_contents($logdir . '/mail_send.log', date('c') . " - fallback mail() sent=" . ($sent ? '1' : '0') . " to={$to} order_id={$order_id}\n", FILE_APPEND);
    return $sent;
}

?>