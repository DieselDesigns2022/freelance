<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/questionnaires.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$templateStmt = db()->prepare('SELECT * FROM questionnaire_templates WHERE id = ?');
$templateStmt->execute([$id]);
$questionnaire = $templateStmt->fetch();
$errors = [];
$fieldValues = null;

if (!$questionnaire) {
    http_response_code(404);
    exit('Questionnaire not found.');
}

function questionnaire_parse_lines(string $value): array
{
    $lines = preg_split('/\R/', str_replace(',', "\n", $value)) ?: [];
    $normalized = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '' && !in_array($line, $normalized, true)) {
            $normalized[] = $line;
        }
    }

    return $normalized;
}

function questionnaire_field_has_history(PDO $pdo, int $templateId, string $fieldKey): bool
{
    $answerStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM order_questionnaire_answers '
        . 'WHERE field_key = ? AND questionnaire_snapshot_id IN '
        . '(SELECT id FROM order_questionnaire_snapshots WHERE questionnaire_template_id = ?)'
    );
    $answerStmt->execute([$fieldKey, $templateId]);

    $answerCount = (int) $answerStmt->fetchColumn();

    $snapshotStmt = $pdo->prepare(
        'SELECT questionnaire_snapshot_json FROM order_questionnaire_snapshots '
        . 'WHERE questionnaire_template_id = ?'
    );
    $snapshotStmt->execute([$templateId]);

    return questionnaire_field_history_found(
        $answerCount,
        $snapshotStmt->fetchAll(PDO::FETCH_COLUMN),
        $fieldKey
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'settings') {
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
            $errors = array_merge(
                $errors,
                questionnaire_definition_errors(load_questionnaire_fields(db(), $id))
            );
        } else {
            $activeProductStmt = db()->prepare(
                "SELECT COUNT(*) FROM products WHERE questionnaire_template_id = ? AND status = 'active'"
            );
            $activeProductStmt->execute([$id]);
            if ((int) $activeProductStmt->fetchColumn() > 0) {
                $errors[] = 'An active product uses this questionnaire. Reassign or deactivate it first.';
            }
        }

        if (!$errors) {
            try {
                $updateStmt = db()->prepare(
                    'UPDATE questionnaire_templates '
                    . 'SET title = ?, internal_description = ?, status = ?, updated_at = NOW() '
                    . 'WHERE id = ?'
                );
                $updateStmt->execute([$title, $description ?: null, $status, $id]);
                flash('success', 'Questionnaire settings updated.');
                redirect('questionnaires-edit.php?id=' . $id);
            } catch (PDOException $exception) {
                $errors[] = 'A questionnaire with that title already exists.';
            }
        }
    } else {
        $fieldId = (int) ($_POST['field_id'] ?? 0);
        $existing = null;

        if ($fieldId > 0) {
            $fieldStmt = db()->prepare(
                'SELECT * FROM questionnaire_fields WHERE id = ? AND questionnaire_template_id = ?'
            );
            $fieldStmt->execute([$fieldId, $id]);
            $existing = $fieldStmt->fetch() ?: null;

            if (!$existing) {
                $errors[] = 'Field not found in this questionnaire.';
            }
        }

        if ($action === 'deactivate' && $existing) {
            $deactivateStmt = db()->prepare(
                'UPDATE questionnaire_fields SET is_active = 0, updated_at = NOW() '
                . 'WHERE id = ? AND questionnaire_template_id = ?'
            );
            $deactivateStmt->execute([$fieldId, $id]);
            flash('success', 'Field deactivated; historical answers remain unchanged.');
            redirect('questionnaires-edit.php?id=' . $id);
        }

        if (in_array($action, ['move_up', 'move_down'], true) && $existing) {
            $orderedStmt = db()->prepare(
                'SELECT id FROM questionnaire_fields WHERE questionnaire_template_id = ? ORDER BY sort_order, id'
            );
            $orderedStmt->execute([$id]);
            $orderedIds = array_map('intval', $orderedStmt->fetchAll(PDO::FETCH_COLUMN));
            $position = array_search($fieldId, $orderedIds, true);
            $target = $action === 'move_up' ? $position - 1 : $position + 1;

            if ($position !== false && isset($orderedIds[$target])) {
                [$orderedIds[$position], $orderedIds[$target]] = [$orderedIds[$target], $orderedIds[$position]];
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $orderStmt = $pdo->prepare(
                        'UPDATE questionnaire_fields SET sort_order = ?, updated_at = NOW() '
                        . 'WHERE id = ? AND questionnaire_template_id = ?'
                    );
                    foreach ($orderedIds as $index => $orderedId) {
                        $orderStmt->execute([($index + 1) * 10, $orderedId, $id]);
                    }
                    $pdo->commit();
                } catch (Throwable $exception) {
                    $pdo->rollBack();
                    throw $exception;
                }
            }

            redirect('questionnaires-edit.php?id=' . $id);
        }

        if ($action === 'duplicate' && $existing) {
            $baseKey = $existing['field_key'] . '_copy';
            $key = $baseKey;
            $suffix = 2;

            while (true) {
                $keyStmt = db()->prepare(
                    'SELECT id FROM questionnaire_fields WHERE questionnaire_template_id = ? AND field_key = ?'
                );
                $keyStmt->execute([$id, $key]);
                if (!$keyStmt->fetch()) {
                    break;
                }
                $key = $baseKey . $suffix++;
            }

            $duplicateStmt = db()->prepare(
                'INSERT INTO questionnaire_fields '
                . '(questionnaire_template_id, field_key, field_type, label, admin_label, help_text, placeholder, '
                . 'options_json, validation_json, is_required, is_active, sort_order, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $duplicateStmt->execute([
                $id,
                $key,
                $existing['field_type'],
                'Copy of ' . $existing['label'],
                $existing['admin_label'],
                $existing['help_text'],
                $existing['placeholder'],
                $existing['options_json'],
                $existing['validation_json'],
                $existing['is_required'],
                $existing['is_active'],
                (int) $existing['sort_order'] + 1,
            ]);
            flash('success', 'Field duplicated.');
            redirect('questionnaires-edit.php?id=' . $id);
        }

        if (in_array($action, ['add_field', 'save_field'], true)) {
            $fieldValues = $_POST;
            $key = trim((string) ($fieldValues['field_key'] ?? ''));
            $type = (string) ($fieldValues['field_type'] ?? '');
            $label = trim((string) ($fieldValues['label'] ?? ''));
            $structural = in_array($type, QUESTIONNAIRE_STRUCTURAL_TYPES, true);
            $options = questionnaire_parse_lines((string) ($fieldValues['options'] ?? ''));
            $validation = [];

            if (!questionnaire_field_key_is_valid($key)) {
                $errors[] = 'Field key must use 2–100 lowercase letters, numbers, and underscores, beginning with a letter.';
            }
            if (!in_array($type, QUESTIONNAIRE_FIELD_TYPES, true)) {
                $errors[] = 'Choose a valid field type.';
            }
            if ($label === '') {
                $errors[] = 'Customer-facing label/content is required.';
            }
            if (in_array($type, QUESTIONNAIRE_OPTION_TYPES, true) && !$options) {
                $errors[] = 'Option fields require at least one non-empty option.';
            }

            if ($existing && questionnaire_field_has_history(db(), $id, (string) $existing['field_key'])) {
                if ($key !== $existing['field_key']) {
                    $errors[] = 'A historical field key cannot change; duplicate and deactivate the old field instead.';
                }
                if ($type !== $existing['field_type']) {
                    $errors[] = 'A historical field type cannot change; duplicate and deactivate the old field instead.';
                }
            }

            foreach (['min_length', 'max_length', 'min_number', 'max_number', 'max_file_count', 'max_file_size'] as $setting) {
                $relevant = match ($setting) {
                    'min_length', 'max_length' => in_array($type, ['short_text', 'long_text'], true),
                    'min_number', 'max_number' => $type === 'number',
                    'max_file_count', 'max_file_size' => in_array($type, QUESTIONNAIRE_FILE_TYPES, true),
                    default => false,
                };
                if (!$relevant) {
                    continue;
                }
                $raw = trim((string) ($fieldValues[$setting] ?? ''));
                if ($raw !== '') {
                    if (!is_numeric($raw)) {
                        $errors[] = ucwords(str_replace('_', ' ', $setting)) . ' must be numeric.';
                    } else {
                        $validation[$setting] = $setting === 'min_number' || $setting === 'max_number'
                            ? (float) $raw
                            : (int) $raw;
                    }
                }
            }

            if (($validation['min_length'] ?? 0) < 0 || ($validation['max_length'] ?? 0) < 0) {
                $errors[] = 'Text lengths cannot be negative.';
            }
            if (isset($validation['min_length'], $validation['max_length']) && $validation['min_length'] > $validation['max_length']) {
                $errors[] = 'Minimum text length cannot exceed maximum text length.';
            }
            if (isset($validation['min_number'], $validation['max_number']) && $validation['min_number'] > $validation['max_number']) {
                $errors[] = 'Minimum number cannot exceed maximum number.';
            }
            if (($validation['max_file_count'] ?? 0) < 0 || ($validation['max_file_size'] ?? 0) < 0) {
                $errors[] = 'File size and count limits cannot be negative.';
            }

            if ($type === 'file') {
                $validation['max_file_count'] = 1;
            }

            $extensions = questionnaire_parse_lines((string) ($fieldValues['allowed_extensions'] ?? ''));
            if ($extensions && in_array($type, QUESTIONNAIRE_FILE_TYPES, true)) {
                $validation['allowed_extensions'] = array_map('strtolower', $extensions);
            }

            if (!in_array($type, ['short_text', 'long_text'], true)) {
                unset($validation['min_length'], $validation['max_length']);
            }
            if ($type !== 'number') {
                unset($validation['min_number'], $validation['max_number']);
            }
            if (!in_array($type, QUESTIONNAIRE_FILE_TYPES, true)) {
                unset(
                    $validation['max_file_count'],
                    $validation['max_file_size'],
                    $validation['allowed_extensions']
                );
            }

            if (!$errors) {
                $parameters = [
                    $key,
                    $type,
                    $label,
                    trim((string) ($fieldValues['admin_label'] ?? '')) ?: null,
                    trim((string) ($fieldValues['help_text'] ?? '')) ?: null,
                    $structural ? null : (trim((string) ($fieldValues['placeholder'] ?? '')) ?: null),
                    in_array($type, QUESTIONNAIRE_OPTION_TYPES, true) ? json_encode($options, JSON_THROW_ON_ERROR) : null,
                    $validation ? json_encode($validation, JSON_THROW_ON_ERROR) : null,
                    $structural ? 0 : (isset($fieldValues['is_required']) ? 1 : 0),
                    isset($fieldValues['is_active']) ? 1 : 0,
                    (int) ($fieldValues['sort_order'] ?? 0),
                ];

                try {
                    if ($existing) {
                        $parameters[] = $fieldId;
                        $parameters[] = $id;
                        $saveStmt = db()->prepare(
                            'UPDATE questionnaire_fields SET field_key = ?, field_type = ?, label = ?, '
                            . 'admin_label = ?, help_text = ?, placeholder = ?, options_json = ?, validation_json = ?, '
                            . 'is_required = ?, is_active = ?, sort_order = ?, updated_at = NOW() '
                            . 'WHERE id = ? AND questionnaire_template_id = ?'
                        );
                    } else {
                        array_unshift($parameters, $id);
                        $saveStmt = db()->prepare(
                            'INSERT INTO questionnaire_fields '
                            . '(questionnaire_template_id, field_key, field_type, label, admin_label, help_text, placeholder, '
                            . 'options_json, validation_json, is_required, is_active, sort_order, created_at, updated_at) '
                            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                        );
                    }

                    $saveStmt->execute($parameters);
                    flash('success', $existing ? 'Field updated.' : 'Field added.');
                    redirect('questionnaires-edit.php?id=' . $id);
                } catch (PDOException $exception) {
                    $errors[] = 'That field key is already used by this questionnaire.';
                }
            }
        }
    }
}

