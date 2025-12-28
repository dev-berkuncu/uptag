-- Quote Repost için migration
-- post_reposts tablosuna quote kolonu ekle ve unique constraint'i kaldır

-- Quote kolonu ekle
ALTER TABLE `post_reposts` 
ADD COLUMN `quote` TEXT NULL DEFAULT NULL AFTER `checkin_id`;

-- Unique constraint'i kaldır (artık aynı user aynı postu birden fazla kez repost edebilir)
ALTER TABLE `post_reposts` 
DROP INDEX `unique_repost`;
