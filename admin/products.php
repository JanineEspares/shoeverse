<?php
include '../includes/config.php';
include '../includes/adminHeader.php';

// Small styles to ensure file-input previews display consistently (preview shown above the chooser)
echo "<style>
.variant-preview{margin-bottom:6px;display:block}
.variant-preview img{width:80px;height:80px;object-fit:cover;border:1px solid #ddd;border-radius:4px;display:block}
.gallery-preview img{width:80px;height:80px;object-fit:cover;border:1px solid #ddd;border-radius:4px;display:block}
</style>";

// ✅ Restrict access to Admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    echo "<div class='alert alert-danger text-center'>Access denied. Admins only.</div>";
    include '../includes/footer.php';
    exit();
}

// Ensure variant tables exist (simple, inline migration)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS product_images (
  image_id INT NOT NULL AUTO_INCREMENT,
  product_id INT NOT NULL,
  image VARCHAR(255) NOT NULL,
  PRIMARY KEY(image_id),
  INDEX (product_id),
  CONSTRAINT fk_prodimg_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS product_variants (
  variant_id INT NOT NULL AUTO_INCREMENT,
  product_id INT NOT NULL,
  color_name VARCHAR(100) NOT NULL,
  color_image VARCHAR(255) NULL,
  size_value VARCHAR(20) NOT NULL,
  stock INT NOT NULL,
  PRIMARY KEY(variant_id),
  INDEX (product_id),
  CONSTRAINT fk_variant_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB");

// ✅ Handle Add Product
// Temp upload directory and session arrays (ensure available early)
$tmp_dir = __DIR__ . '/../item/tmp_uploads/';
if (!is_dir($tmp_dir)) @mkdir($tmp_dir, 0777, true);
if (!isset($_SESSION['tmp_product_images'])) $_SESSION['tmp_product_images'] = [];
if (!isset($_SESSION['tmp_variant_color_images'])) $_SESSION['tmp_variant_color_images'] = [];
// ✅ Handle Add Product
if (isset($_POST['add_product'])) {
    $brand_id = intval($_POST['brand_id']);
    $category_id = intval($_POST['category_id']);
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $price = floatval($_POST['price']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $date_added = date('Y-m-d');

    // Variants arrays
    $var_color = isset($_POST['variant_color']) ? $_POST['variant_color'] : [];
    $var_size  = isset($_POST['variant_size']) ? $_POST['variant_size'] : [];
    $var_stock = isset($_POST['variant_stock']) ? $_POST['variant_stock'] : [];

    if (empty($brand_id) || empty($category_id) || empty($product_name) || empty($price)) {
        echo "<script>alert('Please fill in all required fields.');</script>";
    } elseif (empty($_FILES['images']['name'][0]) && empty($_SESSION['tmp_product_images'])) {
        // allow previously-temporarily-uploaded images
        echo "<script>alert('Please upload at least one product image.');</script>";
    } elseif (count($var_color) == 0) {
        echo "<script>alert('Add at least one variant (color, size, stock).');</script>";
    } else {
        // Prevent duplicate product names under the same brand
        $check = mysqli_query($conn, "SELECT * FROM products WHERE product_name = '$product_name' AND brand_id = '$brand_id'");
        if (mysqli_num_rows($check) > 0) {
            echo "<script>alert('Product with this name already exists under the selected brand.');</script>";
        } else {
            $target_dir_web = "../item/images/";
            $target_dir_fs = __DIR__ . '/../item/images/';
            if (!is_dir($target_dir_fs)) @mkdir($target_dir_fs, 0777, true);
            $allowed_types = ['jpg','jpeg','png','webp'];

            // Collect uploaded images from session-temp (first) then from posted files
            $uploaded_images = [];

            // Move session-temp images to permanent folder
            if (!empty($_SESSION['tmp_product_images'])) {
                foreach ($_SESSION['tmp_product_images'] as $tmpName) {
                    $src = $tmp_dir . $tmpName;
                    if (!file_exists($src)) continue;
                    $ext = strtolower(pathinfo($tmpName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed_types)) continue;
                    $newName = time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                    $destFs = $target_dir_fs . $newName;
                    if (@rename($src, $destFs)) {
                        $uploaded_images[] = $newName;
                    } else {
                        // fallback copy
                        if (@copy($src, $destFs)) {
                            $uploaded_images[] = $newName;
                            @unlink($src);
                        }
                    }
                }
            }

            // Upload main product images (multiple) from fresh POST
            if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                foreach ($_FILES['images']['name'] as $idx => $name) {
                    if (empty($name)) continue;
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed_types)) continue;
                    $newName = time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                    $destFs = $target_dir_fs . $newName;
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$idx], $destFs)) {
                        $uploaded_images[] = $newName;
                    }
                }
            }

            if (count($uploaded_images) == 0) {
                echo "<script>alert('Failed to upload images.');</script>";
            } else {
                // Compute total stock from variants
                $total_stock = 0;
                foreach ($var_stock as $s) { $total_stock += intval($s); }

                // Use first uploaded image as product main image to satisfy NOT NULL
                $main_image = $uploaded_images[0];

                // Insert product. Some schemas may not have a `size` column — detect and adapt.
                $has_size_col = false;
                $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM `products` LIKE 'size'");
                if ($colCheck && mysqli_num_rows($colCheck) > 0) $has_size_col = true;

                if ($has_size_col) {
                    $insert_query = "INSERT INTO products (brand_id, category_id, product_name, size, price, stock, description, image, date_added) VALUES ('$brand_id', '$category_id', '$product_name', 0, '$price', '$total_stock', '$description', '$main_image', '$date_added')";
                } else {
                    $insert_query = "INSERT INTO products (brand_id, category_id, product_name, price, stock, description, image, date_added) VALUES ('$brand_id', '$category_id', '$product_name', '$price', '$total_stock', '$description', '$main_image', '$date_added')";
                }

                if (mysqli_query($conn, $insert_query)) {
                    $product_id = mysqli_insert_id($conn);

                    // Save extra images
                    foreach ($uploaded_images as $img) {
                        mysqli_query($conn, "INSERT INTO product_images (product_id, image) VALUES ($product_id, '" . mysqli_real_escape_string($conn, $img) . "')");
                    }

                    // Handle variant uploads and insert. Prefer new POSTed files, else use session-temp variant images.
                    $colorImages = isset($_FILES['variant_color_image']) ? $_FILES['variant_color_image'] : null;
                    for ($i = 0; $i < count($var_color); $i++) {
                        $cname = trim($var_color[$i]);
                        $sz = trim($var_size[$i]);
                        $stk = intval($var_stock[$i]);
                        if ($cname === '' || $sz === '' || $stk < 0) continue;

                        $colImgNameSaved = NULL;

                        // First, if a new file was uploaded in this submit
                        if ($colorImages && !empty($colorImages['name'][$i])) {
                            $cExt = strtolower(pathinfo($colorImages['name'][$i], PATHINFO_EXTENSION));
                            if (in_array($cExt, $allowed_types)) {
                                $cNew = 'color_' . time() . '_' . mt_rand(1000,9999) . '.' . $cExt;
                                $cDestFs = $target_dir_fs . $cNew;
                                if (move_uploaded_file($colorImages['tmp_name'][$i], $cDestFs)) {
                                    $colImgNameSaved = $cNew;
                                }
                            }
                        }

                        // Else, if a temp color image exists for this row from prior intermediate posts
                        if ($colImgNameSaved === NULL && isset($_SESSION['tmp_variant_color_images'][$i])) {
                            $tmpName = $_SESSION['tmp_variant_color_images'][$i];
                            $src = $tmp_dir . $tmpName;
                            if (file_exists($src)) {
                                $cExt = strtolower(pathinfo($tmpName, PATHINFO_EXTENSION));
                                if (in_array($cExt, $allowed_types)) {
                                    $cNew = 'color_' . time() . '_' . mt_rand(1000,9999) . '.' . $cExt;
                                    $cDestFs = $target_dir_fs . $cNew;
                                    if (@rename($src, $cDestFs)) {
                                        $colImgNameSaved = $cNew;
                                    } else {
                                        if (@copy($src, $cDestFs)) {
                                            $colImgNameSaved = $cNew;
                                            @unlink($src);
                                        }
                                    }
                                }
                            }
                        }

                        $cnameEsc = mysqli_real_escape_string($conn, $cname);
                        $szEsc = mysqli_real_escape_string($conn, $sz);
                        $imgVal = $colImgNameSaved ? "'".mysqli_real_escape_string($conn, $colImgNameSaved)."'" : 'NULL';
                        mysqli_query($conn, "INSERT INTO product_variants (product_id, color_name, color_image, size_value, stock) VALUES ($product_id, '$cnameEsc', $imgVal, '$szEsc', $stk)");
                    }

                    // Clear temp session buffers
                    $_SESSION['tmp_product_images'] = [];
                    $_SESSION['tmp_variant_color_images'] = [];

                    echo "<script>alert('Product with variants added successfully!'); window.location.href='products.php';</script>";
                } else {
                    echo "<div class='alert alert-danger text-center'>Database Error: " . mysqli_error($conn) . "</div>";
                }
            }
        }
    }
}