$fields = load_questionnaire_fields(db(), $id);
$editId = (int) ($_GET['field'] ?? 0);
$editing = null;

foreach ($fields as $field) {
    if ((int) $field['id'] === $editId) {
        $editing = $field;
        break;
    }
}

if ($fieldValues === null) {
    $fieldValues = $editing ?: [
        'field_key' => '',
        'field_type' => 'short_text',
        'label' => '',
        'admin_label' => '',
        'help_text' => '',
        'placeholder' => '',
        'options' => [],
        'validation' => [],
        'is_required' => 0,
        'is_active' => 1,
        'sort_order' => (count($fields) + 1) * 10,
    ];
}

$fieldOptions = isset($fieldValues['options'])
    ? (array) $fieldValues['options']
    : questionnaire_parse_lines((string) ($fieldValues['options'] ?? ''));
if (isset($fieldValues['options']) && is_string($fieldValues['options'])) {
    $fieldOptions = questionnaire_parse_lines($fieldValues['options']);
}
$fieldValidation = isset($fieldValues['validation']) && is_array($fieldValues['validation'])
    ? $fieldValues['validation']
    : $fieldValues;

$adminTitle = 'Edit Questionnaire';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Edit Questionnaire</h1>

<?php foreach ($errors as $error): ?>
    <p class="error-text"><?= e($error) ?></p>
