<?php
include '../includes/config.php';
include '../includes/adminHeader.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    echo "<div class='alert alert-danger text-center'>Access denied. Admins only.</div>";
    include '../includes/footer.php';
    exit();
}

if (isset($_POST['add_brand'])) {
    $brand_name = mysqli_real_escape_string($conn, $_POST['brand_name']);

    $check = mysqli_query($conn, "SELECT * FROM brands WHERE brand_name = '$brand_name'");
    if (mysqli_num_rows($check) > 0) {
        echo "<script>alert('Brand already exists!');</script>";
    } else {
        $insert = mysqli_query($conn, "INSERT INTO brands (brand_name) VALUES ('$brand_name')");
        if ($insert) {
            echo "<script>alert('Brand added successfully!'); window.location.href='brands.php';</script>";
        } else {
            echo "<div class='alert alert-danger text-center'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
}

if (isset($_GET['delete'])) {
    $brand_id = intval($_GET['delete']);
    $chk = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM products WHERE brand_id = '$brand_id'");
    $hasProducts = false;
    if ($chk) {
        $r = mysqli_fetch_assoc($chk);
        if ($r && intval($r['cnt']) > 0) $hasProducts = true;
    }

    if ($hasProducts) {
        echo "<div class='container mt-4'><div class='alert alert-warning text-center'>Cannot delete this brand because there are products assigned to it. Delete or reassign those products first.<br><a href='products.php' class='btn btn-sm btn-primary mt-2'>View products</a></div></div>";
    } else {
        $delete = mysqli_query($conn, "DELETE FROM brands WHERE brand_id = '$brand_id'");
        if ($delete) {
            echo "<script>alert('Brand deleted successfully!'); window.location.href='brands.php';</script>";
        } else {
            if (mysqli_errno($conn) == 1451) {
                echo "<div class='container mt-4'><div class='alert alert-warning text-center'>Cannot delete this brand due to related records.<br><a href='brands.php' class='btn btn-sm btn-primary mt-2'>Back</a></div></div>";
            } else {
                echo "<div class='alert alert-danger text-center'>Error deleting brand: " . mysqli_error($conn) . "</div>";
            }
        }
    }
}

if (isset($_POST['update_brand'])) {
    $brand_id = intval($_POST['brand_id']);
    $brand_name = mysqli_real_escape_string($conn, $_POST['brand_name']);

    $checkDuplicate = mysqli_query($conn, "SELECT * FROM brands WHERE brand_name = '$brand_name' AND brand_id != '$brand_id'");
    if (mysqli_num_rows($checkDuplicate) > 0) {
        echo "<script>alert('Brand name already exists!'); window.location.href='brands.php';</script>";
        exit();
    }

    $update = mysqli_query($conn, "UPDATE brands SET brand_name = '$brand_name' WHERE brand_id = '$brand_id'");
    if ($update) {
        echo "<script>alert('Brand updated successfully!'); window.location.href='brands.php';</script>";
    } else {
        echo "<div class='alert alert-danger text-center'>Update failed: " . mysqli_error($conn) . "</div>";
    }
}

$brands = mysqli_query($conn, "SELECT * FROM brands ORDER BY brand_id ASC");
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">🏷️ Manage Brands</h2>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">Add New Brand</div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <input type="text" name="brand_name" class="form-control" placeholder="Enter brand name" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="add_brand" class="btn btn-success w-100">➕ Add Brand</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
            <span>Brand List</span>
            <small>Total: <?= mysqli_num_rows($brands) ?></small>
        </div>
        <div class="card-body">
            <table class="table table-hover align-middle text-center">
                <thead class="table-secondary">
                    <tr>
                        <th>ID</th>
                        <th>Brand Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($brands) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($brands)): ?>
                            <tr>
                                <td><?= $row['brand_id'] ?></td>
                                <td><?= htmlspecialchars($row['brand_name']) ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editBrand<?= $row['brand_id'] ?>">✏️ Edit</button>
                                    <a href="brands.php?delete=<?= $row['brand_id'] ?>" onclick="return confirm('Are you sure you want to delete this brand?');" class="btn btn-danger btn-sm">🗑️ Delete</a>
                                </td>
                            </tr>

                            <div class="modal fade" id="editBrand<?= $row['brand_id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header bg-dark text-white">
                                                <h5 class="modal-title">Edit Brand</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="brand_id" value="<?= $row['brand_id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Brand Name</label>
                                                    <input type="text" name="brand_name" class="form-control" value="<?= htmlspecialchars($row['brand_name']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="update_brand" class="btn btn-success" onclick="return confirm('Save changes to this brand?');">💾 Save</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No brands found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>