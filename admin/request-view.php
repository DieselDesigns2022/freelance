<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$statuses = ['new', 'reviewing', 'contacted', 'quoted', 'accepted', 'declined', 'archived'];
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM website_requests WHERE id=?');
$stmt->execute([$id]);
$request = $stmt->fetch();

if (!$request) {
    exit('Request not found.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['delete_request'])) {
        db()->prepare('DELETE FROM website_requests WHERE id=?')->execute([$id]);
        flash('success', 'Request deleted.');
        redirect('requests.php');
    }

    if (isset($_POST['archive_request'])) {
        db()->prepare("UPDATE website_requests SET status='archived', updated_at=NOW() WHERE id=?")->execute([$id]);
        flash('success', 'Request archived.');
        redirect('request-view.php?id=' . $id);
    }

    $status = $_POST['status'] ?? $request['status'];
    if (!in_array($status, $statuses, true)) {
        $errors[] = 'Invalid request status.';
    }

    if (!$errors) {
        $markContacted = isset($_POST['mark_contacted']);
        if ($markContacted && !in_array($request['status'], ['accepted', 'declined', 'archived'], true)) {
            $status = 'contacted';
        }

        $sql = 'UPDATE website_requests SET status=?, admin_notes=?, updated_at=NOW()';
        $params = [$status, $_POST['admin_notes'] ?: null];

        if ($markContacted && !$request['contacted_at']) {
            $sql .= ', contacted_at=NOW()';
        }

        $sql .= ' WHERE id=?';
        $params[] = $id;
        db()->prepare($sql)->execute($params);

        flash('success', 'Request updated.');
        redirect('request-view.php?id=' . $id);
    }
}

$adminTitle = 'View Website Request';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Website Request</h1>
<?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>
<section class="admin-card detail-list">
    <?php foreach (['name','email','phone','business_name','preferred_contact_method','project_type','platform','current_website_url','services_needed','project_description','inspiration_links','budget_range','timeline','notes','status','created_at','updated_at','contacted_at'] as $field): ?>
        <p><strong><?= e(str_replace('_', ' ', ucfirst($field))) ?>:</strong><br><?= nl2br(e($request[$field] ?: '—')) ?></p>
    <?php endforeach; ?>
</section>
<section class="admin-card">
    <h2>Admin Notes & Status</h2>
    <form method="post">
        <?= csrf_field() ?>
        <label>Status<select name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $request['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
        <label>Private admin notes<textarea name="admin_notes"><?= e($request['admin_notes'] ?? '') ?></textarea></label>
        <button class="btn" name="save_request" value="1">Save Request</button>
        <button class="btn btn-ghost" name="mark_contacted" value="1">Mark Contacted</button>
        <button class="btn btn-ghost" name="archive_request" value="1">Archive</button>
    </form>
    <form method="post" onsubmit="return confirm('Delete this request?')">
        <?= csrf_field() ?>
        <button class="btn danger" name="delete_request" value="1">Delete Request</button>
    </form>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
