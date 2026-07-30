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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $questionnaire = array_merge($questionnaire, $_POST);
    $title = trim((string) ($questionnaire['title'] ?? ''));
    $description = trim((string) ($questionnaire['internal_description'] ?? ''));
    $status = (string) ($questionnaire['status'] ?? 'draft');

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if (!in_array($status, QUESTIONNAIRE_STATUSES, true)) {
        $errors[] = 'Choose a valid status.';
    }
    if ($status === 'active') {
        $errors[] = 'Create the questionnaire as a draft, add valid fields, and then activate it.';
    }

    if ($title !== '') {
        $duplicateStmt = db()->prepare('SELECT id FROM questionnaire_templates WHERE title = ?');
        $duplicateStmt->execute([$title]);
        if ($duplicateStmt->fetch()) {
            $errors[] = 'A questionnaire with this title already exists.';
        }
    }

    if (!$errors) {
        $insertStmt = db()->prepare(
            'INSERT INTO questionnaire_templates '
            . '(title, internal_description, status, created_at, updated_at) '
            . 'VALUES (?, ?, ?, NOW(), NOW())'
        );
        $insertStmt->execute([$title, $description ?: null, $status]);

        redirect('questionnaires-edit.php?id=' . (int) db()->lastInsertId());
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

    <button class="btn btn-accent">Create and add fields</button>
</form>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
