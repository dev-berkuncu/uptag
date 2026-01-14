-- Karakter bilgileri için users tablosuna ek kolonlar
-- Bu dosyayı MySQL'de çalıştırın

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS gta_character_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS gta_character_name VARCHAR(100) DEFAULT NULL;

-- Index ekle (performans için)
CREATE INDEX IF NOT EXISTS idx_gta_character_id ON users(gta_character_id);
