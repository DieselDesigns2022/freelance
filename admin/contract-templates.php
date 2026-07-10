<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

$templates = db()->query('SELECT * FROM contract_templates ORDER BY title, version')->fetchAll();
$adminTitle = 'Contract Templates';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Contract Templates</h1>
<p><a class="btn" href="contract-templates-create.php">Add Contract Template</a></p>
<section class="admin-card">
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Service</th>
                <th>Version</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $template): ?>
                <tr>
                    <td><?= e($template['title']) ?></td>
                    <td><?= e(service_type_label($template['service_type'])) ?></td>
                    <td><?= e($template['version']) ?></td>
                    <td><?= e($template['status']) ?></td>
                    <td>
                        <a href="contract-templates-edit.php?id=<?= (int) $template['id'] ?>">Edit</a>
                        · <a href="contract-template-preview.php?id=<?= (int) $template['id'] ?>">Preview</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
