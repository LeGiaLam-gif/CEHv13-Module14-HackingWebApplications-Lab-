<?php
$actionLabels = [
    "login" => "Đăng nhập",
    "logout" => "Đăng xuất",
    "create" => "Thêm mới",
    "update" => "Cập nhật",
    "delete" => "Xóa",
    "status" => "Đổi trạng thái",
    "cancel" => "Hủy đơn",
    "inventory_in" => "Nhập kho",
    "inventory_out" => "Xuất kho",
];
$entityLabels = [
    "admin" => "Tài khoản Admin",
    "category" => "Danh mục",
    "product" => "Sản phẩm",
    "customer" => "Khách hàng",
    "order" => "Đơn hàng",
];
?>
<div class="panel">
    <h2>Lịch sử hoạt động quản trị</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Thời gian</th><th>Admin</th><th>Hoạt động</th><th>Đối tượng</th><th>Chi tiết</th><th>Địa chỉ IP</th></tr>
            </thead>
            <tbody>
                <?php if (!$logs): ?>
                    <tr><td class="empty" colspan="6">Chưa có hoạt động nào được ghi nhận.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e($log["created_at"]) ?></td>
                            <td><?= e($log["actor_name"]) ?></td>
                            <td><span class="badge muted"><?= e($actionLabels[$log["action"]] ?? $log["action"]) ?></span></td>
                            <td><?= e($entityLabels[$log["entity_type"]] ?? $log["entity_type"]) ?><?= $log["entity_id"] !== null ? " #" . e((string) $log["entity_id"]) : "" ?></td>
                            <td><?= e($log["description"]) ?></td>
                            <td><?= e($log["ip_address"] ?: "—") ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="help">Hiển thị tối đa 200 hoạt động gần nhất.</p>
</div>