// ✅ Handle Delete Product
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    // Delete associated images (main + gallery + color images)
    $result = mysqli_query($conn, "SELECT image FROM products WHERE product_id = '$delete_id'");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $image_path = "../item/images/" . $row['image'];
        if (!empty($row['image']) && file_exists($image_path)) unlink($image_path);
    }

    $gallery = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id = '$delete_id'");
    if ($gallery) {
        while ($g = mysqli_fetch_assoc($gallery)) {
            $path = "../item/images/" . $g['image'];
            if (!empty($g['image']) && file_exists($path)) unlink($path);
        }
        mysqli_query($conn, "DELETE FROM product_images WHERE product_id = '$delete_id'");
    }

    $vars = mysqli_query($conn, "SELECT color_image FROM product_variants WHERE product_id = '$delete_id'");
    if ($vars) {
        while ($v = mysqli_fetch_assoc($vars)) {
            if (!empty($v['color_image'])) {
                $path = "../item/images/" . $v['color_image'];
                if (file_exists($path)) unlink($path);
            }
        }

        $delVars = mysqli_query($conn, "DELETE FROM product_variants WHERE product_id = '$delete_id'");
        if (!$delVars) {
            // MySQL error 1451: Cannot delete or update a parent row: a foreign key constraint fails
            if (mysqli_errno($conn) == 1451) {
                echo "<div class='container mt-4'><div class='alert alert-warning text-center'>Cannot delete product because customers still have orders referencing its variants. Please cancel or resolve those orders before deleting this product.<br><a href='products.php' class='btn btn-sm btn-primary mt-2'>Back to products</a></div></div>";
                include '../includes/footer.php';
                exit();
            } else {
                echo "<div class='alert alert-danger text-center'>Error deleting product variants: " . mysqli_error($conn) . "</div>";
                include '../includes/footer.php';
                exit();
            }
        }
    }

    $delete = mysqli_query($conn, "DELETE FROM products WHERE product_id = '$delete_id'");
    if ($delete) {
        echo "<script>alert('Product deleted successfully!'); window.location.href='products.php';</script>";
    } else {
        // If delete fails because of FK constraints (leftover references), show friendly message
        if (mysqli_errno($conn) == 1451) {
            echo "<div class='container mt-4'><div class='alert alert-warning text-center'>Cannot delete product because customers still have orders referencing its variants. Please cancel or resolve those orders before deleting this product.<br><a href='products.php' class='btn btn-sm btn-primary mt-2'>Back to products</a></div></div>";
        } else {
            echo "<div class='alert alert-danger text-center'>Error deleting product: " . mysqli_error($conn) . "</div>";
        }
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

// Prepare variant rows for PHP-only add/remove behavior (no JS)
$posted_variant_colors = isset($_POST['variant_color']) && is_array($_POST['variant_color']) ? $_POST['variant_color'] : [''];
$posted_variant_sizes = isset($_POST['variant_size']) && is_array($_POST['variant_size']) ? $_POST['variant_size'] : [''];
$posted_variant_stocks = isset($_POST['variant_stock']) && is_array($_POST['variant_stock']) ? $_POST['variant_stock'] : ['0'];

// Handle remove-variant action (a submit button per row with name "remove_variant")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_variant'])) {
    $remIdx = intval($_POST['remove_variant']);
    if (isset($posted_variant_colors[$remIdx])) unset($posted_variant_colors[$remIdx]);
    if (isset($posted_variant_sizes[$remIdx])) unset($posted_variant_sizes[$remIdx]);
    if (isset($posted_variant_stocks[$remIdx])) unset($posted_variant_stocks[$remIdx]);
    // reindex arrays
    $posted_variant_colors = array_values($posted_variant_colors);
    $posted_variant_sizes = array_values($posted_variant_sizes);
    $posted_variant_stocks = array_values($posted_variant_stocks);
}

