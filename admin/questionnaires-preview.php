<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/questionnaires.php';
require_admin();
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM questionnaire_templates WHERE id = ?');
$stmt->execute([$id]);
$questionnaire = $stmt->fetch();
if (!$questionnaire) { http_response_code(404); exit('Questionnaire not found.'); }
$questionnaire['fields'] = load_questionnaire_fields(db(), $id, true);
$questionnaire['rules'] = load_questionnaire_rules(db(), $id, true);
$adminTitle = 'Preview Questionnaire';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="admin-page-heading"><div><p class="eyebrow">Preview</p><h1><?= e($questionnaire['title']) ?></h1></div><a class="btn" href="questionnaires-edit.php?id=<?= $id ?>">Back to builder</a></div>
<section class="admin-card questionnaire-preview">
<?php foreach ($questionnaire['fields'] as $field): $type=$field['field_type']; ?>
  <?php if ($type === 'section_heading'): ?><h2><?= e($field['label']) ?></h2>
  <?php elseif ($type === 'information'): ?><p><?= nl2br(e($field['label'])) ?></p>
  <?php else: ?><div class="questionnaire-preview-field" data-conditional-field="<?= e((string) $field['field_key']) ?>" data-base-required="<?= !empty($field['is_required']) ? '1' : '0' ?>"><strong><?= e($field['label']) ?><span data-required-indicator><?= !empty($field['is_required']) ? ' *' : '' ?></span></strong><?php if ($field['help_text']): ?><small><?= e($field['help_text']) ?></small><?php endif; ?>
  <?php if ($type === 'addon'): $config=questionnaire_addon_config($field); ?><div class="questionnaire-preview-input questionnaire-addon-preview"
    data-addon-preview
    data-method="<?= e($config['pricing_method']) ?>"
    data-price="<?= (int) $config['unit_price_cents'] ?>"
    data-included="<?= (int) $config['included_quantity'] ?>">
    <?php if ($config['pricing_method'] === 'per_additional_item'): ?>
      <p><strong><?= (int) $config['included_quantity'] ?> included</strong></p>
      <p><?= e(questionnaire_format_cents($config['unit_price_cents'])) ?> per item over <?= (int) $config['included_quantity'] ?>.</p>
      <label>Total quantity
        <input type="number" min="0" max="<?= (int) $config['max_quantity'] ?>" step="1" value="<?= (int) $config['included_quantity'] ?>" data-addon-preview-input>
      </label>
      <p>Billable quantity: <strong data-addon-preview-billable>0</strong></p>
      <p>Sample add-on total: <strong data-addon-preview-total><?= e(questionnaire_format_cents(0)) ?></strong></p>
    <?php elseif ($config['pricing_method'] === 'flat_fee'): ?>
      <p><strong><?= e(questionnaire_format_cents($config['unit_price_cents'])) ?> flat fee</strong></p>
      <label><input type="checkbox" data-addon-preview-input> Add this upgrade</label>
      <p>Sample add-on total: <strong data-addon-preview-total><?= e(questionnaire_format_cents(0)) ?></strong></p>
    <?php else: ?>
      <p><strong><?= e(questionnaire_format_cents($config['unit_price_cents'])) ?> each</strong></p>
      <label>Quantity
        <input type="number" min="0" max="<?= (int) $config['max_quantity'] ?>" step="1" value="0" data-addon-preview-input>
      </label>
      <p>Sample add-on total: <strong data-addon-preview-total><?= e(questionnaire_format_cents(0)) ?></strong></p>
    <?php endif; ?>
  </div>
  <?php else: ?>
    <div class="questionnaire-preview-input">
      <?php
      $key = (string) ($field['field_key'] ?? '');
      $options = is_array($field['options'] ?? null) ? $field['options'] : [];
      $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
      ?>

      <?php if ($type === 'short_text'): ?>
        <input type="text" name="preview[<?= e($key) ?>]" placeholder="<?= e((string) ($field['placeholder'] ?? '')) ?>">

      <?php elseif ($type === 'long_text'): ?>
        <textarea name="preview[<?= e($key) ?>]" placeholder="<?= e((string) ($field['placeholder'] ?? '')) ?>"></textarea>

      <?php elseif ($type === 'email'): ?>
        <input type="email" name="preview[<?= e($key) ?>]" placeholder="<?= e((string) ($field['placeholder'] ?? '')) ?>">

      <?php elseif ($type === 'phone'): ?>
        <input type="tel" name="preview[<?= e($key) ?>]" placeholder="<?= e((string) ($field['placeholder'] ?? '')) ?>">

      <?php elseif ($type === 'url'): ?>
        <input type="url" name="preview[<?= e($key) ?>]" placeholder="<?= e((string) ($field['placeholder'] ?? '')) ?>">

      <?php elseif ($type === 'number'): ?>
        <input
          type="number"
          name="preview[<?= e($key) ?>]"
          <?php if (($validation['min_number'] ?? '') !== ''): ?>min="<?= e((string) $validation['min_number']) ?>"<?php endif; ?>
          <?php if (($validation['max_number'] ?? '') !== ''): ?>max="<?= e((string) $validation['max_number']) ?>"<?php endif; ?>
        >

      <?php elseif ($type === 'date'): ?>
        <input type="date" name="preview[<?= e($key) ?>]">

      <?php elseif ($type === 'yes_no'): ?>
        <label><input type="radio" name="preview[<?= e($key) ?>]" value="yes"> Yes</label>
        <label><input type="radio" name="preview[<?= e($key) ?>]" value="no"> No</label>

      <?php elseif ($type === 'dropdown'): ?>
        <select name="preview[<?= e($key) ?>]">
          <option value="">Choose one</option>
          <?php foreach ($options as $option): ?>
            <option value="<?= e((string) $option) ?>"><?= e((string) $option) ?></option>
          <?php endforeach; ?>
        </select>

      <?php elseif ($type === 'radio'): ?>
        <?php foreach ($options as $option): ?>
          <label>
            <input type="radio" name="preview[<?= e($key) ?>]" value="<?= e((string) $option) ?>">
            <?= e((string) $option) ?>
          </label>
        <?php endforeach; ?>

      <?php elseif ($type === 'checkboxes'): ?>
        <?php foreach ($options as $option): ?>
          <label>
            <input type="checkbox" name="preview[<?= e($key) ?>][]" value="<?= e((string) $option) ?>">
            <?= e((string) $option) ?>
          </label>
        <?php endforeach; ?>

      <?php elseif ($type === 'multiple_inputs'): ?>
        <?php
        $inputCount = max(
            2,
            min(10, (int) ($validation['input_count'] ?? 4))
        );
        ?>
        <div class="questionnaire-multiple-inputs">
          <?php for ($inputIndex = 0; $inputIndex < $inputCount; $inputIndex++): ?>
            <label
              class="visually-hidden"
              for="<?= e($key . '-preview-' . $inputIndex) ?>"
            >
              Input <?= $inputIndex + 1 ?>
            </label>
            <input
              id="<?= e($key . '-preview-' . $inputIndex) ?>"
              type="text"
              name="preview[<?= e($key) ?>][<?= $inputIndex ?>]"
              placeholder="Input <?= $inputIndex + 1 ?>"
            >
          <?php endfor; ?>
        </div>
        <small>Complete as many boxes as needed. Individual boxes may be left blank.</small>

      <?php elseif (in_array($type, QUESTIONNAIRE_FILE_TYPES, true)): ?>
        <?php
        $extensions = $validation['allowed_extensions'] ?? ['png', 'jpg', 'jpeg', 'webp', 'pdf'];
        $accept = implode(',', array_map(
            static fn ($extension): string => '.' . ltrim((string) $extension, '.'),
            $extensions
        ));
        ?>
        <input
          type="file"
          name="preview_<?= e($key) ?><?= $type === 'multiple_files' ? '[]' : '' ?>"
          accept="<?= e($accept) ?>"
          <?= $type === 'multiple_files' ? 'multiple' : '' ?>
          data-preview-file
        >
        <small data-preview-file-status hidden></small>

      <?php else: ?>
        <?= e(questionnaire_field_type_label($type)) ?>
      <?php endif; ?>
    </div>
  <?php endif; ?></div><?php endif; ?>
