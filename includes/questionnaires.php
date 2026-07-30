<?php
declare(strict_types=1);

const QUESTIONNAIRE_STATUSES = ['draft', 'active', 'archived'];
const QUESTIONNAIRE_FIELD_TYPES = [
    'short_text', 'long_text', 'email', 'phone', 'url', 'number', 'date', 'yes_no',
    'dropdown', 'radio', 'checkboxes', 'file', 'multiple_files', 'addon', 'information', 'section_heading',
];
const QUESTIONNAIRE_STRUCTURAL_TYPES = ['information', 'section_heading'];
const QUESTIONNAIRE_OPTION_TYPES = ['dropdown', 'radio', 'checkboxes'];
const QUESTIONNAIRE_FILE_TYPES = ['file', 'multiple_files'];
const QUESTIONNAIRE_ADDON_PRICING_METHODS = ['flat_fee', 'per_additional_item', 'quantity_priced'];

function questionnaire_field_type_label(string $type): string
{
    return match ($type) {
        'file' => 'File Upload',
        'multiple_files' => 'Multiple File Uploads',
        'addon' => 'Add-On / Upgrade',
        default => ucwords(str_replace('_', ' ', $type)),
    };
}

/** Converts a non-negative decimal dollar string to cents without floating point arithmetic. */
function questionnaire_dollars_to_cents(string $dollars): ?int
{
    $dollars = trim($dollars);
    if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $dollars, $matches) !== 1) {
        return null;
    }
    $whole = ltrim($matches[1], '0');
    $whole = $whole === '' ? '0' : $whole;
    $fraction = str_pad($matches[2] ?? '', 2, '0');
    $cents = ltrim($whole . $fraction, '0');
    $cents = $cents === '' ? '0' : $cents;
    $maximum = (string) PHP_INT_MAX;
    if (strlen($cents) > strlen($maximum) || (strlen($cents) === strlen($maximum) && strcmp($cents, $maximum) > 0)) {
        return null;
    }
    return (int) $cents;
}

function questionnaire_cents_to_dollars(int $cents): string
{
    return intdiv(max(0, $cents), 100) . '.' . str_pad((string) (max(0, $cents) % 100), 2, '0', STR_PAD_LEFT);
}

function questionnaire_format_cents(int $cents): string
{
    return '$' . questionnaire_cents_to_dollars($cents);
}

function questionnaire_addon_config(array $field): array
{
    $config = is_array($field['validation'] ?? null) ? $field['validation'] : [];
    return [
        'pricing_method' => in_array($config['pricing_method'] ?? '', QUESTIONNAIRE_ADDON_PRICING_METHODS, true) ? $config['pricing_method'] : '',
        'unit_price_cents' => filter_var($config['unit_price_cents'] ?? null, FILTER_VALIDATE_INT) !== false ? (int) $config['unit_price_cents'] : -1,
        'included_quantity' => filter_var($config['included_quantity'] ?? 0, FILTER_VALIDATE_INT) !== false ? (int) ($config['included_quantity'] ?? 0) : -1,
        'min_quantity' => filter_var($config['min_quantity'] ?? 0, FILTER_VALIDATE_INT) !== false ? (int) ($config['min_quantity'] ?? 0) : -1,
        'max_quantity' => filter_var($config['max_quantity'] ?? 0, FILTER_VALIDATE_INT) !== false ? (int) ($config['max_quantity'] ?? 0) : -1,
        'quantity_step' => filter_var($config['quantity_step'] ?? 1, FILTER_VALIDATE_INT) !== false ? (int) ($config['quantity_step'] ?? 1) : -1,
    ];
}

