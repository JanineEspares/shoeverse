<?php
include '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    // Only logged-in users can have a cart
    header("Location: ../user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$variant_id = isset($_GET['variant_id']) ? intval($_GET['variant_id']) : 0;
$quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;
$date_added = date('Y-m-d');

// Detect whether the `variant_id` column exists in the `carts` table on this database.
// Some environments may not have the schema updated; guard SQL that references the column.
$variantColumnExists = false;
$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM carts LIKE 'variant_id'");
if ($colCheck && mysqli_num_rows($colCheck) > 0) {
    $variantColumnExists = true;
}

switch ($action) {
    case 'add':
        if ($product_id > 0) {
            // Check if product (and variant, if supported) already exists in user's cart
            $checkSql = "SELECT * FROM carts WHERE user_id=$user_id AND product_id=$product_id";
            if ($variantColumnExists) {
                if ($variant_id > 0) {
                    $checkSql .= " AND variant_id=$variant_id";
                } else {
                    $checkSql .= " AND variant_id IS NULL";
                }
            }
            $check = mysqli_query($conn, $checkSql);

            if (mysqli_num_rows($check) > 0) {
                // If product+variant already in cart, increase quantity
                $row = mysqli_fetch_assoc($check);
                $new_qty = $row['quantity'] + $quantity;
                mysqli_query($conn, "UPDATE carts SET quantity=$new_qty WHERE cart_id={$row['cart_id']}");
            } else {
                // Insert new item into cart. Only include variant_id if the DB supports the column.
                if ($variantColumnExists && $variant_id > 0) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO carts (user_id, product_id, variant_id, quantity, date_added) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "iiiis", $user_id, $product_id, $variant_id, $quantity, $date_added);
                } else {
                    $stmt = mysqli_prepare($conn, "INSERT INTO carts (user_id, product_id, quantity, date_added) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "iiis", $user_id, $product_id, $quantity, $date_added);
                }
                mysqli_stmt_execute($stmt);
            }
        }
        header("Location: view_cart.php");
        break;

    case 'update':
        $cart_id = isset($_GET['cart_id']) ? intval($_GET['cart_id']) : 0;
        if ($cart_id > 0 && $quantity > 0) {
            mysqli_query($conn, "UPDATE carts SET quantity=$quantity WHERE cart_id=$cart_id AND user_id=$user_id");
        }
        header("Location: view_cart.php");
        break;

    case 'delete':
        $cart_id = isset($_GET['cart_id']) ? intval($_GET['cart_id']) : 0;
        if ($cart_id > 0) {
            mysqli_query($conn, "DELETE FROM carts WHERE cart_id=$cart_id AND user_id=$user_id");
        }
        header("Location: view_cart.php");
        break;

    case 'clear':
        // Remove all items from this user's cart
        mysqli_query($conn, "DELETE FROM carts WHERE user_id=$user_id");
        header("Location: view_cart.php");
        break;

    default:
        header("Location: view_cart.php");
        break;
}
?>