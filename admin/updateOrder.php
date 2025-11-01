<?php
include '../includes/config.php';
include '../includes/adminHeader.php';

// Restrict access to Admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    echo "<div class='alert alert-danger text-center'>Access denied. Admins only.</div>";
    include '../includes/footer.php';
    exit();
}

// Make sure there's an ID passed
if (!isset($_GET['id'])) {
    echo "<script>alert('Invalid request.'); window.location.href='products.php';</script>";
    exit();
}

$product_id = intval($_GET['id']); // ensure it's an integer

// Fetch product data
$product_query = mysqli_query($conn, "SELECT * FROM products WHERE product_id = '$product_id'");
if (mysqli_num_rows($product_query) == 0) {
    echo "<script>alert('Product not found.'); window.location.href='products.php';</script>";
    exit();
}
$product = mysqli_fetch_assoc($product_query);

// Fetch dropdown data
$brands = mysqli_query($conn, "SELECT * FROM brands");
$categories = mysqli_query($conn, "SELECT * FROM category");

// Handle form submission
if (isset($_POST['update_product'])) {
    $brand_id = intval($_POST['brand_id']);
    $category_id = intval($_POST['category_id']);
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $size = floatval($_POST['size']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    // Handle image update
    $image_sql = "";
    if (!empty($_FILES['image']['name'])) {
        $image_name = $_FILES['image']['name'];
        $target_dir = "../item/images/";
        $target_file = $target_dir . basename($image_name);

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_sql = ", image = '$image_name'";
        }
    }

    $update_query = "
        UPDATE products 
        SET brand_id = '$brand_id',
            category_id = '$category_id',
            product_name = '$product_name',
            size = '$size',
            price = '$price',
            stock = '$stock',
            description = '$description'
            $image_sql
        WHERE product_id = '$product_id'
    ";

    if (mysqli_query($conn, $update_query)) {
        echo "<script>alert('Product updated successfully!'); window.location.href='products.php';</script>";
        exit();
    } else {
        echo "<div class='alert alert-danger text-center'>Update failed: " . mysqli_error($conn) . "</div>";
    }
}
?>

<div class="container mt-4 mb-5">
    <h2 class="text-center mb-4">✏️ Update Product</h2>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">Edit Product Details</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col">
                        <label>Brand</label>
                        <select name="brand_id" class="form-select" required>
                            <?php if (mysqli_num_rows($brands) > 0): ?>
                                <?php while ($b = mysqli_fetch_assoc($brands)): ?>
                                    <option value="<?= $b['brand_id'] ?>" <?= ($b['brand_id'] == $product['brand_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['brand_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option disabled>No brands available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label>Category</label>
                        <select name="category_id" class="form-select" required>
                            <?php if (mysqli_num_rows($categories) > 0): ?>
                                <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                                    <option value="<?= $c['category_id'] ?>" <?= ($c['category_id'] == $product['category_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['category_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option disabled>No categories available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Product Name</label>
                    <input type="text" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>" class="form-control" required>
                </div>

                <div class="row mb-3">
                    <div class="col">
                        <label>Size</label>
                        <input type="number" name="size" value="<?= $product['size'] ?>" class="form-control" required>
                    </div>
                    <div class="col">
                        <label>Price</label>
                        <input type="number" name="price" step="0.01" value="<?= $product['price'] ?>" class="form-control" required>
                    </div>
                    <div class="col">
                        <label>Stock</label>
                        <input type="number" name="stock" value="<?= $product['stock'] ?>" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($product['description']) ?></textarea>
                </div>

                <div class="mb-3">
                    <label>Product Image</label><br>
                    <?php
                    $image_path = "../item/images/" . htmlspecialchars($product['image']);
                    if (!file_exists($image_path) || empty($product['image'])) {
                        $image_path = "../item/images/default.jpg";
                    }
                    ?>
                    <img src="<?= $image_path ?>" width="100" height="100" class="border rounded mb-2">
                    <input type="file" name="image" class="form-control">
                    <small class="text-muted">Leave empty if you don't want to change the image.</small>
                </div>

                <div class="text-center">
                    <button type="submit" name="update_product" class="btn btn-success px-5">💾 Save Changes</button>
                    <a href="products.php" class="btn btn-secondary px-5">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