/** Returns an immutable, integer-cent answer snapshot or validation errors. */
function questionnaire_calculate_addon(array $field, mixed $submitted): array
{
    $config = questionnaire_addon_config($field);
    $errors = [];
    if ($config['pricing_method'] === '' || $config['unit_price_cents'] < 0) $errors[] = 'has invalid pricing configuration.';
    foreach (['included_quantity', 'min_quantity', 'max_quantity'] as $key) if ($config[$key] < 0) $errors[] = 'has invalid quantity configuration.';
    if ($config['quantity_step'] < 1) $errors[] = 'has an invalid quantity step.';
    if ($config['max_quantity'] < $config['min_quantity']) $errors[] = 'has an invalid quantity range.';

    $raw = is_scalar($submitted) ? trim((string) $submitted) : '';
    if ($config['pricing_method'] === 'flat_fee') {
        if (!in_array($raw, ['', '0', '1'], true)) $errors[] = 'has an invalid selection.';
        $selected = $raw === '1' ? 1 : 0;
    } else {
        if ($raw === '' || preg_match('/^\d+$/', $raw) !== 1) {
            $selected = 0;
            $errors[] = 'must be a non-negative whole number.';
        } else {
            $selected = (int) $raw;
        }
        if ($selected < $config['min_quantity'] || $selected > $config['max_quantity']) $errors[] = 'is outside the allowed range.';
        if ($config['quantity_step'] > 0 && (($selected - $config['min_quantity']) % $config['quantity_step']) !== 0) $errors[] = 'does not match the required quantity step.';
    }
    $billable = $config['pricing_method'] === 'per_additional_item' ? max(0, $selected - $config['included_quantity']) : $selected;
    if ($billable > 0 && $config['unit_price_cents'] > intdiv(PHP_INT_MAX, $billable)) {
        $errors[] = 'total is too large.';
        $total = 0;
    } else {
        $total = $billable * max(0, $config['unit_price_cents']);
    }
    return [array_values(array_unique($errors)), [
        'upgrade_name' => (string) ($field['label'] ?? ''), 'pricing_method' => $config['pricing_method'],
        'unit_price_cents' => $config['unit_price_cents'], 'included_quantity' => $config['included_quantity'],
        'selected_quantity' => $selected, 'billable_quantity' => $billable, 'total_cents' => $total,
    ]];
}

function questionnaire_addon_total(array $answers): int
{
    return array_sum(array_map(static fn ($answer): int => is_array($answer) && isset($answer['total_cents']) ? max(0, (int) $answer['total_cents']) : 0, $answers));
}

function questionnaire_tables_ready(PDO $pdo): bool
{
    return table_exists($pdo, 'questionnaire_templates')
        && table_exists($pdo, 'questionnaire_fields')
        && table_exists($pdo, 'order_questionnaire_snapshots')
        && table_exists($pdo, 'order_questionnaire_answers');
}

function questionnaire_field_key_is_valid(string $key): bool
{
    return preg_match('/^[a-z][a-z0-9_]{1,99}$/', $key) === 1;
}

function questionnaire_unique_field_key(PDO $pdo, int $templateId, string $requestedKey): string
{
    $base = strtolower(trim(preg_replace('/[^a-z0-9_]+/', '_', $requestedKey) ?? '', '_'));
    if ($base === '' || !preg_match('/^[a-z]/', $base)) {
        $base = 'question_' . $base;
    }
    if (strlen($base) < 2) {
        $base .= '_field';
    }
    $base = substr($base, 0, 90);
    $candidate = $base;
    $suffix = 2;
    $stmt = $pdo->prepare('SELECT 1 FROM questionnaire_fields WHERE questionnaire_template_id = ? AND field_key = ?');
    while (true) {
        $stmt->execute([$templateId, $candidate]);
        if (!$stmt->fetchColumn()) {
            if (!questionnaire_field_key_is_valid($candidate)) {
                throw new RuntimeException('Unable to generate a valid field key.');
            }
            return $candidate;
        }
        $candidate = substr($base, 0, 90) . '_' . $suffix++;
    }
}

