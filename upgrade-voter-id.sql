-- ============================================================
-- UPGRADE DATABASE — if0_41894075_suarasmk (InfinityFree)
-- ============================================================
-- Jalankan file ini DI phpMyAdmin (tab SQL) SATU KALI saja.
-- Fungsi: menambah kolom voter_id untuk anti-duplikat per
-- perangkat (1 suara per hari per HP/browser).
-- Kompatibel MariaDB 10+ / 11 (server kamu: MariaDB 11.4).
-- ============================================================

-- Tambah kolom voter_id (32 karakter hex acak dari cookie browser)
ALTER TABLE `ratings`
    ADD COLUMN IF NOT EXISTS `voter_id` VARCHAR(32) NULL DEFAULT NULL AFTER `voter_ip`;

-- Index agar pengecekan duplikat cepat
ALTER TABLE `ratings`
    ADD INDEX IF NOT EXISTS `idx_voter_id` (`voter_id`);

-- Opsional tapi disarankan: perlebar kolom kategori agar
-- CRUD organisasi di panel admin lebih fleksibel
ALTER TABLE `organizations`
    MODIFY `category` VARCHAR(50) NOT NULL DEFAULT 'Organisasi';
