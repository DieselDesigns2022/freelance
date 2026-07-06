<?php $isEdit = isset($project); ?>
<form method="post" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>
    <label>
        Section/type
        <select name="section_type" required>
            <option value="website" <?= ($project['section_type'] ?? '') === 'website' ? 'selected' : '' ?>>Website Build</option>
            <option value="shopify_makeover" <?= ($project['section_type'] ?? '') === 'shopify_makeover' ? 'selected' : '' ?>>Shopify Theme / Shopify Make-Over</option>
        </select>
    </label>
    <label>Project title<input name="title" value="<?= e($project['title'] ?? '') ?>" required></label>
    <label>
        Slug
        <input name="slug" value="<?= e($project['slug'] ?? '') ?>">
        <small>Leave blank to auto-generate from title.</small>
    </label>
    <label>Short Description<textarea name="short_description" required><?= e($project['short_description'] ?? '') ?></textarea></label>
    <label>Full Description<textarea name="full_description"><?= e($project['full_description'] ?? '') ?></textarea></label>
    <label>Client/business name<input name="client_name" value="<?= e($project['client_name'] ?? '') ?>"></label>
    <label>Website type / makeover type<input name="project_type" value="<?= e($project['project_type'] ?? '') ?>"></label>
    <label>
        Live Website Link
        <input type="url" name="live_url" value="<?= e($project['live_url'] ?? '') ?>">
        <small>Paste the full website link, including https://</small>
    </label>
    <label>Tools/technologies used<textarea name="tools_used"><?= e($project['tools_used'] ?? '') ?></textarea></label>
    <label>Launch/completion date<input type="date" name="completion_date" value="<?= e($project['completion_date'] ?? '') ?>"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= e((string) ($project['sort_order'] ?? 0)) ?>"></label>
    <label>
        Status
        <select name="status" required>
            <option value="draft" <?= ($project['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= ($project['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
        </select>
    </label>
    <label class="check"><input type="checkbox" name="is_featured" value="1" <?= !empty($project['is_featured']) ? 'checked' : '' ?>> Featured</label>
    <label>SEO title<input name="seo_title" value="<?= e($project['seo_title'] ?? '') ?>"></label>
    <label>SEO description<input name="seo_description" value="<?= e($project['seo_description'] ?? '') ?>"></label>
    <?php if ($isEdit && !empty($project['thumbnail_path'])): ?>
        <p>
            Current thumbnail:<br>
            <img class="admin-thumb" src="../<?= e($project['thumbnail_path']) ?>" alt="">
        </p>
    <?php endif; ?>
    <label>
        Thumbnail Image
        <input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp">
        <small>This image appears on portfolio cards.</small>
    </label>
    <button class="btn">Save Project</button>
</form>
