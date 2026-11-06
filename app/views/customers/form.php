<div class="panel form-panel">
    <?php if (!empty($errors)): ?>
        <div class="alert error"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <?php $values = $old ?? $customer ?? []; ?>
    <form method="POST" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="grid-2">
            <div class="form-group">
                <label for="customer_name">Tên khách hàng</label>
                <input id="customer_name" name="name" type="text" maxlength="150" required value="<?= e(is_string($values["name"] ?? null) ? $values["name"] : "") ?>">
            </div>
            <div class="form-group">
                <label for="customer_phone">Số điện thoại</label>
                <input id="customer_phone" name="phone" type="tel" maxlength="30" required value="<?= e(is_string($values["phone"] ?? null) ? $values["phone"] : "") ?>">
            </div>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label for="customer_email">Email</label>
                <input id="customer_email" name="email" type="email" maxlength="150" value="<?= e(is_string($values["email"] ?? null) ? $values["email"] : "") ?>">
            </div>
            <div class="form-group">
                <label for="customer_address">Địa chỉ</label>
                <input id="customer_address" name="address" type="text" maxlength="500" required value="<?= e(is_string($values["address"] ?? null) ? $values["address"] : "") ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="customer_notes">Ghi chú</label>
            <textarea id="customer_notes" name="notes" rows="3" maxlength="500"><?= e(is_string($values["notes"] ?? null) ? $values["notes"] : "") ?></textarea>
        </div>
        <div class="actions">
            <button class="btn primary" type="submit"><?= $isEdit ? "Lưu thay đổi" : "Thêm khách hàng" ?></button>
            <a class="btn secondary" href="/customers">Quay lại</a>
        </div>
    </form>
</div>
