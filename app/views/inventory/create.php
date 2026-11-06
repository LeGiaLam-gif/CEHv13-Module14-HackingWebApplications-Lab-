<div class="panel form-panel">
    <?php if (!empty($errors)): ?>
        <div class="alert error"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if (!$products): ?>
        <div class="alert error">Chưa có sản phẩm. Hãy thêm sản phẩm trước khi tạo phiếu kho.</div>
    <?php endif; ?>
    <?php
    $oldProductId = $old["product_id"] ?? "";
    $oldProductId = is_string($oldProductId) || is_int($oldProductId) ? (string) $oldProductId : "";
    ?>
    <form method="POST" action="/inventory/create">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="product_id">Sản phẩm</label>
            <select id="product_id" name="product_id" required>
                <option value="">-- Chọn sản phẩm --</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e((string) $product["id"]) ?>" <?= $oldProductId === (string) $product["id"] ? "selected" : "" ?>>
                        <?= e($product["name"]) ?><?= $product["sku"] ? " (" . e($product["sku"]) . ")" : "" ?> — tồn <?= number_format((int) $product["quantity"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label for="movement_type">Loại giao dịch</label>
                <select id="movement_type" name="movement_type" required>
                    <option value="in" <?= ($old["movement_type"] ?? "in") === "in" ? "selected" : "" ?>>Nhập kho</option>
                    <option value="out" <?= ($old["movement_type"] ?? "") === "out" ? "selected" : "" ?>>Xuất kho</option>
                </select>
            </div>
            <div class="form-group">
                <label for="quantity">Số lượng</label>
                <input id="quantity" name="quantity" type="number" min="1" max="2147483647" step="1" required value="<?= e(is_string($old["quantity"] ?? null) ? $old["quantity"] : "1") ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="note">Ghi chú</label>
            <textarea id="note" name="note" rows="3" maxlength="500"><?= e(is_string($old["note"] ?? null) ? $old["note"] : "") ?></textarea>
        </div>
        <div class="actions">
            <button class="btn primary" type="submit" <?= !$products ? "disabled" : "" ?>>Lưu giao dịch</button>
            <a class="btn secondary" href="/inventory">Quay lại</a>
        </div>
    </form>
</div>
