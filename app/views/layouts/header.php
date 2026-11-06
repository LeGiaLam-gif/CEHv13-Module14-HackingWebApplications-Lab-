<?php
$config = require __DIR__ . "/../../src/config.php";
$currentPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? $config["app"]["name"]) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/product-features.css">
    <?php if (str_starts_with($currentPath, "/orders")): ?><link rel="stylesheet" href="/assets/css/orders.css"><?php endif; ?>
</head>
<body>
<?php if (!empty($_SESSION["admin_id"])): ?>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="brand"><div class="brand-icon">A</div><div><strong>Admin Panel</strong><small>Product Manager</small></div></div>
        <nav class="nav">
            <a class="<?= $currentPath === '/dashboard' ? 'active' : '' ?>" href="/dashboard">Dashboard</a>
            <a class="<?= str_starts_with($currentPath, '/categories') ? 'active' : '' ?>" href="/categories">Danh mục</a>
            <a class="<?= str_starts_with($currentPath, '/brands') ? 'active' : '' ?>" href="/brands">Thương hiệu</a>
            <a class="<?= str_starts_with($currentPath, '/products') ? 'active' : '' ?>" href="/products">Sản phẩm</a>
            <a class="<?= str_starts_with($currentPath, '/customers') ? 'active' : '' ?>" href="/customers">Khách hàng</a>
            <a class="<?= str_starts_with($currentPath, '/inventory') ? 'active' : '' ?>" href="/inventory">Nhập xuất kho</a>
            <a class="<?= str_starts_with($currentPath, '/orders') ? 'active' : '' ?>" href="/orders">Đơn hàng</a>
            <a class="<?= str_starts_with($currentPath, '/activity-logs') ? 'active' : '' ?>" href="/activity-logs">Log hoạt động</a>
            <a href="/logout">Đăng xuất</a>
        </nav>
    </aside>
    <main class="main">
        <header class="topbar">
            <div><h1><?= e($title ?? "Admin") ?></h1><p>Xin chào, <?= e($_SESSION["admin_full_name"] ?? $_SESSION["admin_username"] ?? "Admin") ?></p></div>
        </header>
<?php endif; ?>
