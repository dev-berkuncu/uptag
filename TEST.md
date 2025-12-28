# Uptag - Test Rehberi

## Hızlı Test Senaryosu

### 1. Veritabanı Kurulumunu Kontrol Et

```bash
# MySQL'e bağlan
mysql -u root -p

# Veritabanının oluşturulduğunu kontrol et
SHOW DATABASES;
USE uptag;
SHOW TABLES;
```

Tablolar görünmeli:
- users
- venues
- checkins
- admin_logs
- settings

### 2. Admin Kullanıcısını Kontrol Et

```sql
SELECT username, email, is_admin, is_active FROM users WHERE username = 'admin';
```

Sonuç:
- username: admin
- email: admin@uptag.com
- is_admin: 1
- is_active: 1

### 3. Web Arayüzünü Test Et

#### Adım 1: Ana Sayfayı Aç
```
http://localhost/uptag/index.php
```
veya
```
http://localhost/uptag/
```

**Beklenen:** Ana sayfa görünmeli, "Giriş Yap" ve "Kayıt Ol" butonları olmalı.

#### Adım 2: Yeni Kullanıcı Kaydı
1. "Kayıt Ol" butonuna tıkla
2. Formu doldur:
   - Kullanıcı adı: `testuser`
   - E-posta: `test@example.com`
   - Şifre: `test123`
   - Şifre Tekrar: `test123`
3. "Kayıt Ol" butonuna tıkla

**Beklenen:** "Kayıt başarılı! Giriş yapabilirsiniz." mesajı görünmeli.

#### Adım 3: Kullanıcı Girişi
1. "Giriş Yap" butonuna tıkla
2. Formu doldur:
   - Kullanıcı adı: `testuser`
   - Şifre: `test123`
3. "Giriş Yap" butonuna tıkla

**Beklenen:** Ana sayfaya yönlendirilmeli, üst menüde kullanıcı adı görünmeli.

#### Adım 4: Admin Paneli - Mekan Ekleme
1. Admin ile giriş yap: `admin` / `admin123`
2. "Admin Panel" linkine tıkla
3. "Mekan Yönetimi" kartına tıkla
4. "Yeni Mekan Ekle" butonuna tıkla
5. Formu doldur:
   - Mekan Adı: `Test Kafe`
   - Açıklama: `Güzel bir kafe`
   - Adres: `Test Mahallesi, Test Sokak No:1`
6. "Oluştur" butonuna tıkla

**Beklenen:** Mekan başarıyla oluşturulmalı ve listede görünmeli.

#### Adım 5: Kullanıcı - Mekan Listesi
1. Test kullanıcısı ile giriş yap
2. "Mekanlar" menüsüne tıkla
3. Eklediğiniz "Test Kafe" görünmeli

**Beklenen:** Mekan kartı görünmeli, "Detay & Check-in" butonu olmalı.

#### Adım 6: Check-in Yapma
1. "Test Kafe" mekanının "Detay & Check-in" butonuna tıkla
2. Check-in formunda:
   - Not (opsiyonel): `İlk check-in!`
3. "Check-in Yap" butonuna tıkla

**Beklenen:** "Check-in başarıyla oluşturuldu!" mesajı görünmeli.

#### Adım 7: Check-in Geçmişi
1. "Check-in Geçmişim" menüsüne tıkla
2. Yaptığınız check-in görünmeli

**Beklenen:** Tabloda "Test Kafe" mekanı, not ve tarih görünmeli.

#### Adım 8: Liderlik Tablosu
1. "Liderlik Tablosu" menüsüne tıkla
2. "Top Kullanıcılar" sekmesinde kendinizi görmelisiniz
3. "Top Mekanlar" sekmesinde "Test Kafe" görünmeli

**Beklenen:** 
- Top Kullanıcılar: `testuser` - 1 check-in
- Top Mekanlar: `Test Kafe` - 1 check-in

#### Adım 9: Cooldown Testi
1. Aynı mekana (Test Kafe) tekrar check-in yapmayı deneyin
2. Hemen ardından tekrar check-in yapmayı deneyin

