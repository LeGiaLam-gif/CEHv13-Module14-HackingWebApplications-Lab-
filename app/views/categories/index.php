<div class="page-actions"><a class="btn primary" href="/categories/create">+ Thêm danh mục</a></div>
<div class="panel">
<table class="table">
<thead><tr><th>ID</th><th>Tên danh mục</th><th>Mô tả</th><th>Trạng thái</th><th width="180">Thao tác</th></tr></thead>
<tbody>
<?php foreach ($categories as $category): ?>
<tr>
<td><?= e($category["id"]) ?></td>
<td><strong><?= e($category["name"]) ?></strong></td>
<td><?= e($category["description"]) ?></td>
<td><span class="badge <?= $category["status"] ? 'success' : 'muted' ?>"><?= $category["status"] ? "Hiển thị" : "Ẩn" ?></span></td>
<td>
<a class="btn small secondary" href="/categories/edit?id=<?= e($category["id"]) ?>">Sửa</a>
<form class="inline" method="POST" action="/categories/delete" onsubmit="return confirmDelete('Bạn chắc chắn muốn xóa danh mục này?')">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= e($category["id"]) ?>"><button class="btn small danger" type="submit">Xóa</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (empty($categories)): ?><tr><td colspan="5" class="empty">Chưa có danh mục.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
