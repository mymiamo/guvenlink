<?php
$title = 'Güvenlik Paneli';
require BASE_PATH . '/src/Views/partials/layout-top.php';

$selectedType = $editEntry['type'] ?? 'domain';
$selectedStatus = $editEntry['status'] ?? 'black';
$selectedActive = isset($editEntry['is_active']) ? (string) $editEntry['is_active'] : '1';
$statusLabels = [
    'black' => 'Riskli',
    'suspicious' => 'Şüpheli',
    'white' => 'Güvenli',
];
$reportStatusLabels = [
    'pending' => 'Bekliyor',
    'false_positive' => 'Yanlış pozitif',
    'confirmed_malicious' => 'Doğrulandı',
    'needs_review' => 'İncelenecek',
    'rejected' => 'Reddedildi',
];
$verdictLabels = [
    'safe' => 'Güvenli',
    'suspicious' => 'Şüpheli',
    'malicious' => 'Zararlı',
    'unknown' => 'Belirsiz',
];

$hour = (int) date('H');
if ($hour < 12) {
    $greeting = 'Günaydın';
} elseif ($hour < 18) {
    $greeting = 'İyi günler';
} else {
    $greeting = 'İyi akşamlar';
}
$adminEmail = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');
$adminName = explode('@', $adminEmail)[0];
?>

<!-- ═══════ TOOLBAR ═══════ -->
<div class="toolbar">
    <div class="header">
        <h1><?= $greeting ?>, <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?> 👋</h1>
        <p><?= $adminEmail ?> olarak giriş yapıldı</p>
        <div class="greeting-time"><?= date('d M Y, H:i') ?></div>
    </div>
    <div class="toolbar-actions">
        <form method="post" action="<?= htmlspecialchars(app_path('/admin/import'), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;margin:0">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-6.22-8.56"/><path d="M21 3v6h-6"/></svg>
                USOM İçe Aktar
            </button>
        </form>
        <a class="button secondary" href="<?= htmlspecialchars(app_path('/admin/logout'), ENT_QUOTES, 'UTF-8') ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Çıkış
        </a>
    </div>
</div>

<!-- ═══════ STAT CARDS ═══════ -->
<div class="summary">
    <div class="stat">
        <div class="stat-header">
            <span class="stat-label">Filtrelenmiş Kayıt</span>
            <div class="stat-icon blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= (int) $total ?></div>
    </div>
    <div class="stat">
        <div class="stat-header">
            <span class="stat-label">Bekleyen Rapor</span>
            <div class="stat-icon orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= (int) ($reportSummary['pending'] ?? 0) ?></div>
    </div>
    <div class="stat">
        <div class="stat-header">
            <span class="stat-label">Yanlış Pozitif</span>
            <div class="stat-icon red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= (int) ($reportSummary['false_positive'] ?? 0) ?></div>
    </div>
    <div class="stat">
        <div class="stat-header">
            <span class="stat-label">İncelenecek</span>
            <div class="stat-icon purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
        </div>
        <div class="stat-value"><?= (int) ($reportSummary['needs_review'] ?? 0) ?></div>
    </div>
</div>

<!-- ═══════ SERVICE HEALTH ═══════ -->
<div class="card" style="margin-bottom:24px">
    <h2>
        <span class="card-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        </span>
        Servis Sağlığı
    </h2>
    <div class="service-grid">
        <?php foreach (($health['services'] ?? []) as $service): ?>
            <?php $online = !empty($service['available']); ?>
            <div class="service-card">
                <strong><?= htmlspecialchars((string) $service['service'], ENT_QUOTES, 'UTF-8') ?></strong>
                <div class="service-status">
                    <span class="status-dot <?= $online ? 'online' : 'offline' ?>"></span>
                    <?= $online ? 'Erişilebilir' : 'Devre dışı / açık devre' ?>
                </div>
                <div class="service-failures">Ardışık hata: <?= (int) ($service['failures'] ?? 0) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══════ MAIN GRID ═══════ -->
