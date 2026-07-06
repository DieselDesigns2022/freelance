<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

$counts = [];
foreach (['website', 'shopify_makeover'] as $section) {
    foreach (['published', 'draft'] as $status) {
        $q = db()->prepare('SELECT COUNT(*) FROM portfolio_projects WHERE section_type=? AND status=?');
        $q->execute([$section, $status]);
        $counts[$section][$status] = $q->fetchColumn();
    }
}

$requestCounts = [];
foreach (['new', 'reviewing', 'contacted', 'quoted'] as $status) {
    $q = db()->prepare('SELECT COUNT(*) FROM website_requests WHERE status=?');
    $q->execute([$status]);
    $requestCounts[$status] = $q->fetchColumn();
}

$recent = db()->query('SELECT * FROM portfolio_projects ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recentRequests = db()->query('SELECT * FROM website_requests ORDER BY created_at DESC LIMIT 5')->fetchAll();
$adminTitle = 'Dashboard';

include __DIR__ . '/includes/admin-header.php';
?>
<h1>Portfolio Dashboard</h1>
<div class="stats">
    <article><b><?= $counts['website']['published'] ?></b><span>Published Website Builds</span></article>
    <article><b><?= $counts['website']['draft'] ?></b><span>Draft Website Builds</span></article>
    <article><b><?= $counts['shopify_makeover']['published'] ?></b><span>Published Shopify Make-Overs</span></article>
    <article><b><?= $counts['shopify_makeover']['draft'] ?></b><span>Draft Shopify Make-Overs</span></article>
    <article><b><?= $requestCounts['new'] ?></b><span>New Website Requests</span></article>
    <article><b><?= $requestCounts['reviewing'] ?></b><span>Reviewing Requests</span></article>
    <article><b><?= $requestCounts['contacted'] ?></b><span>Contacted Requests</span></article>
    <article><b><?= $requestCounts['quoted'] ?></b><span>Quoted Requests</span></article>
</div>
<p>
    <a class="btn" href="projects-create.php">Add New Project</a>
    <a class="btn" href="requests.php">Manage Requests</a>
    <a class="btn btn-ghost" href="../index.php">View Public Site</a>
</p>
<section class="admin-card">
    <h2>Recent Website Requests</h2>
    <?php if ($recentRequests): ?><ul><?php foreach ($recentRequests as $request): ?><li><a href="request-view.php?id=<?= (int) $request['id'] ?>"><?= e($request['name']) ?></a> — <?= e($request['project_type']) ?> / <?= e($request['status']) ?></li><?php endforeach; ?></ul><?php else: ?><p>No website requests yet.</p><?php endif; ?>
</section>
<section class="admin-card">
    <h2>Recent Projects</h2>
    <?php if ($recent): ?><ul><?php foreach ($recent as $project): ?><li><a href="projects-edit.php?id=<?= (int) $project['id'] ?>"><?= e($project['title']) ?></a> — <?= e(section_label($project['section_type'])) ?> / <?= e(status_label($project['status'])) ?></li><?php endforeach; ?></ul><?php else: ?><p>No projects added yet.</p><?php endif; ?>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
