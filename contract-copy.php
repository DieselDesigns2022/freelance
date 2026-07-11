<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$noindex = true;
$token = trim($_GET['token'] ?? '');
$contract = null;

if ($token !== '' && table_exists(db(), 'contract_instances')) {
    $stmt = db()->prepare(
        'SELECT ci.*, o.order_number, o.customer_name, o.customer_email, o.business_name, o.website_url, o.created_at, o.product_name_snapshot, o.product_price_snapshot '
        . 'FROM contract_instances ci JOIN orders o ON ci.order_id = o.id WHERE ci.token_hash = ?'
    );
    $stmt->execute([token_hash($token)]);
    $contract = $stmt->fetch();

    if ($contract) {
        $contract['rendered_contract_snapshot'] = contract_display_body($contract);
    }
}

function signed_contract_html(array $contract): string
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Signed Contract ' . e($contract['order_number']) . '</title></head><body>'
        . '<h1>Signed Contract Copy</h1>'
        . '<h2>' . e($contract['contract_title_snapshot']) . '</h2>'
        . '<p><strong>Diesel Designs</strong></p>'
        . '<p><strong>Order:</strong> ' . e($contract['order_number']) . '<br>'
        . '<strong>Customer:</strong> ' . e($contract['customer_name']) . ' (' . e($contract['customer_email']) . ')<br>'
        . '<strong>Business:</strong> ' . e($contract['business_name'] ?: 'N/A') . '<br>'
        . '<strong>Service:</strong> ' . e($contract['product_name_snapshot']) . ' — ' . e(money_format_dd($contract['product_price_snapshot'])) . '</p>'
        . '<hr><div style="white-space: pre-line; line-height: 1.65;">' . e($contract['rendered_contract_snapshot']) . '</div><hr>'
        . '<h2>Signature &amp; Audit Trail</h2>'
        . '<p><strong>Legal name:</strong> ' . e($contract['signer_legal_name']) . '<br>'
        . '<strong>Typed signature:</strong> ' . e($contract['typed_signature']) . '<br>'
        . '<strong>Signed at:</strong> ' . e($contract['signed_at']) . '<br>'
        . '<strong>IP:</strong> ' . e($contract['signer_ip']) . '<br>'
        . '<strong>User agent:</strong> ' . e($contract['signer_user_agent']) . '<br>'
        . '<strong>Terms agreed at:</strong> ' . e($contract['terms_agreed_at']) . '<br>'
        . '<strong>E-sign consent at:</strong> ' . e($contract['esign_agreed_at']) . '<br>'
        . '<strong>Signed contract hash:</strong> ' . e($contract['signed_contract_hash']) . '</p>'
        . '</body></html>';
}

if (($_GET['download'] ?? '') === '1' && $contract && $contract['status'] === 'signed') {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . safe_download_filename($contract['order_number']) . '"');
    echo signed_contract_html($contract);
    exit;
}

$pageTitle = 'Contract Copy | Diesel Designs';
$metaDescription = 'Secure Diesel Designs contract copy.';
include __DIR__ . '/includes/header.php';
?>
<section class="section contract-copy">
    <?php if (!$contract): ?>
        <div class="empty"><h1>Contract not found</h1></div>
    <?php elseif (in_array($contract['status'], signable_contract_statuses(), true)): ?>
        <div class="empty">
            <h1>Signature pending</h1>
            <p>This contract is not signed yet. Signed-contract downloads are available only after signing.</p>
            <p><a class="btn" href="sign-contract.php?token=<?= e($token) ?>">Sign Contract</a></p>
        </div>
    <?php elseif ($contract['status'] === 'void'): ?>
        <div class="empty">
            <h1>Contract unavailable</h1>
            <p>This contract is no longer available. Please contact Diesel Designs if you need a new contract link.</p>
        </div>
    <?php elseif ($contract['status'] === 'signed'): ?>
        <h1>Signed Contract Copy</h1>
        <p>
            <button class="btn" onclick="window.print()">Print / Save as PDF</button>
            <a class="btn btn-ghost" href="contract-copy.php?token=<?= e($token) ?>&download=1">Download HTML Copy</a>
        </p>
        <h2><?= e($contract['contract_title_snapshot']) ?></h2>
        <p><strong>Diesel Designs</strong></p>
        <p>
            <strong>Order:</strong> <?= e($contract['order_number']) ?><br>
            <strong>Customer:</strong> <?= e($contract['customer_name']) ?> (<?= e($contract['customer_email']) ?>)<br>
            <strong>Business:</strong> <?= e($contract['business_name'] ?: 'N/A') ?><br>
            <strong>Service:</strong> <?= e($contract['product_name_snapshot']) ?> — <?= e(money_format_dd($contract['product_price_snapshot'])) ?>
        </p>
        <hr>
        <div class="contract-body"><?= e($contract['rendered_contract_snapshot']) ?></div>
        <hr>
        <h2>Signature & Audit Trail</h2>
        <p>
            <strong>Legal name:</strong> <?= e($contract['signer_legal_name']) ?><br>
            <strong>Typed signature:</strong> <?= e($contract['typed_signature']) ?><br>
            <strong>Signed at:</strong> <?= e($contract['signed_at']) ?><br>
            <strong>IP:</strong> <?= e($contract['signer_ip']) ?><br>
            <strong>User agent:</strong> <?= e($contract['signer_user_agent']) ?><br>
            <strong>Terms agreed at:</strong> <?= e($contract['terms_agreed_at']) ?><br>
            <strong>E-sign consent at:</strong> <?= e($contract['esign_agreed_at']) ?><br>
            <strong>Signed contract hash:</strong> <?= e($contract['signed_contract_hash']) ?>
        </p>
    <?php else: ?>
        <div class="empty">
            <h1>Contract unavailable</h1>
            <p>This contract cannot be viewed right now. Please contact Diesel Designs if you need help.</p>
        </div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
