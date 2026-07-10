<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM contract_templates WHERE id = ?');
$stmt->execute([$id]);
$template = $stmt->fetch();
$errors = [];
$statuses = allowed_contract_template_statuses();

if (!$template) {
    http_response_code(404);
    exit('Not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $template = array_merge($template, $_POST);
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

    $dupe = db()->prepare('SELECT id FROM contract_templates WHERE slug = ? AND id != ?');
    $dupe->execute([$slug, $id]);
    if ($dupe->fetch()) {
        $errors[] = 'Slug already exists.';
    }

    if (!$errors) {
        db()->prepare('UPDATE contract_templates SET title=?, slug=?, service_type=?, version=?, body=?, status=?, updated_at=NOW() WHERE id=?')
            ->execute([$title, $slug, $serviceType, $version, $body, $status, $id]);

        flash('success', 'Template updated.');
        redirect('contract-templates.php');
    }
}

$adminTitle = 'Edit Contract Template';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Edit Contract Template</h1>
<?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>
<?php include __DIR__ . '/contract-template-form.php'; ?>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
