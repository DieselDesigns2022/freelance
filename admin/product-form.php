<?php
$serviceTypes = ['website_kit', 'website_build', 'shopify_makeover', 'website_revamp', 'custom_service'];
$fulfillmentTypes = ['service', 'digital_kit', 'hybrid'];
$statuses = ['draft', 'active', 'archived'];
$templates = db()->query("SELECT id, title, version, status FROM contract_templates ORDER BY title")->fetchAll();
?>
<form method="post" class="admin-form">
    <?= csrf_field() ?>

    <label>Name *
        <input name="name" required maxlength="255" value="<?= e($product['name'] ?? '') ?>">
    </label>

    <label>Slug
        <input name="slug" maxlength="255" value="<?= e($product['slug'] ?? '') ?>">
        <small>Leave blank to generate from name.</small>
    </label>

    <label>Short description *
        <textarea name="short_description" required><?= e($product['short_description'] ?? '') ?></textarea>
    </label>

    <label>Full description
        <textarea name="full_description"><?= e($product['full_description'] ?? '') ?></textarea>
    </label>

    <label>Service type
        <select name="service_type">
            <?php foreach ($serviceTypes as $type): ?>
                <option value="<?= e($type) ?>" <?= ($product['service_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(service_type_label($type)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Fulfillment type
        <select name="fulfillment_type">
            <?php foreach ($fulfillmentTypes as $type): ?>
                <option value="<?= e($type) ?>" <?= ($product['fulfillment_type'] ?? 'service') === $type ? 'selected' : '' ?>><?= e(fulfillment_type_label($type)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Price *
        <input name="price" type="number" step="0.01" min="0" required value="<?= e((string) ($product['price'] ?? '0.00')) ?>">
    </label>

    <label>Deposit amount
        <input name="deposit_amount" type="number" step="0.01" min="0" value="<?= e((string) ($product['deposit_amount'] ?? '')) ?>">
    </label>

    <label>Turnaround
        <textarea name="turnaround_text"><?= e($product['turnaround_text'] ?? '') ?></textarea>
    </label>

    <label>Includes
        <textarea name="includes_text"><?= e($product['includes_text'] ?? '') ?></textarea>
    </label>

    <label>Requirements
        <textarea name="requirements_text"><?= e($product['requirements_text'] ?? '') ?></textarea>
    </label>

    <label>Status
        <select name="status">
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= ($product['status'] ?? 'draft') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="check">
        <input type="checkbox" name="is_featured" value="1" <?= !empty($product['is_featured']) ? 'checked' : '' ?>> Featured
    </label>

    <label>Sort order
        <input name="sort_order" type="number" value="<?= e((string) ($product['sort_order'] ?? 0)) ?>">
    </label>

    <label>Contract template
        <select name="contract_template_id">
            <option value="">None</option>
            <?php foreach ($templates as $template): ?>
                <option value="<?= (int) $template['id'] ?>" <?= (string) ($product['contract_template_id'] ?? '') === (string) $template['id'] ? 'selected' : '' ?>>
                    <?= e($template['title'] . ' v' . $template['version'] . ' (' . $template['status'] . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Active products require an active contract template before they can be purchased.</small>
    </label>

    <button class="btn btn-accent">Save Product</button>
</form>
