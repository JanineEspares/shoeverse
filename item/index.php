<?php
// shoeverse/item/index.php
// Handles both product listing and single product detail view

include '../includes/config.php';
include '../includes/header.php';

// If an ID is provided, show detail view
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $product_id = intval($_GET['id']);

    // Fetch product
    $stmt = mysqli_prepare($conn,
        "SELECT p.*, b.brand_name, c.category_name
         FROM products p
         JOIN brands b ON p.brand_id = b.brand_id
         JOIN category c ON p.category_id = c.category_id
         WHERE p.product_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res && mysqli_num_rows($res) > 0) {
        $product = mysqli_fetch_assoc($res);

        // Fetch gallery images
        $images = [];
        if (!empty($product['image'])) { $images[] = $product['image']; }
        $imgsRes = mysqli_query($conn, "SELECT image FROM product_images WHERE product_id = " . $product_id);
        if ($imgsRes) {
            while ($img = mysqli_fetch_assoc($imgsRes)) { $images[] = $img['image']; }
        }
        if (count($images) === 0) {
            $images[] = 'https://via.placeholder.com/450x450?text=ShoeVerse';
        }

        // Fetch variants
        $varRes = mysqli_query($conn, "SELECT variant_id, color_name, color_image, size_value, stock FROM product_variants WHERE product_id = " . $product_id . " ORDER BY color_name, size_value");
        $colors = [];
        $sizesByColor = [];
        $stockMap = [];
        $variantMap = [];
        $colorImage = [];
        if ($varRes && mysqli_num_rows($varRes) > 0) {
            while ($v = mysqli_fetch_assoc($varRes)) {
                $c = $v['color_name'];
                if (!in_array($c, $colors)) $colors[] = $c;
                if (!isset($sizesByColor[$c])) $sizesByColor[$c] = [];
                if (!in_array($v['size_value'], $sizesByColor[$c])) $sizesByColor[$c][] = $v['size_value'];
                $stockMap[$c.'|'.$v['size_value']] = (int)$v['stock'];
                $variantMap[$c.'|'.$v['size_value']] = (int)$v['variant_id'];
                // remember color image per color
                if (!isset($colorImage[$c]) && !empty($v['color_image'])) {
                    $colorImage[$c] = $v['color_image'];
                }
            }
        }
        ?>
        <div class="row">
          <div class="col-md-6">
            <div class="text-center mb-3">
              <img id="mainImage" src="<?php echo (strpos($images[0],'http')===0? $images[0] : 'images/'.htmlspecialchars($images[0])); ?>" 
                   alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                   class="img-fluid rounded shadow-sm" 
                   style="max-height: 450px; object-fit: cover;">
            </div>
            <?php if (count($images) > 1): ?>
              <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($images as $im): ?>
                  <img class="thumb border rounded" src="<?php echo (strpos($im,'http')===0? $im : 'images/'.htmlspecialchars($im)); ?>" style="width:70px;height:70px;object-fit:cover;cursor:pointer;" onclick="document.getElementById('mainImage').src=this.src">
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="col-md-6">
            <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>
            <p class="text-muted mb-1">
              Brand: <strong><?php echo htmlspecialchars($product['brand_name']); ?></strong><br>
              Category: <strong><?php echo htmlspecialchars($product['category_name']); ?></strong>
            </p>
            <h4 class="text-primary mb-3">₱<?php echo number_format($product['price'], 2); ?></h4>
            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

            <?php
            // Only show variant selectors and Add-to-Cart to non-admin users (customers).
            // Admins should see product information only (no buttons, selects or forms).
            $isAdmin = isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin';
            if (!$isAdmin):
            ?>
              <?php if (!empty($colors)): ?>
                <div class="mb-3">
                  <label class="form-label">Color</label>
                  <div id="colorRow" class="d-flex flex-wrap gap-2">
                    <?php foreach ($colors as $c): $ci = isset($colorImage[$c]) ? $colorImage[$c] : null; ?>
                      <button type="button" class="btn btn-outline-secondary btn-sm color-choice" data-color="<?php echo htmlspecialchars($c); ?>">
                        <?php if ($ci): ?>
                          <img src="images/<?php echo htmlspecialchars($ci); ?>" style="width:28px;height:28px;object-fit:cover;border-radius:4px;vertical-align:middle;" alt="">
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($c); ?></span>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label">Size</label>
                  <select id="sizeSelect" class="form-select" disabled>
                    <option value="">Select size</option>
                  </select>
                </div>

                <div class="mb-2 text-muted">Available stock: <span id="stockInfo">—</span></div>
              <?php endif; ?>

              <form action="../cart/cart_update.php" method="GET" class="mt-3">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="id" value="<?php echo $product['product_id']; ?>">
                <input type="hidden" id="variantIdField" name="variant_id" value="">
                <div class="input-group mb-3" style="max-width: 280px;">
                  <input id="qtyInput" type="number" name="quantity" class="form-control" value="1" min="1" placeholder="Qty">
                  <button type="submit" class="btn btn-primary">Add to Cart</button>
                </div>
              </form>

            <?php else: /* Admin read-only view: show colors, sizes and stock as information (no buttons, selects, forms) */ ?>
              <?php if (!empty($colors)): ?>
                <div class="mb-3">
                  <label class="form-label">Color / Sizes (read-only)</label>
                  <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($colors as $c): $ci = isset($colorImage[$c]) ? $colorImage[$c] : null; ?>
                      <div class="border rounded p-2 text-center" style="min-width:140px;">
                        <?php if ($ci): ?>
                          <img src="images/<?php echo htmlspecialchars($ci); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:6px;" alt="">
                        <?php endif; ?>
                        <div class="fw-bold mt-2"><?php echo htmlspecialchars($c); ?></div>
                        <div class="mt-2 text-start">
                          <?php if (isset($sizesByColor[$c]) && count($sizesByColor[$c])>0): ?>
                            <?php foreach ($sizesByColor[$c] as $sz):
                              $key = $c . '|' . $sz;
                              $stk = isset($stockMap[$key]) ? (int)$stockMap[$key] : 0;
                            ?>
                              <div class="d-flex align-items-center justify-content-between bg-light border rounded px-2 py-1 mb-1">
                                <small class="me-2">Size: <strong><?php echo htmlspecialchars($sz); ?></strong></small>
                                <small class="text-muted">Stock: <strong><?php echo $stk; ?></strong></small>
                              </div>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <div class="text-muted small">No sizes</div>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php else: ?>
                <div class="mb-3 text-muted">No variant information available.</div>
              <?php endif; ?>
            <?php endif; /* end role-based UI */ ?>

            <a href="../index.php" class="btn btn-outline-secondary">← Back to Shop</a>
          </div>
        </div>

        <script>
        (function(){
          const sizesByColor = <?php echo json_encode($sizesByColor ?? []); ?>;
          const stockMap = <?php echo json_encode($stockMap ?? []); ?>;
          const variantMap = <?php echo json_encode($variantMap ?? []); ?>;
          const sizeSel = document.getElementById('sizeSelect');
          const stockEl = document.getElementById('stockInfo');
          const qty = document.getElementById('qtyInput');
          const varField = document.getElementById('variantIdField');
          let chosenColor = null;

          function setSizes(color){
            sizeSel.innerHTML = '<option value="">Select size</option>';
            if(!color || !sizesByColor[color]){ sizeSel.disabled = true; stockEl.textContent = '—'; qty.removeAttribute('max'); varField.value=''; return; }
            sizeSel.disabled = false;
            sizesByColor[color].forEach(sz=>{
              const opt = document.createElement('option');
              opt.value = sz; opt.textContent = sz; sizeSel.appendChild(opt);
            });
          }

          document.querySelectorAll('.color-choice').forEach(btn=>{
            btn.addEventListener('click', function(){
              document.querySelectorAll('.color-choice').forEach(b=>b.classList.remove('active'));
              this.classList.add('active');
              chosenColor = this.getAttribute('data-color');
              setSizes(chosenColor);
              stockEl.textContent = '—';
              qty.value = 1; qty.removeAttribute('max');
              varField.value = '';
            });
          });

          sizeSel && sizeSel.addEventListener('change', function(){
            const key = (chosenColor||'') + '|' + (this.value||'');
            const st = stockMap[key] || 0;
            const vid = variantMap[key] || '';
            stockEl.textContent = st;
            varField.value = vid;
            if(st>0){ qty.max = st; if(parseInt(qty.value||'1',10)>st) qty.value = st; }
            else { qty.removeAttribute('max'); }
          });
        })();
        </script>
        <?php
    } else {
        echo "<div class='alert alert-warning text-center'>Product not found.</div>";
    }

    mysqli_stmt_close($stmt);
    include '../includes/footer.php';
    exit();
}