/** Rewrites the complete owned field order; unknown, duplicate, or omitted IDs are rejected. */
function questionnaire_save_field_order(PDO $pdo, int $templateId, array $submittedIds): bool
{
    $stmt = $pdo->prepare('SELECT id FROM questionnaire_fields WHERE questionnaire_template_id = ? ORDER BY sort_order, id');
    $stmt->execute([$templateId]);
    $owned = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $submitted = array_map('intval', $submittedIds);
    if (count($owned) !== count($submitted) || count($submitted) !== count(array_unique($submitted))) {
        return false;
    }
    $expected = $owned;
    sort($expected);
    $actual = $submitted;
    sort($actual);
    if ($expected !== $actual) {
        return false;
    }
    $update = $pdo->prepare('UPDATE questionnaire_fields SET sort_order = ?, updated_at = NOW() WHERE id = ? AND questionnaire_template_id = ?');
    foreach ($submitted as $index => $fieldId) {
        $update->execute([($index + 1) * 10, $fieldId, $templateId]);
    }
    return true;
}

/** Copies fields without changing their source and resolves destination key collisions. */
function questionnaire_copy_fields(PDO $pdo, int $sourceId, int $destinationId, array $fieldIds, int $insertIndex): int
{
    $sourceStmt = $pdo->prepare('SELECT * FROM questionnaire_fields WHERE questionnaire_template_id = ? ORDER BY sort_order, id');
    $sourceStmt->execute([$sourceId]);
    $selected = array_flip(array_map('intval', $fieldIds));
    $sourceFields = array_values(array_filter($sourceStmt->fetchAll(), static fn (array $field): bool => isset($selected[(int) $field['id']])));
    if (!$sourceFields) {
        return 0;
    }
    $destination = load_questionnaire_fields($pdo, $destinationId);
    $insertIndex = max(0, min($insertIndex, count($destination)));
    $insert = $pdo->prepare('INSERT INTO questionnaire_fields (questionnaire_template_id, field_key, field_type, label, admin_label, help_text, placeholder, options_json, validation_json, is_required, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $newIds = [];
    foreach ($sourceFields as $field) {
        $key = questionnaire_unique_field_key($pdo, $destinationId, (string) $field['field_key']);
        $insert->execute([$destinationId, $key, $field['field_type'], $field['label'], $field['admin_label'], $field['help_text'], $field['placeholder'], $field['options_json'], $field['validation_json'], $field['is_required'], $field['is_active'], 0]);
        $newIds[] = (int) $pdo->lastInsertId();
    }
    $order = array_map(static fn (array $field): int => (int) $field['id'], $destination);
    array_splice($order, $insertIndex, 0, $newIds);
    if (!questionnaire_save_field_order($pdo, $destinationId, $order)) {
        throw new RuntimeException('The imported field order could not be saved.');
    }
    return count($newIds);
}

function questionnaire_snapshots_contain_field_key(array $snapshotJsonRows, string $fieldKey): bool
{
    foreach ($snapshotJsonRows as $snapshotJson) {
        if (!is_string($snapshotJson) || $snapshotJson === '') {
            continue;
        }

        try {
            $snapshot = json_decode($snapshotJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            continue;
        }

        if (!is_array($snapshot) || !is_array($snapshot['fields'] ?? null)) {
            continue;
        }

        foreach ($snapshot['fields'] as $field) {
            if (is_array($field) && ($field['field_key'] ?? null) === $fieldKey) {
                return true;
            }
        }
    }

    return false;
}

function questionnaire_field_history_found(int $answerCount, array $snapshotJsonRows, string $fieldKey): bool
{
    return $answerCount > 0 || questionnaire_snapshots_contain_field_key($snapshotJsonRows, $fieldKey);
}

function decode_questionnaire_field(array $field): array
{
    foreach (['options_json' => 'options', 'validation_json' => 'validation'] as $column => $key) {
        $decoded = !empty($field[$column]) ? json_decode((string) $field[$column], true) : [];
        $field[$key] = is_array($decoded) ? $decoded : [];
    }

    return $field;
}

function load_questionnaire_fields(PDO $pdo, int $templateId, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM questionnaire_fields WHERE questionnaire_template_id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order, id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$templateId]);

    return array_map('decode_questionnaire_field', $stmt->fetchAll());
}

