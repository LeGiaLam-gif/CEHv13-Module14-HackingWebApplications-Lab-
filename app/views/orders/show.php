<div class="page-actions"><a class="btn secondary" href="/orders">← Danh sách đơn hàng</a></div>
<?php foreach ($errors ?? [] as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
<?php if (!empty($notice)): ?><div class="alert success"><?= e($notice) ?></div><?php endif; ?>
<div class="panel order-summary">
    <div>
        <span>Mã đơn hàng</span>
        <strong>DH-<?= e(str_pad((string) $order["id"], 6, "0", STR_PAD_LEFT)) ?></strong>
    </div>
    <div>
        <span>Trạng thái</span>
        <strong><span class="badge order-status-<?= e($order["status"]) ?>"><?= e(order_status_labels()[$order["status"]] ?? $order["status"]) ?></span></strong>
    </div>
    <div>
        <span>Ngày tạo</span>
        <strong><?= e($order["created_at"]) ?></strong>
    </div>
</div>

<?php if (!empty($statusOptions) || !empty($canCancel)): ?>
    <div class="panel order-status-actions">
        <h2>Cập nhật đơn hàng</h2>
        <?php if (!empty($statusOptions)): ?>
            <form method="POST" action="/orders/status" class="status-update-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $order["id"]) ?>">
                <div class="form-group">
                    <label for="orderStatus">Chuyển trạng thái</label>
                    <select id="orderStatus" name="status" required>
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn primary" type="submit">Cập nhật trạng thái</button>
            </form>
        <?php endif; ?>
        <?php if (!empty($canCancel)): ?>
            <form method="POST" action="/orders/status" onsubmit="return confirm('Hủy đơn hàng này và hoàn lại tồn kho?')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $order["id"]) ?>">
                <input type="hidden" name="status" value="cancelled">
                <button class="btn danger" type="submit">Hủy đơn hàng</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid-2">
    <div class="panel">
        <h2>Thông tin người nhận</h2>
        <p><strong><?= e($order["customer_name"]) ?></strong></p>
        <p><?= e($order["customer_phone"]) ?></p>
        <p><?= nl2br(e($order["shipping_address"])) ?></p>
    </div>
    <div class="panel order-total">
        <span>Tổng thanh toán</span>
        <strong><?= number_format((float) $order["total_amount"], 0, ',', '.') ?> đ</strong>
    </div>
</div>

<div class="panel">
    <h2>Sản phẩm</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Sản phẩm</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th></tr></thead>
            <tbody>
                <?php foreach ($order["items"] as $item): ?>
                    <tr>
                        <td><?= e($item["product_name"]) ?><?php if ($item["product_sku"]): ?><small><?= e($item["product_sku"]) ?></small><?php endif; ?></td>
                        <td><?= number_format((float) $item["unit_price"], 0, ',', '.') ?> đ</td>
                        <td><?= number_format((int) $item["quantity"]) ?></td>
                        <td><?= number_format((float) $item["line_total"], 0, ',', '.') ?> đ</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="3">Tổng cộng</th><th><?= number_format((float) $order["total_amount"], 0, ',', '.') ?> đ</th></tr></tfoot>
        </table>
    </div>
</div>
