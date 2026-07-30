<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/questionnaires.php';

require_admin();

if (!questionnaire_tables_ready(db())) {
    http_response_code(503);
    exit('Run the Phase 2.3 migration before managing questionnaires.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($id < 1) {
        flash('error', 'Choose a valid questionnaire.');
        redirect('questionnaires.php');
    }

    if ($action === 'duplicate') {
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $templateStmt = $pdo->prepare('SELECT * FROM questionnaire_templates WHERE id = ?');
            $templateStmt->execute([$id]);
            $template = $templateStmt->fetch();

            if (!$template) {
                throw new RuntimeException('Questionnaire not found.');
            }

            $baseTitle = 'Copy of ' . $template['title'];
            $title = $baseTitle;
            $suffix = 2;

            while (true) {
                $titleStmt = $pdo->prepare('SELECT id FROM questionnaire_templates WHERE title = ?');
                $titleStmt->execute([$title]);
                if (!$titleStmt->fetch()) {
                    break;
                }
                $title = $baseTitle . ' (' . $suffix++ . ')';
            }

            $insertTemplateStmt = $pdo->prepare(
                "INSERT INTO questionnaire_templates "
                . "(title, internal_description, status, created_at, updated_at) "
                . "VALUES (?, ?, 'draft', NOW(), NOW())"
            );
            $insertTemplateStmt->execute([$title, $template['internal_description']]);
            $newId = (int) $pdo->lastInsertId();

            $copyFieldsStmt = $pdo->prepare(
                'INSERT INTO questionnaire_fields '
                . '(questionnaire_template_id, field_key, field_type, label, admin_label, help_text, '
                . 'placeholder, options_json, validation_json, is_required, is_active, sort_order, created_at, updated_at) '
                . 'SELECT ?, field_key, field_type, label, admin_label, help_text, placeholder, options_json, '
                . 'validation_json, is_required, is_active, sort_order, NOW(), NOW() '
                . 'FROM questionnaire_fields WHERE questionnaire_template_id = ?'
            );
            $copyFieldsStmt->execute([$newId, $id]);

            $pdo->commit();
            flash('success', 'Questionnaire duplicated as a draft.');
            redirect('questionnaires-edit.php?id=' . $newId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'The questionnaire could not be duplicated. Please try again.');
            redirect('questionnaires.php');
        }
    }

    if ($action === 'archive') {
        $activeProductsStmt = db()->prepare(
            "SELECT COUNT(*) FROM products WHERE questionnaire_template_id = ? AND status = 'active'"
        );
        $activeProductsStmt->execute([$id]);

        if ((int) $activeProductsStmt->fetchColumn() > 0) {
            flash('error', 'Reassign or deactivate active products before archiving this questionnaire.');
        } else {
            $archiveStmt = db()->prepare(
                "UPDATE questionnaire_templates SET status = 'archived', updated_at = NOW() WHERE id = ?"
            );
            $archiveStmt->execute([$id]);
            flash('success', 'Questionnaire archived.');
        }

        redirect('questionnaires.php');
    }

    flash('error', 'Choose a valid questionnaire action.');
    redirect('questionnaires.php');
}

$rows = db()->query(
    'SELECT qt.*, COUNT(DISTINCT qf.id) AS field_count, COUNT(DISTINCT p.id) AS product_count '
    . 'FROM questionnaire_templates qt '
    . 'LEFT JOIN questionnaire_fields qf ON qf.questionnaire_template_id = qt.id '
    . 'LEFT JOIN products p ON p.questionnaire_template_id = qt.id '
    . 'GROUP BY qt.id ORDER BY qt.updated_at DESC, qt.id DESC'
)->fetchAll();

$adminTitle = 'Questionnaires';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="admin-page-heading">
    <h1>Questionnaires</h1>
    <a class="btn btn-accent" href="questionnaires-create.php">Create Questionnaire</a>
</div>

<?php if (!$rows): ?>
    <div class="empty">
        <h2>No questionnaires yet</h2>
        <p>Create a reusable customer intake template.</p>
    </div>
<?php else: ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th><th>Status</th><th>Fields</th><th>Products</th>
                    <th>Created</th><th>Updated</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['title']) ?></td>
                        <td><?= e($row['status']) ?></td>
                        <td><?= (int) $row['field_count'] ?></td>
                        <td><?= (int) $row['product_count'] ?></td>
                        <td><?= e($row['created_at']) ?></td>
                        <td><?= e($row['updated_at'] ?? $row['created_at']) ?></td>
                        <td>
                            <a class="btn btn-small" href="questionnaires-edit.php?id=<?= (int) $row['id'] ?>">Edit</a>
                            <form method="post" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-ghost" name="action" value="duplicate">Duplicate</button>
                                <?php if ($row['status'] !== 'archived'): ?>
                                    <button class="btn btn-ghost" name="action" value="archive" onclick="return confirm('Archive this questionnaire?')">Archive</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
