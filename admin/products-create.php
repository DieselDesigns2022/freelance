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
    $fulfillmentType = $_POST['fulfillment_type'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    $price = trim($_POST['price'] ?? '');
    $deposit = trim($_POST['deposit_amount'] ?? '');
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
    if (!in_array($fulfillmentType, allowed_fulfillment_types(), true)) {
        $errors[] = 'Choose a valid fulfillment type.';
    }
    if (!in_array($status, allowed_product_statuses(), true)) {
        $errors[] = 'Choose a valid status.';
    }
    if ($price === '' || !is_numeric($price) || (float) $price < 0) {
        $errors[] = 'Price must be a number greater than or equal to zero.';
    }
    if ($deposit !== '' && (!is_numeric($deposit) || (float) $deposit < 0)) {
        $errors[] = 'Deposit amount must be blank or a number greater than or equal to zero.';
    }
    if ($deposit !== '' && is_numeric($deposit) && is_numeric($price) && (float) $deposit > (float) $price) {
        $errors[] = 'Deposit amount cannot be greater than the full price.';
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
        db()->prepare(
            'INSERT INTO products '
            . '(name,slug,short_description,full_description,service_type,fulfillment_type,price,deposit_amount,turnaround_text,includes_text,requirements_text,status,is_featured,sort_order,contract_template_id,created_at) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        )->execute([
            $name,
            $slug,
            $shortDescription,
            trim($_POST['full_description'] ?? '') ?: null,
            $serviceType,
            $fulfillmentType,
            (float) $price,
            $deposit === '' ? null : (float) $deposit,
            trim($_POST['turnaround_text'] ?? '') ?: null,
            trim($_POST['includes_text'] ?? '') ?: null,
            trim($_POST['requirements_text'] ?? '') ?: null,
            $status,
            isset($_POST['is_featured']) ? 1 : 0,
            (int) ($_POST['sort_order'] ?? 0),
            $contractTemplateId === '' ? null : (int) $contractTemplateId,
        ]);

        flash('success', 'Product created.');
        redirect('products.php');
    }
}

$adminTitle = 'Create Product';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Create Product</h1>
<?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>
<?php include __DIR__ . '/product-form.php'; ?>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
