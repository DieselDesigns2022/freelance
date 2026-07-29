<?php
declare(strict_types=1);

const CONTACT_EMAIL = 'diesel.designs.contact@gmail.com';
const UPLOAD_RELATIVE_DIR = 'uploads/portfolio/';
const ORDER_UPLOAD_RELATIVE_DIR = 'uploads/order-assets/';
const MAX_IMAGE_BYTES = 10485760;
const MAX_ORDER_UPLOAD_BYTES = 15728640;

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

function allowed_product_intake_types(): array
{
    return ['shopify_revamp_standard', 'shopify_custom_kit', 'website_custom_build', 'general_service'];
}

function product_intake_type_labels(): array
{
    return [
        'shopify_revamp_standard' => 'Standard Shopify Revamp',
        'shopify_custom_kit' => 'Shopify Custom Design Kit',
        'website_custom_build' => 'Custom Website Build',
        'general_service' => 'General Service',
    ];
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


function upload_order_asset(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'File upload failed. Please try again.'];
    }

    if (($file['size'] ?? 0) > MAX_ORDER_UPLOAD_BYTES) {
        return [null, 'Uploaded files must be 15MB or smaller each.'];
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];

    if (!isset($allowed[$ext])) {
        return [null, 'Only PNG, JPG, WEBP, and PDF files are allowed.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if ($mime !== $allowed[$ext]) {
        return [null, 'The uploaded file type is not allowed.'];
    }

    $dir = dirname(__DIR__) . '/' . ORDER_UPLOAD_RELATIVE_DIR;

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $target = $dir . $name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return [null, 'Could not save uploaded file.'];
    }

    return [[
        'path' => ORDER_UPLOAD_RELATIVE_DIR . $name,
        'original_name' => substr((string) ($file['name'] ?? ''), 0, 255),
        'mime_type' => $mime,
        'file_size' => (int) ($file['size'] ?? 0),
    ], null];
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

function product_service_card(array $product): string
{
    $imageHtml = '';

    if (!empty($product['image_path'])) {
        $alt = $product['alt_text'] ?: $product['name'];
        $imageHtml = '<a href="product-service.php?slug=' . e($product['slug']) . '">'
            . '<img class="product-card-thumb" src="' . e($product['image_path']) . '" alt="' . e($alt) . '">'
            . '</a>';
    }

    return '<article class="project-card product-service-card">'
        . $imageHtml
        . '<div class="card-body">'
        . '<span class="pill">' . e(service_type_label($product['service_type'])) . '</span>'
        . '<h3>' . e($product['name']) . '</h3>'
        . '<p>' . e($product['short_description']) . '</p>'
        . '<p><strong>' . e(money_format_dd($product['price'])) . '</strong></p>'
        . '<div class="card-actions">'
        . '<a class="btn" href="product-service.php?slug=' . e($product['slug']) . '">View Details</a>'
        . '<a class="btn btn-accent" href="purchase.php?product=' . e($product['slug']) . '">Purchase / Start Order</a>'
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

function contract_legacy_placeholder_replacements(array $data): array
{
    $clientName = trim((string) ($data['client_name'] ?? $data['customer_name'] ?? ''));
    $clientEmail = trim((string) ($data['client_email'] ?? $data['customer_email'] ?? ''));
    $businessName = trim((string) ($data['business_name'] ?? ''));
    $orderId = trim((string) ($data['order_id'] ?? $data['order_number'] ?? ''));
    $productName = trim((string) ($data['product_name'] ?? $data['product_name_snapshot'] ?? ''));
    $productPrice = trim((string) ($data['product_price'] ?? $data['product_price_snapshot'] ?? ''));
    $projectUrl = trim((string) ($data['project_url'] ?? $data['website_url'] ?? ''));
    $orderDate = trim((string) ($data['order_date'] ?? ''));

    return [
        '[Client Name]' => $clientName !== '' ? $clientName : 'N/A',
        "[Client's Name]" => $clientName !== '' ? $clientName : 'N/A',
        "[Client’s Name]" => $clientName !== '' ? $clientName : 'N/A',
        '[Client Email]' => $clientEmail !== '' ? $clientEmail : 'N/A',
        '[Business Name]' => $businessName !== '' ? $businessName : 'N/A',
        '[Order ID]' => $orderId !== '' ? $orderId : 'N/A',
        '[Order Number]' => $orderId !== '' ? $orderId : 'N/A',
        '[Product Name]' => $productName !== '' ? $productName : 'N/A',
        '[Product Price]' => $productPrice !== '' ? $productPrice : 'N/A',
        '[Project URL]' => $projectUrl !== '' ? $projectUrl : 'N/A',
        '[Order Date]' => $orderDate !== '' ? $orderDate : date('Y-m-d'),
    ];
}

function normalize_contract_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = (string) (preg_replace("/\n{3,}/", "\n\n", $body) ?? $body);

    return trim($body);
}

