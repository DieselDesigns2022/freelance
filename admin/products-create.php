<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$product = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $product = $_POST;
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

    $dupe = db()->prepare('SELECT id FROM products WHERE slug = ?');
    $dupe->execute([$slug]);
    if ($dupe->fetch()) {
        $errors[] = 'Slug already exists.';
    }

    if (!$errors) {
        $insertStmt = db()->prepare(
            'INSERT INTO products '
            . '(name,slug,short_description,full_description,service_type,fulfillment_type,price,deposit_amount,turnaround_text,includes_text,requirements_text,status,is_featured,sort_order,contract_template_id,demo_url,demo_password,created_at) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $insertStmt->execute([
            $name,
            $slug,
            $shortDescription,
            trim($_POST['full_description'] ?? '') ?: null,
            $serviceType,
            $fulfillmentType,
            (float) $price,
            null,
            null,
            null,
            null,
            $status,
            isset($_POST['is_featured']) ? 1 : 0,
            (int) ($_POST['sort_order'] ?? 0),
            $contractTemplateId === '' ? null : (int) $contractTemplateId,
            $demoUrl ?: null,
            $demoPassword ?: null,
        ]);

        $newProductId = (int) db()->lastInsertId();
        flash('success', 'Product created. Add screenshots/images on this edit page.');
        redirect('products-edit.php?id=' . $newProductId);
    }
}

$adminTitle = 'Create Product';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Create Product</h1>
<?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>
<?php include __DIR__ . '/product-form.php'; ?>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
