# Guvenlik

USOM destekli tehdit kontrolu yapan WebExtension ve PHP/MySQL backend projesi.

## Yapilar

- `backend/`: PHP REST API, admin paneli, USOM importer
- `extension/`: Chromium ve Firefox uyumlu tarayici eklentisi
- `database/`: SQL kurulum dosyalari
- `scripts/`: yardimci komutlar

## Hizli Baslangic

1. `backend/config.php` dosyasindaki ayarlari sunucunuza gore duzenleyin.
   `APP_BASE_PATH` degeri uygulamanin alt dizinini gostermelidir. Bu proje icin varsayilan: `/guvenlink/backend/public`
2. MySQL tarafinda `database/schema.sql` calistirin.
3. `backend/public` klasorunu web kokunuze baglayin veya gelistirme icin:

```bash
php -S localhost:8000 -t backend/public
```

4. Eklenti icinde `Ayarlar` alanindan API adresini backend'inize gore ayarlayin.
5. Admin panele `/admin/login` uzerinden girin. Ilk kullanici `config.php` icindeki varsayilan yonetici ile otomatik olusturulur.

## USOM Kontrolu

USOM kayitlari artik canli API uzerinden `api/check` cagrisi sirasinda sorgulanir.
Bu nedenle USOM icin cron zorunlu degildir.

Opsiyonel olarak `scripts/import_usom.php` sadece canli baglantiyi test etmek icin calistirilabilir.
