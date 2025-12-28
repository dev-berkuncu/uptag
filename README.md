# Uptag - Check-in Web Uygulaması

PHP/HTML tabanlı, MySQL üzerinde çalışan, kullanıcıların mekan seçerek check-in yapabildiği ve haftalık liderlik tablolarının oluşturulduğu web uygulaması.

## Özellikler

### Kullanıcı Tarafı
- ✅ Kullanıcı kayıt/giriş/çıkış sistemi
- ✅ Mekan listeleme ve isim üzerinden arama
- ✅ Mekan detay sayfasında check-in oluşturma (opsiyonel not ile)
- ✅ Kullanıcının kendi check-in geçmişini görüntüleme (son 20 kayıt)
- ✅ Haftalık liderlik tablosu (Top kullanıcılar ve Top mekanlar)
- ✅ Cooldown ve rate limit kontrolleri ile spam koruması

### Admin Paneli
- ✅ Kullanıcı yönetimi (listeleme, arama, detay, ban/unban)
- ✅ Mekan yönetimi (CRUD işlemleri, aktif/pasif yönetimi)
- ✅ Check-in yönetimi (izleme, işaretleme, silme, leaderboard'dan hariç tutma)
- ✅ Leaderboard kontrolü (haftalık listeleri görüntüleme ve doğrulama)
- ✅ Sistem ayarları (Top N değerleri, cooldown/rate limit, site ayarları)
- ✅ İşlem logları (audit trail)

## Gereksinimler

- PHP 7.4 veya üzeri
- MySQL 5.7 veya üzeri
- Apache/Nginx web sunucusu
- PDO MySQL extension

## Kurulum

### 1. Veritabanı Kurulumu

1. MySQL'de yeni bir veritabanı oluşturun (veya `schema.sql` dosyasındaki CREATE DATABASE komutunu kullanın)
2. `database/schema.sql` dosyasını içe aktarın:

```bash
mysql -u root -p uptag < database/schema.sql
```

Veya phpMyAdmin üzerinden `database/schema.sql` dosyasını içe aktarın.

### 2. Yapılandırma

`config/config.php` dosyasını düzenleyin:

```php
// Veritabanı ayarları
define('DB_HOST', 'localhost');
define('DB_NAME', 'uptag');
define('DB_USER', 'root');
define('DB_PASS', ''); // Kendi şifrenizi girin

// Site ayarları
define('BASE_URL', 'http://localhost/uptag'); // Kendi URL'nizi girin
```

### 3. Dosya İzinleri

Web sunucusunun yazma iznine ihtiyacı yoktur (tüm veriler veritabanında saklanır).

### 4. Varsayılan Admin Kullanıcı

Veritabanı şeması ile birlikte varsayılan bir admin kullanıcı oluşturulur:

- **Kullanıcı adı:** `admin`
- **Şifre:** `admin123`

⚠️ **ÖNEMLİ:** Üretim ortamında mutlaka bu şifreyi değiştirin!

## Kullanım

### Kullanıcı Akışı

1. **Kayıt Ol:** `/register.php` sayfasından yeni hesap oluşturun
2. **Giriş Yap:** `/login.php` sayfasından giriş yapın
3. **Mekanları Görüntüle:** `/venues.php` sayfasından mekanları listeleyin ve arayın
4. **Check-in Yap:** Mekan detay sayfasından check-in oluşturun
5. **Geçmişi Görüntüle:** `/my-checkins.php` sayfasından kendi check-in geçmişinizi görüntüleyin
6. **Liderlik Tablosu:** `/leaderboard.php` sayfasından haftalık liderlik tablosunu görüntüleyin

### Admin Paneli

1. Admin kullanıcısı ile giriş yapın
2. `/admin/index.php` sayfasına gidin
3. İstediğiniz modüle erişin:
   - **Kullanıcı Yönetimi:** Kullanıcıları listeleme, arama, detay görüntüleme, ban/unban
   - **Mekan Yönetimi:** Mekan ekleme/düzenleme/silme, aktif/pasif yönetimi
   - **Check-in Yönetimi:** Check-in'leri izleme, işaretleme, silme, leaderboard'dan hariç tutma
   - **Leaderboard Kontrolü:** Haftalık listeleri görüntüleme ve doğrulama
   - **Sistem Ayarları:** Top N değerleri, cooldown/rate limit, site ayarları
   - **İşlem Logları:** Tüm admin işlemlerinin audit trail'i

## Haftalık Leaderboard Sistemi

- **Zaman Dilimi:** Europe/Istanbul
- **Hafta Aralığı:** Pazartesi 00:00 - Pazar 23:59
- **Sıralama:** Check-in sayısına göre azalan sırada
- **Eşitlik Durumu:** Daha erken check-in yapan önceliklidir (deterministik)

## Güvenlik Özellikleri

### Cooldown Sistemi
- Aynı mekana kısa sürede tekrar check-in yapılmasını engeller
- Varsayılan: 300 saniye (5 dakika)
- Admin panelinden ayarlanabilir

### Rate Limit
- Kısa zaman penceresinde aşırı sayıda check-in yapılmasını engeller
- Varsayılan: 10 check-in / 3600 saniye (1 saat)
- Admin panelinden ayarlanabilir

### Admin Log Sistemi
- Tüm admin işlemleri loglanır
- IP adresi, tarih, işlem tipi ve detaylar kaydedilir

## Proje Yapısı

```
uptag/
├── admin/              # Admin paneli sayfaları
│   ├── index.php      # Admin dashboard
│   ├── users.php      # Kullanıcı yönetimi
│   ├── venues.php     # Mekan yönetimi
│   ├── checkins.php   # Check-in yönetimi
│   ├── leaderboard.php # Leaderboard kontrolü
│   ├── settings.php   # Sistem ayarları
│   └── logs.php       # İşlem logları
├── assets/
│   └── css/
│       └── style.css  # Ana stil dosyası
├── classes/           # PHP sınıfları
│   ├── User.php       # Kullanıcı işlemleri
│   ├── Venue.php      # Mekan işlemleri
│   ├── Checkin.php    # Check-in işlemleri
│   └── Leaderboard.php # Leaderboard işlemleri
├── config/
│   ├── config.php     # Ana yapılandırma
│   └── database.php   # Veritabanı bağlantı sınıfı
├── database/
│   └── schema.sql     # Veritabanı şeması
├── includes/
│   ├── functions.php  # Yardımcı fonksiyonlar
│   ├── header.php     # Sayfa başlığı
│   └── footer.php     # Sayfa alt bilgisi
├── index.php          # Ana sayfa
├── login.php          # Giriş sayfası
├── register.php       # Kayıt sayfası
├── logout.php         # Çıkış işlemi
├── venues.php         # Mekan listesi
├── venue-detail.php   # Mekan detay ve check-in
├── my-checkins.php    # Kullanıcı check-in geçmişi
├── leaderboard.php    # Haftalık liderlik tablosu
└── README.md          # Bu dosya
```

## Veritabanı Şeması

- **users:** Kullanıcı bilgileri
- **venues:** Mekan bilgileri
- **checkins:** Check-in kayıtları
- **admin_logs:** Admin işlem logları
- **settings:** Sistem ayarları

Detaylı şema için `database/schema.sql` dosyasına bakın.

## Sorun Giderme

### Veritabanı Bağlantı Hatası
- `config/config.php` dosyasındaki veritabanı bilgilerini kontrol edin
- MySQL servisinin çalıştığından emin olun
- Veritabanının oluşturulduğundan emin olun

### Session Sorunları
- PHP session desteğinin aktif olduğundan emin olun
- `config/config.php` dosyasında `session_start()` çağrısının yapıldığını kontrol edin

### Zaman Dilimi Sorunları
- `config/config.php` dosyasında `date_default_timezone_set('Europe/Istanbul')` ayarını kontrol edin

## Lisans

Bu proje eğitim amaçlı geliştirilmiştir.

## Destek

Sorularınız için: admin@uptag.com (varsayılan iletişim e-postası, admin panelinden değiştirilebilir)
