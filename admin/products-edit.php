<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/questionnaires.php';

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

$liveExamplesTableExists = table_exists(db(), 'product_live_examples');

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
    } elseif ($action === 'set_thumbnail') {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        $imgStmt = db()->prepare('SELECT id FROM product_images WHERE id = ? AND product_id = ?');
        $imgStmt->execute([$imageId, $id]);
        $img = $imgStmt->fetch();

        if ($img) {
            db()->prepare(
                'UPDATE product_images '
                . 'SET sort_order = CASE WHEN id = ? THEN -1000 ELSE GREATEST(sort_order, 0) END '
                . 'WHERE product_id = ?'
            )->execute([$imageId, $id]);

            flash('success', 'Product thumbnail updated.');
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
    } elseif ($action === 'save_live_example') {
        $exampleId = (int) ($_POST['example_id'] ?? 0);
        $title = trim($_POST['example_title'] ?? '');
        $url = trim($_POST['example_url'] ?? '');
        $sortOrder = (int) ($_POST['example_sort_order'] ?? 0);

        if (!$liveExamplesTableExists) {
            $errors[] = 'Run the Phase 2.2 database migration before managing live examples.';
        } elseif ($title === '') {
            $errors[] = 'Live example title is required.';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Live example title must be 255 characters or fewer.';
        }
        if ($url === '' || !valid_url_or_blank($url)) {
            $errors[] = 'Live example URL must be a valid http:// or https:// URL.';
        } elseif (strlen($url) > 500) {
            $errors[] = 'Live example URL must be 500 characters or fewer.';
        }

        if (!$errors && $exampleId > 0) {
            $exampleStmt = db()->prepare('SELECT id FROM product_live_examples WHERE id = ? AND product_id = ?');
            $exampleStmt->execute([$exampleId, $id]);
            if (!$exampleStmt->fetch()) {
                $errors[] = 'Live example not found for this product.';
            }
        }

        if (!$errors) {
            if ($exampleId > 0) {
                db()->prepare('UPDATE product_live_examples SET title = ?, url = ?, sort_order = ? WHERE id = ? AND product_id = ?')
                    ->execute([$title, $url, $sortOrder, $exampleId, $id]);
                $message = 'Live example updated.';
            } else {
                db()->prepare('INSERT INTO product_live_examples (product_id, title, url, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())')
                    ->execute([$id, $title, $url, $sortOrder]);
                $message = 'Live example added.';
            }
            flash('success', $message);
            redirect('products-edit.php?id=' . $id);
        }
    } elseif ($action === 'delete_live_example') {
        if (!$liveExamplesTableExists) {
            $errors[] = 'Run the Phase 2.2 database migration before managing live examples.';
        } else {
            $exampleId = (int) ($_POST['example_id'] ?? 0);
            $deleteStmt = db()->prepare('DELETE FROM product_live_examples WHERE id = ? AND product_id = ?');
            $deleteStmt->execute([$exampleId, $id]);
            if ($deleteStmt->rowCount() !== 1) {
                $errors[] = 'Live example not found for this product.';
            } else {
                flash('success', 'Live example deleted.');
                redirect('products-edit.php?id=' . $id);
            }
        }
    } elseif ($action === 'save') {
        $existingQuestionnaireTemplateId = $product['questionnaire_template_id']
            ? (int) $product['questionnaire_template_id']
            : null;
        $product = array_merge($product, $_POST);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: slugify($name);
        $shortDescription = trim($_POST['short_description'] ?? '');
        $serviceType = $_POST['service_type'] ?? '';
        $intakeType = $_POST['intake_type'] ?? 'general_service';
        $fulfillmentType = 'service';
        $status = $_POST['status'] ?? 'draft';
        $price = trim($_POST['price'] ?? '');
        $demoUrl = trim($_POST['demo_url'] ?? '');
        $demoPassword = trim($_POST['demo_password'] ?? '');
        $contractTemplateId = trim($_POST['contract_template_id'] ?? '');
        $contractTemplate = null;
        $questionnaireTemplateId = trim($_POST['questionnaire_template_id'] ?? '');

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if ($shortDescription === '') {
            $errors[] = 'Short description is required.';
        }
        if (!in_array($serviceType, allowed_service_types(), true)) {
            $errors[] = 'Choose a valid service type.';
        }
        if (!in_array($intakeType, allowed_product_intake_types(), true)) {
            $errors[] = 'Choose a valid customer intake type.';
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
        $errors = array_merge(
            $errors,
            validate_product_questionnaire_assignment(
                db(),
                $intakeType,
                $status,
                $questionnaireTemplateId,
                $existingQuestionnaireTemplateId
            )
        );

        $dupe = db()->prepare('SELECT id FROM products WHERE slug = ? AND id != ?');
        $dupe->execute([$slug, $id]);
        if ($dupe->fetch()) {
            $errors[] = 'Slug already exists.';
        }

        if (!$errors) {
            db()->prepare(
                'UPDATE products SET name=?, slug=?, short_description=?, full_description=?, service_type=?, intake_type=?, '
                . 'fulfillment_type=?, price=?, deposit_amount=?, turnaround_text=?, includes_text=?, '
                . 'requirements_text=?, status=?, is_featured=?, sort_order=?, contract_template_id=?, '
                . 'questionnaire_template_id=?, demo_url=?, demo_password=?, updated_at=NOW() WHERE id=?'
            )->execute([
                $name,
                $slug,
                $shortDescription,
                trim($_POST['full_description'] ?? '') ?: null,
                $serviceType,
                $intakeType,
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
                $questionnaireTemplateId === '' ? null : (int) $questionnaireTemplateId,
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
$liveExamples = [];
if ($liveExamplesTableExists) {
    $exampleStmt = db()->prepare('SELECT * FROM product_live_examples WHERE product_id = ? ORDER BY sort_order, id');
    $exampleStmt->execute([$id]);
    $liveExamples = $exampleStmt->fetchAll();
}
?>
<?php if (in_array(($product['intake_type'] ?? 'general_service'), ['shopify_custom_kit', 'website_custom_build'], true)): ?>
<section class="admin-card product-live-examples-panel">
    <h2>Live Examples</h2>
    <p class="helper">Add public examples after saving the product. Links appear in display order on the product detail page.</p>

    <?php if (!$liveExamplesTableExists): ?>
        <p>Run the Phase 2.2 database migration before managing live examples.</p>
    <?php else: ?>
        <?php foreach ($liveExamples as $example): ?>
            <form method="post" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_live_example">
                <input type="hidden" name="example_id" value="<?= (int) $example['id'] ?>">
                <label>Title *<input name="example_title" required maxlength="255" value="<?= e($example['title']) ?>"></label>
                <label>URL *<input name="example_url" type="url" required maxlength="500" value="<?= e($example['url']) ?>"></label>
                <label>Display order<input name="example_sort_order" type="number" value="<?= (int) $example['sort_order'] ?>"></label>
                <button class="btn">Save Example</button>
            </form>
            <form method="post" onsubmit="return confirm('Delete this live example?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_live_example">
                <input type="hidden" name="example_id" value="<?= (int) $example['id'] ?>">
                <button class="btn danger">Delete Example</button>
            </form>
        <?php endforeach; ?>

        <form method="post" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_live_example">
            <h3>Add Live Example</h3>
            <label>Title *<input name="example_title" required maxlength="255"></label>
            <label>URL *<input name="example_url" type="url" required maxlength="500" placeholder="https://"></label>
            <label>Display order<input name="example_sort_order" type="number" value="0"></label>
            <button class="btn">Add Example</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php
$productImages = [];
if (table_exists(db(), 'product_images')) {
    $images = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $images->execute([$id]);
    $productImages = $images->fetchAll();
}
?>
<section class="admin-card product-images-panel">
    <h2>Product Images</h2>
    <p class="helper">Upload watermarked previews here. Use “Set as Thumbnail” to choose the preview shown on category pages and cards.</p>

    <form method="post" enctype="multipart/form-data" class="admin-form product-image-upload-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_image">

        <label>Upload screenshots/images
            <input type="file" name="product_images[]" accept="image/jpeg,image/png,image/webp" multiple required>
        </label>

        <label>Alt text
            <input name="alt_text" maxlength="255">
        </label>

        <button class="btn">Upload Images</button>
    </form>

    <?php if ($productImages): ?>
        <?php $thumbnailImageId = (int) ($productImages[0]['id'] ?? 0); ?>

        <div class="product-image-manager">
            <?php foreach ($productImages as $image): ?>
                <?php $isThumbnail = (int) $image['id'] === $thumbnailImageId; ?>

                <article class="admin-card product-image-admin-card <?= $isThumbnail ? 'is-thumbnail' : '' ?>">
                    <div class="product-image-preview-wrap">
                        <img
                            class="product-image-admin-preview"
                            src="../<?= e($image['image_path']) ?>"
                            alt="<?= e($image['alt_text'] ?: $product['name']) ?>"
                        >

                        <?php if ($isThumbnail): ?>
                            <span class="thumbnail-badge">Current thumbnail</span>
                        <?php endif; ?>
                    </div>

                    <form method="post" class="product-image-meta-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_images">

                        <label>Alt text
                            <input
                                name="images[<?= (int) $image['id'] ?>][alt_text]"
                                maxlength="255"
                                value="<?= e($image['alt_text'] ?? '') ?>"
                            >
                        </label>

                        <label>Display order
                            <input
                                name="images[<?= (int) $image['id'] ?>][sort_order]"
                                type="number"
                                value="<?= e((string) $image['sort_order']) ?>"
                            >
                        </label>

                        <button class="btn">Save Image Details</button>
                    </form>

                    <div class="product-image-actions">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_thumbnail">
                            <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                            <button class="btn btn-ghost" <?= $isThumbnail ? 'disabled' : '' ?>>Set as Thumbnail</button>
                        </form>

                        <form method="post" onsubmit="return confirm('Delete this image permanently?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_image">
                            <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                            <button class="btn danger">Delete Image</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>No product images uploaded yet.</p>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
