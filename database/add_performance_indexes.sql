-- Performance Indexes for Uptag
-- Takip sorgularını hızlandırmak için indeksler

-- user_follows tablosu için indeksler
-- (Eğer indeksler zaten varsa hata vermez, IF NOT EXISTS kullanılıyor)

-- Takip eden kullanıcılar için indeks (follower_id)
CREATE INDEX IF NOT EXISTS idx_user_follows_follower ON user_follows(follower_id);

-- Takip edilen kullanıcılar için indeks (following_id)  
CREATE INDEX IF NOT EXISTS idx_user_follows_following ON user_follows(following_id);

-- Birleşik indeks (her iki yöne de hızlı sorgu için)
CREATE INDEX IF NOT EXISTS idx_user_follows_both ON user_follows(follower_id, following_id);
