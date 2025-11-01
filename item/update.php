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

// Handle image upload
if (isset($_FILES['img_path']) && $_FILES['img_path']['name'] != "") {
    $image = $_FILES['img_path']['name'];
    $target = "images/" . basename($image);
    move_uploaded_file($_FILES['img_path']['tmp_name'], $target);
    $query = "UPDATE products SET brand_id=$brand_id, category_id=$category_id, product_name='$product_name', size=$size, price=$price, stock=$stock, description='$description', image='$image' WHERE product_id=$product_id";
} else {
    // If no new image uploaded, keep old image
    $query = "UPDATE products SET brand_id=$brand_id, category_id=$category_id, product_name='$product_name', size=$size, price=$price, stock=$stock, description='$description' WHERE product_id=$product_id";
}

if (mysqli_query($conn, $query)) {
    header("Location: index.php");
    exit;
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
