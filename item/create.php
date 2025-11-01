<?php
session_start();
include('../includes/header.php');
include('../includes/config.php');

// Fetch brands and categories for dropdowns
$brandsResult = mysqli_query($conn, "SELECT * FROM brands");
$categoriesResult = mysqli_query($conn, "SELECT * FROM category");
?>

<body>
<div class="container mt-4">
    <h2>Add New Product</h2>
    <form method="POST" action="store.php" enctype="multipart/form-data">
        <div class="form-group mb-3">
            <label for="brand">Brand</label>
            <select class="form-control" id="brand" name="brand_id">
                <option value="">-- Select Brand --</option>
                <?php while ($brand = mysqli_fetch_assoc($brandsResult)): ?>
                    <option value="<?php echo $brand['brand_id']; ?>"
                        <?php if (isset($_SESSION['brand_id']) && $_SESSION['brand_id'] == $brand['brand_id']) echo "selected"; ?>>
                        <?php echo $brand['brand_name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group mb-3">
            <label for="category">Category</label>
            <select class="form-control" id="category" name="category_id">
                <option value="">-- Select Category --</option>
                <?php while ($category = mysqli_fetch_assoc($categoriesResult)): ?>
                    <option value="<?php echo $category['category_id']; ?>"
                        <?php if (isset($_SESSION['category_id']) && $_SESSION['category_id'] == $category['category_id']) echo "selected"; ?>>
                        <?php echo $category['category_name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group mb-3">
            <label for="name">Product Name</label>
            <input type="text" class="form-control" id="name" name="product_name"
                value="<?php if (isset($_SESSION['product_name'])) echo $_SESSION['product_name']; ?>">
            <small class="text-danger">
                <?php
                if (isset($_SESSION['nameError'])) {
                    echo $_SESSION['nameError'];
                    unset($_SESSION['nameError']);
                }
                ?>
            </small>
        </div>

        <div class="form-group mb-3">
            <label for="size">Size</label>
            <input type="number" class="form-control" id="size" name="size"
                value="<?php if (isset($_SESSION['size'])) echo $_SESSION['size']; ?>">
        </div>

        <div class="form-group mb-3">
            <label for="price">Price</label>
            <input type="text" class="form-control" id="price" name="price"
                value="<?php if (isset($_SESSION['price'])) echo $_SESSION['price']; ?>">
        </div>

        <div class="form-group mb-3">
            <label for="stock">Stock</label>
            <input type="number" class="form-control" id="stock" name="stock"
                value="<?php if (isset($_SESSION['stock'])) echo $_SESSION['stock']; ?>">
        </div>

        <div class="form-group mb-3">
            <label for="desc">Description</label>
            <textarea class="form-control" id="desc" name="description" rows="2"><?php if (isset($_SESSION['description'])) echo $_SESSION['description']; ?></textarea>
        </div>

        <div class="form-group mb-3">
            <label for="img">Image</label>
            <input type="file" class="form-control" id="img" name="img_path">
        </div>

        <button type="submit" class="btn btn-primary" name="submit">Add Product</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include('../includes/footer.php'); ?>
