<?php
session_start();
include('../includes/config.php');

// Update (handler) - process product updates, images, and variants
if (!isset($_POST['product_id'])) {
    header("Location: index.php");
    exit;
}

$product_id = intval($_POST['product_id']);
$product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
$size = isset($_POST['size']) && is_numeric($_POST['size']) ? intval($_POST['size']) : 0;
$price = isset($_POST['price']) && is_numeric($_POST['price']) ? floatval($_POST['price']) : 0.0;
$stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$brand_id = isset($_POST['brand_id']) ? intval($_POST['brand_id']) : 0;
$category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

$imageName = null;
if (isset($_FILES['img_path']) && !empty($_FILES['img_path']['name'])) {
    $image = time() . '_' . basename($_FILES['img_path']['name']);
    $target = "images/" . $image;
    if (move_uploaded_file($_FILES['img_path']['tmp_name'], $target)) {
        $imageName = $image;
    }
}

$total_stock = 0;
$hasVariants = isset($_POST['variant_color']) && is_array($_POST['variant_color']) && count($_POST['variant_color'])>0;
if ($hasVariants) {
    foreach ($_POST['variant_stock'] as $vs) { $total_stock += intval($vs); }
    $final_stock = $total_stock;
} else {
    $final_stock = intval($stock);
}

function column_exists($conn, $table, $column) {
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `" . mysqli_real_escape_string($conn, $table) . "` LIKE '" . mysqli_real_escape_string($conn, $column) . "'");
    return ($q && mysqli_num_rows($q) > 0);
}

$fields = [];
$types = '';
$values = [];

if (column_exists($conn, 'products', 'brand_id')) { $fields[] = 'brand_id = ?'; $types .= 'i'; $values[] = $brand_id; }

if (column_exists($conn, 'products', 'category_id')) { $fields[] = 'category_id = ?'; $types .= 'i'; $values[] = $category_id; }

if (column_exists($conn, 'products', 'product_name')) { $fields[] = 'product_name = ?'; $types .= 's'; $values[] = $product_name; }

if (column_exists($conn, 'products', 'size')) { $fields[] = 'size = ?'; $types .= 'i'; $values[] = $size; }

if (column_exists($conn, 'products', 'price')) { $fields[] = 'price = ?'; $types .= 'd'; $values[] = $price; }

if (column_exists($conn, 'products', 'stock')) { $fields[] = 'stock = ?'; $types .= 'i'; $values[] = $final_stock; }

if (column_exists($conn, 'products', 'description')) { $fields[] = 'description = ?'; $types .= 's'; $values[] = $description; }

if ($imageName !== null && column_exists($conn, 'products', 'image')) { $fields[] = 'image = ?'; $types .= 's'; $values[] = $imageName; }

if (count($fields) === 0) {

    die('Nothing to update: no known product columns present.');
}

$sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE product_id = ?';
$types .= 'i';
$values[] = $product_id;

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    die('Prepare failed: ' . mysqli_error($conn));
}

