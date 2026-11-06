<div class="panel form-panel">
    <?php if (!empty($errors)): ?>
        <div class="alert error"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <?php if (!$products): ?>
        <div class="alert error">Hiện không có sản phẩm đang bán còn hàng. Hãy cập nhật sản phẩm và tồn kho trước khi tạo đơn.</div>
    <?php endif; ?>

    <?php
    $oldItems = is_array($old["items"] ?? null) ? array_values($old["items"]) : [];
    if (!$oldItems) $oldItems = [["product_id" => "", "quantity" => 1]];
    ?>
    <form method="POST" action="/orders/create" id="orderForm">
        <?= csrf_field() ?>
        <h2>Thông tin người nhận</h2>
        <?php
        $selectedCustomerId = $old["customer_id"] ?? "";
        $selectedCustomerId = is_string($selectedCustomerId) || is_int($selectedCustomerId) ? (string) $selectedCustomerId : "";
        ?>
        <div class="form-group">
            <label for="customer_id">Khách hàng đã lưu (không bắt buộc)</label>
            <select id="customer_id" name="customer_id">
                <option value="">Nhập thông tin người nhận thủ công</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= e((string) $customer["id"]) ?>"
                            data-name="<?= e($customer["name"]) ?>"
                            data-phone="<?= e($customer["phone"]) ?>"
                            data-address="<?= e($customer["address"]) ?>"
                        <?= $selectedCustomerId === (string) $customer["id"] ? "selected" : "" ?>>
                        <?= e($customer["name"]) ?> — <?= e($customer["phone"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="help"><a href="/customers/create">+ Thêm khách hàng mới</a></small>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label for="customer_name">Tên người nhận</label>
                <input id="customer_name" name="customer_name" type="text" maxlength="150" required data-customer-field
                       value="<?= e(is_string($old["customer_name"] ?? null) ? $old["customer_name"] : "") ?>">
            </div>
            <div class="form-group">
                <label for="customer_phone">Số điện thoại</label>
                <input id="customer_phone" name="customer_phone" type="tel" maxlength="30" required data-customer-field
                       value="<?= e(is_string($old["customer_phone"] ?? null) ? $old["customer_phone"] : "") ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="shipping_address">Địa chỉ giao hàng</label>
            <textarea id="shipping_address" name="shipping_address" rows="3" maxlength="500" required data-customer-field><?= e(is_string($old["shipping_address"] ?? null) ? $old["shipping_address"] : "") ?></textarea>
        </div>

        <div class="order-items-heading">
            <h2>Sản phẩm trong đơn</h2>
            <button class="btn secondary" type="button" id="addOrderItem" <?= !$products ? "disabled" : "" ?>>+ Thêm sản phẩm</button>
        </div>
        <div id="orderItems">
            <?php foreach ($oldItems as $index => $oldItem): ?>
                <?php
                $oldItem = is_array($oldItem) ? $oldItem : [];
                $oldProductId = $oldItem["product_id"] ?? "";
                $oldProductId = is_string($oldProductId) || is_int($oldProductId) ? (string) $oldProductId : "";
                $oldQuantity = $oldItem["quantity"] ?? 1;
                ?>
                <div class="order-item-row" data-order-item>
                    <div class="form-group">
                        <label>Sản phẩm</label>
                        <select name="items[<?= (int) $index ?>][product_id]" required>
                            <option value="">-- Chọn sản phẩm --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= e((string) $product["id"]) ?>"
                                    <?= (string) $oldProductId === (string) $product["id"] ? "selected" : "" ?>>
                                    <?= e($product["name"]) ?><?= $product["sku"] ? " (" . e($product["sku"]) . ")" : "" ?>
                                    — còn <?= number_format((int) $product["quantity"]) ?> — <?= number_format((float) $product["unit_price"], 0, ',', '.') ?> đ
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Số lượng</label>
                        <input type="number" name="items[<?= (int) $index ?>][quantity]" min="1" step="1" required
                               value="<?= e(is_string($oldQuantity) || is_int($oldQuantity) ? (string) $oldQuantity : "1") ?>">
                    </div>
                    <button class="btn small danger remove-order-item" type="button">Xóa</button>
                </div>
            <?php endforeach; ?>
        </div>

        <template id="orderItemTemplate">
            <div class="order-item-row" data-order-item>
                <div class="form-group">
                    <label>Sản phẩm</label>
                    <select name="items[__INDEX__][product_id]" required>
                        <option value="">-- Chọn sản phẩm --</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= e((string) $product["id"]) ?>">
                                <?= e($product["name"]) ?><?= $product["sku"] ? " (" . e($product["sku"]) . ")" : "" ?>
                                — còn <?= number_format((int) $product["quantity"]) ?> — <?= number_format((float) $product["unit_price"], 0, ',', '.') ?> đ
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Số lượng</label>
                    <input type="number" name="items[__INDEX__][quantity]" min="1" step="1" value="1" required>
                </div>
                <button class="btn small danger remove-order-item" type="button">Xóa</button>
            </div>
        </template>

        <div class="actions">
            <button class="btn primary" type="submit" <?= !$products ? "disabled" : "" ?>>Tạo đơn hàng</button>
            <a class="btn secondary" href="/orders">Quay lại</a>
        </div>
    </form>
</div>
