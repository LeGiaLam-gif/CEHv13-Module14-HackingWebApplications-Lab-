<div class="page-actions"><a class="btn primary" href="/brands/create">+ Thêm thương hiệu</a></div>
<?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="panel">
<table class="table">
<thead><tr><th>ID</th><th>Tên thương hiệu</th><th>Mô tả</th><th>Sản phẩm</th><th>Trạng thái</th><th width="180">Thao tác</th></tr></thead>
<tbody>
<?php foreach ($brands as $brand): ?>
<tr>
<td><?= e($brand["id"]) ?></td>
<td><strong><?= e($brand["name"]) ?></strong></td>
<td><?= e($brand["description"]) ?></td>
<td><?= number_format((int) $brand["product_count"]) ?></td>
<td><span class="badge <?= $brand["status"] ? 'success' : 'muted' ?>"><?= $brand["status"] ? "Hiển thị" : "Ẩn" ?></span></td>
<td>
<a class="btn small secondary" href="/brands/edit?id=<?= e($brand["id"]) ?>">Sửa</a>
<form class="inline" method="POST" action="/brands/delete" onsubmit="return confirmDelete('Bạn chắc chắn muốn xóa thương hiệu này?')">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= e($brand["id"]) ?>"><button class="btn small danger" type="submit">Xóa</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (empty($brands)): ?><tr><td colspan="6" class="empty">Chưa có thương hiệu.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