function load_product_questionnaire(PDO $pdo, array $product, bool $activeOnly = true): ?array
{
    if (!questionnaire_tables_ready($pdo) || empty($product['questionnaire_template_id'])) {
        return null;
    }

    $sql = 'SELECT * FROM questionnaire_templates WHERE id = ?';
    if ($activeOnly) {
        $sql .= " AND status = 'active'";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int) $product['questionnaire_template_id']]);
    $template = $stmt->fetch();

    if (!$template) {
        return null;
    }

    $template['fields'] = load_questionnaire_fields($pdo, (int) $template['id'], $activeOnly);

    return $template;
}

function questionnaire_requires_assignment(string $intakeType): bool
{
    return in_array($intakeType, ['shopify_revamp_standard', 'shopify_custom_kit'], true);
}

function questionnaire_definition_errors(array $fields): array
{
    $errors = [];
    $activeCount = 0;
    $answerFieldCount = 0;
    $seenKeys = [];

    foreach ($fields as $field) {
        if (empty($field['is_active'])) {
            continue;
        }

        $activeCount++;
        $key = (string) ($field['field_key'] ?? '');
        $type = (string) ($field['field_type'] ?? '');

        if (!questionnaire_field_key_is_valid($key)) {
            $errors[] = 'Active field keys must use stable lowercase letters, numbers, and underscores.';
        }
        if (isset($seenKeys[$key])) {
            $errors[] = 'Active questionnaire fields must have unique field keys.';
        }
        $seenKeys[$key] = true;

        if (!in_array($type, QUESTIONNAIRE_FIELD_TYPES, true)) {
            $errors[] = 'Every active field must use a supported field type.';
            continue;
        }

        if (!in_array($type, QUESTIONNAIRE_STRUCTURAL_TYPES, true)) {
            $answerFieldCount++;
        } elseif (!empty($field['is_required'])) {
            $errors[] = 'Structural fields must be optional.';
        }

        if (in_array($type, QUESTIONNAIRE_OPTION_TYPES, true)) {
            $submittedOptions = [];
            foreach ($field['options'] ?? [] as $option) {
                if (!is_string($option)) {
                    $errors[] = 'Active option fields must contain only text options.';
                    continue;
                }
                $submittedOptions[] = trim($option);
            }
            $options = array_values(array_unique(array_filter(
                $submittedOptions,
                static fn (string $option): bool => $option !== ''
            )));
            if (!$options) {
                $errors[] = 'Every active option field must have at least one non-empty option.';
            }
            if (count($options) !== count($submittedOptions)) {
                $errors[] = 'Active option fields cannot contain empty or duplicate options.';
            }
        }
        if ($type === 'addon') {
            $addonConfig = questionnaire_addon_config($field);
            [$addonErrors] = questionnaire_calculate_addon($field, (string) ($addonConfig['pricing_method'] === 'flat_fee' ? 0 : max(0, $addonConfig['min_quantity'])));
            foreach ($addonErrors as $error) $errors[] = ($field['label'] ?? 'Add-on') . ' ' . $error;
        }
    }

    if ($activeCount === 0) {
        $errors[] = 'An active questionnaire must have at least one active field.';
    }
    if ($answerFieldCount === 0) {
        $errors[] = 'An active questionnaire must have at least one active answer field.';
    }

    return array_values(array_unique($errors));
}

