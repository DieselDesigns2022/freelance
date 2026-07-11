<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$slug = trim($_GET['product'] ?? $_POST['product_slug'] ?? '');
$product = null;
$errors = [];
$success = null;
$spamSuccess = false;
$values = $_POST;

if ($slug !== '' && table_exists(db(), 'products')) {
    $stmt = db()->prepare(
        "SELECT p.*, ct.title AS contract_title, ct.version AS contract_version, ct.body AS contract_body, ct.status AS contract_template_status "
        . "FROM products p LEFT JOIN contract_templates ct ON p.contract_template_id = ct.id "
        . "WHERE p.slug = ? AND p.status = 'active'"
    );
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    verify_csrf();

    if (trim($_POST['website_url_confirm'] ?? '') !== '') {
        $spamSuccess = true;
        $values = [];
    } else {
        $isShopify = $product['service_type'] === 'shopify_makeover';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $businessName = trim($_POST['business_name'] ?? '');
        $url = trim($_POST['website_url'] ?? '');
        $intakeAnswers = [];

        if ($isShopify) {
            $intakeAnswers = [
                'shopify_store_url' => trim($_POST['shopify_store_url'] ?? ''),
                'shopify_store_name' => trim($_POST['shopify_store_name'] ?? ''),
                'main_goal' => trim($_POST['main_goal'] ?? ''),
                'brand_colors' => trim($_POST['brand_colors'] ?? ''),
                'asset_link' => trim($_POST['asset_link'] ?? ''),
                'featured_products' => trim($_POST['featured_products'] ?? ''),
                'requested_sections' => trim($_POST['requested_sections'] ?? ''),
                'inspiration_links' => trim($_POST['inspiration_links'] ?? ''),
                'launch_timing' => trim($_POST['launch_timing'] ?? ''),
                'extra_notes' => trim($_POST['extra_notes'] ?? ''),
            ];
            $url = $intakeAnswers['shopify_store_url'];
        }

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if ($businessName === '') {
            $errors[] = 'Business name is required.';
        }
        if ($url !== '' && !valid_url_or_blank($url)) {
            $errors[] = 'Website/platform URL must start with http:// or https://.';
        }
        if ($isShopify && $intakeAnswers['asset_link'] !== '' && !valid_url_or_blank($intakeAnswers['asset_link'])) {
            $errors[] = 'Logo/branding asset link must start with http:// or https://.';
        }

        if ($isShopify) {
            $requiredShopifyFields = [
                'main_goal' => 'Main goal',
                'brand_colors' => 'Brand colors',
                'featured_products' => 'Products or collections to feature',
                'requested_sections' => 'Pages/sections to focus on',
            ];

            foreach ($requiredShopifyFields as $key => $label) {
                if ($intakeAnswers[$key] === '') {
                    $errors[] = $label . ' is required.';
                }
            }
        }

        if (!$product['contract_template_id'] || $product['contract_template_status'] !== 'active') {
            $errors[] = 'This service is temporarily unavailable while its contract is assigned.';
        }

        if (!$errors) {
            $pdo = db();
            $token = create_contract_token();
            $orderNumber = '';

            try {
                $pdo->beginTransaction();

                $intakeSummary = $isShopify ? build_intake_summary($intakeAnswers) : trim($_POST['project_notes'] ?? '');
                $snapshot = [
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'service_type' => $product['service_type'],
                    'description' => $product['short_description'],
                    'contract_template_id' => $product['contract_template_id'],
                    'contract_title' => $product['contract_title'],
                    'contract_version' => $product['contract_version'],
                    'demo_url' => $product['demo_url'] ?? null,
                ];

                $pdo->prepare(
                    'INSERT INTO orders '
                    . '(product_id,customer_name,customer_email,business_name,phone,preferred_contact_method,'
                    . 'website_url,project_notes,product_name_snapshot,product_price_snapshot,service_type_snapshot,'
                    . 'product_snapshot_json,intake_answers_json,customer_ip,customer_user_agent,order_status,'
                    . 'payment_status,contract_status,created_at) '
                    . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
                )->execute([
                    (int) $product['id'],
                    $name,
                    $email,
                    $businessName,
                    trim($_POST['phone'] ?? '') ?: null,
                    trim($_POST['preferred_contact_method'] ?? '') ?: null,
                    $url ?: null,
                    $intakeSummary ?: null,
                    $product['name'],
                    $product['price'],
                    $product['service_type'],
                    json_encode($snapshot),
                    $isShopify ? json_encode($intakeAnswers) : null,
                    substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 100),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    'pending_contract',
                    'pending',
                    'pending',
                ]);

                $orderId = (int) $pdo->lastInsertId();
                $orderNumber = order_number($orderId);
                $pdo->prepare('UPDATE orders SET order_number = ? WHERE id = ?')->execute([$orderNumber, $orderId]);

                $contractBody = $product['contract_body'];
                $placeholderData = array_merge($intakeAnswers, [
                    'client_name' => $name,
                    'client_email' => $email,
                    'business_name' => $businessName,
                    'order_id' => $orderNumber,
                    'product_name' => $product['name'],
                    'product_price' => money_format_dd($product['price']),
                    'service_type' => service_type_label($product['service_type']),
                    'project_url' => $url,
                    'order_date' => date('Y-m-d'),
                    'designer_name' => 'Diesel Designs',
                    'site_name' => 'Diesel Designs',
                    'intake_summary' => $intakeSummary,
                ]);
                $rendered = render_contract_template($contractBody, $placeholderData);

                $pdo->prepare(
                    'INSERT INTO contract_instances '
                    . '(order_id,contract_template_id,contract_title_snapshot,contract_version_snapshot,'
                    . 'contract_body_snapshot,rendered_contract_snapshot,token_hash,status,created_at) '
                    . 'VALUES (?,?,?,?,?,?,?,?,NOW())'
                )->execute([
                    $orderId,
                    $product['contract_template_id'],
                    $product['contract_title'],
                    $product['contract_version'],
                    $contractBody,
                    $rendered,
                    token_hash($token),
                    'pending',
                ]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'We could not start this order. Please try again or contact Diesel Designs.';
            }

            if (!$errors) {
                $signingUrl = base_url_from_request() . '/sign-contract.php?token=' . $token;
                notify_admin_order_created([
                    'order_number' => $orderNumber,
                    'customer_name' => $name,
                    'customer_email' => $email,
                    'product_name' => $product['name'],
                ], $signingUrl);

                $success = ['order' => $orderNumber, 'token' => $token];
                $values = [];
            }
        }
    }
}

