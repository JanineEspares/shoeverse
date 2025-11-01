<?php
session_start();
include('../includes/header.php');
include('../includes/config.php');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo "<p class='text-danger'>Access denied. Admins only.</p>";
    include('../includes/footer.php');
    exit;
}

// Get product ID from URL
if (!isset($_GET['id'])) {
    echo "<p class='text-danger'>No product selected.</p>";
    include('../includes/footer.php');
    exit;
}

$product_id = $_GET['id'];

// Fetch product
$result = mysqli_query($conn, "SELECT * FROM products WHERE product_id = $product_id");
if (mysqli_num_rows($result) == 0) {
    echo "<p class='text-danger'>Product not found.</p>";
    include('../includes/footer.php');
    exit;
}

$product = mysqli_fetch_assoc($result);

// Fetch brands and categories
$brandsResult = mysqli_query($conn, "SELECT * FROM brands");
$categoriesResult = mysqli_query($conn, "SELECT * FROM category");
?>

<body>
<div class="container mt-4">
    <h2>Edit Product</h2>
    <form method="POST" action="update.php" enctype="multipart/form-data">
        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">

        <div class="form-group mb-3">
            <label>Brand</label>
            <select class="form-control" name="brand_id">
                <?php while ($brand = mysqli_fetch_assoc($brandsResult)): ?>
                    <option value="<?php echo $brand['brand_id']; ?>" 
                        <?php if ($brand['brand_id'] == $product['brand_id']) echo "selected"; ?>>
                        <?php echo $brand['brand_name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group mb-3">
            <label>Category</label>
            <select class="form-control" name="category_id">
                <?php while ($category = mysqli_fetch_assoc($categoriesResult)): ?>
                    <option value="<?php echo $category['category_id']; ?>" 
                        <?php if ($category['category_id'] == $product['category_id']) echo "selected"; ?>>
                        <?php echo $category['category_name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group mb-3">
            <label>Product Name</label>
            <input type="text" class="form-control" name="product_name" value="<?php echo $product['product_name']; ?>">
        </div>

        <div class="form-group mb-3">
            <label>Size</label>
            <input type="number" class="form-control" name="size" value="<?php echo $product['size']; ?>">
        </div>

        <div class="form-group mb-3">
            <label>Price</label>
            <input type="text" class="form-control" name="price" value="<?php echo $product['price']; ?>">
        </div>

        <div class="form-group mb-3">
            <label>Stock</label>
            <input type="number" class="form-control" name="stock" value="<?php echo $product['stock']; ?>">
        </div>

        <div class="form-group mb-3">
            <label>Description</label>
            <textarea class="form-control" name="description"><?php echo $product['description']; ?></textarea>
        </div>

        <div class="form-group mb-3">
            <label>Current Image</label><br>
            <img src="images/<?php echo $product['image']; ?>" style="height:100px;">
        </div>

        <div class="form-group mb-3">
            <label>Change Image (optional)</label>
            <input type="file" class="form-control" name="img_path">
        </div>

        <button type="submit" class="btn btn-primary">Update Product</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include('../includes/footer.php'); ?>
