<div class="panel form-panel">
<?php if (!empty($errors)): ?><div class="alert error"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="POST" action="<?= e($action) ?>">
<?= csrf_field() ?>
<div class="form-group"><label>Tên thương hiệu</label><input type="text" name="name" maxlength="150" value="<?= e($brand["name"] ?? "") ?>" placeholder="Ví dụ: Dell"></div>
<div class="form-group"><label>Mô tả</label><textarea name="description" rows="4" maxlength="2000" placeholder="Mô tả ngắn về thương hiệu"><?= e($brand["description"] ?? "") ?></textarea></div>
<label class="checkbox"><input type="checkbox" name="status" <?= !isset($brand["status"]) || $brand["status"] ? "checked" : "" ?>> Hiển thị thương hiệu</label>
<div class="actions"><button class="btn primary" type="submit"><?= e($button) ?></button><a class="btn secondary" href="/brands">Quay lại</a></div>
</form>
</div>
