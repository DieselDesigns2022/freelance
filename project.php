<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare("SELECT * FROM portfolio_projects WHERE slug=? AND status='published' LIMIT 1");
$stmt->execute([$slug]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    $pageTitle = 'Project Not Found | Diesel Designs';
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="page-hero">
        <h1>Project not found</h1>
        <p>This project is still being updated or is not published yet.</p>
        <a class="btn" href="index.php">Back to portfolio</a>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = ($project['seo_title'] ?: $project['title']) . ' | Diesel Designs';
$metaDescription = $project['seo_description'] ?: $project['short_description'];

$img = db()->prepare('SELECT * FROM portfolio_project_images WHERE project_id=? ORDER BY sort_order ASC, created_at DESC');
$img->execute([$project['id']]);
$images = $img->fetchAll();
$pairs = [];

if ($project['section_type'] === 'shopify_makeover') {
    $ps = db()->prepare('SELECT * FROM portfolio_before_after_pairs WHERE project_id=? ORDER BY sort_order ASC, created_at DESC');
    $ps->execute([$project['id']]);
    $pairs = $ps->fetchAll();
}

$groups = [
    'gallery' => [],
    'before' => [],
    'after' => [],
];

foreach ($images as $image) {
    $groups[$image['image_type']][] = $image;
}

include __DIR__ . '/includes/header.php';
?>
<section class="project-detail">
    <div>
        <p class="eyebrow"><?= e(section_label($project['section_type'])) ?></p>
        <h1><?= e($project['title']) ?></h1>
        <p class="lead"><?= e($project['short_description']) ?></p>
        <div class="meta">
            <?php if ($project['client_name']): ?>
                <span>Client: <?= e($project['client_name']) ?></span>
            <?php endif; ?>
            <?php if ($project['project_type']): ?>
                <span>Type: <?= e($project['project_type']) ?></span>
            <?php endif; ?>
            <?php if ($project['tools_used']): ?>
                <span>Tools: <?= e($project['tools_used']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($project['live_url']): ?>
            <a class="btn btn-accent" href="<?= e($project['live_url']) ?>" target="_blank" rel="noopener">Visit Live Website</a>
        <?php endif; ?>
    </div>
    <?php if ($project['thumbnail_path']): ?>
        <button class="image-button" data-lightbox-src="<?= e($project['thumbnail_path']) ?>" data-lightbox-alt="<?= e($project['title']) ?>">
            <img src="<?= e($project['thumbnail_path']) ?>" alt="<?= e($project['title']) ?>">
        </button>
    <?php endif; ?>
</section>
<?php if ($project['full_description']): ?>
    <section class="section prose">
        <h2>Project Notes</h2>
        <?= nl2br(e($project['full_description'])) ?>
    </section>
<?php endif; ?>
<?php if ($project['section_type'] === 'shopify_makeover'): ?>
    <section class="section">
        <h2>Before & After</h2>
        <?php if (!$pairs && !$groups['before'] && !$groups['after']): ?>
            <div class="empty">Before and after photos are coming soon.</div>
        <?php endif; ?>
        <?php foreach ($pairs as $pair): ?>
            <article class="ba-pair">
                <h3><?= e($pair['caption'] ?: 'Shopify glow-up comparison') ?></h3>
                <div>
                    <figure>
                        <figcaption>Before</figcaption>
                        <button class="image-button" data-lightbox-src="<?= e($pair['before_image_path']) ?>" data-lightbox-alt="<?= e($pair['before_alt_text'] ?: $project['title'] . ' before') ?>">
                            <img src="<?= e($pair['before_image_path']) ?>" alt="<?= e($pair['before_alt_text'] ?: $project['title'] . ' before') ?>">
                        </button>
                    </figure>
                    <figure>
                        <figcaption>After</figcaption>
                        <button class="image-button" data-lightbox-src="<?= e($pair['after_image_path']) ?>" data-lightbox-alt="<?= e($pair['after_alt_text'] ?: $project['title'] . ' after') ?>">
                            <img src="<?= e($pair['after_image_path']) ?>" alt="<?= e($pair['after_alt_text'] ?: $project['title'] . ' after') ?>">
                        </button>
                    </figure>
                </div>
            </article>
        <?php endforeach; ?>
        <div class="split-gallery">
            <?php foreach (['before' => 'Before Photos', 'after' => 'After Photos'] as $type => $label): ?>
                <div>
                    <h3><?= $label ?></h3>
                    <?php if ($groups[$type]): ?>
                        <div class="mini-grid">
                            <?php foreach ($groups[$type] as $image): ?>
                                <button class="image-button" data-lightbox-src="<?= e($image['image_path']) ?>" data-lightbox-alt="<?= e($image['alt_text'] ?: $project['title']) ?>">
                                    <img src="<?= e($image['image_path']) ?>" alt="<?= e($image['alt_text'] ?: $project['title']) ?>">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">No <?= strtolower($label) ?> uploaded yet.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<section class="section">
    <h2>Gallery</h2>
    <?php if ($groups['gallery']): ?>
        <div class="gallery-grid">
            <?php foreach ($groups['gallery'] as $image): ?>
                <figure>
                    <button class="image-button" data-lightbox-src="<?= e($image['image_path']) ?>" data-lightbox-alt="<?= e($image['alt_text'] ?: $project['title']) ?>">
                        <img src="<?= e($image['image_path']) ?>" alt="<?= e($image['alt_text'] ?: $project['title']) ?>">
                    </button>
                    <?php if ($image['caption']): ?>
                        <figcaption><?= e($image['caption']) ?></figcaption>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">No images uploaded for this project yet.</div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
