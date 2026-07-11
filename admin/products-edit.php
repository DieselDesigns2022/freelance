<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
$errors = [];

if (!$product) {
    http_response_code(404);
    exit('Not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'upload_image') {
        foreach (($_FILES['product_images']['name'] ?? []) as $idx => $unused) {
            $file = [
                'name' => $_FILES['product_images']['name'][$idx] ?? '',
                'type' => $_FILES['product_images']['type'][$idx] ?? '',
                'tmp_name' => $_FILES['product_images']['tmp_name'][$idx] ?? '',
                'error' => $_FILES['product_images']['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES['product_images']['size'][$idx] ?? 0,
            ];

            [$path, $uploadError] = upload_image($file);

            if ($uploadError) {
                $errors[] = $uploadError;
                continue;
            }

            if ($path) {
                db()->prepare(
                    'INSERT INTO product_images (product_id,image_path,alt_text,sort_order,created_at) '
                    . 'VALUES (?,?,?,?,NOW())'
                )->execute([
                    $id,
                    $path,
                    trim($_POST['alt_text'] ?? '') ?: null,
                    (int) ($_POST['image_sort_order'] ?? 0),
                ]);
            }
        }

        if (!$errors) {
            flash('success', 'Product image uploaded.');
            redirect('products-edit.php?id=' . $id);
        }
    } elseif ($action === 'delete_image') {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        $imgStmt = db()->prepare('SELECT image_path FROM product_images WHERE id = ? AND product_id = ?');
        $imgStmt->execute([$imageId, $id]);
        $img = $imgStmt->fetch();

        if ($img) {
            db()->prepare('DELETE FROM product_images WHERE id = ? AND product_id = ?')->execute([$imageId, $id]);
            delete_portfolio_file($img['image_path']);
            flash('success', 'Product image deleted.');
            redirect('products-edit.php?id=' . $id);
        }

        $errors[] = 'Image not found.';
    } elseif ($action === 'update_images') {
        foreach (($_POST['images'] ?? []) as $imageId => $imageData) {
            db()->prepare('UPDATE product_images SET alt_text = ?, sort_order = ? WHERE id = ? AND product_id = ?')
                ->execute([
                    trim($imageData['alt_text'] ?? '') ?: null,
                    (int) ($imageData['sort_order'] ?? 0),
                    (int) $imageId,
                    $id,
                ]);
        }

        flash('success', 'Product images updated.');
        redirect('products-edit.php?id=' . $id);
    } elseif ($action === 'save') {
        $product = array_merge($product, $_POST);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: slugify($name);
        $shortDescription = trim($_POST['short_description'] ?? '');
        $serviceType = $_POST['service_type'] ?? '';
        $fulfillmentType = 'service';
        $status = $_POST['status'] ?? 'draft';
        $price = trim($_POST['price'] ?? '');
        $demoUrl = trim($_POST['demo_url'] ?? '');
        $demoPassword = trim($_POST['demo_password'] ?? '');
        $contractTemplateId = trim($_POST['contract_template_id'] ?? '');
        $contractTemplate = null;

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if ($shortDescription === '') {
            $errors[] = 'Short description is required.';
        }
        if (!in_array($serviceType, allowed_service_types(), true)) {
            $errors[] = 'Choose a valid service type.';
        }
        if (!in_array($status, allowed_product_statuses(), true)) {
            $errors[] = 'Choose a valid status.';
        }
        if ($price === '' || !is_numeric($price) || (float) $price < 0) {
            $errors[] = 'Price must be a number greater than or equal to zero.';
        }
        if (!valid_url_or_blank($demoUrl)) {
            $errors[] = 'Demo URL must be blank or start with http:// or https://.';
        }

        if ($contractTemplateId !== '') {
            $templateStmt = db()->prepare('SELECT id, status FROM contract_templates WHERE id = ?');
            $templateStmt->execute([(int) $contractTemplateId]);
            $contractTemplate = $templateStmt->fetch();

            if (!$contractTemplate) {
                $errors[] = 'Choose an existing contract template.';
            }
        }

        if ($status === 'active' && (!$contractTemplate || $contractTemplate['status'] !== 'active')) {
            $errors[] = 'Active products must have an active contract template assigned.';
        }

        $dupe = db()->prepare('SELECT id FROM products WHERE slug = ? AND id != ?');
        $dupe->execute([$slug, $id]);
        if ($dupe->fetch()) {
            $errors[] = 'Slug already exists.';
        }

        if (!$errors) {
            db()->prepare(
                'UPDATE products SET name=?, slug=?, short_description=?, full_description=?, service_type=?, '
                . 'fulfillment_type=?, price=?, deposit_amount=?, turnaround_text=?, includes_text=?, '
                . 'requirements_text=?, status=?, is_featured=?, sort_order=?, contract_template_id=?, '
                . 'demo_url=?, demo_password=?, updated_at=NOW() WHERE id=?'
            )->execute([
                $name,
                $slug,
                $shortDescription,
                trim($_POST['full_description'] ?? '') ?: null,
                $serviceType,
                $fulfillmentType,
                (float) $price,
                ($product['deposit_amount'] ?? null) === '' ? null : ($product['deposit_amount'] ?? null),
                $product['turnaround_text'] ?? null,
                $product['includes_text'] ?? null,
                $product['requirements_text'] ?? null,
                $status,
                isset($_POST['is_featured']) ? 1 : 0,
                (int) ($_POST['sort_order'] ?? 0),
                $contractTemplateId === '' ? null : (int) $contractTemplateId,
                $demoUrl ?: null,
                $demoPassword ?: null,
                $id,
            ]);

            flash('success', 'Product updated.');
            redirect('products.php');
        }
    } else {
        $errors[] = 'Unknown action.';
    }
}

$adminTitle = 'Edit Product';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Edit Product</h1>

<?php foreach ($errors as $error): ?>
    <p class="error-text"><?= e($error) ?></p>
<?php endforeach; ?>

<?php include __DIR__ . '/product-form.php'; ?>

<?php
$productImages = [];
if (table_exists(db(), 'product_images')) {
    $images = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $images->execute([$id]);
    $productImages = $images->fetchAll();
}
?>
<section class="admin-card">
    <h2>Product Images</h2>

    <form method="post" enctype="multipart/form-data" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_image">

        <label>Upload screenshots/images
            <input type="file" name="product_images[]" accept="image/jpeg,image/png,image/webp" multiple required>
        </label>

        <label>Alt text
            <input name="alt_text" maxlength="255">
        </label>

        <label>Sort order
            <input name="image_sort_order" type="number" value="0">
        </label>

        <button class="btn">Upload Images</button>
    </form>

    <?php if ($productImages): ?>
        <form method="post" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_images">

            <?php foreach ($productImages as $image): ?>
                <div class="admin-card">
                    <img
                        src="../<?= e($image['image_path']) ?>"
                        alt="<?= e($image['alt_text'] ?: $product['name']) ?>"
                        style="max-width:180px;height:auto"
                    >

                    <label>Alt text
                        <input
                            name="images[<?= (int) $image['id'] ?>][alt_text]"
                            maxlength="255"
                            value="<?= e($image['alt_text'] ?? '') ?>"
                        >
                    </label>

                    <label>Sort order
                        <input
                            name="images[<?= (int) $image['id'] ?>][sort_order]"
                            type="number"
                            value="<?= e((string) $image['sort_order']) ?>"
                        >
                    </label>
                </div>
            <?php endforeach; ?>

            <button class="btn">Save Image Details</button>
        </form>

        <?php foreach ($productImages as $image): ?>
            <form method="post" onsubmit="return confirm('Delete this image?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_image">
                <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                <button class="btn btn-ghost">Delete <?= e($image['image_path']) ?></button>
            </form>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No product images uploaded yet.</p>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
