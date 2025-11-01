<?php
include '../includes/config.php';
include '../includes/adminHeader.php';

// ✅ Restrict access to Admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    echo "<div class='alert alert-danger text-center'>Access denied. Admins only.</div>";
    include '../includes/footer.php';
    exit();
}

// ✅ Handle Add Product
if (isset($_POST['add_product'])) {
    $brand_id = intval($_POST['brand_id']);
    $category_id = intval($_POST['category_id']);
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $size = intval($_POST['size']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $date_added = date('Y-m-d');

    if (empty($brand_id) || empty($category_id) || empty($product_name) || empty($price) || empty($stock)) {
        echo "<script>alert('Please fill in all required fields.');</script>";
    } else {
        // Prevent duplicate product names under the same brand
        $check = mysqli_query($conn, "SELECT * FROM products WHERE product_name = '$product_name' AND brand_id = '$brand_id'");
        if (mysqli_num_rows($check) > 0) {
            echo "<script>alert('Product with this name already exists under the selected brand.');</script>";
        } else {
            // ✅ Handle Image Upload
            $image_name = $_FILES['image']['name'];
            $target_dir = "../item/images/";
            $target_file = $target_dir . basename($image_name);
            $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
            $file_ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_types)) {
                echo "<script>alert('Invalid image format. Please upload JPG, PNG, or WEBP files only.');</script>";
            } else {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $insert_query = "
                        INSERT INTO products (brand_id, category_id, product_name, size, price, stock, description, image, date_added)
                        VALUES ('$brand_id', '$category_id', '$product_name', '$size', '$price', '$stock', '$description', '$image_name', '$date_added')
                    ";
                    if (mysqli_query($conn, $insert_query)) {
                        echo "<script>alert('Product added successfully!'); window.location.href='products.php';</script>";
                    } else {
                        echo "<div class='alert alert-danger text-center'>Database Error: " . mysqli_error($conn) . "</div>";
                    }
                } else {
                    echo "<script>alert('Error uploading image.');</script>";
                }
            }
        }
    }
}

// ✅ Handle Delete Product
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    // Delete associated image
    $result = mysqli_query($conn, "SELECT image FROM products WHERE product_id = '$delete_id'");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $image_path = "../item/images/" . $row['image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

    $delete = mysqli_query($conn, "DELETE FROM products WHERE product_id = '$delete_id'");
    if ($delete) {
        echo "<script>alert('Product deleted successfully!'); window.location.href='products.php';</script>";
    } else {
        echo "<div class='alert alert-danger text-center'>Error deleting product: " . mysqli_error($conn) . "</div>";
    }
    exit();
}

// ✅ Fetch all products
$products = mysqli_query($conn, "
    SELECT p.*, b.brand_name, c.category_name
    FROM products p
    JOIN brands b ON p.brand_id = b.brand_id
    JOIN category c ON p.category_id = c.category_id
    ORDER BY p.date_added DESC
");

// Fetch brands and categories for dropdowns
$brands = mysqli_query($conn, "SELECT * FROM brands ORDER BY brand_name ASC");
$categories = mysqli_query($conn, "SELECT * FROM category ORDER BY category_name ASC");
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">🛍 Manage Products</h2>

    <!-- ✅ Add Product Form -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">Add New Product</div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col">
                        <label>Brand</label>
                        <select name="brand_id" class="form-select" required>
                            <option value="">Select Brand</option>
                            <?php while ($b = mysqli_fetch_assoc($brands)): ?>
                                <option value="<?= $b['brand_id'] ?>"><?= htmlspecialchars($b['brand_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label>Category</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Product Name</label>
                    <input type="text" name="product_name" class="form-control" required>
                </div>

                <div class="row mb-3">
                    <div class="col">
                        <label>Size</label>
                        <input type="number" name="size" class="form-control" required>
                    </div>
                    <div class="col">
                        <label>Price</label>
                        <input type="number" name="price" step="0.01" class="form-control" required>
                    </div>
                    <div class="col">
                        <label>Stock</label>
                        <input type="number" name="stock" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label>Product Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                </div>

                <button type="submit" name="add_product" class="btn btn-success w-100">➕ Add Product</button>
            </form>
        </div>
    </div>

    <!-- ✅ Product List -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">Product List</div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle text-center">
                <thead class="table-secondary">
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Size</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($products) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($products)): ?>
                            <tr>
                                <td><?= $row['product_id'] ?></td>
                                <td>
                                    <?php if (!empty($row['image']) && file_exists("../item/images/" . $row['image'])): ?>
                                        <img src="../item/images/<?= htmlspecialchars($row['image']) ?>" width="60" height="60" style="object-fit:cover;" class="rounded border">
                                    <?php else: ?>
                                        <span class="text-muted">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td><?= htmlspecialchars($row['brand_name']) ?></td>
                                <td><?= htmlspecialchars($row['category_name']) ?></td>
                                <td><?= $row['size'] ?></td>
                                <td>₱<?= number_format($row['price'], 2) ?></td>
                                <td><?= $row['stock'] ?></td>
                                <td><?= $row['date_added'] ?></td>
                                <td>
                                    <a href="updateProduct.php?id=<?= $row['product_id'] ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
                                    <a href="?delete=<?= $row['product_id'] ?>" onclick="return confirm('Delete this product?');" class="btn btn-sm btn-danger">🗑️ Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="10">No products found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
