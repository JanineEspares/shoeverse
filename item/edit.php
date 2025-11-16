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

// Fetch product images (gallery)
$galleryRes = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id = $product_id");
$galleryImages = [];
if ($galleryRes) {
    while ($gi = mysqli_fetch_assoc($galleryRes)) { $galleryImages[] = $gi['image']; }
}

// Fetch variants for this product
$varRes = mysqli_query($conn, "SELECT variant_id, color_name, color_image, size_value, stock FROM product_variants WHERE product_id = " . $product_id . " ORDER BY color_name, size_value");
$existingVariants = [];
if ($varRes && mysqli_num_rows($varRes) > 0) {
    while ($v = mysqli_fetch_assoc($varRes)) {
        $existingVariants[] = $v;
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
                    <div class="position-relative border rounded p-1" style="width:100px;">
                        <img src="images/<?php echo htmlspecialchars($gimg); ?>" style="width:100%;height:70px;object-fit:cover;display:block;">
                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 remove-existing-image" data-filename="<?php echo htmlspecialchars($gimg); ?>">✖</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="file" name="images[]" id="newGalleryInput" class="form-control mb-2" multiple accept="image/*">
            <div id="newGalleryPreview" class="d-flex gap-2 flex-wrap"></div>
        </div>

        <hr>
        <h5>Variants (Color + Size + Stock)</h5>
        <div id="variants-wrapper">
            <?php if (count($existingVariants) > 0): ?>
                <?php foreach ($existingVariants as $ev): ?>
                    <div class="row g-2 align-items-end variant-row mb-2">
                        <input type="hidden" name="variant_id[]" value="<?php echo $ev['variant_id']; ?>">
                        <div class="col-md-3">
                            <label>Color Name</label>
                            <input type="text" name="variant_color[]" class="form-control" placeholder="e.g., Red" required value="<?php echo htmlspecialchars($ev['color_name']); ?>">
                        </div>
                        <div class="col-md-3">
                            <label>Color Image</label>
                            <?php if (!empty($ev['color_image'])): ?>
                                <div class="mb-1">
                                    <img src="images/<?php echo htmlspecialchars($ev['color_image']); ?>" style="height:48px;object-fit:cover;border-radius:4px;">
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
                            <button type="button" class="btn btn-outline-danger remove-variant">✖</button>
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
                        <button type="button" class="btn btn-outline-danger remove-variant">✖</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <button type="button" id="add-variant" class="btn btn-outline-primary btn-sm">+ Add Variant</button>
        </div>

        <button type="submit" class="btn btn-primary">Update Product</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include('../includes/footer.php'); ?>

<script>
// Gallery new files preview with removable items
(function(){
    const newInput = document.getElementById('newGalleryInput');
    const preview = document.getElementById('newGalleryPreview');
    const form = document.querySelector('form');
    if (!newInput) return;

    // Use DataTransfer to manage file list
    let dt = new DataTransfer();

    function renderPreview(){
        preview.innerHTML = '';
        Array.from(dt.files).forEach((file, idx) => {
            const url = URL.createObjectURL(file);
            const wrap = document.createElement('div'); wrap.className = 'position-relative border rounded p-1'; wrap.style.width='100px';
            const img = document.createElement('img'); img.src = url; img.style.width='100%'; img.style.height='70px'; img.style.objectFit='cover';
            const btn = document.createElement('button'); btn.type='button'; btn.className='btn btn-sm btn-danger position-absolute top-0 end-0 remove-new-image'; btn.textContent='✖';
            btn.addEventListener('click', ()=>{
                // remove file at idx
                const newDt = new DataTransfer();
                Array.from(dt.files).forEach((f,i)=>{ if(i!==idx) newDt.items.add(f); });
                dt = newDt; newInput.files = dt.files; renderPreview();
            });
            wrap.appendChild(img); wrap.appendChild(btn); preview.appendChild(wrap);
        });
    }

    newInput.addEventListener('change', function(e){
        // add selected files to dt
        Array.from(this.files).forEach(f=> dt.items.add(f));
        this.value='';
        newInput.files = dt.files;
        renderPreview();
    });

    // Existing images removal: add hidden inputs named images_to_delete[] when removed
    document.querySelectorAll('.remove-existing-image').forEach(btn=>{
        btn.addEventListener('click', function(){
            const filename = this.getAttribute('data-filename');
            const hidden = document.createElement('input'); hidden.type='hidden'; hidden.name='images_to_delete[]'; hidden.value=filename;
            form.appendChild(hidden);
            this.closest('[data-filename]')?.remove?.();
            // fallback: remove parent node
            this.parentElement.remove();
        });
    });

    // Variant add/remove
    const wrapper = document.getElementById('variants-wrapper');
    const addBtn = document.getElementById('add-variant');
    function bindRemove(btn){ btn.addEventListener('click', function(){ const rows = wrapper.querySelectorAll('.variant-row'); if(rows.length>1) this.closest('.variant-row').remove(); }); }
    wrapper.querySelectorAll('.remove-variant').forEach(bindRemove);
    addBtn.addEventListener('click', function(){
        const first = wrapper.querySelector('.variant-row');
        const clone = first.cloneNode(true);
        // clear inputs
        clone.querySelectorAll('input').forEach(inp=>{ if(inp.type==='file'){ inp.value=''; } else if(inp.type==='hidden' && inp.name==='variant_existing_color_image[]'){ inp.value=''; } else { inp.value=''; } });
        wrapper.appendChild(clone);
        bindRemove(clone.querySelector('.remove-variant'));
    });
})();
</script>