<div class="grid two">
    <!-- LEFT COLUMN -->
    <div class="stack">
        <!-- ENTRY FORM -->
        <div class="card">
            <h2>
                <span class="card-icon" style="background:<?= $editEntry ? 'rgba(245,158,11,0.12)' : 'rgba(34,197,94,0.12)' ?>;color:<?= $editEntry ? 'var(--orange)' : 'var(--green)' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php if ($editEntry): ?><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/><?php else: ?><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/><?php endif; ?></svg>
                </span>
                <?= $editEntry ? 'Kaydı Düzenle' : 'Manuel Kayıt Ekle' ?>
            </h2>
            <form method="post" action="<?= htmlspecialchars(app_path('/admin/entries/save'), ENT_QUOTES, 'UTF-8') ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <div class="row">
                    <label><span class="muted">ID</span><input type="number" name="id" value="<?= htmlspecialchars((string) ($editEntry['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $editEntry ? 'readonly' : '' ?>></label>
                    <label><span class="muted">Tip</span>
                        <select name="type">
                            <option value="domain" <?= $selectedType === 'domain' ? 'selected' : '' ?>>domain</option>
                            <option value="url" <?= $selectedType === 'url' ? 'selected' : '' ?>>url</option>
                        </select>
                    </label>
                </div>
                <label><span class="muted">Değer</span><input name="match_value" value="<?= htmlspecialchars((string) ($editEntry['match_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="örnek.com veya örnek.com/path" required></label>
                <div class="row">
                    <label><span class="muted">Durum</span>
                        <select name="status">
                            <option value="black" <?= $selectedStatus === 'black' ? 'selected' : '' ?>>Riskli</option>
                            <option value="suspicious" <?= $selectedStatus === 'suspicious' ? 'selected' : '' ?>>Şüpheli</option>
                            <option value="white" <?= $selectedStatus === 'white' ? 'selected' : '' ?>>Güvenli</option>
                        </select>
                    </label>
                    <label><span class="muted">Aktif</span>
                        <select name="is_active">
                            <option value="1" <?= $selectedActive === '1' ? 'selected' : '' ?>>Evet</option>
                            <option value="0" <?= $selectedActive === '0' ? 'selected' : '' ?>>Hayır</option>
                        </select>
                    </label>
                </div>
                <label><span class="muted">Sebep</span><textarea name="reason" placeholder="Açıklama"><?= htmlspecialchars((string) ($editEntry['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label>
                <div class="actions">
                    <button type="submit">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= $editEntry ? 'Güncelle' : 'Kaydet' ?>
                    </button>
                    <?php if ($editEntry): ?>
                        <a class="button secondary" href="<?= htmlspecialchars(app_path('/admin'), ENT_QUOTES, 'UTF-8') ?>">Yeni Kayıt Modu</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ENTRIES TABLE -->
        <div class="card">
            <h2>
                <span class="card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </span>
                Kayıtlar
            </h2>
            <form method="get" action="<?= htmlspecialchars(app_path('/admin'), ENT_QUOTES, 'UTF-8') ?>" class="stack" style="margin-bottom:18px">
                <div class="row">
                    <label><span class="muted">Ara</span><input name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Domain veya URL..."></label>
                    <label><span class="muted">Durum</span>
                        <select name="status">
                            <option value="">Tümü</option>
                            <option value="black" <?= ($filters['status'] ?? '') === 'black' ? 'selected' : '' ?>>Riskli</option>
                            <option value="suspicious" <?= ($filters['status'] ?? '') === 'suspicious' ? 'selected' : '' ?>>Şüpheli</option>
                            <option value="white" <?= ($filters['status'] ?? '') === 'white' ? 'selected' : '' ?>>Güvenli</option>
                        </select>
                    </label>
                </div>
                <div class="row">
                    <label><span class="muted">Tip</span>
                        <select name="type">
                            <option value="">Tümü</option>
                            <option value="domain" <?= ($filters['type'] ?? '') === 'domain' ? 'selected' : '' ?>>domain</option>
                            <option value="url" <?= ($filters['type'] ?? '') === 'url' ? 'selected' : '' ?>>url</option>
                        </select>
                    </label>
                    <label><span class="muted">Kaynak</span>
                        <select name="source">
                            <option value="">Tümü</option>
                            <option value="usom" <?= ($filters['source'] ?? '') === 'usom' ? 'selected' : '' ?>>usom</option>
                            <option value="manual" <?= ($filters['source'] ?? '') === 'manual' ? 'selected' : '' ?>>manual</option>
                        </select>
                    </label>
                </div>
                <button type="submit" class="secondary" style="width:auto">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filtrele
                </button>
            </form>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th><th>Tip</th><th>Değer</th><th>Durum</th><th>Kaynak</th><th>Aktif</th><th>Güncel</th><th>İşlem</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="8" class="empty-state">Kayıt bulunamadı</td></tr>
                    <?php else: ?>
                        <?php foreach ($entries as $entry): ?>
                            <?php
                            $_sMap = ['black' => 'red', 'suspicious' => 'orange', 'white' => 'green'];
                            $statusClass = $_sMap[$entry['status']] ?? 'neutral';
                            ?>
                            <tr>
                                <td><?= (int) $entry['id'] ?></td>
                                <td><span class="badge neutral"><?= htmlspecialchars((string) $entry['type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars((string) $entry['match_value'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabels[$entry['status']] ?? (string) $entry['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="badge info"><?= htmlspecialchars((string) $entry['source'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?php if ((int) $entry['is_active']): ?><span class="badge green">Evet</span><?php else: ?><span class="badge neutral">Hayır</span><?php endif; ?></td>
                                <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars((string) $entry['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <a class="button secondary" href="<?= htmlspecialchars(app_path('/admin?edit=' . (int) $entry['id']), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;padding:6px 12px;font-size:12px">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Düzenle
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="stack">
        <!-- REPORTS TABLE -->
        <div class="card">
            <h2>
                <span class="card-icon" style="background:rgba(245,158,11,0.12);color:var(--orange)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </span>
                Kullanıcı Raporları
            </h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th><th>URL</th><th>Durum</th><th>Canlı Analiz</th><th>İşlem</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($reports)): ?>
                        <tr><td colspan="5" class="empty-state">Henüz rapor yok</td></tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <?php
                            $analysis = $reportAnalyses[(int) $report['id']] ?? null;
                            $_rsMap = ['pending' => 'orange', 'false_positive' => 'red', 'confirmed_malicious' => 'red', 'needs_review' => 'purple', 'rejected' => 'neutral'];
                            $rStatusClass = $_rsMap[$report['status']] ?? 'neutral';
                            ?>
                            <tr>
                                <td><?= (int) $report['id'] ?></td>
                                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    <div><?= htmlspecialchars((string) $report['report_url'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($report['note'])): ?>
                                        <div class="muted" style="margin-top:4px;font-size:12px"><?= htmlspecialchars((string) $report['note'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $rStatusClass ?>"><?= htmlspecialchars($reportStatusLabels[$report['status']] ?? (string) $report['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td style="font-size:12px">
                                    <?php if (is_array($analysis)): ?>
                                        <?php
                                        $_vMap = ['safe' => 'green', 'suspicious' => 'orange', 'malicious' => 'red'];
                                        $verdictClass = $_vMap[$analysis['verdict'] ?? ''] ?? 'neutral';
                                        ?>
                                        <div><span class="badge <?= $verdictClass ?>"><?= htmlspecialchars($verdictLabels[$analysis['verdict']] ?? $analysis['verdict'], ENT_QUOTES, 'UTF-8') ?></span></div>
                                        <div class="muted" style="margin-top:4px">Skor <?= (int) ($analysis['score'] ?? 0) ?> · Güven <?= htmlspecialchars((string) ($analysis['confidence'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="muted" style="margin-top:2px;font-size:11px">
                                            <?php
                                            $checkParts = [];
                                            foreach (($analysis['checks'] ?? []) as $check) {
                                                $checkParts[] = ($check['service'] ?? '-') . ':' . ($check['status'] ?? '-');
                                            }
                                            echo htmlspecialchars(implode(', ', $checkParts), ENT_QUOTES, 'UTF-8');
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">Analiz alınamadı</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array($report['status'], ['pending', 'false_positive', 'needs_review'], true)): ?>
                                        <div class="actions-vertical">
                                            <form method="post" action="<?= htmlspecialchars(app_path('/admin/reports/approve'), ENT_QUOTES, 'UTF-8') ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                                                <select name="entry_status" style="width:auto;min-width:100px;padding:6px 28px 6px 10px;font-size:12px">
                                                    <option value="black">Riskli</option>
                                                    <option value="suspicious">Şüpheli</option>
                                                    <option value="white">Güvenli</option>
                                                </select>
                                                <button type="submit" style="padding:6px 12px;font-size:12px" class="success">İşle</button>
                                            </form>
                                            <div class="actions">
                                                <form method="post" action="<?= htmlspecialchars(app_path('/admin/reports/review'), ENT_QUOTES, 'UTF-8') ?>" style="width:auto">
                                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                                                    <button type="submit" class="secondary" style="padding:6px 12px;font-size:12px;width:auto">İncele</button>
                                                </form>
                                                <form method="post" action="<?= htmlspecialchars(app_path('/admin/reports/reject'), ENT_QUOTES, 'UTF-8') ?>" style="width:auto">
                                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                                                    <button type="submit" class="danger" style="padding:6px 12px;font-size:12px;width:auto">Reddet</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge neutral">Tamamlandı</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AUDIT LOGS -->
        <div class="card">
            <h2>
                <span class="card-icon" style="background:rgba(168,85,247,0.12);color:var(--purple)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </span>
                Son İşlem Kayıtları
            </h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Zaman</th><th>Kullanıcı</th><th>İşlem</th><th>Hedef</th></tr></thead>
                    <tbody>
                    <?php if (empty($auditLogs)): ?>
                        <tr><td colspan="4" class="empty-state">Henüz işlem kaydı yok</td></tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td style="font-size:12px;white-space:nowrap"><?= htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="font-size:12px"><?= htmlspecialchars((string) $log['actor_email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge info"><?= htmlspecialchars((string) $log['action'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td style="font-size:12px"><?= htmlspecialchars((string) $log['target_type'], ENT_QUOTES, 'UTF-8') ?> #<?= (int) ($log['target_id'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RECENT IMPORTS -->
        <div class="card">
            <h2>
                <span class="card-icon" style="background:rgba(34,197,94,0.12);color:var(--green)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </span>
                Son İçe Aktarmalar
            </h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Durum</th><th>Eklendi</th><th>Güncellendi</th><th>Pasif</th></tr></thead>
                    <tbody>
                    <?php if (empty($recentImports)): ?>
                        <tr><td colspan="5" class="empty-state">Henüz içe aktarma yok</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentImports as $run): ?>
                            <?php
                            $_iMap = ['success' => 'green', 'completed' => 'green', 'failed' => 'red', 'error' => 'red'];
                            $importStatusClass = $_iMap[$run['status']] ?? 'neutral';
                            ?>
                            <tr>
                                <td><?= (int) $run['id'] ?></td>
                                <td><span class="badge <?= $importStatusClass ?>"><?= htmlspecialchars((string) $run['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td style="font-weight:600;color:var(--green)"><?= (int) $run['added_count'] ?></td>
                                <td style="font-weight:600;color:var(--accent)"><?= (int) $run['updated_count'] ?></td>
                                <td style="font-weight:600;color:var(--red)"><?= (int) $run['deactivated_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/src/Views/partials/layout-bottom.php'; ?>
