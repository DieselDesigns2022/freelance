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

assert(questionnaire_field_type_label('file') === 'File Upload');
assert(questionnaire_field_type_label('multiple_files') === 'Multiple File Uploads');
assert(questionnaire_field_type_label('addon') === 'Add-On / Upgrade');
assert(in_array('file', QUESTIONNAIRE_FIELD_TYPES, true) && in_array('multiple_files', QUESTIONNAIRE_FIELD_TYPES, true));
foreach ([['2',200,'2.00'], ['2.00',200,'2.00'], ['12.50',1250,'12.50'], ['50.00',5000,'50.00'], ['0.99',99,'0.99'], ['0',0,'0.00']] as $expected) {
    [$dollars,$cents,$display] = $expected;
    assert(questionnaire_dollars_to_cents($dollars) === $cents, $dollars . ' must convert deterministically to integer cents.');
    assert(questionnaire_cents_to_dollars($cents) === $display);
}
foreach (['', '-1', '-0.01', '1.234', '.99', '1.', '1,00', 'abc'] as $invalidPrice) {
    assert(questionnaire_dollars_to_cents($invalidPrice) === null, $invalidPrice . ' must be rejected as malformed dollars.');
}

$addonField = static function (string $method, int $price, int $included=0, int $min=0, int $max=10, int $step=1): array {
    return ['field_key'=>'upgrade', 'field_type'=>'addon', 'label'=>'Rush Service', 'help_text'=>'', 'is_required'=>0,
        'options'=>[], 'sort_order'=>10, 'validation'=>compact('included') + [
            'pricing_method'=>$method, 'unit_price_cents'=>$price, 'included_quantity'=>$included,
            'min_quantity'=>$min, 'max_quantity'=>$max, 'quantity_step'=>$step,
        ]];
};
[$errors,$flat] = questionnaire_calculate_addon($addonField('flat_fee', 5000), '1');
assert(!$errors && $flat['total_cents'] === 5000 && $flat['billable_quantity'] === 1);
[$errors,$additional] = questionnaire_calculate_addon($addonField('per_additional_item', 200, 20), '3');
assert(!$errors && $additional['included_quantity'] === 20 && $additional['billable_quantity'] === 3 && $additional['total_cents'] === 600);
[$errors,$quantity] = questionnaire_calculate_addon($addonField('quantity_priced', 500), '4');
assert(!$errors && $quantity['total_cents'] === 2000);
[, $zero] = questionnaire_calculate_addon($addonField('quantity_priced', 500), '0');
assert($zero['total_cents'] === 0);
[$minErrors] = questionnaire_calculate_addon($addonField('quantity_priced', 100, 0, 2, 10), '1');
[$maxErrors] = questionnaire_calculate_addon($addonField('quantity_priced', 100, 0, 0, 3), '4');
[$stepErrors] = questionnaire_calculate_addon($addonField('quantity_priced', 100, 0, 1, 9, 2), '2');
assert($minErrors && $maxErrors && $stepErrors);
assert(questionnaire_addon_total([$flat,$additional,$quantity]) === 7600);

$template=['title'=>'Test','fields'=>[$addonField('flat_fee',5000)]];
[$forgedErrors,$serverAnswers] = validate_questionnaire_submission($template, ['q'=>['upgrade'=>'1'], 'upgrade_total'=>'1'], []);
assert(!$forgedErrors && $serverAnswers['upgrade']['total_cents'] === 5000, 'An unused forged browser total must be ignored while the server recalculates cents.');
$snapshot=questionnaire_snapshot($template);
$template['fields'][0]['validation']['unit_price_cents']=9999;
assert($snapshot['fields'][0]['validation']['unit_price_cents']===5000, 'Snapshot pricing must be immutable after questionnaire changes.');
assert(!questionnaire_snapshots_contain_field_key(['{}', '{malformed'], 'old_key'), 'Historical malformed snapshots remain safely readable.');

$pdo->exec("INSERT INTO questionnaire_fields(questionnaire_template_id,field_key,field_type,label,validation_json,is_required,is_active,sort_order) VALUES(3,'addon_source','addon','Upgrade','{\"pricing_method\":\"flat_fee\",\"unit_price_cents\":5000,\"included_quantity\":0,\"min_quantity\":0,\"max_quantity\":1,\"quantity_step\":1}',0,1,10)");
$sourceId=(int)$pdo->lastInsertId();
assert(questionnaire_copy_fields($pdo,3,4,[$sourceId],0)===1);
$copied=load_questionnaire_fields($pdo,4)[0];
assert($copied['validation']['unit_price_cents']===5000 && $copied['field_type']==='addon', 'Import/copy must retain all add-on configuration.');

echo "Questionnaire builder focused tests passed\n";
