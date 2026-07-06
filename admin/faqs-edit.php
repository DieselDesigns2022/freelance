<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM faqs WHERE id=?');
$stmt->execute([$id]);
$faq = $stmt->fetch();
if (!$faq) exit('FAQ not found.');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['delete_faq'])) {
        db()->prepare('DELETE FROM faqs WHERE id=?')->execute([$id]);
        flash('success', 'FAQ deleted.');
        redirect('faqs.php');
    }

    $faq = array_merge($faq, $_POST);
    if (trim($_POST['question'] ?? '') === '') $errors[] = 'Question is required.';
    if (strlen(trim((string) ($_POST['question'] ?? ''))) > 255) $errors[] = 'Question must be 255 characters or fewer.';
    if (strlen(trim((string) ($_POST['category'] ?? ''))) > 100) $errors[] = 'Category must be 100 characters or fewer.';
    if (trim($_POST['answer'] ?? '') === '') $errors[] = 'Answer is required.';
    if (!in_array($_POST['status'] ?? '', ['draft', 'published'], true)) $errors[] = 'Invalid status.';

    if (!$errors) {
        db()->prepare('UPDATE faqs SET question=?, answer=?, category=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?')
            ->execute([$_POST['question'], $_POST['answer'], $_POST['category'] ?: null, $_POST['status'], (int) $_POST['sort_order'], $id]);
        flash('success', 'FAQ updated.');
        redirect('faqs-edit.php?id=' . $id);
    }
}

$adminTitle = 'Edit FAQ';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Edit FAQ</h1>
<?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>
<?php include __DIR__ . '/faq-form.php'; ?>
<form method="post" onsubmit="return confirm('Delete this FAQ?')"><?= csrf_field() ?><button class="btn danger" name="delete_faq" value="1">Delete FAQ</button></form>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
