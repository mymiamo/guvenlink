<?php $title = 'Guvenlik Paneli'; require BASE_PATH . '/src/Views/partials/layout-top.php'; ?>
<div class="toolbar">
    <div class="header">
        <h1>Guvenlik Paneli</h1>
        <p><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?> olarak giris yapildi.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="post" action="/admin/import" style="width:auto">
            <button type="submit">USOM Import Calistir</button>
        </form>
        <a class="button secondary" href="/admin/logout" style="width:auto;padding:10px 16px">Cikis</a>
    </div>
</div>

<div class="summary">
    <div class="stat"><span class="muted">Filtrelenmis kayit</span><strong><?= (int) $total ?></strong></div>
    <div class="stat"><span class="muted">Son import</span><strong><?= htmlspecialchars($latestImport['status'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
    <div class="stat"><span class="muted">Bitis zamani</span><strong style="font-size:16px"><?= htmlspecialchars($latestImport['finished_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></div>
</div>

<div class="grid two" style="margin-top:18px">
    <div class="stack">
        <div class="card">
            <h2>Manuel Kayit Ekle / Guncelle</h2>
            <form method="post" action="/admin/entries/save" class="stack">
                <div class="row">
                    <label><span class="muted">ID (opsiyonel)</span><input type="number" name="id"></label>
                    <label><span class="muted">Tip</span>
                        <select name="type">
                            <option value="domain">domain</option>
                            <option value="url">url</option>
                        </select>
                    </label>
                </div>
                <label><span class="muted">Deger</span><input name="match_value" placeholder="ornek.com veya ornek.com/path" required></label>
                <div class="row">
                    <label><span class="muted">Durum</span>
                        <select name="status">
                            <option value="black">black</option>
                            <option value="white">white</option>
                        </select>
                    </label>
                    <label><span class="muted">Aktif</span>
                        <select name="is_active">
                            <option value="1">1</option>
                            <option value="0">0</option>
                        </select>
                    </label>
                </div>
                <label><span class="muted">Sebep</span><textarea name="reason" placeholder="Aciklama"></textarea></label>
                <button type="submit">Kaydet</button>
            </form>
        </div>

        <div class="card">
            <h2>Kayitlar</h2>
            <form method="get" action="/admin" class="stack" style="margin-bottom:14px">
                <div class="row">
                    <label><span class="muted">Ara</span><input name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                    <label><span class="muted">Durum</span>
                        <select name="status">
                            <option value="">Tumu</option>
                            <option value="black" <?= ($filters['status'] ?? '') === 'black' ? 'selected' : '' ?>>black</option>
                            <option value="white" <?= ($filters['status'] ?? '') === 'white' ? 'selected' : '' ?>>white</option>
                        </select>
                    </label>
                </div>
                <div class="row">
                    <label><span class="muted">Tip</span>
                        <select name="type">
                            <option value="">Tumu</option>
                            <option value="domain" <?= ($filters['type'] ?? '') === 'domain' ? 'selected' : '' ?>>domain</option>
                            <option value="url" <?= ($filters['type'] ?? '') === 'url' ? 'selected' : '' ?>>url</option>
                        </select>
                    </label>
                    <label><span class="muted">Kaynak</span>
                        <select name="source">
                            <option value="">Tumu</option>
                            <option value="usom" <?= ($filters['source'] ?? '') === 'usom' ? 'selected' : '' ?>>usom</option>
                            <option value="manual" <?= ($filters['source'] ?? '') === 'manual' ? 'selected' : '' ?>>manual</option>
                        </select>
                    </label>
                </div>
                <button type="submit" class="secondary">Filtrele</button>
            </form>
            <div style="overflow:auto">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Tip</th><th>Deger</th><th>Durum</th><th>Kaynak</th><th>Aktif</th><th>Guncel</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= (int) $entry['id'] ?></td>
                            <td><?= htmlspecialchars($entry['type'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($entry['match_value'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($entry['status'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($entry['source'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $entry['is_active'] ?></td>
                            <td><?= htmlspecialchars($entry['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <h2>Son Importlar</h2>
            <table>
                <thead><tr><th>ID</th><th>Durum</th><th>Eklendi</th><th>Guncellendi</th><th>Pasif</th></tr></thead>
                <tbody>
                <?php foreach ($recentImports as $run): ?>
                    <tr>
                        <td><?= (int) $run['id'] ?></td>
                        <td><?= htmlspecialchars($run['status'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $run['added_count'] ?></td>
                        <td><?= (int) $run['updated_count'] ?></td>
                        <td><?= (int) $run['deactivated_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <h2>API Uclari</h2>
            <div class="stack muted">
                <div><strong>POST</strong> /api/auth/login</div>
                <div><strong>GET</strong> /api/check?url=...</div>
                <div><strong>GET</strong> /api/feed?page=1&amp;perPage=5000</div>
                <div><strong>GET</strong> /api/meta</div>
                <div><strong>GET/POST/PUT</strong> /api/admin/entries</div>
                <div><strong>POST</strong> /api/admin/import/usom</div>
            </div>
        </div>
    </div>
</div>
<?php require BASE_PATH . '/src/Views/partials/layout-bottom.php'; ?>

