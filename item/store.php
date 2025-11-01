<?php
session_start();
include('../includes/config.php');

// Clear old session data
unset($_SESSION['nameError'], $_SESSION['product_name'], $_SESSION['price'], $_SESSION['size'], $_SESSION['stock'], $_SESSION['description'], $_SESSION['brand_id'], $_SESSION['category_id']);

// Check if form was submitted
if (isset($_POST['submit'])) {
    
    $errors = [];
    
    // Collect form values
    $product_name = trim($_POST['product_name']);
    $size = trim($_POST['size']);
    $price = trim($_POST['price']);
    $stock = trim($_POST['stock']);
    $description = trim($_POST['description']);
    $brand_id = $_POST['brand_id'];
    $category_id = $_POST['category_id'];

    $_SESSION['product_name'] = $product_name;
    $_SESSION['size'] = $size;
    $_SESSION['price'] = $price;
    $_SESSION['stock'] = $stock;
    $_SESSION['description'] = $description;
    $_SESSION['brand_id'] = $brand_id;
    $_SESSION['category_id'] = $category_id;

    // Validate required fields
    if ($product_name == "") $errors['nameError'] = "Product name is required.";
    if ($size == "" || !is_numeric($size)) $errors['sizeError'] = "Valid size is required.";
    if ($price == "" || !is_numeric($price)) $errors['priceError'] = "Valid price is required.";
    if ($stock == "" || !is_numeric($stock)) $errors['stockError'] = "Valid stock is required.";
    if ($description == "") $errors['descriptionError'] = "Description is required.";
    if ($brand_id == "") $errors['brandError'] = "Select a brand.";
    if ($category_id == "") $errors['categoryError'] = "Select a category.";

    // Validate image
    if (isset($_FILES['img_path']) && $_FILES['img_path']['name'] != "") {
        $image = $_FILES['img_path']['name'];
        $target = "images/" . basename($image);
        if (!move_uploaded_file($_FILES['img_path']['tmp_name'], $target)) {
            $errors['imageError'] = "Failed to upload image.";
        }
    } else {
        $errors['imageError'] = "Image is required.";
    }

    // If errors, store in session and redirect back
    if (!empty($errors)) {
        foreach ($errors as $key => $value) {
            $_SESSION[$key] = $value;
        }
        header("Location: create.php");
        exit;
    }

    // Insert into database
    $date_added = date("Y-m-d");
    $query = "INSERT INTO products (brand_id, category_id, product_name, size, price, stock, description, image, date_added)
              VALUES ($brand_id, $category_id, '$product_name', $size, $price, $stock, '$description', '$image', '$date_added')";

    if (mysqli_query($conn, $query)) {
        // Clear form session values after success
        unset($_SESSION['product_name'], $_SESSION['size'], $_SESSION['price'], $_SESSION['stock'], $_SESSION['description'], $_SESSION['brand_id'], $_SESSION['category_id']);
        $_SESSION['success'] = "Product added successfully!";
        header("Location: index.php"); // redirect to product list
        exit;
    } else {
        $_SESSION['nameError'] = "Database error: " . mysqli_error($conn);
        header("Location: create.php");
        exit;
    }
} else {
    header("Location: create.php");
    exit;
}
?>
