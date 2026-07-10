<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$template = ['version' => '1.0'];
$errors = [];
$statuses = allowed_contract_template_statuses();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $template = $_POST;
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '') ?: slugify($title);
    $serviceType = $_POST['service_type'] ?? '';
    $version = trim($_POST['version'] ?? '') ?: '1.0';
    $body = trim($_POST['body'] ?? '');
    $status = $_POST['status'] ?? 'draft';

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($body === '') {
        $errors[] = 'Body is required.';
    }
    if (!in_array($serviceType, allowed_service_types(), true)) {
        $errors[] = 'Choose a valid service type.';
    }
    if (!in_array($status, $statuses, true)) {
        $errors[] = 'Choose a valid status.';
    }

    $dupe = db()->prepare('SELECT id FROM contract_templates WHERE slug = ?');
    $dupe->execute([$slug]);
    if ($dupe->fetch()) {
        $errors[] = 'Slug already exists.';
    }

    if (!$errors) {
        db()->prepare('INSERT INTO contract_templates (title, slug, service_type, version, body, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$title, $slug, $serviceType, $version, $body, $status]);

        flash('success', 'Template created.');
        redirect('contract-templates.php');
    }
}

$adminTitle = 'Create Contract Template';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Create Contract Template</h1>
<?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>
<?php include __DIR__ . '/contract-template-form.php'; ?>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
