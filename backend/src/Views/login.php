<?php $title = 'Admin Girisi'; require BASE_PATH . '/src/Views/partials/layout-top.php'; ?>
<div class="card" style="max-width:460px;margin:72px auto">
    <div class="header">
        <h1>Guvenlik Admin</h1>
        <p>USOM kaynakli tehdit kayitlarini yonetin.</p>
    </div>
    <?php if (!empty($error)): ?>
        <p class="pill malicious"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/admin/login" class="stack" style="margin-top:18px">
        <label>
            <span class="muted">E-posta</span>
            <input type="email" name="email" required>
        </label>
        <label>
            <span class="muted">Sifre</span>
            <input type="password" name="password" required>
        </label>
        <button type="submit">Giris Yap</button>
    </form>
</div>
<?php require BASE_PATH . '/src/Views/partials/layout-bottom.php'; ?>

