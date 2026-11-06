<div class="page-actions"><a class="btn primary" href="/orders/create">+ Thêm đơn hàng</a></div>
<div class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Mã đơn</th><th>Người nhận</th><th>Số điện thoại</th><th>Sản phẩm</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr><td class="empty" colspan="8">Chưa có đơn hàng nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>DH-<?= e(str_pad((string) $order["id"], 6, "0", STR_PAD_LEFT)) ?></td>
                            <td><?= e($order["customer_name"]) ?></td>
                            <td><?= e($order["customer_phone"]) ?></td>
                            <td><?= number_format((int) $order["item_count"]) ?></td>
                            <td><?= number_format((float) $order["total_amount"], 0, ',', '.') ?> đ</td>
                            <td><span class="badge order-status-<?= e($order["status"]) ?>"><?= e(order_status_labels()[$order["status"]] ?? $order["status"]) ?></span></td>
                            <td><?= e($order["created_at"]) ?></td>
                            <td><a class="btn small secondary" href="/orders/show?id=<?= e((string) $order["id"]) ?>">Chi tiết</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