// Handle add-variant-row action (adds an empty variant row and re-renders form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_variant_row'])) {
    $posted_variant_colors[] = '';
    $posted_variant_sizes[] = '';
    $posted_variant_stocks[] = 0;
}

// --- Temporary upload support: keep uploaded images across add/remove variant posts ---
// Temp uploads are stored under item/tmp_uploads and referenced in session so file inputs
// do not need to be reselected while manipulating variant rows.
if (!isset($_SESSION['tmp_product_images'])) $_SESSION['tmp_product_images'] = [];
if (!isset($_SESSION['tmp_variant_color_images'])) $_SESSION['tmp_variant_color_images'] = [];

$tmp_dir = __DIR__ . '/../item/tmp_uploads/';
if (!is_dir($tmp_dir)) @mkdir($tmp_dir, 0777, true);

// Reindex tmp variant images to align with current posted variant rows (after add/remove)
$reindexed = [];
foreach ($posted_variant_colors as $i => $v) {
    if (isset($_SESSION['tmp_variant_color_images'][$i])) $reindexed[$i] = $_SESSION['tmp_variant_color_images'][$i];
}
$_SESSION['tmp_variant_color_images'] = $reindexed;

// If this is an intermediate POST (not the final add_product submit), persist any uploaded
// files into the temp folder and record them in session for subsequent postbacks.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['add_product'])) {
    // Product gallery images
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        foreach ($_FILES['images']['name'] as $idx => $name) {
            if (empty($name)) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed_types = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed_types)) continue;
            $tmpName = 'tmp_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
            $dest = $tmp_dir . $tmpName;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$idx], $dest)) {
                $_SESSION['tmp_product_images'][] = $tmpName;
            }
        }
    }

    // Variant color images (indexed by variant row)
    if (isset($_FILES['variant_color_image']) && is_array($_FILES['variant_color_image']['name'])) {
        foreach ($_FILES['variant_color_image']['name'] as $i => $name) {
            if (empty($name)) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed_types = ['jpg','jpeg','png','webp'];
            if (!in_array($ext, $allowed_types)) continue;
            $tmpName = 'tmp_color_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
            $dest = $tmp_dir . $tmpName;
            if (move_uploaded_file($_FILES['variant_color_image']['tmp_name'][$i], $dest)) {
                // record by index so it stays with the row
                $_SESSION['tmp_variant_color_images'][$i] = $tmpName;
            }
        }
    }
}