$pageTitle = 'Begin Purchase | Diesel Designs';
$metaDescription = 'Start a Diesel Designs service order and receive a secure contract signing link.';
include __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <h1>Purchase / Start Order</h1>
    <?php if ($product): ?>
        <p><?= e($product['name']) ?> · <?= e(money_format_dd($product['price'])) ?></p>
    <?php endif; ?>
</section>

<section class="section">
    <?php if (!$product): ?>
        <div class="empty">
            <h2>Service not available</h2>
            <p>Please choose an active service from the store.</p>
            <a class="btn" href="store.php">Back to Store</a>
        </div>
    <?php elseif (!$product['contract_template_id'] || $product['contract_template_status'] !== 'active'): ?>
        <div class="empty">
            <h2>Temporarily unavailable</h2>
            <p>This service needs a contract assigned before public purchase.</p>
        </div>
    <?php elseif ($spamSuccess): ?>
        <div class="empty success-message">
            <h2>Thanks, your request was received.</h2>
            <p>Diesel Designs will review your request and follow up if needed.</p>
        </div>
    <?php elseif ($success): ?>
        <div class="empty success-message">
            <h2>Order started</h2>
            <p>Your order <?= e($success['order']) ?> was created. Please sign the contract before work begins.</p>
            <p><a class="btn btn-accent" href="sign-contract.php?token=<?= e($success['token']) ?>">Sign Contract</a></p>
        </div>
    <?php else: ?>
        <?php foreach ($errors as $error): ?>
            <p class="error-text"><?= e($error) ?></p>
        <?php endforeach; ?>

        <form method="post" class="admin-form request-form">
            <?= csrf_field() ?>
            <input type="hidden" name="product_slug" value="<?= e($slug) ?>">
            <label class="honeypot" aria-hidden="true">Leave blank<input name="website_url_confirm" tabindex="-1"></label>

            <label>Name *
                <input name="name" maxlength="190" required value="<?= e($values['name'] ?? '') ?>">
            </label>

            <label>Email *
                <input type="email" name="email" maxlength="190" required value="<?= e($values['email'] ?? '') ?>">
            </label>

            <label>Business name *
                <input name="business_name" maxlength="190" required value="<?= e($values['business_name'] ?? '') ?>">
            </label>

            <label>Phone
                <input name="phone" maxlength="100" value="<?= e($values['phone'] ?? '') ?>">
            </label>

            <label>Preferred contact method
                <input name="preferred_contact_method" maxlength="50" value="<?= e($values['preferred_contact_method'] ?? '') ?>">
            </label>

            <?php if ($product['service_type'] === 'shopify_makeover'): ?>
                <p class="notice">Do not enter Shopify admin passwords here. If access is needed, Diesel Designs will request it separately.</p>

                <label>Current Shopify store URL
                    <input type="url" name="shopify_store_url" maxlength="500" value="<?= e($values['shopify_store_url'] ?? '') ?>">
                </label>

                <label>Shopify store/business name
                    <input name="shopify_store_name" maxlength="190" value="<?= e($values['shopify_store_name'] ?? '') ?>">
                </label>

                <label>Main goal for the revamp *
                    <textarea name="main_goal" required><?= e($values['main_goal'] ?? '') ?></textarea>
                </label>

                <label>Brand colors *
                    <textarea name="brand_colors" required><?= e($values['brand_colors'] ?? '') ?></textarea>
                </label>

                <label>Logo/branding asset link
                    <input type="url" name="asset_link" maxlength="500" value="<?= e($values['asset_link'] ?? '') ?>">
                </label>

                <label>Products or collections to feature *
                    <textarea name="featured_products" required><?= e($values['featured_products'] ?? '') ?></textarea>
                </label>

                <label>Pages/sections you want focused on *
                    <textarea name="requested_sections" required><?= e($values['requested_sections'] ?? '') ?></textarea>
                </label>

                <label>Inspiration links
                    <textarea name="inspiration_links"><?= e($values['inspiration_links'] ?? '') ?></textarea>
                </label>

                <label>Deadline or preferred launch timing
                    <textarea name="launch_timing"><?= e($values['launch_timing'] ?? '') ?></textarea>
                </label>

                <label>Extra notes
                    <textarea name="extra_notes"><?= e($values['extra_notes'] ?? '') ?></textarea>
                </label>
            <?php else: ?>
                <label>Website/platform URL
                    <input type="url" name="website_url" maxlength="500" value="<?= e($values['website_url'] ?? '') ?>">
                </label>

                <label>Project notes/details
                    <textarea name="project_notes"><?= e($values['project_notes'] ?? '') ?></textarea>
                </label>
            <?php endif; ?>

            <button class="btn btn-accent">Begin Purchase</button>
        </form>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