// Otherwise: product listing (grid)

// Add New Product button for Admins
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    echo '<div class="mb-3 text-end">
            <a href="create.php" class="btn btn-success">+ Add New Product</a>
          </div>';
}

// ---------- Product filters (brand / category) for customers ----------
// Filters
$filter_brand = isset($_GET['brand']) ? intval($_GET['brand']) : 0;
$filter_category = isset($_GET['category']) ? intval($_GET['category']) : 0;
// Search query from navbar
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Fetch brand and category lists for the filter selects
$brandsList = mysqli_query($conn, "SELECT brand_id, brand_name FROM brands ORDER BY brand_name ASC");
$categoriesList = mysqli_query($conn, "SELECT category_id, category_name FROM category ORDER BY category_name ASC");

// Build product query with optional filters
$where = '';
if ($filter_brand > 0) { $where .= " AND p.brand_id = $filter_brand"; }
if ($filter_category > 0) { $where .= " AND p.category_id = $filter_category"; }
if ($search_q !== '') {
  $sq = mysqli_real_escape_string($conn, $search_q);
  $where .= " AND (p.product_name LIKE '%$sq%' OR p.description LIKE '%$sq%' OR b.brand_name LIKE '%$sq%' OR c.category_name LIKE '%$sq%')";
}

$query = "SELECT p.*, b.brand_name, c.category_name 
          FROM products p
          JOIN brands b ON p.brand_id = b.brand_id
          JOIN category c ON p.category_id = c.category_id
          WHERE 1=1 " . $where . "
          ORDER BY p.date_added DESC";
