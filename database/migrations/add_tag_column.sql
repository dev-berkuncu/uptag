-- Add tag column to users table for @ mentions
-- Run this migration if the column doesn't exist

ALTER TABLE `users` ADD COLUMN `tag` VARCHAR(30) DEFAULT NULL AFTER `username`;
ALTER TABLE `users` ADD UNIQUE KEY `tag` (`tag`);