// Handle explicit Cancel action: remove temp files and clear session buffers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    // remove product temp images
    if (!empty($_SESSION['tmp_product_images'])) {
        foreach ($_SESSION['tmp_product_images'] as $f) {
            $p = $tmp_dir . $f;
            if (file_exists($p)) @unlink($p);
        }
    }
    // remove variant temp images
    if (!empty($_SESSION['tmp_variant_color_images'])) {
        foreach ($_SESSION['tmp_variant_color_images'] as $f) {
            $p = $tmp_dir . $f;
            if (file_exists($p)) @unlink($p);
        }
    }
    $_SESSION['tmp_product_images'] = [];
    $_SESSION['tmp_variant_color_images'] = [];
    // redirect to clear POST
    header('Location: products.php');
    exit();
}

// Auto-clean temp files when arriving on the page via GET (assume previous editing session ended)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['tmp_product_images'])) {
        foreach ($_SESSION['tmp_product_images'] as $f) {
            $p = $tmp_dir . $f;
            if (file_exists($p)) @unlink($p);
        }
        $_SESSION['tmp_product_images'] = [];
    }
    if (!empty($_SESSION['tmp_variant_color_images'])) {
        foreach ($_SESSION['tmp_variant_color_images'] as $f) {
            $p = $tmp_dir . $f;
            if (file_exists($p)) @unlink($p);
        }
        $_SESSION['tmp_variant_color_images'] = [];
    }
}

