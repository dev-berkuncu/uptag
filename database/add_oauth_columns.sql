-- OAuth için users tablosuna ek kolonlar
-- Bu dosyayı MySQL'de çalıştırın

-- GTA World kullanıcı bilgileri
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS gta_user_id INT UNIQUE DEFAULT NULL,
ADD COLUMN IF NOT EXISTS gta_username VARCHAR(100) DEFAULT NULL;

-- password_hash nullable yapma (OAuth kullanıcıları için)
ALTER TABLE users 
MODIFY COLUMN password_hash VARCHAR(255) DEFAULT NULL;
