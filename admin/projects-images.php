<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM portfolio_projects WHERE id=?');
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    exit('Project not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['delete_image'])) {
        $q = db()->prepare('SELECT image_path FROM portfolio_project_images WHERE id=? AND project_id=?');
        $q->execute([(int) $_POST['image_id'], $id]);

        if ($image = $q->fetch()) {
            delete_portfolio_file($image['image_path']);
            db()->prepare('DELETE FROM portfolio_project_images WHERE id=?')->execute([(int) $_POST['image_id']]);
            flash('success', 'Image deleted.');
        }
    } elseif (isset($_POST['delete_pair'])) {
        $q = db()->prepare('SELECT * FROM portfolio_before_after_pairs WHERE id=? AND project_id=?');
        $q->execute([(int) $_POST['pair_id'], $id]);

        if ($pair = $q->fetch()) {
            delete_portfolio_file($pair['before_image_path']);
            delete_portfolio_file($pair['after_image_path']);
            db()->prepare('DELETE FROM portfolio_before_after_pairs WHERE id=?')->execute([(int) $_POST['pair_id']]);
            flash('success', 'Pair deleted.');
        }
    } elseif (isset($_POST['upload_pair'])) {
        $before = null;
        $after = null;

        [$before, $beforeError] = upload_image($_FILES['before_image'] ?? []);
        [$after, $afterError] = upload_image($_FILES['after_image'] ?? []);

        if ($beforeError || $afterError || !$before || !$after) {
            if ($before) {
                delete_portfolio_file($before);
            }

            if ($after) {
                delete_portfolio_file($after);
            }

            flash('error', $beforeError ?: $afterError ?: 'Both before and after images are required.');
        } else {
            try {
                db()->prepare(
                    'INSERT INTO portfolio_before_after_pairs '
                    . '(project_id,before_image_path,after_image_path,caption,before_alt_text,after_alt_text,sort_order,created_at) '
                    . 'VALUES (?,?,?,?,?,?,?,NOW())'
                )->execute([
                    $id,
                    $before,
                    $after,
                    $_POST['caption'] ?: null,
                    $_POST['before_alt_text'] ?: null,
                    $_POST['after_alt_text'] ?: null,
                    (int) $_POST['sort_order'],
                ]);
            } catch (Throwable $exception) {
                delete_portfolio_file($before);
                delete_portfolio_file($after);

                throw $exception;
            }

            flash('success', 'Before/after pair uploaded.');
        }
    } else {
        $type = $_POST['image_type'] ?? 'gallery';
        $err = null;

        if (!in_array($type, ['gallery', 'before', 'after'], true)) {
            $err = 'Invalid image type.';
        }

        $path = null;

        if (!$err) {
            [$path, $err] = upload_image($_FILES['image'] ?? []);
        }

        if ($err || !$path) {
            if ($path) {
                delete_portfolio_file($path);
            }

            flash('error', $err ?: 'Choose an image to upload.');
        } else {
            try {
                db()->prepare(
                    'INSERT INTO portfolio_project_images '
                    . '(project_id,image_type,image_path,caption,alt_text,sort_order,created_at) '
                    . 'VALUES (?,?,?,?,?,?,NOW())'
                )->execute([
                    $id,
                    $type,
                    $path,
                    $_POST['caption'] ?: null,
                    $_POST['alt_text'] ?: null,
                    (int) $_POST['sort_order'],
                ]);
            } catch (Throwable $exception) {
                delete_portfolio_file($path);

                throw $exception;
            }

            flash('success', 'Image uploaded.');
        }
    }

    redirect('projects-images.php?id=' . $id);
}

$imagesStmt = db()->prepare('SELECT * FROM portfolio_project_images WHERE project_id=? ORDER BY image_type, sort_order ASC');
$imagesStmt->execute([$id]);
$images = $imagesStmt->fetchAll();

$pairsStmt = db()->prepare('SELECT * FROM portfolio_before_after_pairs WHERE project_id=? ORDER BY sort_order ASC');
$pairsStmt->execute([$id]);
$pairs = $pairsStmt->fetchAll();

$adminTitle = 'Manage Images';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Manage Images: <?= e($project['title']) ?></h1>
<?php if ($project['section_type'] === 'shopify_makeover'): ?>
    <p class="helper">Use Before images to show the original store design and After images to show the finished makeover.</p>
<?php endif; ?>
<section class="admin-card">
    <h2>Upload Image</h2>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label>
            Image type
            <select name="image_type">
                <option value="gallery">Gallery</option>
                <option value="before">Before</option>
                <option value="after">After</option>
            </select>
        </label>
        <label>Caption<input name="caption"></label>
        <label>Alt text<input name="alt_text"></label>
        <label>Sort order<input type="number" name="sort_order" value="0"></label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
        <button class="btn">Upload Image</button>
    </form>
</section>
<?php if ($project['section_type'] === 'shopify_makeover'): ?>
    <section class="admin-card">
        <h2>Upload Paired Before/After Set</h2>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="upload_pair" value="1">
            <label>Pair title/caption<input name="caption"></label>
            <label>Before alt text<input name="before_alt_text"></label>
            <label>After alt text<input name="after_alt_text"></label>
            <label>Sort order<input type="number" name="sort_order" value="0"></label>
            <label>Before image<input type="file" name="before_image" required></label>
            <label>After image<input type="file" name="after_image" required></label>
            <button class="btn">Upload Pair</button>
        </form>
    </section>
<?php endif; ?>
<section class="admin-card">
    <h2>Uploaded Images</h2>
    <?php if (!$images): ?>
        <p>No images uploaded for this project yet.</p>
    <?php endif; ?>
    <div class="admin-gallery">
        <?php foreach ($images as $image): ?>
            <article>
                <img src="../<?= e($image['image_path']) ?>" alt="">
                <b><?= e(ucfirst($image['image_type'])) ?></b>
                <p><?= e($image['caption'] ?: 'No caption') ?></p>
                <form method="post" onsubmit="return confirm('Delete this image?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                    <button name="delete_image" value="1" class="danger">Delete</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<section class="admin-card">
    <h2>Before/After Pairs</h2>
    <?php if (!$pairs): ?>
        <p>No paired before/after sets yet.</p>
    <?php endif; ?>
    <div class="admin-gallery">
        <?php foreach ($pairs as $pair): ?>
            <article>
                <img src="../<?= e($pair['before_image_path']) ?>" alt="">
                <img src="../<?= e($pair['after_image_path']) ?>" alt="">
                <p><?= e($pair['caption'] ?: 'Pair') ?></p>
                <form method="post" onsubmit="return confirm('Delete this pair?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pair_id" value="<?= (int) $pair['id'] ?>">
                    <button name="delete_pair" value="1" class="danger">Delete Pair</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
