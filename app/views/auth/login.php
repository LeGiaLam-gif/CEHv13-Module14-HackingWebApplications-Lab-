<div class="login-page">
    <div class="login-card">
        <h1>Admin Login</h1>
        <p>Đăng nhập để quản lý danh mục và sản phẩm.</p>
        <?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="POST" action="/login" id="loginForm">
            <?= csrf_field() ?>
            <div class="form-group"><label>Tài khoản hoặc email</label><input type="text" name="username" id="username" placeholder="admin"><small class="field-error" id="usernameError"></small></div>
            <div class="form-group"><label>Mật khẩu</label><input type="password" name="password" id="password" placeholder="admin123"><small class="field-error" id="passwordError"></small></div>
            <button class="btn primary full" type="submit">Đăng nhập</button>
        </form>
        <p><a href="/forgot-password">Quên mật khẩu?</a></p>
        <div class="hint">Tài khoản demo: <strong>admin</strong> / <strong>admin123</strong></div>
    </div>
</div>
