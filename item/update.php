<?php
session_start();
include('../includes/config.php');

if (!isset($_POST['product_id'])) {
    header("Location: index.php");
    exit;
}

$product_id = $_POST['product_id'];
$product_name = $_POST['product_name'];
$size = $_POST['size'];
$price = $_POST['price'];
$stock = $_POST['stock'];
$description = $_POST['description'];
$brand_id = $_POST['brand_id'];
$category_id = $_POST['category_id'];
// Handle main image upload (optional)
$mainImageSql = '';
if (isset($_FILES['img_path']) && !empty($_FILES['img_path']['name'])) {
    $image = time() . '_' . basename($_FILES['img_path']['name']);
    $target = "images/" . $image;
    if (move_uploaded_file($_FILES['img_path']['tmp_name'], $target)) {
        $mainImageSql = ", image='" . mysqli_real_escape_string($conn, $image) . "'";
    }
}

// If variants are submitted, compute total stock from variants; otherwise use provided stock
$total_stock = 0;
$hasVariants = isset($_POST['variant_color']) && is_array($_POST['variant_color']) && count($_POST['variant_color'])>0;
if ($hasVariants) {
    foreach ($_POST['variant_stock'] as $vs) { $total_stock += intval($vs); }
    $final_stock = $total_stock;
} else {
    $final_stock = intval($stock);
}

// Update product
$query = "UPDATE products SET brand_id=$brand_id, category_id=$category_id, product_name='" . mysqli_real_escape_string($conn, $product_name) . "', size=$size, price=$price, stock=$final_stock, description='" . mysqli_real_escape_string($conn, $description) . "' " . $mainImageSql . " WHERE product_id=$product_id";

if (mysqli_query($conn, $query)) {
    // Handle gallery image deletions
    if (isset($_POST['images_to_delete']) && is_array($_POST['images_to_delete'])) {
        foreach ($_POST['images_to_delete'] as $del) {
            $fn = basename($del);
            $path = "images/" . $fn;
            if (file_exists($path)) @unlink($path);
            mysqli_query($conn, "DELETE FROM product_images WHERE product_id=$product_id AND image='" . mysqli_real_escape_string($conn, $fn) . "'");
        }
    }

    // Handle new gallery uploads
    if (isset($_FILES['images']) && isset($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
        for ($i=0;$i<count($_FILES['images']['name']);$i++) {
            if (empty($_FILES['images']['name'][$i])) continue;
            $iname = time() . '_' . basename($_FILES['images']['name'][$i]);
            $itarget = "images/" . $iname;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $itarget)) {
                mysqli_query($conn, "INSERT INTO product_images (product_id, image) VALUES ($product_id, '" . mysqli_real_escape_string($conn, $iname) . "')");
            }
        }
    }

    // Handle variants: delete existing and re-insert from posted arrays
    if ($hasVariants) {
        mysqli_query($conn, "DELETE FROM product_variants WHERE product_id=$product_id");
        $var_colors = $_POST['variant_color'];
        $var_sizes = $_POST['variant_size'];
        $var_stocks = $_POST['variant_stock'];
        $existing_color_imgs = isset($_POST['variant_existing_color_image']) ? $_POST['variant_existing_color_image'] : [];

        for ($i=0;$i<count($var_colors);$i++) {
            $cname = mysqli_real_escape_string($conn, $var_colors[$i]);
            $sz = mysqli_real_escape_string($conn, $var_sizes[$i]);
            $stk = intval($var_stocks[$i]);

            // handle color image upload for this variant
            $colorImageName = null;
            if (isset($_FILES['variant_color_image']) && isset($_FILES['variant_color_image']['name'][$i]) && !empty($_FILES['variant_color_image']['name'][$i])) {
                $cimg = time() . '_var_' . basename($_FILES['variant_color_image']['name'][$i]);
                $ctarget = "images/" . $cimg;
                if (move_uploaded_file($_FILES['variant_color_image']['tmp_name'][$i], $ctarget)) {
                    $colorImageName = $cimg;
                }
            } else {
                // keep existing color image if provided
                if (isset($existing_color_imgs[$i]) && !empty($existing_color_imgs[$i])) {
                    $colorImageName = mysqli_real_escape_string($conn, $existing_color_imgs[$i]);
                } else {
                    $colorImageName = NULL;
                }
            }

            $colImgSql = $colorImageName ? "'" . mysqli_real_escape_string($conn, $colorImageName) . "'" : "NULL";
            $insert = "INSERT INTO product_variants (product_id, color_name, color_image, size_value, stock) VALUES ($product_id, '$cname', $colImgSql, '$sz', $stk)";
            mysqli_query($conn, $insert);
        }
    }

    // Redirect back to admin products list if the user is an Admin, otherwise to the public products page
    if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin') {
        header("Location: ../admin/products.php");
    } else {
        header("Location: index.php");
    }
    exit;
} else {
    echo "Error: " . mysqli_error($conn);
}
?>