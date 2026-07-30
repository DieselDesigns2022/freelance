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
$adminTitle = 'Preview Questionnaire';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="admin-page-heading"><div><p class="eyebrow">Preview</p><h1><?= e($questionnaire['title']) ?></h1></div><a class="btn" href="questionnaires-edit.php?id=<?= $id ?>">Back to builder</a></div>
<section class="admin-card questionnaire-preview">
<?php foreach ($questionnaire['fields'] as $field): $type=$field['field_type']; ?>
  <?php if ($type === 'section_heading'): ?><h2><?= e($field['label']) ?></h2>
  <?php elseif ($type === 'information'): ?><p><?= nl2br(e($field['label'])) ?></p>
  <?php else: ?><div class="questionnaire-preview-field"><strong><?= e($field['label']) ?><?= !empty($field['is_required']) ? ' *' : '' ?></strong><?php if ($field['help_text']): ?><small><?= e($field['help_text']) ?></small><?php endif; ?><div class="questionnaire-preview-input"><?= in_array($type, QUESTIONNAIRE_OPTION_TYPES, true) ? e(implode(' · ', $field['options'])) : e(ucwords(str_replace('_', ' ', $type))) ?></div></div><?php endif; ?>
<?php endforeach; ?>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
