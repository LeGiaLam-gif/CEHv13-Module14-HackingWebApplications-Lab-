<div class="login-page">
    <div class="login-card">
        <h1>Quên mật khẩu</h1>
        <p>Nhập email đã đăng ký cho tài khoản quản trị.</p>
        <?php if ($error !== ""): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($message !== ""): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($message === ""): ?>
        <form method="POST" action="/forgot-password">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required maxlength="150" autocomplete="email">
            </div>
            <button class="btn primary full" type="submit">Gửi liên kết đặt lại</button>
        </form>
        <?php endif; ?>
        <p><a href="/login">Quay lại đăng nhập</a></p>
    </div>
</div>
