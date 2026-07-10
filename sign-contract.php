<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$noindex = true;
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$contract = null;
$errors = [];
$signed = false;

if ($token !== '' && table_exists(db(), 'contract_instances')) {
    $stmt = db()->prepare(
        'SELECT ci.*, o.order_number, o.customer_name, o.customer_email, o.product_name_snapshot, o.product_price_snapshot, o.service_type_snapshot '
        . 'FROM contract_instances ci JOIN orders o ON ci.order_id = o.id WHERE ci.token_hash = ?'
    );
    $stmt->execute([token_hash($token)]);
    $contract = $stmt->fetch();

    if ($contract && in_array($contract['status'], ['pending', 'sent'], true)) {
        db()->prepare("UPDATE contract_instances SET status = 'viewed', viewed_at = COALESCE(viewed_at, NOW()), updated_at = NOW() WHERE id = ?")
            ->execute([$contract['id']]);
        db()->prepare("UPDATE orders SET contract_status = 'viewed', updated_at = NOW() WHERE id = ? AND contract_status NOT IN ('viewed', 'signed', 'void')")
            ->execute([$contract['order_id']]);
        $contract['status'] = 'viewed';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $contract) {
    verify_csrf();

    $legalName = trim($_POST['signer_legal_name'] ?? '');
    $typedSignature = trim($_POST['typed_signature'] ?? '');

    if (!in_array($contract['status'], signable_contract_statuses(), true)) {
        $errors[] = 'This contract cannot be signed.';
    }
    if ($legalName === '') {
        $errors[] = 'Legal name is required.';
    }
    if ($typedSignature === '') {
        $errors[] = 'Typed signature is required.';
    }
    if (empty($_POST['agree_terms']) || empty($_POST['agree_esign'])) {
        $errors[] = 'Both confirmation checkboxes are required.';
    }

    if (!$errors) {
        $signStmt = db()->prepare(
            "UPDATE contract_instances SET status = 'signed', signed_at = NOW(), signer_legal_name = ?, typed_signature = ?, signer_ip = ?, signer_user_agent = ?, updated_at = NOW() WHERE id = ? AND status IN ('pending', 'sent', 'viewed')"
        );
        $signStmt->execute([
            $legalName,
            $typedSignature,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            $contract['id'],
        ]);

        if ($signStmt->rowCount() > 0) {
            db()->prepare("UPDATE orders SET contract_status = 'signed', order_status = 'contract_signed', updated_at = NOW() WHERE id = ?")
                ->execute([$contract['order_id']]);
            $signed = true;
            $contract['status'] = 'signed';
        } else {
            $refreshStmt = db()->prepare('SELECT status, signed_at FROM contract_instances WHERE id = ?');
            $refreshStmt->execute([$contract['id']]);
            $currentContract = $refreshStmt->fetch();
            if ($currentContract) {
                $contract['status'] = $currentContract['status'];
                $contract['signed_at'] = $currentContract['signed_at'];
            }
            $errors[] = 'This contract can no longer be signed.';
        }
    }
}

$pageTitle = 'Sign Contract | Diesel Designs';
$metaDescription = 'Secure Diesel Designs contract signing page.';
include __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><h1>Contract Signing</h1></section>
<section class="section">
    <?php if (!$contract): ?>
        <div class="empty">
            <h2>Contract link not found</h2>
            <p>Please check the secure link you were given.</p>
        </div>
    <?php elseif ($signed): ?>
        <div class="empty success-message">
            <h2>Contract signed</h2>
            <p>Thank you. Your signed contract has been stored on file.</p>
            <p><a class="btn" href="contract-copy.php?token=<?= e($token) ?>">View / Download Contract Copy</a></p>
        </div>
    <?php elseif ($contract['status'] === 'signed'): ?>
        <div class="empty">
            <h2>Already signed</h2>
            <p>This contract was signed on <?= e($contract['signed_at']) ?>.</p>
            <p><a class="btn" href="contract-copy.php?token=<?= e($token) ?>">View Contract Copy</a></p>
        </div>
    <?php elseif ($contract['status'] === 'void'): ?>
        <div class="empty">
            <h2>Contract unavailable</h2>
            <p>This contract is no longer available. Please contact Diesel Designs if you need a new contract link.</p>
        </div>
    <?php else: ?>
        <?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>
        <div class="admin-card">
            <h2><?= e($contract['contract_title_snapshot']) ?></h2>
            <p><strong>Order:</strong> <?= e($contract['order_number']) ?> · <strong>Service:</strong> <?= e($contract['product_name_snapshot']) ?> (<?= e(money_format_dd($contract['product_price_snapshot'])) ?>)</p>
            <p><strong>Customer:</strong> <?= e($contract['customer_name']) ?>, <?= e($contract['customer_email']) ?></p>
            <div class="contract-body"><?= nl2br(e($contract['rendered_contract_snapshot'])) ?></div>
        </div>

        <form method="post" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>Legal name *<input name="signer_legal_name" required></label>
            <label>Typed signature *<input name="typed_signature" required></label>
            <label class="check"><input type="checkbox" name="agree_terms" value="1" required> I have read and agree to this contract.</label>
            <label class="check"><input type="checkbox" name="agree_esign" value="1" required> I agree my typed signature is an electronic signature.</label>
            <button class="btn btn-accent">Sign Contract</button>
        </form>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
