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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['delete_project'])) {
        $imgs = db()->prepare('SELECT image_path FROM portfolio_project_images WHERE project_id=?');
        $imgs->execute([$id]);

        foreach ($imgs->fetchAll() as $image) {
            delete_portfolio_file($image['image_path']);
        }

        $pairs = db()->prepare('SELECT before_image_path,after_image_path FROM portfolio_before_after_pairs WHERE project_id=?');
        $pairs->execute([$id]);

        foreach ($pairs->fetchAll() as $pair) {
            delete_portfolio_file($pair['before_image_path']);
            delete_portfolio_file($pair['after_image_path']);
        }

        delete_portfolio_file($project['thumbnail_path']);
        db()->prepare('DELETE FROM portfolio_projects WHERE id=?')->execute([$id]);

        flash('success', 'Project deleted.');
        redirect('projects.php');
    }

    foreach (['title', 'section_type', 'short_description', 'status'] as $required) {
        if (trim($_POST[$required] ?? '') === '') {
            $errors[] = "$required is required.";
        }
    }

    if (!in_array($_POST['section_type'] ?? '', ['website', 'shopify_makeover'], true)) {
        $errors[] = 'Invalid section type.';
    }

    if (!in_array($_POST['status'] ?? '', ['draft', 'published'], true)) {
        $errors[] = 'Invalid status.';
    }

    if (!valid_url_or_blank($_POST['live_url'] ?? '')) {
        $errors[] = 'Live URL must be a valid http or https URL.';
    }

    $thumb = null;

    if (!$errors) {
        [$thumb, $uploadError] = upload_image($_FILES['thumbnail'] ?? []);

        if ($uploadError) {
            $errors[] = $uploadError;
        }
    }

    if (!$errors) {
        if ($thumb && $project['thumbnail_path']) {
            delete_portfolio_file($project['thumbnail_path']);
        }

        $slug = unique_slug(db(), $_POST['slug'] ?: $_POST['title'], $id);
        try {
            db()->prepare(
                'UPDATE portfolio_projects SET '
                . 'section_type=?,title=?,slug=?,short_description=?,full_description=?,client_name=?,project_type=?,live_url=?,tools_used=?,completion_date=?,thumbnail_path=?,is_featured=?,status=?,sort_order=?,seo_title=?,seo_description=?,updated_at=NOW() '
                . 'WHERE id=?'
            )->execute([
                $_POST['section_type'],
                $_POST['title'],
                $slug,
                $_POST['short_description'],
                $_POST['full_description'] ?: null,
                $_POST['client_name'] ?: null,
                $_POST['project_type'] ?: null,
                $_POST['live_url'] ?: null,
                $_POST['tools_used'] ?: null,
                $_POST['completion_date'] ?: null,
                $thumb ?: $project['thumbnail_path'],
                !empty($_POST['is_featured']) ? 1 : 0,
                $_POST['status'],
                (int) $_POST['sort_order'],
                $_POST['seo_title'] ?: null,
                $_POST['seo_description'] ?: null,
                $id,
            ]);
        } catch (Throwable $exception) {
            if ($thumb) {
                delete_portfolio_file($thumb);
            }

            throw $exception;
        }

        flash('success', 'Project updated.');
        redirect('projects-edit.php?id=' . $id);
    }
}

$adminTitle = 'Edit Project';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Edit Project</h1>
<p>
    <a class="btn btn-ghost" href="projects-images.php?id=<?= $id ?>">Manage Images</a>
    <?php if ($project['status'] === 'published'): ?>
        <a class="btn" href="../project.php?slug=<?= e($project['slug']) ?>">View Public Project</a>
    <?php endif; ?>
</p>
<?php foreach ($errors as $error): ?>
    <p class="error-text"><?= e($error) ?></p>
<?php endforeach; ?>
<?php include __DIR__ . '/project-form.php'; ?>
<form method="post" onsubmit="return confirm('Delete this project and related images?')">
    <?= csrf_field() ?>
    <button class="btn danger" name="delete_project" value="1">Delete Project</button>
</form>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
