<?php
// Delete (Frontend handler) - simple product delete (may fail if FK constraints exist)
session_start();
include('../includes/config.php');

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$product_id = $_GET['id'];

$query = "DELETE FROM products WHERE product_id = $product_id";

if (mysqli_query($conn, $query)) {
    header("Location: index.php");
    exit;
} else {
    echo "Error deleting product: " . mysqli_error($conn);
}
?>