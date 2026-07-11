<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$generatedSigningUrl = null;
$errors = [];

function load_order(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT o.*, ci.id AS contract_id, ci.contract_title_snapshot, ci.contract_version_snapshot, ci.status AS ci_status, ci.sent_at, ci.viewed_at, ci.signed_at, ci.signer_legal_name, ci.typed_signature, ci.signer_ip, ci.signer_user_agent, ci.terms_agreed_at, ci.esign_agreed_at, ci.signed_contract_hash '
        . 'FROM orders o LEFT JOIN contract_instances ci ON ci.order_id = o.id WHERE o.id = ?'
    );
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    return $order ?: null;
}

$order = load_order($id);
if (!$order) {
    http_response_code(404);
    exit('Not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'update';

    if ($action === 'regen') {
        if (!$order['contract_id']) {
            $errors[] = 'This order does not have a contract instance.';
        } elseif (!in_array($order['ci_status'], contract_admin_mutable_statuses(), true)) {
            $errors[] = 'Replacement signing links can only be generated for pending, sent, or viewed contracts.';
        } else {
            $token = create_contract_token();
            $regenStmt = db()->prepare("UPDATE contract_instances SET token_hash = ?, updated_at = NOW() WHERE id = ? AND status IN ('pending', 'sent', 'viewed')");
            $regenStmt->execute([token_hash($token), $order['contract_id']]);

            if ($regenStmt->rowCount() > 0) {
                $generatedSigningUrl = base_url_from_request() . '/sign-contract.php?token=' . $token;
                flash('success', 'Replacement signing link generated. Copy it before leaving this page.');
                $order = load_order($id) ?: $order;
            } else {
                $errors[] = 'Replacement signing link could not be generated because this contract is no longer pending, sent, or viewed.';
                $order = load_order($id) ?: $order;
            }
        }
    } elseif ($action === 'sent') {
        if (!$order['contract_id']) {
            $errors[] = 'This order does not have a contract instance.';
        } else {
            $sentStmt = db()->prepare("UPDATE contract_instances SET status = 'sent', sent_at = COALESCE(sent_at, NOW()), updated_at = NOW() WHERE id = ? AND status = 'pending'");
            $sentStmt->execute([$order['contract_id']]);

            if ($sentStmt->rowCount() > 0) {
                db()->prepare("UPDATE orders SET contract_status = 'sent', order_status = CASE WHEN order_status = 'pending_contract' THEN 'contract_sent' ELSE order_status END, updated_at = NOW() WHERE id = ? AND contract_status = 'pending'")
                    ->execute([$id]);
                flash('success', 'Contract marked as sent.');
                redirect('order-view.php?id=' . $id);
            }

            $errors[] = 'Contract could not be marked sent because it is no longer pending.';
            $order = load_order($id) ?: $order;
        }
    } elseif ($action === 'void') {
        if (!$order['contract_id']) {
            $errors[] = 'This order does not have a contract instance.';
        } elseif (!in_array($order['ci_status'], contract_admin_mutable_statuses(), true)) {
            $errors[] = 'Only pending, sent, or viewed contracts can be voided from this control.';
        } else {
            $voidStmt = db()->prepare("UPDATE contract_instances SET status = 'void', updated_at = NOW() WHERE id = ? AND status IN ('pending', 'sent', 'viewed')");
            $voidStmt->execute([$order['contract_id']]);

            if ($voidStmt->rowCount() > 0) {
                db()->prepare("UPDATE orders SET contract_status = 'void', updated_at = NOW() WHERE id = ? AND contract_status IN ('pending', 'sent', 'viewed')")
                    ->execute([$id]);
                flash('success', 'Contract voided.');
                redirect('order-view.php?id=' . $id);
            }

            $errors[] = 'This contract can no longer be voided.';
        }
    } else {
        $orderStatus = $_POST['order_status'] ?? '';
        $paymentStatus = $_POST['payment_status'] ?? '';

        if (!in_array($orderStatus, allowed_order_statuses(), true)) {
            $errors[] = 'Choose a valid order status.';
        }
        if (!in_array($paymentStatus, allowed_payment_statuses(), true)) {
            $errors[] = 'Choose a valid payment status.';
        }

        if (!$errors) {
            db()->prepare('UPDATE orders SET order_status = ?, payment_status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$orderStatus, $paymentStatus, trim($_POST['admin_notes'] ?? '') ?: null, $id]);
            flash('success', 'Order updated.');
            redirect('order-view.php?id=' . $id);
        }
    }
}

$adminTitle = 'Order ' . $order['order_number'];
include __DIR__ . '/includes/admin-header.php';
?>
<h1>Order <?= e($order['order_number']) ?></h1>

<?php foreach ($errors as $error): ?><p class="error-text"><?= e($error) ?></p><?php endforeach; ?>

<?php if ($generatedSigningUrl): ?>
    <section class="admin-card">
        <h2>Replacement Signing Link</h2>
        <p>Copy this link now. For security, the raw token is not stored and cannot be displayed again after you leave or refresh this page.</p>
        <label>Signing URL
            <textarea readonly rows="3" onclick="this.select()"><?= e($generatedSigningUrl) ?></textarea>
        </label>
    </section>
<?php endif; ?>

<section class="admin-card">
    <h2>Customer</h2>
    <p>
        <?= e($order['customer_name']) ?> · <?= e($order['customer_email']) ?><br>
        <?= e($order['business_name'] ?? '') ?> <?= e($order['phone'] ?? '') ?><br>
        <?php if ($order['website_url']): ?><a href="<?= e($order['website_url']) ?>" target="_blank" rel="noopener"><?= e($order['website_url']) ?></a><?php endif; ?>
    </p>

    <h2>Product Snapshot</h2>
    <p><?= e($order['product_name_snapshot']) ?> — <?= e(money_format_dd($order['product_price_snapshot'])) ?> (<?= e(service_type_label($order['service_type_snapshot'])) ?>)</p>
    <p><?= nl2br(e($order['project_notes'] ?? '')) ?></p>

    <?php $intakeAnswers = $order['intake_answers_json'] ? json_decode($order['intake_answers_json'], true) : []; ?>
    <?php if (is_array($intakeAnswers) && $intakeAnswers): ?>
        <h2>Shopify Revamp Intake</h2>
        <dl>
            <?php foreach ($intakeAnswers as $key => $value): if (trim((string) $value) === '') continue; ?>
                <dt><?= e(ucwords(str_replace('_', ' ', $key))) ?></dt>
                <dd><?= nl2br(e((string) $value)) ?></dd>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>

    <h2>Contract</h2>
    <p><?= e($order['contract_title_snapshot'] ?? 'None') ?> v<?= e($order['contract_version_snapshot'] ?? '') ?> · Status: <?= e($order['ci_status'] ?? '') ?></p>
    <p>The original raw signing link cannot be displayed after creation because only a secure token hash is stored. Generate a replacement link before signing if you need to send a new link.</p>

    <?php if ($order['contract_id']): ?>
        <?php if ($order['ci_status'] === 'pending'): ?>
            <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="sent">
                <button class="btn">Mark Contract Sent</button>
            </form>
        <?php endif; ?>

        <?php if (in_array($order['ci_status'], contract_admin_mutable_statuses(), true)): ?>
            <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="regen">
                <button class="btn btn-ghost">Generate Replacement Signing Link</button>
            </form>
            <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="void">
                <button class="btn btn-ghost">Void Contract</button>
            </form>
        <?php endif; ?>



        <?php if ($order['ci_status'] === 'void'): ?>
            <p class="error-text">This contract has been voided. It cannot be signed and replacement signing links cannot be generated from this contract.</p>
        <?php endif; ?>

        <?php if ($order['ci_status'] === 'signed'): ?>
            <p><a class="btn" href="contract-copy.php?id=<?= (int) $order['contract_id'] ?>">View / Download Signed Contract</a></p>
            <p>
                <strong>Signed:</strong> <?= e($order['signed_at']) ?><br>
                <strong>Legal name:</strong> <?= e($order['signer_legal_name']) ?><br>
                <strong>Typed signature:</strong> <?= e($order['typed_signature']) ?><br>
                <strong>IP/User agent:</strong> <?= e($order['signer_ip']) ?> / <?= e($order['signer_user_agent']) ?><br>
                <strong>Terms agreed:</strong> <?= e($order['terms_agreed_at']) ?><br>
                <strong>E-sign consent:</strong> <?= e($order['esign_agreed_at']) ?><br>
                <strong>Signed contract hash:</strong> <?= e($order['signed_contract_hash']) ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="admin-card">
    <h2>Update Order / Payment Status</h2>
    <p>Contract status is controlled by signing, viewing, sending, replacement-token, and void actions. Signed status can only come from the customer signing flow.</p>
    <form method="post" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">

        <label>Order status
            <select name="order_status">
                <?php foreach (allowed_order_statuses() as $status): ?>
                    <option value="<?= e($status) ?>" <?= $order['order_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Payment status
            <select name="payment_status">
                <?php foreach (allowed_payment_statuses() as $status): ?>
                    <option value="<?= e($status) ?>" <?= $order['payment_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Internal admin notes
            <textarea name="admin_notes"><?= e($order['admin_notes'] ?? '') ?></textarea>
        </label>

        <button class="btn btn-accent">Save</button>
    </form>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
