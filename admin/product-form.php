<?php
$serviceTypes = ['website_kit', 'website_build', 'shopify_makeover', 'website_revamp', 'custom_service'];
$statuses = ['draft', 'active', 'archived'];
$intakeTypes = product_intake_type_labels();
$templates = db()->query("SELECT id, title, version, status FROM contract_templates ORDER BY title")->fetchAll();
?>
<form method="post" enctype="multipart/form-data" class="admin-form admin-product-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <section class="admin-card product-form-section">
        <div class="product-form-section-heading">
            <h2>Product Setup</h2>
            <p>Choose how this product is categorized, purchased, and connected to its contract.</p>
        </div>

        <div class="product-form-grid">
            <label>Product name *
                <input name="name" required maxlength="255" value="<?= e($product['name'] ?? '') ?>">
            </label>

            <label>Product type / service type
                <select name="service_type">
                    <?php foreach ($serviceTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($product['service_type'] ?? 'shopify_makeover') === $type ? 'selected' : '' ?>>
                            <?= e(service_type_label($type)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Customer intake type
                <select name="intake_type">
                    <?php foreach ($intakeTypes as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($product['intake_type'] ?? 'general_service') === $value ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Controls which customer order form this product will use.</small>
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
                <small>Active products require an active contract template.</small>
            </label>

            <label>Status
                <select name="status">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($product['status'] ?? 'draft') === $status ? 'selected' : '' ?>>
                            <?= e(ucfirst($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="feature-check">
                <span>Featured product</span>
                <input type="checkbox" name="is_featured" value="1" <?= !empty($product['is_featured']) ? 'checked' : '' ?>>
            </label>
        </div>
    </section>

    <section class="admin-card product-form-section">
        <div class="product-form-section-heading">
            <h2>Product Information</h2>
            <p>Add the pricing and customer-facing description for this service.</p>
        </div>

        <div class="product-form-grid product-information-grid">
            <label>Price *
                <input name="price" type="number" step="0.01" min="0" required value="<?= e((string) ($product['price'] ?? '0.00')) ?>">
            </label>

            <label class="product-form-full">Short description *
                <textarea name="short_description" required><?= e($product['short_description'] ?? '') ?></textarea>
            </label>

            <label class="product-form-full">Full description
                <textarea class="product-full-description" name="full_description"><?= e($product['full_description'] ?? '') ?></textarea>
            </label>
        </div>
    </section>

    <?php if (($product['intake_type'] ?? 'general_service') === 'shopify_revamp_standard'): ?>
        <section class="admin-card product-form-section">
            <div class="product-form-section-heading">
                <h2>Live Demo</h2>
                <p>Premade Shopify kits use one live demo link and an optional storefront password.</p>
            </div>

            <div class="product-form-grid">
                <label>Live demo URL
                    <input name="demo_url" type="url" maxlength="500" value="<?= e($product['demo_url'] ?? '') ?>">
                    <small>Optional. Must start with http:// or https://.</small>
                </label>

                <label>Demo password
                    <input name="demo_password" maxlength="255" value="<?= e($product['demo_password'] ?? '') ?>">
                </label>
            </div>
        </section>
    <?php endif; ?>

    <?php if (empty($product['id'])): ?>
        <section class="admin-card product-form-section product-image-create-card">
            <div class="product-form-section-heading">
                <h2>Product Images</h2>
                <p>Upload screenshots now or add them later from the edit page.</p>
            </div>

            <div class="product-form-grid">
                <label>Product screenshots/images
                    <input type="file" name="product_images[]" accept="image/jpeg,image/png,image/webp" multiple>
                </label>

                <label>Alt text for uploaded images
                    <input name="image_alt_text" maxlength="255" value="<?= e($product['image_alt_text'] ?? '') ?>">
                </label>
            </div>
        </section>
    <?php endif; ?>

    <div class="product-form-actions">
        <button class="btn btn-accent">Save Product</button>
    </div>
</form>
