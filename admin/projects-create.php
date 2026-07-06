<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$errors = [];
$project = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $project = $_POST;

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
        $slug = unique_slug(db(), $_POST['slug'] ?: $_POST['title']);
        $stmt = db()->prepare(
            'INSERT INTO portfolio_projects '
            . '(section_type,title,slug,short_description,full_description,client_name,project_type,live_url,tools_used,completion_date,thumbnail_path,is_featured,status,sort_order,seo_title,seo_description,created_at) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        try {
            $stmt->execute([
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
                $thumb,
                !empty($_POST['is_featured']) ? 1 : 0,
                $_POST['status'],
                (int) $_POST['sort_order'],
                $_POST['seo_title'] ?: null,
                $_POST['seo_description'] ?: null,
            ]);
        } catch (Throwable $exception) {
            if ($thumb) {
                delete_portfolio_file($thumb);
            }

            throw $exception;
        }

        flash('success', 'Project created. Add images next.');
        redirect('projects-images.php?id=' . db()->lastInsertId());
    }
}

$adminTitle = 'Add Project';
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Add Project</h1>
<?php foreach ($errors as $error): ?>
    <p class="error-text"><?= e($error) ?></p>
<?php endforeach; ?>
<?php include __DIR__ . '/project-form.php'; ?>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
