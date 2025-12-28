# Uptag - Kurulum Talimatları

## Hızlı Kurulum

### 1. Dosyaları Yerleştirin

Proje dosyalarını web sunucunuzun root dizinine (örneğin `htdocs`, `www`, `public_html`) kopyalayın.

### 2. Veritabanını Oluşturun

MySQL'de yeni bir veritabanı oluşturun:

```sql
CREATE DATABASE uptag CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Veritabanı Şemasını İçe Aktarın

`database/schema.sql` dosyasını içe aktarın:

**phpMyAdmin ile:**
1. phpMyAdmin'i açın
2. `uptag` veritabanını seçin
3. "Import" sekmesine gidin
4. `database/schema.sql` dosyasını seçin
5. "Go" butonuna tıklayın

**Komut satırı ile:**
```bash
mysql -u root -p uptag < database/schema.sql
```

### 4. Yapılandırmayı Düzenleyin

`config/config.php` dosyasını açın ve aşağıdaki ayarları düzenleyin:

```php
// Veritabanı ayarları
define('DB_HOST', 'localhost');      // Veritabanı sunucu adresi
define('DB_NAME', 'uptag');          // Veritabanı adı
define('DB_USER', 'root');           // Veritabanı kullanıcı adı
define('DB_PASS', '');               // Veritabanı şifresi

// Site ayarları
define('BASE_URL', 'http://localhost/uptag'); // Kendi URL'nizi girin
```

### 5. Admin Şifresini Değiştirin (ÖNEMLİ!)

Varsayılan admin şifresi `admin123`'tür. Üretim ortamında mutlaka değiştirin!

**Yöntem 1: Setup Script ile**
```
http://localhost/uptag/setup/admin-password.php?password=YENI_SIFRE
```

**Yöntem 2: SQL ile**
```sql
UPDATE users SET password_hash = '$2y$10$YENI_HASH_BURAYA' WHERE username = 'admin';
```

**Yöntem 3: PHP ile Hash Oluştur**
```php
<?php
echo password_hash('YENI_SIFRE', PASSWORD_DEFAULT);
?>
```

### 6. Test Edin

1. Tarayıcınızda `http://localhost/uptag` adresine gidin
2. "Kayıt Ol" ile yeni bir kullanıcı oluşturun
3. Giriş yapın
4. Admin paneli için `admin` / `admin123` ile giriş yapın

## Sorun Giderme

### Veritabanı Bağlantı Hatası

**Hata:** `Veritabanı bağlantı hatası: ...`

**Çözüm:**
- `config/config.php` dosyasındaki veritabanı bilgilerini kontrol edin
- MySQL servisinin çalıştığından emin olun
- Veritabanı kullanıcısının gerekli izinlere sahip olduğundan emin olun

### Session Hatası

**Hata:** Session çalışmıyor

**Çözüm:**
- PHP'de `session` extension'ının aktif olduğundan emin olun
- `php.ini` dosyasında `session.save_path` ayarını kontrol edin
- Web sunucusunun session dizinine yazma izni olduğundan emin olun

### Zaman Dilimi Hatası

**Hata:** Tarih/saat yanlış görünüyor

**Çözüm:**
- `config/config.php` dosyasında `date_default_timezone_set('Europe/Istanbul')` ayarının olduğundan emin olun
- PHP'nin zaman dilimi ayarını kontrol edin

### 404 Hatası

**Hata:** Sayfa bulunamadı

**Çözüm:**
- `.htaccess` dosyasının mevcut olduğundan emin olun
- Apache'de `mod_rewrite` modülünün aktif olduğundan emin olun
- `BASE_URL` ayarının doğru olduğundan emin olun

## Geliştirme Ortamı

### XAMPP/WAMP/MAMP

1. Dosyaları `htdocs` (XAMPP) veya `www` (WAMP) dizinine kopyalayın
2. Apache ve MySQL'i başlatın
3. `http://localhost/uptag` adresine gidin

### Docker (Opsiyonel)

```dockerfile
FROM php:7.4-apache
RUN docker-php-ext-install pdo pdo_mysql
COPY . /var/www/html/
```

## Üretim Ortamı

### Güvenlik Kontrol Listesi

- [ ] Admin şifresini değiştirin
- [ ] `config/config.php` dosyasındaki hata raporlamayı kapatın
- [ ] `.htaccess` dosyasındaki güvenlik ayarlarını kontrol edin
- [ ] Veritabanı kullanıcısı için sadece gerekli izinleri verin
- [ ] HTTPS kullanın
- [ ] Düzenli yedekleme yapın

### Performans

- PHP OPcache'i aktif edin
- MySQL query cache'i aktif edin
- Gerekirse CDN kullanın

## Destek

Sorularınız için: admin@uptag.com

