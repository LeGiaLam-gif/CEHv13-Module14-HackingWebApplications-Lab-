<div class="page-actions"><a class="btn primary" href="/inventory/create">+ Tạo phiếu nhập / xuất</a></div>
<div class="panel">
    <h2>Lịch sử nhập xuất kho</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Thời gian</th><th>Sản phẩm</th><th>Loại</th><th>Số lượng</th><th>Tồn kho</th><th>Ghi chú / tham chiếu</th><th>Người thực hiện</th></tr>
            </thead>
            <tbody>
                <?php if (!$movements): ?>
                    <tr><td class="empty" colspan="7">Chưa có giao dịch kho nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($movements as $movement): ?>
                        <tr>
                            <td><?= e($movement["created_at"]) ?></td>
                            <td><strong><?= e($movement["product_name"]) ?></strong><small>SKU: <?= e($movement["product_sku"] ?: "N/A") ?></small></td>
                            <td><span class="badge <?= $movement["movement_type"] === "in" ? "success" : "muted" ?>"><?= $movement["movement_type"] === "in" ? "Nhập kho" : "Xuất kho" ?></span></td>
                            <td><?= $movement["movement_type"] === "in" ? "+" : "-" ?><?= number_format((int) $movement["quantity"]) ?></td>
                            <td><?= number_format((int) $movement["stock_before"]) ?> → <?= number_format((int) $movement["stock_after"]) ?></td>
                            <td><?= e($movement["note"] ?: ($movement["reference_type"] === "order" ? "Đơn hàng DH-" . str_pad((string) $movement["reference_id"], 6, "0", STR_PAD_LEFT) : "—")) ?></td>
                            <td><?= e($movement["admin_name"] ?: "Hệ thống") ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="help">Hiển thị tối đa 200 giao dịch gần nhất.</p>
</div>
