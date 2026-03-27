<?php $title = 'Kurulum'; require BASE_PATH . '/src/Views/partials/layout-top.php'; ?>
<div class="card" style="max-width:520px;margin:72px auto">
    <div class="header">
        <h1>İlk Yönetici Oluştur</h1>
        <p>Bu ekran yalnızca ilk kurulum sırasında görünür.</p>
    </div>
    <?php if (!empty($error)): ?>
        <p class="pill malicious"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars(app_path('/admin/install'), ENT_QUOTES, 'UTF-8') ?>" class="stack" style="margin-top:18px">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <label>
            <span class="muted">E-posta</span>
            <input type="email" name="email" required>
        </label>
        <label>
            <span class="muted">Şifre</span>
            <input type="password" name="password" minlength="12" required>
        </label>
        <label>
            <span class="muted">Şifre Tekrar</span>
            <input type="password" name="password_repeat" minlength="12" required>
        </label>
        <button type="submit">Yöneticiyi Oluştur</button>
    </form>
</div>
<?php require BASE_PATH . '/src/Views/partials/layout-bottom.php'; ?>
