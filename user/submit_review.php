<?php
session_start();
include __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please login to submit a review.'); window.location='/db_shoeverse/user/login.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /db_shoeverse/index.php'); exit();
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

if ($product_id <= 0 || $rating < 1 || $rating > 5) {
    echo "<script>alert('Invalid review data.'); window.history.back();</script>";
    exit();
}

// mask bad words
function mask_bad_words($text) {
    $badWords = ['damn','crap','stupid','idiot','hell','shit','fuck','gago','gagu','bobo','tangina','tangena','tang ina'];
    $pattern = '/\b(' . implode('|', array_map('preg_quote', $badWords)) . ')\b/i';
    return preg_replace_callback($pattern, function($m){
        return str_repeat('*', mb_strlen($m[1]));
    }, $text);
}

$review_text = mask_bad_words($review_text);

// Ensure user has a delivered order for this product
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM orderline ol JOIN orders o ON ol.order_id = o.order_id WHERE o.user_id = ? AND ol.product_id = ? AND o.status IN ('Delivered','Completed')");
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $product_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (empty($count) || $count == 0) {
    echo "<script>alert('You can only review products you have received (Delivered).'); window.history.back();</script>";
    exit();
}

// Check if user already has a review for this product
$check = mysqli_prepare($conn, "SELECT review_id FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
mysqli_stmt_bind_param($check, 'ii', $product_id, $user_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) > 0) {
    // update
    mysqli_stmt_bind_result($check, $existing_id);
    mysqli_stmt_fetch($check);
    mysqli_stmt_close($check);
    $ust = mysqli_prepare($conn, "UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE review_id = ?");
    mysqli_stmt_bind_param($ust, 'isi', $rating, $review_text, $existing_id);
    if (mysqli_stmt_execute($ust)) {
        echo "<script>alert('Review updated.'); window.location='/db_shoeverse/user/profile.php';</script>";
        exit();
    } else {
        echo "<script>alert('Failed to update review.'); window.history.back();</script>";
        exit();
    }
} else {
    mysqli_stmt_close($check);
    $ist = mysqli_prepare($conn, "INSERT INTO reviews (user_id, product_id, rating, review_text, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    mysqli_stmt_bind_param($ist, 'iiis', $user_id, $product_id, $rating, $review_text);
    if (mysqli_stmt_execute($ist)) {
        echo "<script>alert('Review submitted.'); window.location='/db_shoeverse/user/profile.php';</script>";
        exit();
    } else {
        echo "<script>alert('Failed to submit review.'); window.history.back();</script>";
        exit();
    }
}

?>
