<?php
declare(strict_types=1);

const QUESTIONNAIRE_STATUSES = ['draft', 'active', 'archived'];
const QUESTIONNAIRE_FIELD_TYPES = [
    'short_text', 'long_text', 'email', 'phone', 'url', 'number', 'date', 'yes_no',
    'dropdown', 'radio', 'checkboxes', 'file', 'multiple_files', 'information', 'section_heading',
];
const QUESTIONNAIRE_STRUCTURAL_TYPES = ['information', 'section_heading'];
const QUESTIONNAIRE_OPTION_TYPES = ['dropdown', 'radio', 'checkboxes'];
const QUESTIONNAIRE_FILE_TYPES = ['file', 'multiple_files'];

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
