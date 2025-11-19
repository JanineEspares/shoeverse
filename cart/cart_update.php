<?php
include '../includes/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    
    header("Location: ../user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$variant_id = isset($_GET['variant_id']) ? intval($_GET['variant_id']) : 0;
$quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;
$date_added = date('Y-m-d');

$variantColumnExists = false;
$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM carts LIKE 'variant_id'");
if ($colCheck && mysqli_num_rows($colCheck) > 0) {
    $variantColumnExists = true;
}

switch ($action) {
    case 'add':
        if ($product_id > 0) {
            
            $available = null;
            if ($variantColumnExists && $variant_id > 0) {
                $vcheck = mysqli_prepare($conn, "SELECT variant_id, stock FROM product_variants WHERE variant_id = ? LIMIT 1");
                $variant_exists = false;
                if ($vcheck) {
                    mysqli_stmt_bind_param($vcheck, 'i', $variant_id);
                    mysqli_stmt_execute($vcheck);
                    mysqli_stmt_bind_result($vcheck, $found_vid, $stock_avail_v);
                    if (mysqli_stmt_fetch($vcheck)) { $variant_exists = true; $stock_avail = intval($stock_avail_v); }
                    mysqli_stmt_close($vcheck);
                }
                if ($variant_exists) {
                    $chk = mysqli_prepare($conn, "SELECT stock FROM product_variants WHERE variant_id = ? LIMIT 1");
                } else {
                    $variant_id = 0;
                    $chk = null;
                }
                
                if ($chk) {
                    mysqli_stmt_bind_param($chk, 'i', $variant_id);
                    mysqli_stmt_execute($chk);
                    mysqli_stmt_bind_result($chk, $stock_avail);
                    if (mysqli_stmt_fetch($chk)) {
                        $available = intval($stock_avail);
                    }
                    mysqli_stmt_close($chk);
                }
            } else {
                $chk2 = mysqli_prepare($conn, "SELECT stock FROM products WHERE product_id = ? LIMIT 1");
                if ($chk2) {
                    mysqli_stmt_bind_param($chk2, 'i', $product_id);
                    mysqli_stmt_execute($chk2);
                    mysqli_stmt_bind_result($chk2, $pstock);
                    if (mysqli_stmt_fetch($chk2)) { $available = intval($pstock); }
                    mysqli_stmt_close($chk2);
                }
            }

            if ($available !== null && $available <= 0) {
                header("Location: view_cart.php?error=out_of_stock");
                exit();
            }
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
                
                $row = mysqli_fetch_assoc($check);
                $new_qty = $row['quantity'] + $quantity;
                if ($available !== null && $new_qty > $available) {
                    header("Location: view_cart.php?error=out_of_stock");
                    exit();
                }
                mysqli_query($conn, "UPDATE carts SET quantity=$new_qty WHERE cart_id={$row['cart_id']}");
            } else {
                if ($available !== null && $quantity > $available) {
                    header("Location: view_cart.php?error=out_of_stock");
                    exit();
                }

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
        mysqli_query($conn, "DELETE FROM carts WHERE user_id=$user_id");
        header("Location: view_cart.php");
        break;

    default:
        header("Location: view_cart.php");
        break;
}
?>