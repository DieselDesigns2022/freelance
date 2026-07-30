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
$pdo->exec('CREATE TABLE questionnaire_field_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, questionnaire_template_id INTEGER NOT NULL, source_field_id INTEGER NOT NULL, operator TEXT NOT NULL, comparison_value_json TEXT, stable_key TEXT NOT NULL, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE questionnaire_rule_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, rule_id INTEGER NOT NULL, action_type TEXT NOT NULL, target_field_id INTEGER, fee_name TEXT, fee_cents INTEGER, sort_order INTEGER, created_at TEXT, updated_at TEXT)');

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
[$errors,$additional] = questionnaire_calculate_addon($addonField('per_additional_item', 200, 20, 0, 50), '23');
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
$pdo->exec("INSERT INTO questionnaire_field_rules(questionnaire_template_id,source_field_id,operator,comparison_value_json,stable_key) VALUES(3,$sourceId,'is_selected','null','copy_fee')");
$copyRuleId=(int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO questionnaire_rule_actions(rule_id,action_type,fee_name,fee_cents,sort_order) VALUES($copyRuleId,'fee','Copied fee',250,10)");
assert(questionnaire_copy_fields($pdo,3,4,[$sourceId],0)===1);
$copied=load_questionnaire_fields($pdo,4)[0];
assert($copied['validation']['unit_price_cents']===5000 && $copied['field_type']==='addon', 'Import/copy must retain all add-on configuration.');
$copiedRules=load_questionnaire_rules($pdo,4);assert(count($copiedRules)===1 && (int)$copiedRules[0]['actions'][0]['fee_cents']===250, 'Import must remap complete owned rules and preserve fee cents.');


$pdo->exec("INSERT INTO questionnaire_fields(questionnaire_template_id,field_key,field_type,label,is_required,is_active,sort_order) VALUES(3,'copy_target','file','Copy target',0,1,20)");
$copyTargetId=(int)$pdo->lastInsertId();
$multiRuleId=questionnaire_save_rule($pdo,3,$sourceId,'is_selected',null,[
 ['action_type'=>'fee','target_field_id'=>null,'fee_name'=>'Logo creation','fee_cents'=>4000],
 ['action_type'=>'hide','target_field_id'=>$copyTargetId,'fee_name'=>null,'fee_cents'=>null],
 ['action_type'=>'optional','target_field_id'=>$copyTargetId,'fee_name'=>null,'fee_cents'=>null],
]);
$multiRules=load_questionnaire_rules($pdo,3);
$loadedMultiRule=array_values(array_filter($multiRules,static fn(array $rule):bool=>(int)$rule['id']===$multiRuleId))[0];
assert(count($loadedMultiRule['actions'])===3, 'One saved condition must load every transactionally saved action.');
assert(array_column($loadedMultiRule['actions'],'action_type')===['fee','hide','optional']);



assert(questionnaire_format_cents(200) === '$2.00');
assert(questionnaire_format_cents(1250) === '$12.50');

$perAdditionalField = [
    'label' => 'Additional Collection Covers',
    'validation' => [
        'pricing_method' => 'per_additional_item',
        'unit_price_cents' => 200,
        'included_quantity' => 20,
        'min_quantity' => 0,
        'max_quantity' => 50,
        'quantity_step' => 1,
    ],
];

[$addonErrors, $addonAnswer] = questionnaire_calculate_addon($perAdditionalField, '27');
assert($addonErrors === []);
assert($addonAnswer['selected_quantity'] === 27);
assert($addonAnswer['billable_quantity'] === 7);
assert($addonAnswer['total_cents'] === 1400);

[$addonErrors, $addonAnswer] = questionnaire_calculate_addon($perAdditionalField, '20');
assert($addonErrors === []);
assert($addonAnswer['billable_quantity'] === 0);
assert($addonAnswer['total_cents'] === 0);

[$addonErrors, $addonAnswer] = questionnaire_calculate_addon($perAdditionalField, '15');
assert($addonErrors === []);
assert($addonAnswer['billable_quantity'] === 0);
assert($addonAnswer['total_cents'] === 0);


assert(questionnaire_field_type_label('multiple_inputs') === 'Multiple Input Options');
assert(in_array('multiple_inputs', QUESTIONNAIRE_FIELD_TYPES, true));

$multipleInputTemplate = [
    'fields' => [[
        'field_key' => 'website_section_text',
        'field_type' => 'multiple_inputs',
        'label' => 'Website section wording',
        'help_text' => '',
        'is_required' => 0,
        'options' => [],
        'validation' => ['input_count' => 4],
        'sort_order' => 10,
    ]],
];

[$multiErrors, $multiAnswers] = validate_questionnaire_submission(
    $multipleInputTemplate,
    ['q' => ['website_section_text' => [
        0 => 'First sentence',
        1 => '',
        2 => 'Third sentence',
        3 => '',
    ]]],
    []
);

assert($multiErrors === []);
assert($multiAnswers['website_section_text'] === [
    'Input 1: First sentence',
    'Input 3: Third sentence',
]);

[$blankMultiErrors, $blankMultiAnswers] = validate_questionnaire_submission(
    $multipleInputTemplate,
    ['q' => ['website_section_text' => ['', '', '', '']]],
    []
);

assert($blankMultiErrors === []);
assert($blankMultiAnswers['website_section_text'] === []);

$multipleInputTemplate['fields'][0]['is_required'] = 1;

[$requiredMultiErrors] = validate_questionnaire_submission(
    $multipleInputTemplate,
    ['q' => ['website_section_text' => ['', '', '', '']]],
    []
);

assert($requiredMultiErrors !== []);

[$unknownMultiErrors] = validate_questionnaire_submission(
    $multipleInputTemplate,
    ['q' => ['website_section_text' => [99 => 'Forged field']]],
    []
);

assert($unknownMultiErrors !== []);


$conditionalFields = [
 ['id'=>101,'field_key'=>'wants_banner','field_type'=>'yes_no','label'=>'Banner?','is_required'=>1,'is_active'=>1,'options'=>[],'validation'=>[],'sort_order'=>10],
 ['id'=>102,'field_key'=>'banner_text','field_type'=>'multiple_inputs','label'=>'Banner text','is_required'=>1,'is_active'=>1,'options'=>[],'validation'=>['input_count'=>4],'sort_order'=>20,'base_visible'=>false],
 ['id'=>103,'field_key'=>'logo','field_type'=>'file','label'=>'Current logo','is_required'=>0,'is_active'=>1,'options'=>[],'validation'=>['max_file_count'=>1,'allowed_extensions'=>['png'],'max_file_size'=>10000],'sort_order'=>30,'base_visible'=>false],
 ['id'=>104,'field_key'=>'choices','field_type'=>'checkboxes','label'=>'Choices','is_required'=>0,'is_active'=>1,'options'=>['A','B'],'validation'=>[],'sort_order'=>40],
 ['id'=>105,'field_key'=>'quantity','field_type'=>'number','label'=>'Quantity','is_required'=>0,'is_active'=>1,'options'=>[],'validation'=>[],'sort_order'=>50],
 ['id'=>106,'field_key'=>'launch','field_type'=>'date','label'=>'Launch','is_required'=>0,'is_active'=>1,'options'=>[],'validation'=>[],'sort_order'=>60],
 ['id'=>107,'field_key'=>'logo_heading','field_type'=>'section_heading','label'=>'Logo files','is_required'=>0,'is_active'=>1,'options'=>[],'validation'=>[],'sort_order'=>70],
 ['id'=>108,'field_key'=>'logo_information','field_type'=>'information','label'=>'Upload current assets','is_required'=>0,'is_active'=>1,'options'=>[],'validation'=>[],'sort_order'=>80],
];
$conditionalRules = [
 ['id'=>1,'stable_key'=>'banner_yes','source_field_key'=>'wants_banner','operator'=>'equals','comparison_value'=>'yes','actions'=>[
  ['id'=>1,'action_type'=>'show','target_field_key'=>'banner_text','sort_order'=>10],['id'=>2,'action_type'=>'required','target_field_key'=>'banner_text','sort_order'=>20],['id'=>3,'action_type'=>'fee','fee_name'=>'Custom logo','fee_cents'=>4000,'sort_order'=>30],
 ]],
 ['id'=>2,'stable_key'=>'banner_no','source_field_key'=>'wants_banner','operator'=>'equals','comparison_value'=>'no','actions'=>[
  ['id'=>4,'action_type'=>'show','target_field_key'=>'logo','sort_order'=>10],['id'=>5,'action_type'=>'required','target_field_key'=>'logo','sort_order'=>20],['id'=>9,'action_type'=>'show','target_field_key'=>'logo_heading','sort_order'=>30],['id'=>10,'action_type'=>'show','target_field_key'=>'logo_information','sort_order'=>40],
 ]],
];
$conditionalTemplate=['title'=>'Conditional','fields'=>$conditionalFields,'rules'=>$conditionalRules];
$yesState=questionnaire_evaluate_rules($conditionalTemplate,['wants_banner'=>'yes']);
assert($yesState['visibility']['banner_text'] && $yesState['required']['banner_text']);
assert(!$yesState['visibility']['logo'] && $yesState['conditional_fee_total_cents']===4000 && count($yesState['fees'])===1);
$noState=questionnaire_evaluate_rules($conditionalTemplate,['wants_banner'=>'no']);
assert(!$noState['visibility']['banner_text'] && !$noState['required']['banner_text']);
assert($noState['visibility']['logo'] && $noState['required']['logo'] && $noState['conditional_fee_total_cents']===0);
assert($noState['visibility']['logo_heading'] && $noState['visibility']['logo_information'], 'Structural fields must respond to Show actions.');
assert(!questionnaire_evaluate_rules($conditionalTemplate,['wants_banner'=>'yes'])['visibility']['logo_heading']);
[$hiddenErrors,$hiddenAnswers,, $hiddenState]=validate_questionnaire_submission($conditionalTemplate,['q'=>['wants_banner'=>'no','banner_text'=>['forged']]],[]);
assert(!isset($hiddenAnswers['banner_text']) && $hiddenState['conditional_fee_total_cents']===0, 'Forged hidden answers and fee totals must be ignored.');
assert(questionnaire_rule_matches($conditionalFields[3],'contains','A',['A']));
assert(questionnaire_rule_matches($conditionalFields[3],'not_contains','B',['A']));
assert(questionnaire_rule_matches($conditionalFields[4],'greater_or_equal','10','10'));
assert(questionnaire_rule_matches($conditionalFields[4],'less_than','11','10'));
assert(questionnaire_rule_matches($conditionalFields[5],'before','2027-01-01','2026-12-31'));
assert(questionnaire_rule_matches($conditionalFields[5],'on_or_after','2026-12-31','2026-12-31'));
assert(questionnaire_rule_matches($conditionalFields[0],'is_blank',null,''));
assert(questionnaire_rule_matches($conditionalFields[0],'is_answered',null,'yes'));
$precedence=$conditionalTemplate;$precedence['rules'][]=['id'=>3,'stable_key'=>'precedence','source_field_key'=>'wants_banner','operator'=>'equals','comparison_value'=>'yes','actions'=>[['id'=>6,'action_type'=>'hide','target_field_key'=>'banner_text'],['id'=>7,'action_type'=>'optional','target_field_key'=>'banner_text'],['id'=>8,'action_type'=>'fee','fee_name'=>'Second fee','fee_cents'=>500]]];
$precedenceState=questionnaire_evaluate_rules($precedence,['wants_banner'=>'yes']);
assert(!$precedenceState['visibility']['banner_text'] && !$precedenceState['required']['banner_text']);
assert($precedenceState['conditional_fee_total_cents']===4500 && count($precedenceState['fees'])===2);
assert(questionnaire_evaluate_rules($precedence,['wants_banner'=>'no'])['conditional_fee_total_cents']===0);
assert(questionnaire_snapshot($conditionalTemplate)['rules'][0]['stable_key']==='banner_yes');
assert(questionnaire_evaluate_rules(['fields'=>$conditionalFields],[])['fees']===[], 'Historical snapshots without rules stay compatible.');
$circular=[['source_field_key'=>'wants_banner','operator'=>'equals','comparison_value'=>'yes','actions'=>[['action_type'=>'show','target_field_key'=>'banner_text']]],['source_field_key'=>'banner_text','operator'=>'is_answered','comparison_value'=>null,'actions'=>[['action_type'=>'show','target_field_key'=>'wants_banner']]]];
assert(questionnaire_rule_definition_errors($conditionalFields,$circular)!==[]);
$cross=$circular[0];$cross['actions'][0]['target_field_key']='external';
assert(questionnaire_rule_definition_errors($conditionalFields,[$cross])!==[]);
$tmp=tempnam(sys_get_temp_dir(),'hidden-upload-');file_put_contents($tmp,'x');
validate_questionnaire_submission($conditionalTemplate,['q'=>['wants_banner'=>'yes']],['q_logo'=>['name'=>'logo.png','type'=>'image/png','tmp_name'=>$tmp,'error'=>UPLOAD_ERR_OK,'size'=>1]]);
assert(!is_file($tmp),'A hidden temporary upload must be cleaned up.');


$purchaseSource=file_get_contents(__DIR__.'/../purchase.php');
$previewSource=file_get_contents(__DIR__.'/../admin/questionnaires-preview.php');
assert(str_contains($purchaseSource,'questionnaire_definition_errors($questionnaire[\'fields\'],$questionnaire[\'rules\']??[])'), 'Public validity must include rule validation.');
assert(str_contains($purchaseSource,'data-required-indicator') && str_contains($purchaseSource,'input.disabled=true'), 'Public rendering must expose a live required indicator and disable hidden controls.');
assert(str_contains($purchaseSource,'data-conditional-field') && str_contains($previewSource,'questionnaire-preview-structural'), 'Public and preview structural fields require conditional metadata.');
assert(str_contains(file_get_contents(__DIR__.'/../admin/questionnaires-edit.php'),'data-add-rule-action') && str_contains(file_get_contents(__DIR__.'/../admin/questionnaires-edit.php'),'data-remove-rule-action'), 'The builder must render an action repeater.');
$builderSource=file_get_contents(__DIR__.'/../admin/questionnaires-edit.php');
assert(str_contains($builderSource,'list="rule-comparison-options"') && str_contains($builderSource,'data-rule-options'), 'The comparison input must reference its rendered conditional-rule options datalist.');

echo "Questionnaire builder focused tests passed\n";
