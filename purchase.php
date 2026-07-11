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
        $businessName = null;
        $url = trim($_POST['website_url'] ?? '');
        $intakeAnswers = [];

        if ($isShopify) {
            $shopifyStoreUrl = trim($_POST['shopify_store_url'] ?? '');

            if ($shopifyStoreUrl !== '' && !preg_match('#^https?://#i', $shopifyStoreUrl)) {
                $shopifyStoreUrl = 'https://' . $shopifyStoreUrl;
            }

            $intakeAnswers = [
                'shopify_store_url' => $shopifyStoreUrl,
                'shopify_collaborator_code' => trim($_POST['shopify_collaborator_code'] ?? ''),
                'top_bar_text' => trim($_POST['top_bar_text'] ?? ''),
                'scrolling_banner_text' => trim($_POST['scrolling_banner_text'] ?? ''),
                'featured_collections' => trim($_POST['featured_collections'] ?? ''),
                'featured_products' => trim($_POST['featured_products'] ?? ''),
                'new_products_collection' => trim($_POST['new_products_collection'] ?? ''),
                'trending_products_collection' => trim($_POST['trending_products_collection'] ?? ''),
                'reviews_app' => trim($_POST['reviews_app'] ?? ''),
            ];

            $url = $shopifyStoreUrl;
        }

        if ($name === '') {
            $errors[] = 'First and last name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if ($url !== '' && !valid_url_or_blank($url)) {
            $errors[] = 'Website/platform URL must be a valid link, like username.myshopify.com.';
        }

        if ($isShopify) {
            if ($intakeAnswers['shopify_store_url'] === '') {
                $errors[] = 'Shopify URL is required.';
            }

            if (!preg_match('/^\d{4}$/', $intakeAnswers['shopify_collaborator_code'])) {
                $errors[] = 'Shopify collaborator request code must be the 4 digit code from Shopify.';
            }

            if ($intakeAnswers['scrolling_banner_text'] === '') {
                $errors[] = 'Scrolling banner text is required.';
            }

            $bannerParts = array_values(array_filter(array_map('trim', explode('-', $intakeAnswers['scrolling_banner_text']))));
            if (count($bannerParts) > 2) {
                $errors[] = 'Scrolling banner text can have a maximum of 2 phrases separated by a dash.';
            }

            $logoErrors = $_FILES['logo_files']['error'] ?? [];
            $hasLogoUpload = false;

            if (is_array($logoErrors)) {
                foreach ($logoErrors as $logoError) {
                    if ((int) $logoError !== UPLOAD_ERR_NO_FILE) {
                        $hasLogoUpload = true;
                        break;
                    }
                }
            }

            if (!$hasLogoUpload) {
                $errors[] = 'Please upload at least one logo file.';
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

                if ($isShopify && table_exists($pdo, 'order_uploads')) {
                    $uploadGroups = [
                        'logo_files' => 'logo',
                    ];

                    foreach ($uploadGroups as $fieldName => $uploadType) {
                        foreach (($_FILES[$fieldName]['name'] ?? []) as $idx => $unused) {
                            $file = [
                                'name' => $_FILES[$fieldName]['name'][$idx] ?? '',
                                'type' => $_FILES[$fieldName]['type'][$idx] ?? '',
                                'tmp_name' => $_FILES[$fieldName]['tmp_name'][$idx] ?? '',
                                'error' => $_FILES[$fieldName]['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                                'size' => $_FILES[$fieldName]['size'][$idx] ?? 0,
                            ];

                            [$asset, $uploadError] = upload_order_asset($file);

                            if ($uploadError) {
                                throw new RuntimeException($uploadError);
                            }

                            if ($asset) {
                                $pdo->prepare(
                                    'INSERT INTO order_uploads '
                                    . '(order_id,file_path,original_name,mime_type,file_size,upload_type,created_at) '
                                    . 'VALUES (?,?,?,?,?,?,NOW())'
                                )->execute([
                                    $orderId,
                                    $asset['path'],
                                    $asset['original_name'],
                                    $asset['mime_type'],
                                    $asset['file_size'],
                                    $uploadType,
                                ]);
                            }
                        }
                    }
                }

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
                $errors[] = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'We could not start this order. Please try again or contact Diesel Designs.';
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

        <form method="post" enctype="multipart/form-data" class="admin-form request-form">
            <?= csrf_field() ?>
            <input type="hidden" name="product_slug" value="<?= e($slug) ?>">
            <label class="honeypot" aria-hidden="true">Leave blank<input name="website_url_confirm" tabindex="-1"></label>

            <label>First &amp; Last name *
                <input name="name" maxlength="190" required value="<?= e($values['name'] ?? '') ?>">
            </label>

            <label>What is your email address? *
                <input type="email" name="email" maxlength="190" required value="<?= e($values['email'] ?? '') ?>">
                <small>We may need to contact you for questions or follow-ups.</small>
            </label>

            <?php if ($product['service_type'] === 'shopify_makeover'): ?>
                <p class="notice">Do not enter Shopify admin passwords here. Diesel Designs only needs the collaborator request code when you already have a Shopify website.</p>

                <label>What is your Shopify URL? *
                    <input name="shopify_store_url" maxlength="500" required placeholder="username.myshopify.com" value="<?= e($values['shopify_store_url'] ?? '') ?>">
                    <small>Your Shopify link is usually similar to username.myshopify.com.</small>
                </label>

                <label>Shopify Collaborator Request Code *
                    <input name="shopify_collaborator_code" maxlength="4" pattern="[0-9]{4}" required value="<?= e($values['shopify_collaborator_code'] ?? '') ?>">
                    <small>Go to Settings → Users → Security, then scroll down to find the 4 digit code. If you do not have a Shopify website yet, skip this question on the custom build form.</small>
                </label>

                <label>Upload your logo(s) *
                    <input type="file" name="logo_files[]" accept="image/jpeg,image/png,image/webp,application/pdf" multiple required>
                    <small>PNG preferred. Please upload high-quality logo files with a transparent background when possible. Diesel Designs will not edit logos unless discussed and paid for before the project.</small>
                </label>

                <label>What text would you like in the thin bar at the very top of the website?
                    <input name="top_bar_text" maxlength="190" value="<?= e($values['top_bar_text'] ?? '') ?>">
                    <small>Example: Welcome to the store, free shipping, or a short announcement.</small>
                </label>

                <label>Scrolling banner text *
                    <input name="scrolling_banner_text" maxlength="190" required value="<?= e($values['scrolling_banner_text'] ?? '') ?>">
                    <small>Use “-” between phrases. Example: Welcome - Free shipping. Maximum of 2 phrases.</small>
                </label>

                <label>Do you have any collections you'd like featured on the home page? If yes, what are they?
                    <textarea name="featured_collections"><?= e($values['featured_collections'] ?? '') ?></textarea>
                </label>

                <label>Do you have any specific products you want featured on the home page? If so, what are they?
                    <textarea name="featured_products"><?= e($values['featured_products'] ?? '') ?></textarea>
                </label>

                <label>What is the name of your collection for NEW products?
                    <input name="new_products_collection" maxlength="190" value="<?= e($values['new_products_collection'] ?? '') ?>">
                    <small>If you do not have a collection for this yet, please create one first and then put the name of the collection in this field.</small>
                </label>

                <label>What is the name of your collection for TRENDING / POPULAR / HOT products?
                    <input name="trending_products_collection" maxlength="190" value="<?= e($values['trending_products_collection'] ?? '') ?>">
                    <small>If you do not have a collection for this yet, please create one first and then put the name of the collection in this field.</small>
                </label>

                <label>Do you have a reviews app installed and want reviews displayed on your homepage?
                    <textarea name="reviews_app"><?= e($values['reviews_app'] ?? '') ?></textarea>
                    <small>Include the app name if yes. Note: Diesel Designs does not install apps as part of this order.</small>
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
