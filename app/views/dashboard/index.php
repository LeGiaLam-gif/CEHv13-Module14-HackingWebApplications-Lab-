<div class="cards">
    <div class="card"><span>Tổng danh mục</span><strong><?= number_format($categoryCount) ?></strong></div>
    <div class="card"><span>Tổng sản phẩm</span><strong><?= number_format($productCount) ?></strong></div>
    <div class="card"><span>Tổng tồn kho</span><strong><?= number_format($totalQuantity) ?></strong></div>
    <div class="card"><span>Giá trị tồn kho</span><strong><?= number_format($totalValue, 0, ',', '.') ?> đ</strong></div>
</div>
<div class="panel">
    <h2>Chức năng chính</h2>
    <p>Trang admin này cho phép quản trị viên quản lý danh mục sản phẩm, sản phẩm, hình ảnh, số lượng tồn kho, đơn giá và trạng thái hiển thị.</p>
    <div class="actions"><a class="btn primary" href="/products/create">Thêm sản phẩm</a><a class="btn secondary" href="/categories/create">Thêm danh mục</a></div>
</div>
