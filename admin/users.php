<?php
include '../includes/config.php';
include '../includes/adminHeader.php';

// Restrict access to Admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    echo "<div class='alert alert-danger text-center'>Access denied. Admins only.</div>";
    include '../includes/footer.php';
    exit();
}

// Handle delete request
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);

    // Prevent admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        echo "<script>alert('You cannot delete your own account.'); window.location.href='users.php';</script>";
        exit();
    }

    $delete_query = mysqli_query($conn, "DELETE FROM users WHERE user_id = $user_id");

    if ($delete_query) {
        echo "<script>alert('User deleted successfully!'); window.location.href='users.php';</script>";
    } else {
        echo "<div class='alert alert-danger text-center'>Error deleting user: " . mysqli_error($conn) . "</div>";
    }
}

// Fetch all users
$query = "SELECT * FROM users ORDER BY role ASC, lname ASC";
$users = mysqli_query($conn, $query);
?>

<div class="container mt-4 mb-5">
    <h2 class="text-center mb-4">👥 Manage Users</h2>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white fw-bold">User List</div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle text-center">
                <thead class="table-secondary">
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($users) > 0): ?>
                        <?php while ($user = mysqli_fetch_assoc($users)): ?>
                            <tr>
                                <td><?= $user['user_id'] ?></td>
                                <td><?= htmlspecialchars($user['fname'] . " " . $user['lname']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['contact_number']) ?></td>
                                <td><?= htmlspecialchars($user['address_line']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] == 'Admin' ? 'primary' : 'secondary' ?>">
                                        <?= $user['role'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= (isset($user['is_active']) && $user['is_active']) ? 'success' : 'secondary' ?>">
                                        <?= (isset($user['is_active']) && $user['is_active']) ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUser<?= $user['user_id'] ?>">✏️ Edit</button>
                                    <a href="users.php?delete=<?= $user['user_id'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this user?');">🗑️ Delete</a>

                                    <!-- Activate / Deactivate -->
                                    <form method="POST" action="updateUser.php" style="display:inline-block;margin-left:6px;">
                                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                        <input type="hidden" name="action" value="<?= (isset($user['is_active']) && $user['is_active']) ? 'deactivate' : 'activate' ?>">
                                        <button type="submit" class="btn btn-sm <?= (isset($user['is_active']) && $user['is_active']) ? 'btn-outline-danger' : 'btn-outline-success' ?>" onclick="return confirm('Are you sure you want to <?= (isset($user['is_active']) && $user['is_active']) ? 'deactivate' : 'activate' ?> this user?');">
                                            <?= (isset($user['is_active']) && $user['is_active']) ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Role Modal -->
                            <div class="modal fade" id="editUser<?= $user['user_id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="updateUser.php">
                                            <div class="modal-header bg-dark text-white">
                                                <h5 class="modal-title">Edit User Role</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Full Name:</label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['fname'] . " " . $user['lname']) ?>" disabled>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Role:</label>
                                                    <select name="role" class="form-select" required>
                                                        <option value="Admin" <?= ($user['role'] == 'Admin') ? 'selected' : '' ?>>Admin</option>
                                                        <option value="Customer" <?= ($user['role'] == 'Customer') ? 'selected' : '' ?>>Customer</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="update_role" class="btn btn-success">💾 Save Changes</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>