function validate_product_questionnaire_assignment(
    PDO $pdo,
    string $intakeType,
    string $productStatus,
    string $questionnaireTemplateId,
    ?int $existingQuestionnaireTemplateId = null
): array {
    if ($questionnaireTemplateId === '') {
        if ($productStatus === 'active' && questionnaire_requires_assignment($intakeType)) {
            return ['This active intake type requires an active questionnaire template.'];
        }
        return [];
    }

    if (!questionnaire_tables_ready($pdo)) {
        return ['Run the Phase 2.3 migration before assigning a questionnaire.'];
    }

    $stmt = $pdo->prepare('SELECT id, status FROM questionnaire_templates WHERE id = ?');
    $stmt->execute([(int) $questionnaireTemplateId]);
    $template = $stmt->fetch();

    if (!$template) {
        return ['Choose an existing questionnaire template.'];
    }
    if ($template['status'] === 'archived' && (int) $template['id'] !== $existingQuestionnaireTemplateId) {
        return ['Archived questionnaires cannot be newly assigned.'];
    }
    if ($productStatus !== 'active' || !questionnaire_requires_assignment($intakeType)) {
        return [];
    }
    if ($template['status'] !== 'active') {
        return ['This active intake type requires an existing active questionnaire template.'];
    }

    $fields = load_questionnaire_fields($pdo, (int) $template['id'], true);
    $definitionErrors = questionnaire_definition_errors($fields);
    if ($definitionErrors) {
        return ['The assigned questionnaire is incomplete or invalid: ' . $definitionErrors[0]];
    }

    return [];
}

