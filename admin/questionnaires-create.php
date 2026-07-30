<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/questionnaires.php';

require_admin();

if (!questionnaire_tables_ready(db())) {
    http_response_code(503);
    exit('Run the Phase 2.3 migration first.');
}

$errors = [];
$questionnaire = [
    'title' => '',
    'internal_description' => '',
    'status' => 'draft',
];
$templates = db()->query("SELECT id, title FROM questionnaire_templates WHERE status <> 'archived' ORDER BY title")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $questionnaire = array_merge($questionnaire, $_POST);
    $title = trim((string) ($questionnaire['title'] ?? ''));
    $description = trim((string) ($questionnaire['internal_description'] ?? ''));
    $status = (string) ($questionnaire['status'] ?? 'draft');
    $creationMode = (string) ($_POST['creation_mode'] ?? 'blank');
    $sourceId = (int) ($_POST['source_questionnaire_id'] ?? 0);

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if (!in_array($status, QUESTIONNAIRE_STATUSES, true)) {
        $errors[] = 'Choose a valid status.';
    }
    if ($status === 'active') {
        $errors[] = 'Create the questionnaire as a draft, add valid fields, and then activate it.';
    }
    if (!in_array($creationMode, ['blank', 'copy'], true)) {
        $errors[] = 'Choose how to start the questionnaire.';
    }
    if ($creationMode === 'copy') {
        $sourceStmt = db()->prepare('SELECT id FROM questionnaire_templates WHERE id = ?');
        $sourceStmt->execute([$sourceId]);
        if (!$sourceStmt->fetchColumn()) $errors[] = 'Choose an existing questionnaire to copy.';
    }

    if ($title !== '') {
        $duplicateStmt = db()->prepare('SELECT id FROM questionnaire_templates WHERE title = ?');
        $duplicateStmt->execute([$title]);
        if ($duplicateStmt->fetch()) {
            $errors[] = 'A questionnaire with this title already exists.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $insertStmt = $pdo->prepare('INSERT INTO questionnaire_templates (title, internal_description, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
            $insertStmt->execute([$title, $description ?: null, $status]);
            $newId = (int) $pdo->lastInsertId();
            if ($creationMode === 'copy') {
                $sourceFields = load_questionnaire_fields($pdo, $sourceId);
                questionnaire_copy_fields($pdo, $sourceId, $newId, array_column($sourceFields, 'id'), 0);
            }
            $pdo->commit();
            redirect('questionnaires-edit.php?id=' . $newId);
        } catch (Throwable $exception) {
            $pdo->rollBack();
            $errors[] = 'The questionnaire could not be created.';
        }
    }
}

$adminTitle = 'Create Questionnaire';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Create Questionnaire</h1>

<?php foreach ($errors as $error): ?>
    <p class="error-text"><?= e($error) ?></p>
<?php endforeach; ?>

<form method="post" class="admin-form admin-card">
    <?= csrf_field() ?>

    <label>Title *
        <input name="title" required maxlength="255" value="<?= e($questionnaire['title']) ?>">
    </label>

    <label>Internal description
        <textarea name="internal_description"><?= e($questionnaire['internal_description']) ?></textarea>
    </label>

    <label>Status
        <select name="status">
            <?php foreach (QUESTIONNAIRE_STATUSES as $status): ?>
                <option value="<?= e($status) ?>" <?= $questionnaire['status'] === $status ? 'selected' : '' ?>>
                    <?= e(ucfirst($status)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>New questionnaires must remain draft until valid fields have been added.</small>
    </label>

    <fieldset class="questionnaire-start-options">
        <legend>Start with</legend>
        <label><input type="radio" name="creation_mode" value="blank" <?= ($_POST['creation_mode'] ?? 'blank') === 'blank' ? 'checked' : '' ?>> Start Blank</label>
        <label><input type="radio" name="creation_mode" value="copy" <?= ($_POST['creation_mode'] ?? '') === 'copy' ? 'checked' : '' ?>> Copy Existing Questionnaire</label>
        <label data-copy-source>Questionnaire to copy
            <select name="source_questionnaire_id"><option value="">Choose a questionnaire</option><?php foreach ($templates as $template): ?><option value="<?= (int) $template['id'] ?>" <?= (int) ($_POST['source_questionnaire_id'] ?? 0) === (int) $template['id'] ? 'selected' : '' ?>><?= e($template['title']) ?></option><?php endforeach; ?></select>
        </label>
    </fieldset>

    <button class="btn btn-accent">Create and add fields</button>
</form>
<script>(()=>{const modes=document.querySelectorAll('[name=creation_mode]'), source=document.querySelector('[data-copy-source]'); const update=()=>source.hidden=document.querySelector('[name=creation_mode]:checked').value!=='copy'; modes.forEach(mode=>mode.addEventListener('change',update)); update();})();</script>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