function render_contract_template(string $body, array $data): string
{
    $knownKeys = array_merge(contract_placeholder_keys(), array_keys($data));
    $replacements = [];

    foreach ($knownKeys as $key) {
        $value = $data[$key] ?? '';
        $replacements['{{' . $key . '}}'] = $value === null || $value === '' ? 'N/A' : (string) $value;
    }

    $body = strtr($body, $replacements);
    $body = strtr($body, contract_legacy_placeholder_replacements($data));

    return normalize_contract_body($body);
}

function contract_display_body(array $contract): string
{
    $body = (string) ($contract['rendered_contract_snapshot'] ?? '');
    $body = strtr($body, contract_legacy_placeholder_replacements([
        'client_name' => $contract['customer_name'] ?? '',
        'client_email' => $contract['customer_email'] ?? '',
        'business_name' => $contract['business_name'] ?? '',
        'order_id' => $contract['order_number'] ?? '',
        'product_name' => $contract['product_name_snapshot'] ?? '',
        'product_price' => isset($contract['product_price_snapshot']) ? money_format_dd($contract['product_price_snapshot']) : '',
        'project_url' => $contract['website_url'] ?? '',
        'order_date' => isset($contract['created_at']) ? substr((string) $contract['created_at'], 0, 10) : '',
    ]));

    return normalize_contract_body($body);
}

function contract_placeholder_keys(): array
{
    return [
        'client_name', 'client_email', 'business_name', 'order_id', 'product_name', 'product_price',
        'service_type', 'project_url', 'order_date', 'designer_name', 'site_name', 'shopify_store_url',
        'shopify_collaborator_code', 'top_bar_text', 'scrolling_banner_text', 'featured_collections',
        'featured_products', 'new_products_collection', 'trending_products_collection',
        'collection_cover_names', 'reviews_app', 'intake_summary',
    ];
}

function build_intake_summary(array $answers): string
{
    $labels = [
        'shopify_store_url' => 'Current Shopify store URL',
        'shopify_store_name' => 'Shopify store/business name',
        'main_goal' => 'Main goal',
        'brand_colors' => 'Brand colors',
        'asset_link' => 'Logo/branding asset link',
        'featured_products' => 'Products or collections to feature',
        'requested_sections' => 'Pages/sections to focus on',
        'inspiration_links' => 'Inspiration links',
        'launch_timing' => 'Deadline or launch timing',
        'extra_notes' => 'Extra notes',
    ];
    $lines = [];
    foreach ($labels as $key => $label) {
        $value = trim((string) ($answers[$key] ?? ''));
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }
    return implode("\n", $lines);
}

function signed_contract_hash(array $contract, string $legalName, string $typedSignature, string $signedAt): string
{
    return hash('sha256', implode('|', [
        (string) ($contract['rendered_contract_snapshot'] ?? ''),
        $legalName,
        $typedSignature,
        $signedAt,
        (string) ($contract['order_id'] ?? ''),
    ]));
}

function notify_admin_order_created(array $order, string $signingUrl): void
{
    $to = getenv('ADMIN_ORDER_EMAIL') ?: getenv('ORDER_NOTIFY_EMAIL') ?: '';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $subject = 'New Diesel Designs order ' . ($order['order_number'] ?? '');
    $message = "A new order was created.\n\nOrder: " . ($order['order_number'] ?? '')
        . "\nCustomer: " . ($order['customer_name'] ?? '') . ' <' . ($order['customer_email'] ?? '') . '>'
        . "\nService: " . ($order['product_name'] ?? '')
        . "\nSigning link: " . $signingUrl . "\n";
    @mail($to, $subject, $message, 'From: no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}

function order_number(int $id): string
{
    return 'DD-' . date('Ymd') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
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
