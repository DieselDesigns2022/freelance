<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$statuses = ['new', 'reviewing', 'contacted', 'quoted', 'accepted', 'declined', 'archived'];
$filter = $_GET['status'] ?? 'all';
$where = '1=1';
$params = [];

if (in_array($filter, $statuses, true)) {
    $where = 'status=?';
    $params[] = $filter;
}

$stmt = db()->prepare("SELECT * FROM website_requests WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$requests = $stmt->fetchAll();
$adminTitle = 'Website Requests';

include __DIR__ . '/includes/admin-header.php';
?>
<h1>Website Requests</h1>
<p class="filters"><a class="btn btn-ghost" href="requests.php">All</a><?php foreach ($statuses as $status): ?> <a class="btn btn-ghost" href="?status=<?= e($status) ?>"><?= e(ucfirst($status)) ?></a><?php endforeach; ?></p>
<?php if (!$requests): ?>
    <div class="empty">No website requests found.</div>
<?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Business</th><th>Project Type</th><th>Budget</th><th>Timeline</th><th>Status</th><th>Created</th><th>View</th></tr></thead><tbody>
    <?php foreach ($requests as $request): ?>
        <tr><td><?= e($request['name']) ?></td><td><?= e($request['email']) ?></td><td><?= e($request['business_name'] ?: '—') ?></td><td><?= e($request['project_type']) ?></td><td><?= e($request['budget_range']) ?></td><td><?= e($request['timeline']) ?></td><td><?= e($request['status']) ?></td><td><?= e($request['created_at']) ?></td><td><a href="request-view.php?id=<?= (int) $request['id'] ?>">View</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
