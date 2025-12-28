-- Repost'lara beğeni ve yorum desteği ekle
-- post_likes ve post_comments tablolarına repost_id kolonu ekleniyor

-- post_likes tablosuna repost_id ekle
ALTER TABLE `post_likes` 
ADD COLUMN `repost_id` INT(11) NULL DEFAULT NULL AFTER `checkin_id`,
ADD KEY `repost_id` (`repost_id`);

-- Foreign key ekle (opsiyonel - cascade delete için)
-- ALTER TABLE `post_likes` ADD CONSTRAINT `post_likes_repost_fk` FOREIGN KEY (`repost_id`) REFERENCES `post_reposts` (`id`) ON DELETE CASCADE;

-- post_comments tablosuna repost_id ekle
ALTER TABLE `post_comments` 
ADD COLUMN `repost_id` INT(11) NULL DEFAULT NULL AFTER `checkin_id`,
ADD KEY `repost_id` (`repost_id`);

-- Foreign key ekle (opsiyonel - cascade delete için)
-- ALTER TABLE `post_comments` ADD CONSTRAINT `post_comments_repost_fk` FOREIGN KEY (`repost_id`) REFERENCES `post_reposts` (`id`) ON DELETE CASCADE;

-- Unique constraint güncelle (repost_id dahil)
-- Aynı kullanıcı aynı repost'u sadece bir kez beğenebilir
ALTER TABLE `post_likes` DROP INDEX `unique_like`;
ALTER TABLE `post_likes` ADD UNIQUE KEY `unique_like` (`user_id`, `checkin_id`, `repost_id`);
