<?php
$serviceTypes = ['website_kit', 'website_build', 'shopify_makeover', 'website_revamp', 'custom_service'];
$statuses = ['draft', 'active', 'archived'];
?>
<form method="post" class="admin-form">
    <?= csrf_field() ?>

    <label>Title *
        <input name="title" required maxlength="255" value="<?= e($template['title'] ?? '') ?>">
    </label>

    <label>Slug
        <input name="slug" maxlength="255" value="<?= e($template['slug'] ?? '') ?>">
        <small>Leave blank to generate from title.</small>
    </label>

    <label>Service type
        <select name="service_type">
            <?php foreach ($serviceTypes as $type): ?>
                <option value="<?= e($type) ?>" <?= ($template['service_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(service_type_label($type)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Version
        <input name="version" maxlength="50" value="<?= e($template['version'] ?? '1.0') ?>">
    </label>

    <label>Status
        <select name="status">
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>" <?= ($template['status'] ?? 'draft') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Body *
        <textarea name="body" rows="16" required><?= e($template['body'] ?? '') ?></textarea>
    </label>

    <p><strong>Placeholders:</strong> {{client_name}}, {{client_email}}, {{business_name}}, {{order_id}}, {{product_name}}, {{product_price}}, {{service_type}}, {{project_url}}, {{order_date}}, {{designer_name}}, {{site_name}}, {{shopify_store_url}}, {{shopify_store_name}}, {{main_goal}}, {{brand_colors}}, {{asset_link}}, {{featured_products}}, {{requested_sections}}, {{inspiration_links}}, {{launch_timing}}, {{extra_notes}}, {{intake_summary}}</p>

    <button class="btn btn-accent">Save Template</button>
</form>
