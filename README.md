# Güvenlink

USOM destekli tehdit kontrolü yapan açık kaynak WebExtension ve PHP/MySQL backend projesi.

## Repo yapısı

- `backend/`: PHP API, admin paneli, kurulum, loglama ve servis katmanı
- `extension-source/`: tarayıcı eklentisinin ortak kaynak dosyaları
- `extension/`: Chromium çıktıları
- `extension-firefox/`: Firefox çıktıları
- `database/`: veritabanı şeması
- `scripts/`: build, paketleme ve veri içe aktarma komutları

## Gereksinimler

- PHP `8.2+`
- Composer
- MySQL `8+` veya uyumlu bir MariaDB sürümü
- PowerShell (`scripts/package_*.ps1` komutları için)

## Hızlı başlangıç

1. Bağımlılıkları kurun:

   ```bash
   composer install
   ```

2. Yerel yapılandırmayı oluşturun:

   ```powershell
   Copy-Item backend\config.local.php.example backend\config.local.php
   ```

3. `backend/config.local.php` içine kendi veritabanı bilgilerinizi, `APP_KEY` değerini ve varsa harici API anahtarlarınızı yazın.
4. `database/schema.sql` dosyasını veritabanına uygulayın.
5. Geliştirme sunucusunu başlatın:

   ```bash
   php -S localhost:8000 -t backend/public
   ```

6. Tarayıcıda `http://localhost:8000/admin/install` adresine gidip ilk yönetici hesabını oluşturun.
7. Eklenti ayarlarından `API adresi` değerini backend'inizin `/api` ucuna göre doğrulayın. Varsayılan geliştirme adresi `http://localhost:8000/api` olarak gelir.

## Güvenlik ve GitHub hazırlığı

- Gerçek sırlar repo dışında tutulur; `backend/config.local.php` git tarafından yok sayılır.
- Runtime logları, önbellek dosyaları, `vendor/`, `dist/` ve arşiv çıktıları git'e girmez.
- Repo içindeki örnek yapılandırma yalnızca güvenli placeholder değerler içerir.
- Üretim ortamında exception detayları istemciye döndürülmez.
- Uygulamayı ilk kurulumdan sonra isterseniz `INSTALL_ALLOW_WEB_BOOTSTRAP` değerini `false` yaparak web üzerinden ilk kullanıcı oluşturmayı kapatabilirsiniz.

## Geliştirme komutları

Test çalıştırmak için:

```bash
composer test
```

Ortak kaynaktan tarayıcı çıktıları üretmek için:

```bash
php scripts/build_extensions.php
```

Firefox paketi üretmek için:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/package_firefox.ps1
```

Chromium paketi üretmek için:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/package_chromium.ps1
```

## Yayınlamadan önce

- `backend/config.local.php` dosyasını repoya eklemeyin.
- Üretim API adresini eklenti ayarlarında veya paketlemeden önce uygun biçimde girin.
- `backend/public/guvenlink/index.html` içindeki gizlilik politikası iletişim adresini kendi iletişim bilginizle değiştirin.
- GitHub Actions CI akışı `composer test` komutunu her `push` ve `pull_request` için çalıştırır.
