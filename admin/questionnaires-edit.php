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
    } elseif ($action === 'reorder') {
        $orderedIds = array_values(array_filter(array_map('intval', explode(',', (string) ($_POST['field_order'] ?? '')))));
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if (!questionnaire_save_field_order($pdo, $id, $orderedIds)) {
                $pdo->rollBack();
                flash('error', 'The question order was invalid. Refresh the builder and try again.');
                redirect('questionnaires-edit.php?id=' . $id);
            }
            $pdo->commit();
            flash('success', 'Question order saved.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'The question order could not be saved. Please try again.');
        }
        redirect('questionnaires-edit.php?id=' . $id);
    } elseif ($action === 'import_fields') {
        $sourceId = (int) ($_POST['source_questionnaire_id'] ?? 0);
        $selected = is_array($_POST['import_field_ids'] ?? null) ? $_POST['import_field_ids'] : [];
        $insertIndex = max(0, (int) ($_POST['insert_index'] ?? 0));
        $sourceCheck = db()->prepare('SELECT id FROM questionnaire_templates WHERE id = ? AND id <> ?');
        $sourceCheck->execute([$sourceId, $id]);
        if (!$sourceCheck->fetchColumn() || !$selected) {
            $errors[] = 'Choose a source questionnaire and at least one question.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $count = questionnaire_copy_fields($pdo, $sourceId, $id, $selected, $insertIndex);
                if ($count === 0) {
                    $pdo->rollBack();
                    $errors[] = 'The selected questions were not available in that questionnaire.';
                } else {
                    $pdo->commit();
                    flash('success', $count . ' question' . ($count === 1 ? '' : 's') . ' imported.');
                    redirect('questionnaires-edit.php?id=' . $id);
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'The selected questions could not be imported. Please try again.';
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
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    flash('error', 'The question order could not be changed. Please try again.');
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
            $insertionIndex = max(0, (int) ($fieldValues['insertion_index'] ?? count(load_questionnaire_fields(db(), $id))));
            if (!$existing && $key === '' && $label !== '') {
                $key = questionnaire_unique_field_key(db(), $id, $label);
                $fieldValues['field_key'] = $key;
            }
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

            if ($type === 'addon') {
                $method = (string) ($fieldValues['pricing_method'] ?? '');
                $price = trim((string) ($fieldValues['price'] ?? ''));
                $priceCents = questionnaire_dollars_to_cents($price);
                if (!in_array($method, QUESTIONNAIRE_ADDON_PRICING_METHODS, true)) $errors[] = 'Choose a valid add-on pricing method.';
                if ($priceCents === null) $errors[] = 'Price must be a non-negative dollar amount with no more than two decimal places.';
                $validation['pricing_method'] = $method;
                $validation['unit_price_cents'] = $priceCents ?? -1;
                foreach (['included_quantity', 'min_quantity', 'max_quantity', 'quantity_step'] as $setting) {
                    $raw = trim((string) ($fieldValues[$setting] ?? ($setting === 'quantity_step' ? '1' : '0')));
                    if (preg_match('/^\d+$/', $raw) !== 1) $errors[] = ucwords(str_replace('_', ' ', $setting)) . ' must be a non-negative integer.';
                    $validation[$setting] = preg_match('/^\d+$/', $raw) === 1 ? (int) $raw : -1;
                }
                if ($validation['quantity_step'] < 1) $errors[] = 'Quantity step must be at least 1.';
                if ($validation['max_quantity'] < $validation['min_quantity']) $errors[] = 'Minimum quantity cannot exceed maximum quantity.';
                if ($method === 'flat_fee') {
                    $validation['included_quantity'] = 0; $validation['min_quantity'] = 0; $validation['max_quantity'] = 1; $validation['quantity_step'] = 1;
                }
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
                    ($structural || $type === 'addon') ? null : (trim((string) ($fieldValues['placeholder'] ?? '')) ?: null),
                    in_array($type, QUESTIONNAIRE_OPTION_TYPES, true) ? json_encode($options, JSON_THROW_ON_ERROR) : null,
                    $validation ? json_encode($validation, JSON_THROW_ON_ERROR) : null,
                    $structural ? 0 : (isset($fieldValues['is_required']) ? 1 : 0),
                    isset($fieldValues['is_active']) ? 1 : 0,
                    (int) ($fieldValues['sort_order'] ?? ((count(load_questionnaire_fields(db(), $id)) + 1) * 10)),
                ];

                $pdo = db();
                try {
                    if ($existing) {
                        $parameters[] = $fieldId;
                        $parameters[] = $id;
                        $saveStmt = $pdo->prepare(
                            'UPDATE questionnaire_fields SET field_key = ?, field_type = ?, label = ?, '
                            . 'admin_label = ?, help_text = ?, placeholder = ?, options_json = ?, validation_json = ?, '
                            . 'is_required = ?, is_active = ?, sort_order = ?, updated_at = NOW() '
                            . 'WHERE id = ? AND questionnaire_template_id = ?'
                        );
                    } else {
                        $existingOrder = array_map(
                            static fn (array $field): int => (int) $field['id'],
                            load_questionnaire_fields($pdo, $id)
                        );
                        $insertionIndex = min($insertionIndex, count($existingOrder));
                        $pdo->beginTransaction();
                        array_unshift($parameters, $id);
                        $saveStmt = $pdo->prepare(
                            'INSERT INTO questionnaire_fields '
                            . '(questionnaire_template_id, field_key, field_type, label, admin_label, help_text, placeholder, '
                            . 'options_json, validation_json, is_required, is_active, sort_order, created_at, updated_at) '
                            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                        );
                    }

                    $saveStmt->execute($parameters);
                    if (!$existing) {
                        $newFieldId = (int) $pdo->lastInsertId();
                        array_splice($existingOrder, $insertionIndex, 0, [$newFieldId]);
                        if (!questionnaire_save_field_order($pdo, $id, $existingOrder)) {
                            throw new RuntimeException('New field ordering failed.');
                        }
                        $pdo->commit();
                    }
                    flash('success', $existing ? 'Field updated.' : 'Field added.');
                    redirect('questionnaires-edit.php?id=' . $id);
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'That field key is already used by this questionnaire.';
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = $existing
                        ? 'The question could not be saved. Please try again.'
                        : 'The question could not be added. Please try again.';
                }
            }
        }
    }
}

