<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/questionnaires.php';

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

$orderUploads = [];
if (table_exists(db(), 'order_uploads')) {
    $uploadStmt = db()->prepare('SELECT * FROM order_uploads WHERE order_id = ? ORDER BY upload_type, id');
    $uploadStmt->execute([$id]);
    $orderUploads = $uploadStmt->fetchAll();
}
$questionnaireSnapshot = null;
$questionnaireAnswers = [];
if (table_exists(db(), 'order_questionnaire_snapshots') && table_exists(db(), 'order_questionnaire_answers')) {
    $snapshotStmt = db()->prepare('SELECT * FROM order_questionnaire_snapshots WHERE order_id = ?');
    $snapshotStmt->execute([$id]);
    $questionnaireSnapshot = $snapshotStmt->fetch() ?: null;
    if ($questionnaireSnapshot) {
        $answerStmt = db()->prepare('SELECT * FROM order_questionnaire_answers WHERE order_id = ? AND questionnaire_snapshot_id = ? ORDER BY sort_order,id');
        $answerStmt->execute([$id, (int) $questionnaireSnapshot['id']]);
        foreach ($answerStmt->fetchAll() as $answer) $questionnaireAnswers[$answer['field_key']] = $answer;
    }
}
$generalOrderUploads = array_values(array_filter(
    $orderUploads,
    static fn (array $upload): bool => !$questionnaireSnapshot || empty($upload['questionnaire_field_key'])
));

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

    <?php if ($questionnaireSnapshot): ?>
        <?php
        $structure = json_decode($questionnaireSnapshot['questionnaire_snapshot_json'], true);
        $snapshotValid = is_array($structure) && is_array($structure['fields'] ?? null);
        $snapshotFields = $snapshotValid ? $structure['fields'] : [];
        ?>
        <h2>Questionnaire Responses: <?= e($questionnaireSnapshot['questionnaire_title']) ?></h2>
        <?php if (!$snapshotValid): ?>
            <p class="error-text">The saved questionnaire snapshot is malformed and cannot be displayed safely. The stored record has not been changed.</p>
        <?php endif; ?>
        <?php foreach ($snapshotFields as $field): ?>
            <?php if (!is_array($field)): ?>
                <p class="error-text">A saved questionnaire field is malformed and was skipped.</p>
                <?php continue; ?>
            <?php endif; ?>
            <?php $type = $field['field_type'] ?? ''; ?>
            <?php if ($type === 'section_heading'): ?><h3><?= e($field['label'] ?? '') ?></h3>
            <?php elseif ($type === 'information'): ?><p class="notice"><?= nl2br(e($field['label'] ?? '')) ?></p>
            <?php elseif ($type === 'addon'): ?><dl><dt><?= e($field['label'] ?? '') ?></dt><dd>See Manual Invoice Add-Ons below.</dd></dl>
            <?php else: $answer = $questionnaireAnswers[$field['field_key'] ?? ''] ?? null; $value = $answer && $answer['answer_json'] ? json_decode($answer['answer_json'], true) : ($answer['answer_text'] ?? ''); ?>
                <dl><dt><?= e($field['label'] ?? '') ?></dt><dd>
                <?php if (is_array($value)): ?><?= e($value ? implode(', ', $value) : 'Not answered') ?>
                <?php elseif ($type === 'url' && $value && valid_url_or_blank((string) $value)): ?><a href="<?= e((string) $value) ?>" target="_blank" rel="noopener"><?= e((string) $value) ?></a>
                <?php elseif ($type === 'yes_no'): ?><?= e($value === 'yes' ? 'Yes' : ($value === 'no' ? 'No' : 'Not answered')) ?>
                <?php else: ?><?= $value !== '' ? nl2br(e((string) $value)) : 'Not answered' ?><?php endif; ?>
                <?php foreach ($orderUploads as $upload): if (($upload['questionnaire_field_key'] ?? null) !== ($field['field_key'] ?? null)) continue; ?><br><a href="order-upload.php?id=<?= (int) $upload['id'] ?>"><?= e($upload['original_name']) ?></a><?php endforeach; ?>
                </dd></dl>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php
        $savedAddons = [];
        foreach ($questionnaireAnswers as $savedAnswer) {
            if (($savedAnswer['field_type'] ?? '') !== 'addon') continue;
            $addon = json_decode((string) ($savedAnswer['addon_snapshot_json'] ?? $savedAnswer['answer_json'] ?? ''), true);
            if (is_array($addon) && (int) ($addon['total_cents'] ?? 0) > 0) $savedAddons[] = $addon;
        }
        $savedAddonTotal = questionnaire_addon_total($savedAddons);
        ?>
        <h2>Manual Invoice Add-Ons</h2>
        <?php if (!$savedAddons): ?><p>No paid add-ons selected.</p><?php else: ?>
            <?php foreach ($savedAddons as $addon): ?>
                <dl>
                    <dt><?= e((string) ($addon['upgrade_name'] ?? 'Add-on')) ?></dt>
                    <dd>
                        <?= e(match ($addon['pricing_method'] ?? '') { 'flat_fee'=>'Flat fee', 'per_additional_item'=>'Per additional item', 'quantity_priced'=>'Quantity priced', default=>'Saved pricing' }) ?><br>
                        Selected quantity: <?= (int) ($addon['selected_quantity'] ?? 0) ?><br>
                        <?php if (($addon['pricing_method'] ?? '') === 'per_additional_item'): ?>Included quantity: <?= (int) ($addon['included_quantity'] ?? 0) ?><br><?php endif; ?>
                        Billable quantity: <?= (int) ($addon['billable_quantity'] ?? 0) ?><br>
                        Unit price: <?= e(money_format_dd((int) ($addon['unit_price_cents'] ?? 0))) ?><br>
                        Line total: <strong><?= e(money_format_dd((int) ($addon['total_cents'] ?? 0))) ?></strong>
                    </dd>
                </dl>
            <?php endforeach; ?>
            <p><strong>Total Additional Amount to Invoice: <?= e(money_format_dd($savedAddonTotal)) ?></strong></p>
        <?php endif; ?>
    <?php else: ?>
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
    <?php endif; ?>

    <?php if ($generalOrderUploads): ?>
        <h2>Uploaded Files</h2>
        <div class="order-upload-list">
            <?php foreach ($generalOrderUploads as $upload): ?>
                <article class="order-upload-card">
                    <strong><?= e(ucfirst((string) $upload['upload_type'])) ?></strong><br>
                    <a href="order-upload.php?id=<?= (int) $upload['id'] ?>" target="_blank" rel="noopener">
                        <?= e($upload['original_name'] ?: basename((string) $upload['file_path'])) ?>
                    </a>
                    <br>
                    <small>
                        <?= e($upload['mime_type'] ?? '') ?>
                        <?php if ($upload['file_size']): ?>
                            · <?= e(number_format(((int) $upload['file_size']) / 1024, 1)) ?> KB
                        <?php endif; ?>
                    </small>
                </article>
            <?php endforeach; ?>
        </div>
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
