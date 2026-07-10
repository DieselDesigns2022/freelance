<?php
declare(strict_types=1);

const CONTACT_EMAIL = 'diesel.designs.contact@gmail.com';
const UPLOAD_RELATIVE_DIR = 'uploads/portfolio/';
const MAX_IMAGE_BYTES = 10485760;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function section_label(string $type): string
{
    return $type === 'shopify_makeover' ? 'Shopify Make-Over' : 'Website Build';
}

function status_label(string $status): string
{
    return $status === 'published' ? 'Published' : 'Draft';
}

function flash(?string $key = null, ?string $message = null): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if ($key && $message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if ($key) {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }

    return null;
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', trim($text));
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);

    return strtolower($text ?: 'project');
}

function unique_slug(PDO $pdo, string $base, ?int $ignoreId = null): string
{
    $slug = slugify($base);
    $candidate = $slug;
    $i = 2;

    while (true) {
        $sql = 'SELECT id FROM portfolio_projects WHERE slug = ?' . ($ignoreId ? ' AND id != ?' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ignoreId ? [$candidate, $ignoreId] : [$candidate]);

        if (!$stmt->fetch()) {
            return $candidate;
        }

        $candidate = $slug . '-' . $i++;
    }
}

function valid_url_or_blank(?string $url): bool
{
    if ($url === null || trim($url) === '') {
        return true;
    }

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true);
}

function upload_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Image upload failed. Please try again.'];
    }

    if (($file['size'] ?? 0) > MAX_IMAGE_BYTES) {
        return [null, 'Image must be 10MB or smaller.'];
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    if (!isset($allowed[$ext])) {
        return [null, 'Only JPG, PNG, and WEBP images are allowed.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if ($mime !== $allowed[$ext]) {
        return [null, 'The uploaded file type is not allowed.'];
    }

    $dir = dirname(__DIR__) . '/' . UPLOAD_RELATIVE_DIR;

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $target = $dir . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return [null, 'Could not save uploaded image.'];
    }

    return [UPLOAD_RELATIVE_DIR . $name, null];
}

function delete_portfolio_file(?string $relative): bool
{
    if (!$relative || !str_starts_with($relative, UPLOAD_RELATIVE_DIR)) {
        return false;
    }

    $base = realpath(dirname(__DIR__) . '/' . UPLOAD_RELATIVE_DIR);
    $path = realpath(dirname(__DIR__) . '/' . $relative);

    if (!$base || !$path || !str_starts_with($path, $base)) {
        return false;
    }

    return is_file($path) ? unlink($path) : false;
}

function project_card(array $project): string
{
    $thumb = $project['thumbnail_path'] ?: 'assets/css/placeholder.svg';
    $live = '';

    if ($project['live_url']) {
        $live = '<a class="btn btn-ghost" href="' . e($project['live_url']) . '" target="_blank" rel="noopener">Visit Live Site</a>';
    }

    return '<article class="project-card">'
        . '<a href="project.php?slug=' . e($project['slug']) . '">'
        . '<img src="' . e($thumb) . '" alt="' . e($project['title']) . '">'
        . '</a>'
        . '<div class="card-body">'
        . '<span class="pill">' . e($project['project_type'] ?: section_label($project['section_type'])) . '</span>'
        . '<h3>' . e($project['title']) . '</h3>'
        . '<p>' . e($project['short_description']) . '</p>'
        . '<div class="card-actions">'
        . '<a class="btn" href="project.php?slug=' . e($project['slug']) . '">View Project</a>'
        . $live
        . '</div>'
        . '</div>'
        . '</article>';
}

function money_format_dd($value): string
{
    return '$' . number_format((float) $value, 2);
}

function service_type_label(string $type): string
{
    return [
        'website_kit' => 'Website Kit',
        'website_build' => 'Website Build',
        'shopify_makeover' => 'Shopify Make-Over',
        'website_revamp' => 'Website Revamp',
        'custom_service' => 'Custom Service',
    ][$type] ?? ucwords(str_replace('_', ' ', $type));
}

function fulfillment_type_label(string $type): string
{
    return ['service' => 'Service', 'digital_kit' => 'Digital Kit', 'hybrid' => 'Hybrid'][$type] ?? ucfirst($type);
}

function token_hash(string $token): string
{
    return hash('sha256', $token);
}

function create_contract_token(): string
{
    return bin2hex(random_bytes(32));
}

function render_contract_template(string $body, array $data): string
{
    $replacements = [];
    foreach ($data as $key => $value) {
        $replacements['{{' . $key . '}}'] = (string) $value;
    }
    return strtr($body, $replacements);
}

function order_number(int $id): string
{
    return 'DD-' . date('Ymd') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function allowed_service_types(): array
{
    return ['website_kit', 'website_build', 'shopify_makeover', 'website_revamp', 'custom_service'];
}

function allowed_fulfillment_types(): array
{
    return ['service', 'digital_kit', 'hybrid'];
}

function allowed_product_statuses(): array
{
    return ['draft', 'active', 'archived'];
}


function allowed_contract_template_statuses(): array
{
    return ['draft', 'active', 'archived'];
}

function allowed_contract_statuses(): array
{
    return ['pending', 'sent', 'viewed', 'signed', 'void'];
}

function signable_contract_statuses(): array
{
    return ['pending', 'sent', 'viewed'];
}

function contract_admin_mutable_statuses(): array
{
    return ['pending', 'sent', 'viewed'];
}

function allowed_order_statuses(): array
{
    return ['pending_contract', 'contract_sent', 'contract_signed', 'payment_pending', 'paid', 'in_progress', 'completed', 'cancelled'];
}

function allowed_payment_statuses(): array
{
    return ['not_required', 'pending', 'paid', 'refunded', 'failed'];
}

function base_url_from_request(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $publicDir = preg_replace('~/admin$~', '', rtrim($scriptDir, '/')) ?: '';

    return $scheme . '://' . $host . $publicDir;
}

function safe_download_filename(string $orderNumber): string
{
    $safeOrder = preg_replace('/[^A-Za-z0-9_-]+/', '-', $orderNumber) ?: 'contract';
    return 'signed-contract-' . trim($safeOrder, '-') . '.html';
}
