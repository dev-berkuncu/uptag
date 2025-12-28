-- Avatar ve Banner kolonları ekle
ALTER TABLE `users` 
ADD COLUMN `avatar` varchar(255) DEFAULT NULL AFTER `banned_until`,
ADD COLUMN `banner` varchar(255) DEFAULT NULL AFTER `avatar`;
