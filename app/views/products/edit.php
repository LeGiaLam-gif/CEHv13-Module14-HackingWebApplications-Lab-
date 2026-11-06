<div class="panel form-panel">
<?php if (!empty($errors)): ?><div class="alert error"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="POST" action="/products/edit?id=<?= e($product["id"]) ?>" enctype="multipart/form-data" id="productForm">
<?= csrf_field() ?>
<div class="grid-2">
<div class="form-group"><label>Tên sản phẩm</label><input type="text" name="name" id="product_name" value="<?= e($product["name"]) ?>"><small class="field-error" id="productNameError"></small></div>
<div class="form-group"><label>Danh mục</label><select name="category_id" id="category_id"><option value="">-- Chọn danh mục --</option><?php foreach ($categories as $category): ?><option value="<?= e($category["id"]) ?>" <?= (int)$product["category_id"] === (int)$category["id"] ? "selected" : "" ?>><?= e($category["name"]) ?></option><?php endforeach; ?></select><small class="field-error" id="categoryError"></small></div>
<div class="form-group"><label>Thương hiệu</label><select name="brand_id"><option value="">-- Không chọn thương hiệu --</option><?php foreach ($brands as $brand): ?><option value="<?= e($brand["id"]) ?>" <?= (int)($product["brand_id"] ?? 0) === (int)$brand["id"] ? "selected" : "" ?>><?= e($brand["name"]) ?><?= !$brand["status"] ? " (Ẩn)" : "" ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>SKU / Mã sản phẩm</label><input type="text" name="sku" value="<?= e($product["sku"]) ?>"></div>
<div class="form-group"><label>Thêm hình ảnh</label><input type="file" name="images[]" id="images" accept="image/jpeg,image/png,image/webp,image/gif" multiple><small class="help">Chọn tối đa 10 ảnh mới; mỗi ảnh tối đa 2MB.</small></div>
<div class="form-group"><label>Số lượng tồn kho</label><input type="number" name="quantity" id="quantity" min="0" value="<?= e($product["quantity"]) ?>" readonly><small class="help">Điều chỉnh tồn kho trong mục Nhập xuất kho.</small></div>
<div class="form-group"><label>Đơn giá</label><input type="number" name="unit_price" id="unit_price" min="0" step="1000" value="<?= e($product["unit_price"]) ?>"><small class="field-error" id="priceError"></small></div>
</div>
<?php if ($images): ?>
<div class="form-group">
<label>Thư viện hình ảnh</label>
<div class="image-gallery">
<?php foreach ($images as $image): ?>
<label class="gallery-image">
<img src="/<?= e($image["image_path"]) ?>" alt="<?= e($product["name"]) ?>">
<span><input type="checkbox" name="remove_image_ids[]" value="<?= e($image["id"]) ?>"> Xóa ảnh</span>
</label>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<div class="form-group"><label>Mô tả</label><textarea name="description" rows="5"><?= e($product["description"]) ?></textarea></div>
<label class="checkbox"><input type="checkbox" name="status" <?= $product["status"] ? "checked" : "" ?>> Hiển thị sản phẩm</label>
<div class="actions"><button class="btn primary" type="submit">Cập nhật sản phẩm</button><a class="btn secondary" href="/products">Quay lại</a></div>
</form>
</div>
