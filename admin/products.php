<?php
include '../includes/config.php';
include '../includes/adminHeader.php';

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
    } elseif (empty($_FILES['images']['name'][0])) {
        echo "<script>alert('Please upload at least one product image.');</script>";
    } elseif (count($var_color) == 0) {
        echo "<script>alert('Add at least one variant (color, size, stock).');</script>";
    } else {
        // Prevent duplicate product names under the same brand
        $check = mysqli_query($conn, "SELECT * FROM products WHERE product_name = '$product_name' AND brand_id = '$brand_id'");
        if (mysqli_num_rows($check) > 0) {
            echo "<script>alert('Product with this name already exists under the selected brand.');</script>";
        } else {
            $target_dir = "../item/images/";
            $allowed_types = ['jpg','jpeg','png','webp'];

            // Upload main product images (multiple)
            $uploaded_images = [];
            foreach ($_FILES['images']['name'] as $idx => $name) {
                if (empty($name)) continue;
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_types)) continue;
                $newName = time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                $dest = $target_dir . $newName;
                if (move_uploaded_file($_FILES['images']['tmp_name'][$idx], $dest)) {
                    $uploaded_images[] = $newName;
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

                // Insert product (size not used now; set to 0)
                $insert_query = "
                    INSERT INTO products (brand_id, category_id, product_name, size, price, stock, description, image, date_added)
                    VALUES ('$brand_id', '$category_id', '$product_name', 0, '$price', '$total_stock', '$description', '$main_image', '$date_added')
                ";

                if (mysqli_query($conn, $insert_query)) {
                    $product_id = mysqli_insert_id($conn);

                    // Save extra images
                    foreach ($uploaded_images as $img) {
                        mysqli_query($conn, "INSERT INTO product_images (product_id, image) VALUES ($product_id, '" . mysqli_real_escape_string($conn, $img) . "')");
                    }

                    // Handle variant uploads and insert
                    $colorImages = isset($_FILES['variant_color_image']) ? $_FILES['variant_color_image'] : null;
                    for ($i = 0; $i < count($var_color); $i++) {
                        $cname = trim($var_color[$i]);
                        $sz = trim($var_size[$i]);
                        $stk = intval($var_stock[$i]);
                        if ($cname === '' || $sz === '' || $stk < 0) continue;

                        $colImgNameSaved = NULL;
                        if ($colorImages && !empty($colorImages['name'][$i])) {
                            $cExt = strtolower(pathinfo($colorImages['name'][$i], PATHINFO_EXTENSION));
                            if (in_array($cExt, $allowed_types)) {
                                $cNew = 'color_' . time() . '_' . mt_rand(1000,9999) . '.' . $cExt;
                                $cDest = $target_dir . $cNew;
                                if (move_uploaded_file($colorImages['tmp_name'][$i], $cDest)) {
                                    $colImgNameSaved = $cNew;
                                }
                            }
                        }

                        $cnameEsc = mysqli_real_escape_string($conn, $cname);
                        $szEsc = mysqli_real_escape_string($conn, $sz);
                        $imgVal = $colImgNameSaved ? "'".mysqli_real_escape_string($conn, $colImgNameSaved)."'" : 'NULL';
                        mysqli_query($conn, "INSERT INTO product_variants (product_id, color_name, color_image, size_value, stock) VALUES ($product_id, '$cnameEsc', $imgVal, '$szEsc', $stk)");
                    }

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
        mysqli_query($conn, "DELETE FROM product_variants WHERE product_id = '$delete_id'");
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
                        <label>Price (base)</label>
                        <input type="number" name="price" step="0.01" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label>Product Images</label>
                    <input type="file" id="addGalleryInput" name="images[]" class="form-control" accept="image/*" multiple required>
                    <small class="text-muted">You can select multiple images.</small>
                    <div id="addGalleryPreview" class="d-flex gap-2 flex-wrap mt-2"></div>
                </div>

                <hr>
                <h5>Variants (Color + Size + Stock)</h5>
                <div id="variants-wrapper">
                    <div class="row g-2 align-items-end variant-row mb-2">
                        <div class="col-md-3">
                            <label>Color Name</label>
                            <input type="text" name="variant_color[]" class="form-control" placeholder="e.g., Red" required>
                        </div>
                        <div class="col-md-3">
                            <label>Color Image</label>
                            <input type="file" name="variant_color_image[]" class="form-control" accept="image/*">
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
                            <button type="button" class="btn btn-outline-danger remove-variant">✖</button>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="button" id="add-variant" class="btn btn-outline-primary btn-sm">+ Add Variant</button>
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

<script>
// Simple JS to duplicate/remove variant rows
(function(){
  const wrapper = document.getElementById('variants-wrapper');
  const addBtn = document.getElementById('add-variant');

  function bindRemove(btn){
    btn.addEventListener('click', function(){
      const row = this.closest('.variant-row');
      if (document.querySelectorAll('.variant-row').length > 1) row.remove();
    });
  }

  // bind for first row
  bindRemove(wrapper.querySelector('.remove-variant'));

  addBtn.addEventListener('click', function(){
    const first = wrapper.querySelector('.variant-row');
    const clone = first.cloneNode(true);
    // clear inputs
    clone.querySelectorAll('input').forEach(inp => { if(inp.type==='file'){ inp.value=''; } else { inp.value=''; } });
    wrapper.appendChild(clone);
    bindRemove(clone.querySelector('.remove-variant'));
  });
})();

// Preview for new gallery images on Add Product
(function(){
    const input = document.getElementById('addGalleryInput');
    const preview = document.getElementById('addGalleryPreview');
    if (!input) return;
    let dt = new DataTransfer();
    function render(){ preview.innerHTML=''; Array.from(dt.files).forEach((file, idx)=>{
            const url = URL.createObjectURL(file);
            const wrap = document.createElement('div'); wrap.className='position-relative border rounded p-1'; wrap.style.width='100px';
            const img = document.createElement('img'); img.src=url; img.style.width='100%'; img.style.height='70px'; img.style.objectFit='cover';
            const btn = document.createElement('button'); btn.type='button'; btn.className='btn btn-sm btn-danger position-absolute top-0 end-0'; btn.textContent='✖';
            btn.addEventListener('click', ()=>{ const nd=new DataTransfer(); Array.from(dt.files).forEach((f,i)=>{ if(i!==idx) nd.items.add(f); }); dt=nd; input.files=dt.files; render(); });
            wrap.appendChild(img); wrap.appendChild(btn); preview.appendChild(wrap);
    }); }
    input.addEventListener('change', function(){ Array.from(this.files).forEach(f=>dt.items.add(f)); this.value=''; input.files=dt.files; render(); });
})();
</script>

<?php include '../includes/footer.php'; ?>