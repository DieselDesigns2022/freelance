<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT ci.*, o.order_number, o.customer_name, o.customer_email, o.business_name, o.product_name_snapshot, o.product_price_snapshot '
    . 'FROM contract_instances ci JOIN orders o ON ci.order_id = o.id WHERE ci.id = ?'
);
$stmt->execute([$id]);
$contract = $stmt->fetch();

function admin_signed_contract_html(array $contract): string
{
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Signed Contract ' . e($contract['order_number']) . '</title></head><body>'
        . '<h1>Signed Contract Copy</h1>'
        . '<h2>' . e($contract['contract_title_snapshot']) . '</h2>'
        . '<p><strong>Diesel Designs</strong></p>'
        . '<p><strong>Order:</strong> ' . e($contract['order_number']) . '<br>'
        . '<strong>Customer:</strong> ' . e($contract['customer_name']) . ' (' . e($contract['customer_email']) . ')<br>'
        . '<strong>Business:</strong> ' . e($contract['business_name'] ?: 'N/A') . '<br>'
        . '<strong>Service:</strong> ' . e($contract['product_name_snapshot']) . ' — ' . e(money_format_dd($contract['product_price_snapshot'])) . '</p>'
        . '<hr><div>' . nl2br(e($contract['rendered_contract_snapshot'])) . '</div><hr>'
        . '<h2>Signature &amp; Audit Trail</h2>'
        . '<p><strong>Legal name:</strong> ' . e($contract['signer_legal_name']) . '<br>'
        . '<strong>Typed signature:</strong> ' . e($contract['typed_signature']) . '<br>'
        . '<strong>Signed at:</strong> ' . e($contract['signed_at']) . '<br>'
        . '<strong>IP:</strong> ' . e($contract['signer_ip']) . '<br>'
        . '<strong>User agent:</strong> ' . e($contract['signer_user_agent']) . '</p>'
        . '</body></html>';
}

if (($_GET['download'] ?? '') === '1' && $contract && $contract['status'] === 'signed') {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . safe_download_filename($contract['order_number']) . '"');
    echo admin_signed_contract_html($contract);
    exit;
}

$adminTitle = 'Signed Contract Copy';
include __DIR__ . '/includes/admin-header.php';
?>
<section class="admin-card">
    <?php if (!$contract): ?>
        <h1>Contract not found</h1>
    <?php elseif ($contract['status'] !== 'signed'): ?>
        <h1>Signature pending</h1>
        <p>This contract is not signed yet. Signed-contract downloads are available only after signing.</p>
    <?php else: ?>
        <h1>Signed Contract Copy</h1>
        <p>
            <button class="btn" onclick="window.print()">Print / Save as PDF</button>
            <a class="btn btn-ghost" href="contract-copy.php?id=<?= (int) $contract['id'] ?>&download=1">Download HTML Copy</a>
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
        <div><?= nl2br(e($contract['rendered_contract_snapshot'])) ?></div>
        <hr>
        <h2>Signature & Audit Trail</h2>
        <p>
            <strong>Legal name:</strong> <?= e($contract['signer_legal_name']) ?><br>
            <strong>Typed signature:</strong> <?= e($contract['typed_signature']) ?><br>
            <strong>Signed at:</strong> <?= e($contract['signed_at']) ?><br>
            <strong>IP:</strong> <?= e($contract['signer_ip']) ?><br>
            <strong>User agent:</strong> <?= e($contract['signer_user_agent']) ?>
        </p>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
