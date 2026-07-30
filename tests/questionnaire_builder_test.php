<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/questionnaires.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
$pdo->exec(
    'CREATE TABLE questionnaire_fields ('
    . 'id INTEGER PRIMARY KEY AUTOINCREMENT, questionnaire_template_id INTEGER NOT NULL, '
    . 'field_key TEXT NOT NULL, field_type TEXT NOT NULL, label TEXT NOT NULL, admin_label TEXT, '
    . 'help_text TEXT, placeholder TEXT, options_json TEXT, validation_json TEXT, is_required INTEGER NOT NULL, '
    . 'is_active INTEGER NOT NULL, sort_order INTEGER NOT NULL, created_at TEXT, updated_at TEXT, '
    . 'UNIQUE(questionnaire_template_id, field_key))'
);

foreach (['A', 'I', '1'] as $label) {
    $key = questionnaire_unique_field_key($pdo, 1, $label);
    assert(questionnaire_field_key_is_valid($key), 'Generated key must be valid for label: ' . $label);
    assert(strlen($key) >= 2 && strlen($key) <= 100);
}

$insert = $pdo->prepare(
    "INSERT INTO questionnaire_fields (questionnaire_template_id, field_key, field_type, label, is_required, is_active, sort_order, created_at) "
    . "VALUES (?, ?, 'short_text', ?, 0, 1, ?, NOW())"
);
$collisionBase = questionnaire_unique_field_key($pdo, 1, 'A');
$insert->execute([1, $collisionBase, 'A', 10]);
$collisionTwo = questionnaire_unique_field_key($pdo, 1, 'A');
$insert->execute([1, $collisionTwo, 'A', 20]);
$collisionThree = questionnaire_unique_field_key($pdo, 1, 'A');
assert($collisionTwo === $collisionBase . '_2');
assert($collisionThree === $collisionBase . '_3');
assert(questionnaire_field_key_is_valid($collisionThree));

$insert->execute([2, 'first', 'First', 10]);
$insert->execute([2, 'last', 'Last', 20]);
for ($addition = 1; $addition <= 2; $addition++) {
    $existing = load_questionnaire_fields($pdo, 2);
    $order = array_map(static fn (array $field): int => (int) $field['id'], $existing);
    $insert->execute([2, 'added_' . $addition, 'Added ' . $addition, 0]);
    $newId = (int) $pdo->lastInsertId();
    array_splice($order, 1, 0, [$newId]);
    assert(questionnaire_save_field_order($pdo, 2, $order));
}
$labels = array_column(load_questionnaire_fields($pdo, 2), 'label');
assert($labels === ['First', 'Added 2', 'Added 1', 'Last'], 'Repeated insertion must use the exact requested index.');

echo "Questionnaire builder focused tests passed\n";