$bind_names = [];
$bind_names[] = $stmt;
$bind_names[] = $types;
for ($i = 0; $i < count($values); $i++) {
    $bind_names[] = & $values[$i];
}
call_user_func_array('mysqli_stmt_bind_param', $bind_names);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);

    if (isset($_POST['images_to_delete']) && is_array($_POST['images_to_delete'])) {
        $delStmt = mysqli_prepare($conn, "DELETE FROM product_images WHERE product_id = ? AND image = ?");
        foreach ($_POST['images_to_delete'] as $del) {
            $fn = basename($del);
            $fnEsc = mysqli_real_escape_string($conn, $fn);
            $pid = intval($product_id);
            $chk = mysqli_prepare($conn, "SELECT image FROM product_images WHERE product_id = ? AND image = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, 'is', $pid, $fn);
            mysqli_stmt_execute($chk);
            mysqli_stmt_bind_result($chk, $stored);
            if (mysqli_stmt_fetch($chk)) {
                mysqli_stmt_close($chk);
                $p = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $stored;
                if (file_exists($p)) @unlink($p);
                mysqli_stmt_bind_param($delStmt, 'is', $pid, $stored);
                mysqli_stmt_execute($delStmt);
            } else {
                mysqli_stmt_close($chk);
            }
        }
        if ($delStmt) mysqli_stmt_close($delStmt);
    }

    if (isset($_FILES['images']) && isset($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
        $insImgStmt = mysqli_prepare($conn, "INSERT INTO product_images (product_id, image) VALUES (?, ?)");
        for ($i=0;$i<count($_FILES['images']['name']);$i++) {
            if (empty($_FILES['images']['name'][$i])) continue;
            $iname = time() . '_' . basename($_FILES['images']['name'][$i]);
            $itarget = "images/" . $iname;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $itarget)) {
                mysqli_stmt_bind_param($insImgStmt, 'is', $product_id, $iname);
                mysqli_stmt_execute($insImgStmt);
            }
        }
        mysqli_stmt_close($insImgStmt);
    }

   
    if ($hasVariants) {
        $var_colors = $_POST['variant_color'];
        $var_sizes = isset($_POST['variant_size']) && is_array($_POST['variant_size']) ? $_POST['variant_size'] : array_fill(0, count($var_colors), '');
        $var_stocks = $_POST['variant_stock'];
        $existing_color_imgs = isset($_POST['variant_existing_color_image']) ? $_POST['variant_existing_color_image'] : [];
        $posted_variant_ids = isset($_POST['variant_id']) && is_array($_POST['variant_id']) ? $_POST['variant_id'] : [];

        if (isset($_POST['remove_variant'])) {
            $remIdx = intval($_POST['remove_variant']);
            if ($remIdx >= 0) {
                if (isset($var_colors[$remIdx])) unset($var_colors[$remIdx]);
                if (isset($var_sizes[$remIdx])) unset($var_sizes[$remIdx]);
                if (isset($var_stocks[$remIdx])) unset($var_stocks[$remIdx]);
                if (isset($existing_color_imgs[$remIdx])) unset($existing_color_imgs[$remIdx]);
                if (isset($posted_variant_ids[$remIdx])) unset($posted_variant_ids[$remIdx]);
      
                $var_colors = array_values($var_colors);
                $var_sizes = array_values($var_sizes);
                $var_stocks = array_values($var_stocks);
                $existing_color_imgs = array_values($existing_color_imgs);
                $posted_variant_ids = array_values($posted_variant_ids);
            }
        }

        $insWithImg = mysqli_prepare($conn, "INSERT INTO product_variants (product_id, color_name, color_image, size_value, stock) VALUES (?, ?, ?, ?, ?)");
        $insNoImg = mysqli_prepare($conn, "INSERT INTO product_variants (product_id, color_name, color_image, size_value, stock) VALUES (?, ?, NULL, ?, ?)");
        $updWithImg = mysqli_prepare($conn, "UPDATE product_variants SET color_name=?, color_image=?, size_value=?, stock=? WHERE variant_id=? AND product_id=?");
        $updNoImg = mysqli_prepare($conn, "UPDATE product_variants SET color_name=?, size_value=?, stock=? WHERE variant_id=? AND product_id=?");

        $processed_variant_ids = [];
        for ($i=0;$i<count($var_colors);$i++) {
            $cname = trim($var_colors[$i]);
            $sz = trim($var_sizes[$i]);
            $stk = intval($var_stocks[$i]);

            $colorImageName = null;
            if (isset($_FILES['variant_color_image']) && isset($_FILES['variant_color_image']['name'][$i]) && !empty($_FILES['variant_color_image']['name'][$i])) {
                $cimg = time() . '_var_' . basename($_FILES['variant_color_image']['name'][$i]);
                $ctarget = "images/" . $cimg;
                if (move_uploaded_file($_FILES['variant_color_image']['tmp_name'][$i], $ctarget)) {
                    $colorImageName = $cimg;
                }
            } else {
                if (isset($existing_color_imgs[$i]) && !empty($existing_color_imgs[$i])) {
                    $colorImageName = $existing_color_imgs[$i];
                } else {
                    $colorImageName = null;
                }
            }

            $vid = isset($posted_variant_ids[$i]) ? intval($posted_variant_ids[$i]) : 0;
            if ($vid > 0) {
                if ($colorImageName !== null) {
                    mysqli_stmt_bind_param($updWithImg, 'sssiii', $cname, $colorImageName, $sz, $stk, $vid, $product_id);
                    mysqli_stmt_execute($updWithImg);
                } else {
                    mysqli_stmt_bind_param($updNoImg, 'ssiii', $cname, $sz, $stk, $vid, $product_id);
                    mysqli_stmt_execute($updNoImg);
                }
                $processed_variant_ids[] = $vid;
            } else {
                if ($colorImageName !== null) {
                    mysqli_stmt_bind_param($insWithImg, 'isssi', $product_id, $cname, $colorImageName, $sz, $stk);
                    mysqli_stmt_execute($insWithImg);
                    $processed_variant_ids[] = mysqli_insert_id($conn);
                } else {
                    mysqli_stmt_bind_param($insNoImg, 'issi', $product_id, $cname, $sz, $stk);
                    mysqli_stmt_execute($insNoImg);
                    $processed_variant_ids[] = mysqli_insert_id($conn);
                }
            }
        }

        if ($insWithImg) mysqli_stmt_close($insWithImg);
        if ($insNoImg) mysqli_stmt_close($insNoImg);
        if ($updWithImg) mysqli_stmt_close($updWithImg);
        if ($updNoImg) mysqli_stmt_close($updNoImg);

       
        $allExisting = [];
        $resAll = mysqli_query($conn, "SELECT variant_id, color_image FROM product_variants WHERE product_id = " . intval($product_id));
        while ($r = mysqli_fetch_assoc($resAll)) { $allExisting[] = $r; }

        $toDelete = [];
        $idToImage = [];
        foreach ($allExisting as $row) {
            $vidx = intval($row['variant_id']);
            $idToImage[$vidx] = $row['color_image'];
            if (!in_array($vidx, $processed_variant_ids)) $toDelete[] = $vidx;
        }

        $blocked = [];
        if (count($toDelete) > 0) {
            $ids = array_map('intval', $toDelete);
            $ids_list = implode(',', $ids);

            $refQ = mysqli_query($conn, "SELECT DISTINCT variant_id FROM orderline WHERE variant_id IN (" . $ids_list . ")");
            $referenced = [];
            if ($refQ) {
                while ($r = mysqli_fetch_assoc($refQ)) { $referenced[] = intval($r['variant_id']); }
            }

            $deletable = array_diff($ids, $referenced);
            if (count($deletable) > 0) {
                $del_list = implode(',', $deletable);
                foreach ($deletable as $dvid) {
                    if (!empty($idToImage[$dvid])) {
                        $p = 'images/' . $idToImage[$dvid];
                        if (file_exists($p)) @unlink($p);
                    }
                }
                mysqli_query($conn, "DELETE FROM product_variants WHERE variant_id IN (" . $del_list . ")");
            }

            if (count($referenced) > 0) {
                $blocked = $referenced;
            }
        }

        if (count($blocked) > 0) {
            echo "<div class='container mt-4'><div class='alert alert-warning'>Some variants could not be removed because customers have orders referencing them. Variant IDs: " . htmlspecialchars(implode(', ', $blocked)) . ". Please cancel or complete those orders before deleting these variants.</div></div>";
        }

        $sRes = mysqli_query($conn, "SELECT SUM(stock) as ssum FROM product_variants WHERE product_id = " . intval($product_id));
        if ($sRes && $sRow = mysqli_fetch_assoc($sRes)) {
            $final_stock = intval($sRow['ssum']);
            mysqli_query($conn, "UPDATE products SET stock = " . $final_stock . " WHERE product_id = " . intval($product_id));
        }
    }

    if (isset($_POST['remove_variant']) && isset($_POST['product_id'])) {
        $redirId = intval($_POST['product_id']);
        header('Location: ../item/edit.php?id=' . $redirId);
    } else {
        if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin') {
            header("Location: ../admin/products.php");
        } else {
            header("Location: index.php");
        }
    }
    exit;
} else {
    echo "Error: " . mysqli_error($conn);
}
?>