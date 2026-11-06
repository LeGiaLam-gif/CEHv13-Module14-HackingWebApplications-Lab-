<div class="login-page">
    <div class="login-card">
        <h1>Đặt lại mật khẩu</h1>
        <?php if ($error !== ""): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($message !== ""): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($tokenValid): ?>
        <form method="POST" action="/reset-password">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="form-group">
                <label for="password">Mật khẩu mới</label>
                <input type="password" name="password" id="password" required minlength="12" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="password_confirmation">Xác nhận mật khẩu</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required minlength="12" autocomplete="new-password">
            </div>
            <button class="btn primary full" type="submit">Cập nhật mật khẩu</button>
        </form>
        <?php elseif ($message === ""): ?>
            <p>Liên kết không hợp lệ, đã hết hạn hoặc đã được sử dụng. Hãy yêu cầu liên kết mới.</p>
        <?php endif; ?>
        <?php if ($message !== ""): ?><p><a href="/login">Quay lại đăng nhập</a></p><?php endif; ?>
    </div>
</div>
