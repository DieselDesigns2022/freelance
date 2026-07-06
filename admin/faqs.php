<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);

    if (isset($_POST['toggle'])) {
        db()->prepare("UPDATE faqs SET status=IF(status='published','draft','published'), updated_at=NOW() WHERE id=?")->execute([$id]);
        flash('success', 'FAQ status updated.');
    } elseif (isset($_POST['delete'])) {
        db()->prepare('DELETE FROM faqs WHERE id=?')->execute([$id]);
        flash('success', 'FAQ deleted.');
    }

    redirect('faqs.php');
}

$filter = $_GET['status'] ?? 'all';
$where = '1=1';
$params = [];

if (in_array($filter, ['draft', 'published'], true)) {
    $where = 'status=?';
    $params[] = $filter;
}

$stmt = db()->prepare("SELECT * FROM faqs WHERE $where ORDER BY sort_order ASC, created_at ASC");
$stmt->execute($params);
$faqs = $stmt->fetchAll();
$adminTitle = 'FAQs';

include __DIR__ . '/includes/admin-header.php';
?>
<h1>FAQs</h1>
<p><a class="btn" href="faqs-create.php">Add FAQ</a> <a class="btn btn-ghost" href="?status=all">All</a> <a class="btn btn-ghost" href="?status=published">Published</a> <a class="btn btn-ghost" href="?status=draft">Draft</a></p>
<?php if (!$faqs): ?><div class="empty">No FAQs added yet.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Question</th><th>Category</th><th>Status</th><th>Sort</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($faqs as $faq): ?><tr><td><?= e($faq['question']) ?></td><td><?= e($faq['category'] ?: '—') ?></td><td><?= e($faq['status']) ?></td><td><?= (int) $faq['sort_order'] ?></td><td><a href="faqs-edit.php?id=<?= (int) $faq['id'] ?>">Edit</a><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $faq['id'] ?>"> <button name="toggle" value="1">Publish/Draft</button> <button class="danger" name="delete" value="1" onclick="return confirm('Delete this FAQ?')">Delete</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