// Preserve other product-level fields across POSTs (so add/remove variant doesn't wipe inputs)
$posted_brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : null;
$posted_category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
$posted_product_name = isset($_POST['product_name']) ? $_POST['product_name'] : '';
$posted_price = isset($_POST['price']) ? $_POST['price'] : '';
$posted_description = isset($_POST['description']) ? $_POST['description'] : '';
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">🛍 Manage Products</h2>

    <!-- ✅ Add Product Form (with variants) -->
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
                                <option value="<?= $b['brand_id'] ?>" <?php echo ($posted_brand_id !== null && $posted_brand_id == $b['brand_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($b['brand_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label>Category</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?= $c['category_id'] ?>" <?php echo ($posted_category_id !== null && $posted_category_id == $c['category_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Product Name</label>
                    <input type="text" name="product_name" class="form-control" required value="<?php echo htmlspecialchars($posted_product_name); ?>">
                </div>

                <div class="row mb-3">
                    <div class="col">
                        <label>Price (base)</label>
                        <input type="number" name="price" step="0.01" class="form-control" required value="<?php echo htmlspecialchars($posted_price); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($posted_description); ?></textarea>
                </div>

                <div class="mb-3">
                    <label>Product Images</label>
                    <input type="file" id="addGalleryInput" name="images[]" class="form-control" accept="image/*" multiple>
                    <small class="text-muted">You can select multiple images. Previously uploaded images will be kept while editing variants.</small>
                    <div id="addGalleryPreview" class="d-flex gap-2 flex-wrap mt-2">
                        <?php // Render previews for temp-uploaded images stored in session ?>
                        <?php if (!empty($_SESSION['tmp_product_images'])): ?>
                            <?php foreach ($_SESSION['tmp_product_images'] as $ti): ?>
                                <?php $tpath = '../item/tmp_uploads/' . htmlspecialchars($ti); ?>
                                <?php if (file_exists(__DIR__ . '/../item/tmp_uploads/' . $ti)): ?>
                                    <div style="width:80px;height:80px;overflow:hidden;border:1px solid #ddd;border-radius:4px;">
                                        <img src="<?= $tpath ?>" style="width:100%;height:100%;object-fit:cover;" alt="preview">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <hr>
                <h5>Variants (Color + Size + Stock)</h5>
                <div>
                    <!-- PHP-driven variant rows (no JavaScript). Each row has a Remove button that submits the form. -->
                    <?php $vcount = max(1, count($posted_variant_colors)); ?>
                    <?php for ($vi = 0; $vi < $vcount; $vi++): ?>
                        <?php $vcolor = htmlspecialchars($posted_variant_colors[$vi] ?? ''); ?>
                        <?php $vsize = htmlspecialchars($posted_variant_sizes[$vi] ?? ''); ?>
                        <?php $vstock = intval($posted_variant_stocks[$vi] ?? 0); ?>
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-3">
                                <label>Color Name</label>
                                <input type="text" name="variant_color[]" class="form-control" placeholder="e.g., Red" required value="<?php echo $vcolor; ?>">
                            </div>
                            <div class="col-md-3">
                                <label>Color Image</label>
                                <?php // preview for temp color image if present (rendered above the file input) ?>
                                <?php if (!empty($_SESSION['tmp_variant_color_images'][$vi]) && file_exists(__DIR__ . '/../item/tmp_uploads/' . $_SESSION['tmp_variant_color_images'][$vi])): ?>
                                    <div class="variant-preview">
                                        <img src="<?= '../item/tmp_uploads/' . htmlspecialchars($_SESSION['tmp_variant_color_images'][$vi]) ?>" alt="color preview">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="variant_color_image[]" class="form-control" accept="image/*">
                            </div>
                            <div class="col-md-3">
                                <label>Size</label>
                                <input type="text" name="variant_size[]" class="form-control" placeholder="e.g., 38" required value="<?php echo $vsize; ?>">
                            </div>
                            <div class="col-md-2">
                                <label>Stock</label>
                                <input type="number" name="variant_stock[]" class="form-control" min="0" value="<?php echo $vstock; ?>" required>
                            </div>
                            <div class="col-md-1 d-grid">
                                <button type="submit" name="remove_variant" value="<?php echo $vi; ?>" class="btn btn-outline-danger">Remove</button>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="mb-3">
                    <button type="submit" name="add_variant_row" class="btn btn-outline-primary btn-sm">+ Add Variant</button>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="add_product" class="btn btn-success w-100">➕ Add Product</button>
                    <button type="submit" name="cancel" class="btn btn-secondary">Cancel</button>
                </div>
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
                        <th>Price</th>
                        <th>Total Stock</th>
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
                                <td>₱<?= number_format($row['price'], 2) ?></td>
                                <td><?= $row['stock'] ?></td>
                                <td><?= $row['date_added'] ?></td>
                                <td>
                                    <a href="../item/edit.php?id=<?= $row['product_id'] ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
                                    <a href="?delete=<?= $row['product_id'] ?>" onclick="return confirm('Delete this product?');" class="btn btn-sm btn-danger">🗑️ Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9">No products found.</td></tr>
                    <?php endif; ?>
                </tbody>
                                        </table>
                                </div>
                        </div>
                </div>

                <?php include '../includes/footer.php'; ?>