function questionnaire_file_list(array $files, string $key): array
{
    if (!isset($files[$key])) {
        return [];
    }

    $group = $files[$key];
    if (!is_array($group['name'] ?? null)) {
        return [$group];
    }

    $result = [];
    foreach ($group['name'] as $index => $name) {
        $result[] = [
            'name' => $name,
            'type' => $group['type'][$index] ?? '',
            'tmp_name' => $group['tmp_name'][$index] ?? '',
            'error' => $group['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $group['size'][$index] ?? 0,
        ];
    }

    return $result;
}

function validate_questionnaire_submission(array $template, array $posted, array $files): array
{
    $errors = [];
    $answers = [];
    $uploads = [];
    $known = [];
    $postedAnswers = is_array($posted['q'] ?? null) ? $posted['q'] : [];

    if (isset($posted['q']) && !is_array($posted['q'])) {
        $errors[] = 'The questionnaire answers are malformed.';
    }

    foreach ($template['fields'] as $field) {
        $key = (string) $field['field_key'];
        $type = (string) $field['field_type'];
        $known[$key] = true;

        if (in_array($type, QUESTIONNAIRE_STRUCTURAL_TYPES, true)) {
            continue;
        }

        if (in_array($type, QUESTIONNAIRE_FILE_TYPES, true)) {
            $list = array_values(array_filter(
                questionnaire_file_list($files, 'q_' . $key),
                static fn (array $file): bool => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ));
            $validation = $field['validation'];
            $maximum = $type === 'file' ? 1 : (int) ($validation['max_file_count'] ?? 10);

            if (!empty($field['is_required']) && !$list) {
                $errors[] = $field['label'] . ' is required.';
            }
            if (count($list) > $maximum) {
                $errors[] = $field['label'] . ' allows a maximum of ' . $maximum . ' files.';
            }

            foreach ($list as $file) {
                $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                $allowed = $validation['allowed_extensions'] ?? ['png', 'jpg', 'jpeg', 'webp', 'pdf'];
                if (!in_array($extension, $allowed, true)) {
                    $errors[] = $field['label'] . ' contains a disallowed file type.';
                }
                if ((int) $file['size'] > (int) ($validation['max_file_size'] ?? MAX_ORDER_UPLOAD_BYTES)) {
                    $errors[] = $field['label'] . ' contains a file that is too large.';
                }
            }

            $answers[$key] = [];
            $uploads[$key] = $list;
            continue;
        }

        $raw = $postedAnswers[$key] ?? null;
        if ($type === 'addon') {
            [$addonErrors, $addon] = questionnaire_calculate_addon($field, $raw);
            if (!empty($field['is_required']) && ($addon['selected_quantity'] ?? 0) === 0) $addonErrors[] = 'is required.';
            foreach ($addonErrors as $error) $errors[] = $field['label'] . ' ' . $error;
            $answers[$key] = $addon;
            continue;
        }
        if (is_array($raw) && $type !== 'checkboxes') {
            $errors[] = $field['label'] . ' has an invalid submission.';
            continue;
        }

        $value = $type === 'checkboxes'
            ? array_values(array_unique(array_filter(array_map('trim', is_array($raw) ? $raw : []), 'strlen')))
            : trim((string) $raw);

        if (!empty($field['is_required']) && ($value === '' || $value === [])) {
            $errors[] = $field['label'] . ' is required.';
        }

        $options = $field['options'];
        $validation = $field['validation'];

        if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $field['label'] . ' must be a valid email address.';
        }
        if ($type === 'url' && $value !== '' && !valid_url_or_blank($value)) {
            $errors[] = $field['label'] . ' must be an HTTP or HTTPS URL.';
        }
        if ($type === 'number' && $value !== '' && !is_numeric($value)) {
            $errors[] = $field['label'] . ' must be a number.';
        }
        if ($type === 'date' && $value !== '') {
            $date = DateTime::createFromFormat('!Y-m-d', (string) $value);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) || !$date || $date->format('Y-m-d') !== $value) {
                $errors[] = $field['label'] . ' must be a valid date.';
            }
        }
        if ($type === 'yes_no' && $value !== '' && !in_array($value, ['yes', 'no'], true)) {
            $errors[] = $field['label'] . ' must be Yes or No.';
        }
        if (in_array($type, ['dropdown', 'radio'], true) && $value !== '' && !in_array($value, $options, true)) {
            $errors[] = $field['label'] . ' has an invalid choice.';
        }
        if ($type === 'checkboxes' && array_diff($value, $options)) {
            $errors[] = $field['label'] . ' has an invalid choice.';
        }
        if (is_string($value) && isset($validation['min_length']) && mb_strlen($value) < (int) $validation['min_length']) {
            $errors[] = $field['label'] . ' is too short.';
        }
        if (is_string($value) && isset($validation['max_length']) && mb_strlen($value) > (int) $validation['max_length']) {
            $errors[] = $field['label'] . ' is too long.';
        }
        if ($type === 'number' && $value !== '' && isset($validation['min_number']) && (float) $value < (float) $validation['min_number']) {
            $errors[] = $field['label'] . ' is below the minimum.';
        }
        if ($type === 'number' && $value !== '' && isset($validation['max_number']) && (float) $value > (float) $validation['max_number']) {
            $errors[] = $field['label'] . ' is above the maximum.';
        }
        if (!empty($validation['pattern']) && $value !== '' && !preg_match('/' . $validation['pattern'] . '/u', (string) $value)) {
            $errors[] = $field['label'] . ' has an invalid format.';
        }
        if (!empty($validation['max_dash_phrases'])) {
            $parts = array_filter(array_map('trim', explode('-', (string) $value)));
            if (count($parts) > (int) $validation['max_dash_phrases']) {
                $errors[] = $field['label'] . ' can have a maximum of ' . $validation['max_dash_phrases'] . ' phrases separated by a dash.';
            }
        }

        $answers[$key] = $value;
    }

    foreach (array_keys($postedAnswers) as $key) {
        if (!isset($known[$key])) {
            $errors[] = 'The questionnaire contains an unknown field.';
        }
    }
    foreach (array_keys($files) as $input) {
        if (str_starts_with((string) $input, 'q_') && !isset($known[substr((string) $input, 2)])) {
            $errors[] = 'The questionnaire contains an unknown upload field.';
        }
    }

    return [array_values(array_unique($errors)), $answers, $uploads];
}

function questionnaire_snapshot(array $template): array
{
    return [
        'title' => $template['title'],
        'fields' => array_map(
            static fn (array $field): array => [
                'field_key' => $field['field_key'],
                'field_type' => $field['field_type'],
                'label' => $field['label'],
                'help_text' => $field['help_text'],
                'is_required' => (bool) $field['is_required'],
                'options' => $field['options'],
                'validation' => $field['validation'],
                'sort_order' => (int) $field['sort_order'],
            ],
            $template['fields']
        ),
    ];
}