$result = mysqli_query($conn, $query);
?>

<h2 class="text-center mb-4">All Products</h2>

<!-- Filter bar -->
<div class="row mb-3">
  <div class="col-md-8">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-auto">
        <select name="brand" class="form-select">
          <option value="0">All Brands</option>
          <?php if ($brandsList): while ($b = mysqli_fetch_assoc($brandsList)): ?>
            <option value="<?= $b['brand_id'] ?>" <?= ($filter_brand===$b['brand_id'])? 'selected': '' ?>><?= htmlspecialchars($b['brand_name']) ?></option>
          <?php endwhile; endif; ?>
        </select>
      </div>
      <div class="col-auto">
        <select name="category" class="form-select">
          <option value="0">All Categories</option>
          <?php if ($categoriesList): while ($c = mysqli_fetch_assoc($categoriesList)): ?>
            <option value="<?= $c['category_id'] ?>" <?= ($filter_category===$c['category_id'])? 'selected': '' ?>><?= htmlspecialchars($c['category_name']) ?></option>
          <?php endwhile; endif; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="index.php" class="btn btn-outline-secondary">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-4">
  <?php if ($result && mysqli_num_rows($result) > 0): ?>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
      <div class="col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm">
          <?php if (!empty($row['image'])): ?>
            <img src="images/<?php echo htmlspecialchars($row['image']); ?>" 
                 class="card-img-top" 
                 alt="<?php echo htmlspecialchars($row['product_name']); ?>" 
                 style="height: 250px; object-fit: cover;">
          <?php else: ?>
            <img src="https://via.placeholder.com/250x250?text=ShoeVerse" class="card-img-top" alt="No image">
          <?php endif; ?>

          <div class="card-body d-flex flex-column">
            <h5 class="card-title"><?php echo htmlspecialchars($row['product_name']); ?></h5>
            <p class="text-muted mb-1 small">
              <?php echo htmlspecialchars($row['brand_name']); ?> — <?php echo htmlspecialchars($row['category_name']); ?>
            </p>
            <p class="fw-bold text-primary mb-2">₱<?php echo number_format($row['price'], 2); ?></p>

            <p class="text-muted small flex-grow-1">
              <?php echo htmlspecialchars(substr($row['description'], 0, 90)); ?>
              <?php echo (strlen($row['description'])>90)?'...':''; ?>
            </p>

            <div class="mt-auto">
              <a href="index.php?id=<?php echo $row['product_id']; ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
                View Details
              </a>
              <a href="../cart/cart_update.php?action=add&id=<?php echo $row['product_id']; ?>" class="btn btn-primary btn-sm w-100 mb-2">
                Add to Cart
              </a>

              <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                <a href="edit.php?id=<?php echo $row['product_id']; ?>" class="btn btn-warning btn-sm w-100 mb-2">
                  Edit
                </a>
                <a href="delete.php?id=<?php echo $row['product_id']; ?>" class="btn btn-danger btn-sm w-100"
                   onclick="return confirm('Are you sure you want to delete this product?');">
                   Delete
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p class="text-center text-muted">No products available at the moment.</p>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>