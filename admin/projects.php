<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int) $_POST['id'];

    if (isset($_POST['toggle'])) {
        db()->prepare("UPDATE portfolio_projects SET status=IF(status='published','draft','published'), updated_at=NOW() WHERE id=?")
            ->execute([$id]);
    }

    flash('success', 'Project status updated.');
    redirect('projects.php');
}

$filter = $_GET['filter'] ?? 'all';
$where = '1=1';
$params = [];

if ($filter === 'website') {
    $where = 'section_type=?';
    $params[] = 'website';
} elseif ($filter === 'shopify') {
    $where = 'section_type=?';
    $params[] = 'shopify_makeover';
} elseif (in_array($filter, ['published', 'draft'], true)) {
    $where = 'status=?';
    $params[] = $filter;
}

$stmt = db()->prepare("SELECT * FROM portfolio_projects WHERE $where ORDER BY sort_order ASC, created_at DESC");
$stmt->execute($params);
$projects = $stmt->fetchAll();
$adminTitle = 'Projects';

include __DIR__ . '/includes/admin-header.php';
?>
<h1>Projects</h1>
<p class="filters">
    <?php foreach (['all' => 'All', 'website' => 'Website Builds', 'shopify' => 'Shopify Make-Overs', 'published' => 'Published', 'draft' => 'Drafts'] as $key => $label): ?>
        <a class="btn btn-ghost" href="?filter=<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</p>
<?php if (!$projects): ?>
    <div class="empty">No projects added yet.</div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Thumbnail</th>
                    <th>Title</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Live URL</th>
                    <th>Sort</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td>
                            <?php if ($project['thumbnail_path']): ?>
                                <img class="admin-thumb" src="../<?= e($project['thumbnail_path']) ?>" alt="">
                            <?php endif; ?>
                        </td>
                        <td><?= e($project['title']) ?></td>
                        <td><?= e(section_label($project['section_type'])) ?></td>
                        <td><?= e(status_label($project['status'])) ?></td>
                        <td>
                            <?php if ($project['live_url']): ?>
                                <a href="<?= e($project['live_url']) ?>">Live</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $project['sort_order'] ?></td>
                        <td><?= e($project['created_at']) ?></td>
                        <td><?= e($project['updated_at'] ?: '—') ?></td>
                        <td>
                            <a href="projects-edit.php?id=<?= (int) $project['id'] ?>">Edit</a> |
                            <a href="projects-images.php?id=<?= (int) $project['id'] ?>">Images</a>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                                <button name="toggle" value="1">Publish/Unpublish</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
