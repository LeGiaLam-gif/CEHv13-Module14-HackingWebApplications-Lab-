<div class="page-actions">
<a class="btn primary" href="/products/create">+ Thêm sản phẩm</a>
<a class="btn secondary" href="/products/export?<?= e(http_build_query(["keyword" => $keyword, "category_id" => $categoryId, "brand_id" => $brandId])) ?>">Xuất Excel</a>
</div>
<div class="panel filter-panel">
<form method="GET" action="/products" class="filter-form">
<input type="text" name="keyword" placeholder="Tìm tên sản phẩm hoặc SKU" value="<?= e($keyword) ?>">
<select name="category_id">
<option value="0">Tất cả danh mục</option>
<?php foreach ($categories as $category): ?>
<option value="<?= e($category["id"]) ?>" <?= (int)$categoryId === (int)$category["id"] ? "selected" : "" ?>><?= e($category["name"]) ?></option>
<?php endforeach; ?>
</select>
<select name="brand_id">
<option value="0">Tất cả thương hiệu</option>
<?php foreach ($brands as $brand): ?>
<option value="<?= e($brand["id"]) ?>" <?= (int)$brandId === (int)$brand["id"] ? "selected" : "" ?>><?= e($brand["name"]) ?></option>
<?php endforeach; ?>
</select>
<button class="btn secondary" type="submit">Tìm kiếm</button>
</form>
</div>
<div class="panel">
<table class="table product-table">
<thead><tr><th>Hình</th><th>Thông tin sản phẩm</th><th>Danh mục</th><th>Thương hiệu</th><th>SL</th><th>Đơn giá</th><th>Trạng thái</th><th width="180">Thao tác</th></tr></thead>
<tbody>
<?php foreach ($products as $product): ?>
<tr>
<td>
<?php if (!empty($product["image_path"])): ?><img class="thumb" src="/<?= e($product["image_path"]) ?>" alt="<?= e($product["name"]) ?>">
<?php else: ?><div class="no-image">No image</div><?php endif; ?>
</td>
<td><strong><?= e($product["name"]) ?></strong><small>SKU: <?= e($product["sku"] ?: "N/A") ?></small><small><?= e($product["description"]) ?></small></td>
<td><?= e($product["category_name"]) ?></td>
<td><?= e($product["brand_name"] ?? "—") ?></td>
<td><?= number_format((int)$product["quantity"]) ?></td>
<td><?= number_format((float)$product["unit_price"], 0, ',', '.') ?> đ</td>
<td><span class="badge <?= $product["status"] ? 'success' : 'muted' ?>"><?= $product["status"] ? "Hiển thị" : "Ẩn" ?></span></td>
<td>
<a class="btn small secondary" href="/products/edit?id=<?= e($product["id"]) ?>">Sửa</a>
<form class="inline" method="POST" action="/products/delete" onsubmit="return confirmDelete('Bạn chắc chắn muốn xóa sản phẩm này?')">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= e($product["id"]) ?>"><button class="btn small danger" type="submit">Xóa</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (empty($products)): ?><tr><td colspan="8" class="empty">Không có sản phẩm phù hợp.</td></tr><?php endif; ?>
</tbody>
</table>
<?php
$paginationParams = [
    "keyword" => $keyword,
    "category_id" => $categoryId,
    "brand_id" => $brandId,
];
$firstPage = max(1, $currentPage - 2);
$lastPage = min($totalPages, $currentPage + 2);
?>
<?php if ($totalProducts > 0): ?>
<div class="pagination">
    <span class="pagination-summary">
        Hiển thị <?= number_format(min(($currentPage - 1) * $perPage + 1, $totalProducts)) ?>
        - <?= number_format(min($currentPage * $perPage, $totalProducts)) ?>
        trên <?= number_format($totalProducts) ?> sản phẩm
    </span>
    <?php if ($totalPages > 1): ?>
    <nav class="pagination-links" aria-label="Phân trang sản phẩm">
        <?php if ($currentPage > 1): ?>
        <a class="btn small secondary" href="/products?<?= e(http_build_query($paginationParams + ["page" => $currentPage - 1])) ?>">Trước</a>
        <?php else: ?>
        <span class="btn small secondary disabled" aria-disabled="true">Trước</span>
        <?php endif; ?>

        <?php for ($page = $firstPage; $page <= $lastPage; $page++): ?>
        <?php if ($page === $currentPage): ?>
        <span class="btn small primary" aria-current="page"><?= $page ?></span>
        <?php else: ?>
        <a class="btn small secondary" href="/products?<?= e(http_build_query($paginationParams + ["page" => $page])) ?>"><?= $page ?></a>
        <?php endif; ?>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
        <a class="btn small secondary" href="/products?<?= e(http_build_query($paginationParams + ["page" => $currentPage + 1])) ?>">Sau</a>
        <?php else: ?>
        <span class="btn small secondary disabled" aria-disabled="true">Sau</span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