$fields = load_questionnaire_fields(db(), $id);
$editId = (int) ($_GET['field'] ?? ($fieldValues['field_id'] ?? 0));
$editing = null;
foreach ($fields as $field) {
    if ((int) $field['id'] === $editId) {
        $editing = $field;
        break;
    }
}
$templatesStmt = db()->prepare('SELECT id, title FROM questionnaire_templates WHERE id <> ? ORDER BY title');
$templatesStmt->execute([$id]);
$importTemplates = $templatesStmt->fetchAll();
$importFields = [];
foreach ($importTemplates as $template) {
    $importFields[(int) $template['id']] = load_questionnaire_fields(db(), (int) $template['id']);
    foreach ($importFields[(int) $template['id']] as &$importField) {
        $importField['field_type_label'] = questionnaire_field_type_label((string) $importField['field_type']);
    }
    unset($importField);
}

function render_field_editor(array $values, bool $editing, int $fieldId, int $sortOrder, int $insertionIndex = 0): void
{
    $type = (string) ($values['field_type'] ?? '');
    $options = $values['options'] ?? questionnaire_parse_lines((string) ($values['options'] ?? ''));
    if (is_string($options)) $options = questionnaire_parse_lines($options);
    $validation = is_array($values['validation'] ?? null) ? $values['validation'] : $values;
    ?>
    <form method="post" class="admin-form questionnaire-field-editor" data-questionnaire-field-form>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'save_field' : 'add_field' ?>">
        <input type="hidden" name="field_id" value="<?= $fieldId ?>">
        <?php if (!$editing): ?><input type="hidden" name="insertion_index" value="<?= $insertionIndex ?>"><?php endif; ?>
        <label>Field type *
            <select name="field_type" data-field-type required>
                <option value="" <?= $type === '' ? 'selected' : '' ?> disabled>Choose a field type first</option>
                <?php foreach (QUESTIONNAIRE_FIELD_TYPES as $choice): ?>
                    <option value="<?= e($choice) ?>" <?= $type === $choice ? 'selected' : '' ?>><?= e(questionnaire_field_type_label($choice)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div data-editor-controls <?= $type === '' ? 'hidden' : '' ?>>
            <label><span data-label-caption>Question</span> *
                <textarea name="label" required><?= e((string) ($values['label'] ?? '')) ?></textarea>
            </label>
            <label data-control="admin-label">Internal label
                <input name="admin_label" value="<?= e((string) ($values['admin_label'] ?? '')) ?>">
            </label>
            <label data-control="help">Help text
                <textarea name="help_text"><?= e((string) ($values['help_text'] ?? '')) ?></textarea>
            </label>
            <label data-control="placeholder">Placeholder
                <input name="placeholder" value="<?= e((string) ($values['placeholder'] ?? '')) ?>">
            </label>
            <label data-control="options">Options (one per line)
                <textarea name="options"><?= e(implode("\n", (array) $options)) ?></textarea>
            </label>
            <div class="product-form-grid" data-control="text-length">
                <label>Minimum length<input type="number" min="0" name="min_length" value="<?= e((string) ($validation['min_length'] ?? '')) ?>"></label>
                <label>Maximum length<input type="number" min="0" name="max_length" value="<?= e((string) ($validation['max_length'] ?? '')) ?>"></label>
            </div>
            <div class="product-form-grid" data-control="number-range">
                <label>Minimum number<input type="number" step="any" name="min_number" value="<?= e((string) ($validation['min_number'] ?? '')) ?>"></label>
                <label>Maximum number<input type="number" step="any" name="max_number" value="<?= e((string) ($validation['max_number'] ?? '')) ?>"></label>
            </div>
            <label data-control="file-settings">Allowed extensions (comma or line separated)
                <input name="allowed_extensions" value="<?= e(implode(',', $validation['allowed_extensions'] ?? [])) ?>">
            </label>
            <label data-control="file-settings">Maximum size in bytes
                <input type="number" min="1" name="max_file_size" value="<?= e((string) ($validation['max_file_size'] ?? '')) ?>">
            </label>
            <label data-control="file-count">Maximum file count
                <input type="number" min="1" name="max_file_count" value="<?= e((string) ($validation['max_file_count'] ?? '')) ?>">
            </label>
            <div data-control="addon-settings">
                <label>Pricing method *<select name="pricing_method">
                    <option value="flat_fee" <?= ($validation['pricing_method'] ?? '') === 'flat_fee' ? 'selected' : '' ?>>Flat fee</option>
                    <option value="per_additional_item" <?= ($validation['pricing_method'] ?? '') === 'per_additional_item' ? 'selected' : '' ?>>Per additional item</option>
                    <option value="quantity_priced" <?= ($validation['pricing_method'] ?? '') === 'quantity_priced' ? 'selected' : '' ?>>Quantity priced</option>
                </select></label>
                <div class="product-form-grid">
                    <?php $priceValue = array_key_exists('price', $values) ? (string) $values['price'] : (isset($validation['unit_price_cents']) ? questionnaire_cents_to_dollars((int) $validation['unit_price_cents']) : ''); ?>
                    <label>Price *<input type="text" inputmode="decimal" name="price" value="<?= e($priceValue) ?>" placeholder="0.00"></label>
                    <label data-addon-included>Included quantity<input type="number" min="0" step="1" name="included_quantity" value="<?= e((string) ($validation['included_quantity'] ?? '0')) ?>"></label>
                    <label data-addon-quantity>Minimum selectable quantity<input type="number" min="0" step="1" name="min_quantity" value="<?= e((string) ($validation['min_quantity'] ?? '0')) ?>"></label>
                    <label data-addon-quantity>Maximum selectable quantity<input type="number" min="0" step="1" name="max_quantity" value="<?= e((string) ($validation['max_quantity'] ?? '0')) ?>"></label>
                    <label data-addon-quantity>Quantity-step amount<input type="number" min="1" step="1" name="quantity_step" value="<?= e((string) ($validation['quantity_step'] ?? '1')) ?>"></label>
                </div>
                <p class="helper">Prices are saved in cents and are for manual invoicing only.</p>
            </div>
            <div class="questionnaire-toggle-row">
                <label data-control="required"><input type="checkbox" name="is_required" value="1" <?= !empty($values['is_required']) ? 'checked' : '' ?>> Required</label>
                <label><input type="checkbox" name="is_active" value="1" <?= !isset($values['is_active']) || !empty($values['is_active']) ? 'checked' : '' ?>> Active</label>
            </div>
            <details class="questionnaire-advanced">
                <summary>Advanced Settings</summary>
                <label>Stable field key
                    <input name="field_key" pattern="[a-z][a-z0-9_]{1,99}" value="<?= e((string) ($values['field_key'] ?? '')) ?>" placeholder="Generated from question when blank">
                </label>
                <label>Display order
                    <input type="number" name="sort_order" value="<?= $sortOrder ?>">
                </label>
                <p class="helper">The key permanently identifies saved answers. Historical keys and types cannot be changed.</p>
            </details>
            <div class="card-actions">
                <button class="btn btn-accent"><?= $editing ? 'Save question' : 'Add question' ?></button>
                <?php if ($editing): ?><a class="btn btn-ghost" href="questionnaires-edit.php?id=<?= (int) ($_GET['id'] ?? 0) ?>">Cancel</a><?php else: ?><button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button><?php endif; ?>
            </div>
        </div>
    </form>
    <?php
}

$adminTitle = 'Edit Questionnaire';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="questionnaire-builder">
    <header class="questionnaire-builder-header admin-card">
        <form method="post" class="admin-form questionnaire-settings">
            <?= csrf_field() ?><input type="hidden" name="action" value="settings">
            <label>Questionnaire title *<input name="title" required value="<?= e($questionnaire['title']) ?>"></label>
            <label>Status<select name="status"><?php foreach (QUESTIONNAIRE_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $questionnaire['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
            <label class="questionnaire-description">Internal description<textarea name="internal_description"><?= e($questionnaire['internal_description'] ?? '') ?></textarea></label>
            <div class="card-actions questionnaire-toolbar">
                <button class="btn">Save Settings</button>
                <a class="btn btn-ghost" href="questionnaires-preview.php?id=<?= $id ?>" target="_blank">Preview</a>
                <button class="btn btn-accent" type="button" data-add-at="<?= count($fields) ?>">Add Question</button>
                <button class="btn btn-ghost" type="button" data-open-import>Import Questions</button>
            </div>
        </form>
    </header>

    <?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>

    <form method="post" data-reorder-form id="questionnaire-reorder-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="reorder"><input type="hidden" name="field_order" data-field-order>
    </form>
        <div class="questionnaire-field-list" data-field-list>
            <?php if (!$fields): ?><p class="empty">No questions yet. Add one or import reusable questions.</p><?php endif; ?>
            <?php foreach ($fields as $index => $field): ?>
                <article class="questionnaire-field-card <?= empty($field['is_active']) ? 'is-inactive' : '' ?>" draggable="true" data-field-id="<?= (int) $field['id'] ?>">
                    <div class="questionnaire-card-summary">
                        <button type="button" class="drag-handle" aria-label="Drag to reorder" title="Drag to reorder">⋮⋮</button>
                        <div class="questionnaire-card-copy">
                            <strong><?= e($field['label']) ?></strong>
                            <span><?= e(questionnaire_field_type_label($field['field_type'])) ?> · <?= in_array($field['field_type'], QUESTIONNAIRE_STRUCTURAL_TYPES, true) ? 'Optional' : (!empty($field['is_required']) ? 'Required' : 'Optional') ?> · <?= !empty($field['is_active']) ? 'Active' : 'Inactive' ?></span>
                        </div>
                        <div class="questionnaire-card-actions">
                            <a class="btn btn-small" href="questionnaires-edit.php?id=<?= $id ?>&field=<?= (int) $field['id'] ?>#field-<?= (int) $field['id'] ?>">Edit</a>
                            <button class="btn btn-ghost" name="action" value="move_up" form="field-action-<?= (int) $field['id'] ?>" <?= $index === 0 ? 'disabled' : '' ?>>Move Up</button>
                            <button class="btn btn-ghost" name="action" value="move_down" form="field-action-<?= (int) $field['id'] ?>" <?= $index === count($fields)-1 ? 'disabled' : '' ?>>Move Down</button>
                            <button class="btn btn-ghost" name="action" value="duplicate" form="field-action-<?= (int) $field['id'] ?>">Duplicate</button>
                            <?php if (!empty($field['is_active'])): ?><button class="btn btn-ghost" name="action" value="deactivate" form="field-action-<?= (int) $field['id'] ?>">Deactivate</button><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($editing && (int) $editing['id'] === (int) $field['id']): ?>
                        <div id="field-<?= (int) $field['id'] ?>" class="questionnaire-inline-editor"><?php render_field_editor($fieldValues ? array_merge($field, $fieldValues) : $field, true, (int) $field['id'], (int) $field['sort_order']); ?></div>
                    <?php endif; ?>
                </article>
                <form method="post" id="field-action-<?= (int) $field['id'] ?>"><?= csrf_field() ?><input type="hidden" name="field_id" value="<?= (int) $field['id'] ?>"></form>
                <button class="questionnaire-add-between" type="button" data-add-at="<?= $index + 1 ?>">+ Add question here</button>
            <?php endforeach; ?>
        </div>
        <?php if (count($fields) > 1): ?><button class="btn" form="questionnaire-reorder-form" data-save-order hidden>Save new order</button><?php endif; ?>
</div>

<dialog class="questionnaire-dialog" data-add-dialog>
    <div class="dialog-heading"><h2>Add Question</h2><button type="button" data-close-dialog aria-label="Close">×</button></div>
    <?php render_field_editor(!$editing && $fieldValues ? array_merge(['field_type'=>'','is_active'=>1], $fieldValues) : ['field_type'=>'','is_active'=>1], false, 0, (count($fields)+1)*10, count($fields)); ?>
</dialog>
<dialog class="questionnaire-dialog" data-import-dialog>
    <div class="dialog-heading"><h2>Import Questions</h2><button type="button" data-close-dialog aria-label="Close">×</button></div>
    <form method="post" class="admin-form" data-import-form>
        <?= csrf_field() ?><input type="hidden" name="action" value="import_fields">
        <label>Copy from<select name="source_questionnaire_id" data-import-source required><option value="">Select a questionnaire</option><?php foreach ($importTemplates as $template): ?><option value="<?= (int) $template['id'] ?>"><?= e($template['title']) ?></option><?php endforeach; ?></select></label>
        <div class="questionnaire-import-fields" data-import-fields><p class="helper">Choose a questionnaire to view its questions.</p></div>
        <label>Insert position<select name="insert_index"><?php for ($position=0;$position<=count($fields);$position++): ?><option value="<?= $position ?>"><?= $position === count($fields) ? 'At the end' : 'Before: '.e($fields[$position]['label']) ?></option><?php endfor; ?></select></label>
        <button class="btn btn-accent">Import selected questions</button>
    </form>
</dialog>
<script type="application/json" data-import-data><?= json_encode($importFields, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script>
(() => {
 const addDialog=document.querySelector('[data-add-dialog]'), importDialog=document.querySelector('[data-import-dialog]');
 document.querySelectorAll('[data-add-at]').forEach(button=>button.addEventListener('click',()=>{ const index=Number(button.dataset.addAt); addDialog.querySelector('[name=insertion_index]').value=index; addDialog.showModal(); }));
 document.querySelector('[data-open-import]').addEventListener('click',()=>importDialog.showModal());
 document.querySelectorAll('[data-close-dialog]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog')?.close()));
 const configure=(form)=>{ const select=form.querySelector('[data-field-type]'), controls=form.querySelector('[data-editor-controls]'); const update=()=>{ const type=select.value; controls.hidden=!type; const visible={ 'admin-label':['section_heading','information','addon'].includes(type), help:!['section_heading','information'].includes(type), placeholder:['short_text','long_text'].includes(type), options:['dropdown','radio','checkboxes'].includes(type), 'text-length':['short_text','long_text'].includes(type), 'number-range':type==='number', 'file-settings':['file','multiple_files'].includes(type), 'file-count':type==='multiple_files', 'addon-settings':type==='addon', required:!['section_heading','information'].includes(type) }; form.querySelectorAll('[data-control]').forEach(el=>el.hidden=!visible[el.dataset.control]); const caption=form.querySelector('[data-label-caption]'); caption.textContent=type==='section_heading'?'Wording':type==='information'?'Information text':type==='addon'?'Customer-facing upgrade name':'Question'; }; select.addEventListener('change',update); update(); };
 document.querySelectorAll('[data-questionnaire-field-form]').forEach(form=>{ configure(form); const pricing=form.querySelector('[name=pricing_method]'); const updatePricing=()=>{ const method=pricing.value; form.querySelectorAll('[data-addon-quantity]').forEach(el=>el.hidden=method==='flat_fee'); form.querySelector('[data-addon-included]').hidden=method!=='per_additional_item'; }; pricing.addEventListener('change',updatePricing); updatePricing(); });
 const list=document.querySelector('[data-field-list]'), order=document.querySelector('[data-field-order]'), save=document.querySelector('[data-save-order]'); let dragged;
 list?.addEventListener('dragstart',event=>{ dragged=event.target.closest('[data-field-id]'); dragged?.classList.add('is-dragging'); });
 list?.addEventListener('dragover',event=>{ event.preventDefault(); const target=event.target.closest('[data-field-id]'); if(target&&dragged&&target!==dragged){ const box=target.getBoundingClientRect(); list.insertBefore(dragged,event.clientY<box.top+box.height/2?target:target.nextSibling); } });
 list?.addEventListener('dragend',()=>{ dragged?.classList.remove('is-dragging'); order.value=[...list.querySelectorAll('[data-field-id]')].map(card=>card.dataset.fieldId).join(','); if(save) save.hidden=false; });
 const data=JSON.parse(document.querySelector('[data-import-data]').textContent), source=document.querySelector('[data-import-source]'), holder=document.querySelector('[data-import-fields]');
 source.addEventListener('change',()=>{ const fields=data[source.value]||[]; holder.innerHTML=fields.length?'<label><input type="checkbox" data-select-all> Select all</label>'+fields.map(field=>`<label><input type="checkbox" name="import_field_ids[]" value="${Number(field.id)}"> ${escapeHtml(field.label)} <small>(${escapeHtml(field.field_type_label)})</small></label>`).join(''):'<p class="helper">This questionnaire has no fields.</p>'; holder.querySelector('[data-select-all]')?.addEventListener('change',event=>holder.querySelectorAll('[name="import_field_ids[]"]').forEach(box=>box.checked=event.target.checked)); });
 function escapeHtml(value){ const node=document.createElement('span'); node.textContent=String(value); return node.innerHTML; }
})();
</script>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
