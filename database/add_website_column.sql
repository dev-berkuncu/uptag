-- Venues tablosuna website kolonu ekle (Facebrowser linki için)
ALTER TABLE venues ADD COLUMN website VARCHAR(255) DEFAULT NULL AFTER address;