**Beklenen:** "Aynı mekana tekrar check-in yapmak için X dakika beklemeniz gerekiyor." hatası görünmeli.

#### Adım 10: Admin - Check-in Yönetimi
1. Admin ile giriş yap
2. Admin Panel > "Check-in Yönetimi"
3. Yaptığınız check-in görünmeli
4. Check-in'i işaretle veya leaderboard'dan hariç tut

**Beklenen:** Check-in listelenmeli, işlemler çalışmalı.

#### Adım 11: Admin - Kullanıcı Yönetimi
1. Admin Panel > "Kullanıcı Yönetimi"
2. `testuser` kullanıcısını bul
3. "Detay" butonuna tıkla
4. Kullanıcı bilgileri ve check-in geçmişi görünmeli

**Beklenen:** Kullanıcı detayları, check-in sayısı ve geçmiş görünmeli.

#### Adım 12: Admin - Sistem Ayarları
1. Admin Panel > "Sistem Ayarları"
2. Ayarları değiştir (örneğin Top Kullanıcı sayısını 10 yap)
3. "Ayarları Kaydet" butonuna tıkla

**Beklenen:** Ayarlar kaydedilmeli, leaderboard'da değişiklik yansımalı.

## Otomatik Test Scripti (Opsiyonel)

Basit bir PHP test scripti oluşturabiliriz. İsterseniz hazırlayabilirim.

## Yaygın Sorunlar ve Çözümleri

### Sorun: "Veritabanı bağlantı hatası"
**Çözüm:** `config/config.php` dosyasındaki veritabanı bilgilerini kontrol edin.

### Sorun: "Sayfa bulunamadı (404)"
**Çözüm:** 
- `BASE_URL` ayarını kontrol edin
- Apache mod_rewrite'in aktif olduğundan emin olun
- `.htaccess` dosyasının mevcut olduğundan emin olun

### Sorun: "Session hatası"
**Çözüm:**
- PHP session extension'ının aktif olduğundan emin olun
- `php.ini` dosyasında session ayarlarını kontrol edin

### Sorun: "Admin girişi çalışmıyor"
**Çözüm:**
- Veritabanında admin kullanıcısının olduğundan emin olun
- Şifre hash'ini kontrol edin: `setup/admin-password.php` scriptini kullanın

## Test Checklist

- [ ] Veritabanı bağlantısı çalışıyor
- [ ] Kullanıcı kaydı çalışıyor
- [ ] Kullanıcı girişi çalışıyor
- [ ] Admin girişi çalışıyor
- [ ] Mekan ekleme çalışıyor
- [ ] Mekan listeleme çalışıyor
- [ ] Mekan arama çalışıyor
- [ ] Check-in oluşturma çalışıyor
- [ ] Check-in geçmişi görüntüleme çalışıyor
- [ ] Cooldown kontrolü çalışıyor
- [ ] Rate limit kontrolü çalışıyor
- [ ] Leaderboard hesaplama çalışıyor
- [ ] Admin - Kullanıcı yönetimi çalışıyor
- [ ] Admin - Mekan yönetimi çalışıyor
- [ ] Admin - Check-in yönetimi çalışıyor
- [ ] Admin - Sistem ayarları çalışıyor
- [ ] Admin - İşlem logları çalışıyor

## Performans Testi

1. **Çoklu Kullanıcı Testi:**
   - Birden fazla kullanıcı oluşturun
   - Her kullanıcı farklı mekanlara check-in yapsın
   - Leaderboard'un doğru hesaplandığını kontrol edin

2. **Haftalık Hesaplama Testi:**
   - Farklı haftalardan check-in'ler oluşturun
   - Leaderboard'un sadece bu haftanın check-in'lerini gösterdiğini kontrol edin

3. **Güvenlik Testi:**
   - Cooldown süresini test edin
   - Rate limit'i test edin
   - Admin paneli erişim kontrolünü test edin

