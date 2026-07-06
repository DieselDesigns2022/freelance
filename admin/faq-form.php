<form method="post" class="admin-form">
    <?= csrf_field() ?>
    <label>Question<input name="question" maxlength="255" value="<?= e($faq['question'] ?? '') ?>" required></label>
    <label>Category<input name="category" maxlength="100" value="<?= e($faq['category'] ?? '') ?>"></label>
    <label>Answer<textarea name="answer" required><?= e($faq['answer'] ?? '') ?></textarea></label>
    <label>Status<select name="status"><option value="draft" <?= ($faq['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="published" <?= ($faq['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published</option></select></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= e((string) ($faq['sort_order'] ?? 0)) ?>"></label>
    <button class="btn">Save FAQ</button>
</form>
