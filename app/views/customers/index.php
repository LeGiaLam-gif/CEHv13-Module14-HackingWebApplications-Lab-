<div class="page-actions"><a class="btn primary" href="/customers/create">+ Thêm khách hàng</a></div>
<div class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Khách hàng</th><th>Số điện thoại</th><th>Email</th><th>Địa chỉ</th><th>Ngày tạo</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (!$customers): ?>
                    <tr><td class="empty" colspan="6">Chưa có khách hàng nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><strong><?= e($customer["name"]) ?></strong><?php if ($customer["notes"]): ?><small><?= e($customer["notes"]) ?></small><?php endif; ?></td>
                            <td><?= e($customer["phone"]) ?></td>
                            <td><?= e($customer["email"] ?: "—") ?></td>
                            <td><?= e($customer["address"]) ?></td>
                            <td><?= e($customer["created_at"]) ?></td>
                            <td><a class="btn small secondary" href="/customers/edit?id=<?= e((string) $customer["id"]) ?>">Sửa</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