<?php endforeach; ?>
</section>
<aside class="admin-card questionnaire-conditional-fees" data-conditional-fees><h2>Conditional fee preview</h2><div data-conditional-fee-lines></div><p>Current conditional total: <strong data-conditional-fee-total>$0.00</strong></p></aside>
<script type="application/json" data-questionnaire-rules><?= json_encode($questionnaire['rules'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>

<script>
(() => {
const node=document.querySelector('[data-questionnaire-rules]'); const rules=JSON.parse(node?.textContent||'[]');
const value=(key)=>{const inputs=[...document.querySelectorAll(`[name="preview[${CSS.escape(key)}]"],[name="preview[${CSS.escape(key)}][]"],[name^="preview[${CSS.escape(key)}]["]`)];if(!inputs.length){const file=document.querySelector(`[name="preview_${CSS.escape(key)}"],[name="preview_${CSS.escape(key)}[]"]`);return file?.files?[...file.files]:'';}if(inputs[0].type==='checkbox'&&inputs.length===1)return inputs[0].checked?'1':'0';if(inputs[0].type==='checkbox')return inputs.filter(i=>i.checked).map(i=>i.value);if(inputs[0].type==='radio')return inputs.find(i=>i.checked)?.value||'';return inputs.length>1?inputs.map(i=>i.value).filter(Boolean):inputs[0].value;};
const blank=v=>Array.isArray(v)?v.length===0:String(v??'').trim()==='';
const matches=(r,v)=>{const c=r.comparison_value,op=r.operator,n=Number(v),x=Number(c);if(op==='is_answered')return !blank(v);if(op==='is_blank'||op==='has_no_file')return blank(v);if(op==='has_file')return !blank(v);if(op==='contains')return v.includes(String(c));if(op==='not_contains')return !v.includes(String(c));if(op==='is_selected')return n>0;if(op==='is_not_selected')return n===0;if(['equals','quantity_equals'].includes(op))return String(v)===String(c);if(op==='not_equals')return String(v)!==String(c);if(['greater_than','after','quantity_greater_than'].includes(op))return isNaN(n)||isNaN(x)?String(v)>String(c):n>x;if(['greater_or_equal','on_or_after'].includes(op))return isNaN(n)||isNaN(x)?String(v)>=String(c):n>=x;if(['less_than','before','quantity_less_than'].includes(op))return isNaN(n)||isNaN(x)?String(v)<String(c):n<x;if(['less_or_equal','on_or_before'].includes(op))return isNaN(n)||isNaN(x)?String(v)<=String(c):n<=x;return false;};
const shownTargets=new Set(rules.flatMap(r=>(r.actions||[]).filter(a=>a.action_type==='show').map(a=>a.target_field_key)));const update=()=>{const effects={},fees=new Map;rules.forEach(r=>{if(!matches(r,value(r.source_field_key)))return;(r.actions||[]).forEach(a=>a.action_type==='fee'?fees.set(`${r.stable_key||r.id}:${a.id||a.sort_order}`,a):(effects[a.target_field_key]??={})[a.action_type]=true)});document.querySelectorAll('[data-conditional-field]').forEach(field=>{const e=effects[field.dataset.conditionalField]||{},visible=e.hide?false:e.show?true:!shownTargets.has(field.dataset.conditionalField),required=e.optional?false:e.required?true:field.dataset.baseRequired==='1';field.hidden=!visible;field.querySelector('[data-required-indicator]').textContent=required?' *':'';field.querySelectorAll('input,select,textarea').forEach(input=>input.required=visible&&required)});const list=document.querySelector('[data-conditional-fee-lines]');list.replaceChildren(...[...fees.values()].map(f=>{const p=document.createElement('p');p.textContent=`${f.fee_name} — ${new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(Number(f.fee_cents)/100)}`;return p}));document.querySelector('[data-conditional-fee-total]').textContent=new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format([...fees.values()].reduce((s,f)=>s+Number(f.fee_cents),0)/100);};document.addEventListener('input',update);document.addEventListener('change',update);update();
})();
document.querySelectorAll('[data-addon-preview]').forEach((box) => {
    const input = box.querySelector('[data-addon-preview-input]');
    const totalOutput = box.querySelector('[data-addon-preview-total]');
    const billableOutput = box.querySelector('[data-addon-preview-billable]');

    const update = () => {
        const method = box.dataset.method;
        const priceCents = Math.max(0, Number(box.dataset.price) || 0);
        const included = Math.max(0, Number(box.dataset.included) || 0);

        const selected = method === 'flat_fee'
            ? (input.checked ? 1 : 0)
            : Math.max(0, Number(input.value) || 0);

        const billable = method === 'per_additional_item'
            ? Math.max(0, selected - included)
            : selected;

        if (billableOutput) {
            billableOutput.textContent = String(billable);
        }

        totalOutput.textContent = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format((billable * priceCents) / 100);
    };

    input.addEventListener('input', update);
    input.addEventListener('change', update);
    update();
});
</script>


<script>
document.querySelectorAll('[data-preview-file]').forEach((input) => {
    const status = input.parentElement.querySelector(
        '[data-preview-file-status]'
    );

    input.addEventListener('change', () => {
        const files = Array.from(input.files || []);

        status.hidden = files.length === 0;
        status.textContent = files.length
            ? files.map((file) => file.name).join(', ')
            : '';
    });
});
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
