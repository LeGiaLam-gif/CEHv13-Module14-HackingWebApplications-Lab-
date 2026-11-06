<?php $action='/categories/create'; $button='Lưu danh mục'; ?>

<div class="panel form-panel">
<?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="POST" action="<?= $action ?>">
<?= csrf_field() ?>
<div class="form-group"><label>Tên danh mục</label><input type="text" name="name" value="<?= e($category["name"] ?? "") ?>" placeholder="Ví dụ: Laptop"></div>
<div class="form-group"><label>Mô tả</label><textarea name="description" rows="4" placeholder="Mô tả ngắn về danh mục"><?= e($category["description"] ?? "") ?></textarea></div>
<label class="checkbox"><input type="checkbox" name="status" <?= !isset($category) || $category["status"] ? "checked" : "" ?>> Hiển thị danh mục</label>
<div class="actions"><button class="btn primary" type="submit"><?= $button ?></button><a class="btn secondary" href="/categories">Quay lại</a></div>
</form>
</div>
