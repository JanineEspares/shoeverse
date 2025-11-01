<?php
include '../includes/config.php';
include '../includes/adminHeader.php';

// Restrict access to Admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    echo "<div class='alert alert-danger text-center'>Access denied. Admins only.</div>";
    include '../includes/footer.php';
    exit();
}

// CREATE category
if (isset($_POST['add_category'])) {
    $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);

    $check = mysqli_query($conn, "SELECT * FROM category WHERE category_name = '$category_name'");
    if (mysqli_num_rows($check) > 0) {
        echo "<script>alert('Category already exists!');</script>";
    } else {
        $insert = mysqli_query($conn, "INSERT INTO category (category_name) VALUES ('$category_name')");
        if ($insert) {
            echo "<script>alert('Category added successfully!'); window.location.href='categories.php';</script>";
        } else {
            echo "<div class='alert alert-danger text-center'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
}

// DELETE category
if (isset($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    $delete = mysqli_query($conn, "DELETE FROM category WHERE category_id = '$category_id'");
    if ($delete) {
        echo "<script>alert('Category deleted successfully!'); window.location.href='categories.php';</script>";
    } else {
        echo "<div class='alert alert-danger text-center'>Error deleting category: " . mysqli_error($conn) . "</div>";
    }
}

// UPDATE category
if (isset($_POST['update_category'])) {
    $category_id = intval($_POST['category_id']);
    $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);

    $checkDuplicate = mysqli_query($conn, "SELECT * FROM category WHERE category_name = '$category_name' AND category_id != '$category_id'");
    if (mysqli_num_rows($checkDuplicate) > 0) {
        echo "<script>alert('Category name already exists!'); window.location.href='categories.php';</script>";
        exit();
    }

    $update = mysqli_query($conn, "UPDATE category SET category_name = '$category_name' WHERE category_id = '$category_id'");
    if ($update) {
        echo "<script>alert('Category updated successfully!'); window.location.href='categories.php';</script>";
    } else {
        echo "<div class='alert alert-danger text-center'>Update failed: " . mysqli_error($conn) . "</div>";
    }
}

// FETCH all categories
$categories = mysqli_query($conn, "SELECT * FROM category ORDER BY category_id DESC");
?>

<div class="container mt-4">
    <h2 class="text-center mb-4">🗂️ Manage Categories</h2>

    <!-- Add Category -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white fw-bold">Add New Category</div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-8">
                        <input type="text" name="category_name" class="form-control" placeholder="Enter category name" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="add_category" class="btn btn-success w-100">➕ Add Category</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Category List -->
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
            <span>Category List</span>
            <small>Total: <?= mysqli_num_rows($categories) ?></small>
        </div>
        <div class="card-body">
            <table class="table table-hover align-middle text-center">
                <thead class="table-secondary">
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($categories) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($categories)): ?>
                            <tr>
                                <td><?= $row['category_id'] ?></td>
                                <td><?= htmlspecialchars($row['category_name']) ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editCategory<?= $row['category_id'] ?>">✏️ Edit</button>
                                    <a href="categories.php?delete=<?= $row['category_id'] ?>" onclick="return confirm('Are you sure you want to delete this category?');" class="btn btn-danger btn-sm">🗑️ Delete</a>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editCategory<?= $row['category_id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header bg-dark text-white">
                                                <h5 class="modal-title">Edit Category</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="category_id" value="<?= $row['category_id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Category Name</label>
                                                    <input type="text" name="category_name" class="form-control" value="<?= htmlspecialchars($row['category_name']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="update_category" class="btn btn-success" onclick="return confirm('Save changes to this category?');">💾 Save</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No categories found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