<?php endforeach; ?>

<section class="admin-card">
    <h2>Questionnaire Settings</h2>
    <form method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="settings">
        <label>Title *
            <input name="title" required value="<?= e($questionnaire['title']) ?>">
        </label>
        <label>Internal description
            <textarea name="internal_description"><?= e($questionnaire['internal_description'] ?? '') ?></textarea>
        </label>
        <label>Status
            <select name="status">
                <?php foreach (QUESTIONNAIRE_STATUSES as $status): ?>
                    <option value="<?= e($status) ?>" <?= $questionnaire['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn">Save settings</button>
    </form>
</section>

<section class="admin-card">
    <h2>Questionnaire Fields</h2>
    <p class="helper">Field keys are permanent answer identifiers. Use Move Up and Move Down to reorder. Deactivate submitted fields instead of deleting them.</p>
    <?php if (!$fields): ?><p>No fields yet.</p><?php endif; ?>

    <?php foreach ($fields as $index => $field): ?>
        <article class="admin-card">
            <strong>#<?= (int) $field['sort_order'] ?> · <?= e($field['admin_label'] ?: $field['label']) ?></strong>
            <p>
                <?= e($field['field_type']) ?> ·
                <?= in_array($field['field_type'], QUESTIONNAIRE_STRUCTURAL_TYPES, true) ? 'structural' : (!empty($field['is_required']) ? 'required' : 'optional') ?> ·
                <?= !empty($field['is_active']) ? 'active' : 'inactive' ?> ·
                <code><?= e($field['field_key']) ?></code>
            </p>
            <a class="btn btn-small" href="questionnaires-edit.php?id=<?= $id ?>&field=<?= (int) $field['id'] ?>#field-form">Edit</a>
            <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="field_id" value="<?= (int) $field['id'] ?>">
                <button class="btn btn-ghost" name="action" value="move_up" <?= $index === 0 ? 'disabled' : '' ?>>Move Up</button>
                <button class="btn btn-ghost" name="action" value="move_down" <?= $index === count($fields) - 1 ? 'disabled' : '' ?>>Move Down</button>
                <button class="btn btn-ghost" name="action" value="duplicate">Duplicate</button>
                <?php if ($field['is_active']): ?>
                    <button class="btn btn-ghost" name="action" value="deactivate">Deactivate</button>
                <?php endif; ?>
            </form>
        </article>
    <?php endforeach; ?>
</section>

<section class="admin-card" id="field-form">
    <h2><?= $editing ? 'Edit Field' : 'Add Field' ?></h2>
    <form method="post" class="admin-form" data-questionnaire-field-form>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'save_field' : 'add_field' ?>">
        <input type="hidden" name="field_id" value="<?= (int) ($editing['id'] ?? 0) ?>">

        <label>Stable field key *
            <input name="field_key" required pattern="[a-z][a-z0-9_]{1,99}" value="<?= e((string) ($fieldValues['field_key'] ?? '')) ?>">
        </label>
        <label>Field type *
            <select name="field_type" data-field-type>
                <?php foreach (QUESTIONNAIRE_FIELD_TYPES as $type): ?>
                    <option value="<?= e($type) ?>" <?= ($fieldValues['field_type'] ?? 'short_text') === $type ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $type)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Customer-facing label/content *
            <textarea name="label" required><?= e((string) ($fieldValues['label'] ?? '')) ?></textarea>
        </label>
        <label>Internal admin label
            <input name="admin_label" value="<?= e((string) ($fieldValues['admin_label'] ?? '')) ?>">
        </label>
        <label>Help text
            <textarea name="help_text"><?= e((string) ($fieldValues['help_text'] ?? '')) ?></textarea>
        </label>
        <label data-control="placeholder">Placeholder
            <input name="placeholder" value="<?= e((string) ($fieldValues['placeholder'] ?? '')) ?>">
        </label>
        <label data-control="options">Options (one per line)
            <textarea name="options"><?= e(implode("\n", $fieldOptions)) ?></textarea>
        </label>
        <div class="product-form-grid">
            <label data-control="text-length">Minimum text length<input type="number" min="0" name="min_length" value="<?= e(isset($fieldValidation['min_length']) ? (string) $fieldValidation['min_length'] : '') ?>"></label>
            <label data-control="text-length">Maximum text length<input type="number" min="0" name="max_length" value="<?= e(isset($fieldValidation['max_length']) ? (string) $fieldValidation['max_length'] : '') ?>"></label>
            <label data-control="number-range">Minimum number<input type="number" step="any" name="min_number" value="<?= e(isset($fieldValidation['min_number']) ? (string) $fieldValidation['min_number'] : '') ?>"></label>
            <label data-control="number-range">Maximum number<input type="number" step="any" name="max_number" value="<?= e(isset($fieldValidation['max_number']) ? (string) $fieldValidation['max_number'] : '') ?>"></label>
            <label data-control="file-count">Maximum file count<input type="number" min="1" name="max_file_count" value="<?= e(isset($fieldValidation['max_file_count']) ? (string) $fieldValidation['max_file_count'] : '') ?>"></label>
            <label data-control="file-settings">Maximum file size in bytes<input type="number" min="1" name="max_file_size" value="<?= e(isset($fieldValidation['max_file_size']) ? (string) $fieldValidation['max_file_size'] : '') ?>"></label>
        </div>
        <label data-control="file-settings">Allowed extensions (comma or line separated)
            <input name="allowed_extensions" value="<?= e(implode(',', $fieldValidation['allowed_extensions'] ?? [])) ?>">
        </label>
        <label>Display order
            <input type="number" name="sort_order" value="<?= (int) ($fieldValues['sort_order'] ?? 0) ?>">
        </label>
        <label data-control="required"><input type="checkbox" name="is_required" value="1" <?= !empty($fieldValues['is_required']) ? 'checked' : '' ?>> Required</label>
        <label><input type="checkbox" name="is_active" value="1" <?= !empty($fieldValues['is_active']) ? 'checked' : '' ?>> Active</label>
        <button class="btn btn-accent"><?= $editing ? 'Save field' : 'Add field' ?></button>
    </form>
</section>
<script>
(() => {
    const form = document.querySelector('[data-questionnaire-field-form]');
    if (!form) return;
    const typeSelect = form.querySelector('[data-field-type]');
    const controls = form.querySelectorAll('[data-control]');
    const update = () => {
        const type = typeSelect.value;
        const visible = {
            options: ['dropdown', 'radio', 'checkboxes'].includes(type),
            'text-length': ['short_text', 'long_text'].includes(type),
            'number-range': type === 'number',
            'file-settings': ['file', 'multiple_files'].includes(type),
            'file-count': type === 'multiple_files',
            required: !['section_heading', 'information'].includes(type),
            placeholder: !['section_heading', 'information', 'dropdown', 'radio', 'checkboxes', 'yes_no', 'file', 'multiple_files'].includes(type),
        };
        controls.forEach((control) => {
            control.hidden = !visible[control.dataset.control];
        });
    };
    typeSelect.addEventListener('change', update);
    update();
})();
</script>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
