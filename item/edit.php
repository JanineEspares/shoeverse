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

// Update (Edit form) - shows existing product data and variant inputs for editing
$product_id = null;
if (isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
} elseif (isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
} else {
    echo "<p class='text-danger'>No product selected.</p>";
    include('../includes/footer.php');
    exit;
}

$result = mysqli_query($conn, "SELECT * FROM products WHERE product_id = $product_id");
if (mysqli_num_rows($result) == 0) {
    echo "<p class='text-danger'>Product not found.</p>";
    include('../includes/footer.php');
    exit;
}

$product = mysqli_fetch_assoc($result);

$brandsResult = mysqli_query($conn, "SELECT * FROM brands");
$categoriesResult = mysqli_query($conn, "SELECT * FROM category");

$galleryRes = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id = $product_id");
$galleryImages = [];
if ($galleryRes) {
    while ($gi = mysqli_fetch_assoc($galleryRes)) { $galleryImages[] = $gi['image']; }
}

$varRes = mysqli_query($conn, "SELECT variant_id, color_name, color_image, size_value, stock FROM product_variants WHERE product_id = " . $product_id . " ORDER BY color_name, size_value");
$existingVariants = [];
if ($varRes && mysqli_num_rows($varRes) > 0) {
    while ($v = mysqli_fetch_assoc($varRes)) {
        $existingVariants[] = $v;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_variant_row']) || isset($_POST['remove_variant']))) {
    $p_ids = isset($_POST['variant_id']) && is_array($_POST['variant_id']) ? $_POST['variant_id'] : [];
    $p_colors = isset($_POST['variant_color']) && is_array($_POST['variant_color']) ? $_POST['variant_color'] : [];
    $p_sizes = isset($_POST['variant_size']) && is_array($_POST['variant_size']) ? $_POST['variant_size'] : [];
    $p_stocks = isset($_POST['variant_stock']) && is_array($_POST['variant_stock']) ? $_POST['variant_stock'] : [];
    $p_existing_imgs = isset($_POST['variant_existing_color_image']) && is_array($_POST['variant_existing_color_image']) ? $_POST['variant_existing_color_image'] : [];

    if (isset($_POST['remove_variant'])) {
        $remIdx = intval($_POST['remove_variant']);
        if (isset($p_ids[$remIdx])) unset($p_ids[$remIdx]);
        if (isset($p_colors[$remIdx])) unset($p_colors[$remIdx]);
        if (isset($p_sizes[$remIdx])) unset($p_sizes[$remIdx]);
        if (isset($p_stocks[$remIdx])) unset($p_stocks[$remIdx]);
        if (isset($p_existing_imgs[$remIdx])) unset($p_existing_imgs[$remIdx]);
     
        $p_ids = array_values($p_ids);
        $p_colors = array_values($p_colors);
        $p_sizes = array_values($p_sizes);
        $p_stocks = array_values($p_stocks);
        $p_existing_imgs = array_values($p_existing_imgs);
    }

    if (isset($_POST['add_variant_row'])) {
        $p_ids[] = '';
        $p_colors[] = '';
        $p_sizes[] = '';
        $p_stocks[] = 0;
        $p_existing_imgs[] = '';
    }

    $existingVariants = [];
    $count = max( count($p_ids), count($p_colors), count($p_sizes), count($p_stocks), count($p_existing_imgs) );
    for ($i=0;$i<$count;$i++) {
        $existingVariants[] = [
            'variant_id' => $p_ids[$i] ?? '',
            'color_name' => $p_colors[$i] ?? '',
            'color_image' => $p_existing_imgs[$i] ?? '',
            'size_value' => $p_sizes[$i] ?? '',
            'stock' => $p_stocks[$i] ?? 0,
        ];
    }
}
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
            <label>Main Image</label><br>
            <img src="images/<?php echo htmlspecialchars($product['image']); ?>" style="height:100px;" alt="Main Image">
        </div>

        <div class="form-group mb-3">
            <label>Change Main Image (optional)</label>
            <input type="file" class="form-control" name="img_path">
        </div>

        <div class="form-group mb-3">
            <label>Gallery Images</label>
            <div id="existingGallery" class="d-flex gap-2 flex-wrap mb-2">
                <?php foreach ($galleryImages as $gimg): ?>
                    <div class="position-relative border rounded p-1 text-center" style="width:120px;">
                        <img src="images/<?php echo htmlspecialchars($gimg); ?>" style="width:100%;height:70px;object-fit:cover;display:block;">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="images_to_delete[]" value="<?php echo htmlspecialchars($gimg); ?>" id="delg<?php echo md5($gimg); ?>">
                            <label class="form-check-label" for="delg<?php echo md5($gimg); ?>">Remove</label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="file" name="images[]" id="newGalleryInput" class="form-control mb-2" multiple accept="image/*">
           
        </div>

        <hr>
        <h5>Variants (Color + Size + Stock)</h5>
        <div id="variants-wrapper">
            <?php if (count($existingVariants) > 0): ?>
                <?php foreach ($existingVariants as $idx => $ev): ?>
                    <div class="row g-2 align-items-end variant-row mb-2">
                        <input type="hidden" name="variant_id[]" value="<?php echo htmlspecialchars($ev['variant_id']); ?>">
                        <div class="col-md-3">
                            <label>Color Name</label>
                            <input type="text" name="variant_color[]" class="form-control" placeholder="e.g., Red" required value="<?php echo htmlspecialchars($ev['color_name']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label>Color Image</label>
                            <?php if (!empty($ev['color_image'])): ?>
                                <div class="mb-1 text-center">
                                    <img src="images/<?php echo htmlspecialchars($ev['color_image']); ?>" style="height:48px;object-fit:cover;border-radius:4px;display:block;margin:0 auto;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="variant_color_image[]" class="form-control">
                            <input type="hidden" name="variant_existing_color_image[]" value="<?php echo htmlspecialchars($ev['color_image']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label>Size</label>
                            <input type="text" name="variant_size[]" class="form-control" placeholder="e.g., 38" required value="<?php echo htmlspecialchars($ev['size_value']); ?>">
                        </div>
                        <div class="col-md-2">
                            <label>Stock</label>
                            <input type="number" name="variant_stock[]" class="form-control" min="0" value="<?php echo (int)$ev['stock']; ?>" required>
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="submit" name="remove_variant" value="<?php echo $idx; ?>" formaction="update.php" formnovalidate class="btn btn-danger" onclick="return confirm('Delete this variant? This will remove it from the database if not referenced by orders.');">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="row g-2 align-items-end variant-row mb-2">
                    <div class="col-md-3">
                        <label>Color Name</label>
                        <input type="text" name="variant_color[]" class="form-control" placeholder="e.g., Red" required>
                    </div>
                    <div class="col-md-3">
                        <label>Color Image</label>
                        <input type="file" name="variant_color_image[]" class="form-control">
                        <input type="hidden" name="variant_existing_color_image[]" value="">
                    </div>
                    <div class="col-md-3">
                        <label>Size</label>
                        <input type="text" name="variant_size[]" class="form-control" placeholder="e.g., 38" required>
                    </div>
                    <div class="col-md-2">
                        <label>Stock</label>
                        <input type="number" name="variant_stock[]" class="form-control" min="0" value="0" required>
                    </div>
                    <div class="col-md-1 d-grid">
                        <button type="submit" name="remove_variant" value="0" formaction="update.php" formnovalidate class="btn btn-danger" onclick="return confirm('Delete this variant? This will remove it from the database if not referenced by orders.');">Delete</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <button type="submit" name="add_variant_row" formaction="edit.php" class="btn btn-outline-primary btn-sm">+ Add Variant</button>
        </div>

        <button type="submit" class="btn btn-primary">Update Product</button>
        <a href="../admin/products.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include('../includes/footer.php'); ?>