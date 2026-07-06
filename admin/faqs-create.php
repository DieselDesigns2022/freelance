<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$faq = ['status' => 'published', 'sort_order' => 0];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $faq = $_POST;

    if (trim($_POST['question'] ?? '') === '') $errors[] = 'Question is required.';
    if (strlen(trim((string) ($_POST['question'] ?? ''))) > 255) $errors[] = 'Question must be 255 characters or fewer.';
    if (strlen(trim((string) ($_POST['category'] ?? ''))) > 100) $errors[] = 'Category must be 100 characters or fewer.';
    if (trim($_POST['answer'] ?? '') === '') $errors[] = 'Answer is required.';
    if (!in_array($_POST['status'] ?? '', ['draft', 'published'], true)) $errors[] = 'Invalid status.';

    if (!$errors) {
        db()->prepare('INSERT INTO faqs (question,answer,category,status,sort_order,created_at) VALUES (?,?,?,?,?,NOW())')
            ->execute([$_POST['question'], $_POST['answer'], $_POST['category'] ?: null, $_POST['status'], (int) $_POST['sort_order']]);
        flash('success', 'FAQ created.');
        redirect('faqs.php');
    }
}

$adminTitle = 'Add FAQ';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Add FAQ</h1>
<?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>
<?php include __DIR__ . '/faq-form.php'; ?>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
