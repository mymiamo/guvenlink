<?php $title = 'Admin Girişi'; require BASE_PATH . '/src/Views/partials/layout-top.php'; ?>
<div class="card" style="max-width:460px;margin:72px auto">
    <div class="header">
        <h1>Güvenlink Admin</h1>
        <p>Tehdit kayıtlarını ve raporları güvenli şekilde yönetin.</p>
    </div>
    <?php if (!empty($error)): ?>
        <p class="pill malicious"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($canInstall)): ?>
        <p class="muted" style="margin-top:12px">İlk kurulum tamamlanmamış. <a href="<?= htmlspecialchars(app_path('/admin/install'), ENT_QUOTES, 'UTF-8') ?>">Yönetici hesabını oluşturun</a>.</p>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars(app_path('/admin/login'), ENT_QUOTES, 'UTF-8') ?>" class="stack" style="margin-top:18px">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <label>
            <span class="muted">E-posta</span>
            <input type="email" name="email" required>
        </label>
        <label>
            <span class="muted">Şifre</span>
            <input type="password" name="password" required>
        </label>
        <button type="submit">Giriş Yap</button>
    </form>
</div>
<?php require BASE_PATH . '/src/Views/partials/layout-bottom.php'; ?>
