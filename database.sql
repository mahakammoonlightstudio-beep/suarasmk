-- ============================================================
-- DATABASE SCHEMA — Anonymous School Organization Rating System
-- ============================================================
-- A) Untuk database BARU: jalankan seluruh file ini sekali.
-- B) Untuk database LAMA (sudah ada tabel ratings tanpa
--    voter_id): jalankan hanya blok UPGRADE di bagian bawah.
-- ============================================================

CREATE DATABASE IF NOT EXISTS school_rating
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE school_rating;

-- -----------------------------------------------------------
-- Tabel: organizations — daftar organisasi yang akan dinilai
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS organizations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    logo_emoji  VARCHAR(10)  NOT NULL DEFAULT '🏛️',
    category    VARCHAR(50)  NOT NULL DEFAULT 'Organisasi',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Tabel: ratings — penilaian anonim dari pengunjung
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ratings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    org_id     INT          NOT NULL,
    rating     TINYINT      NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT         NULL,
    voter_ip   VARCHAR(45)  NOT NULL,
    voter_id   VARCHAR(32)  NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_org_id (org_id),
    INDEX idx_voter_ip (voter_ip),
    INDEX idx_voter_id (voter_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===========================================================
-- BLOK UPGRADE — khusus database LAMA yang tabel `ratings`-nya
-- belum punya kolom `voter_id`. Jalankan SATU varian saja.
-- (Untuk database baru, lewati blok ini — kolom sudah ada.)
-- ===========================================================
-- Varian 1 — MariaDB / MySQL 8.0.29+ (mendukung IF NOT EXISTS):
--
-- ALTER TABLE ratings
--     ADD COLUMN IF NOT EXISTS voter_id VARCHAR(32) NULL DEFAULT NULL AFTER voter_ip;
-- ALTER TABLE ratings ADD INDEX IF NOT EXISTS idx_voter_id (voter_id);
--
-- Varian 2 — MySQL 8 standar / versi lama:
--
-- ALTER TABLE ratings ADD COLUMN voter_id VARCHAR(32) NULL DEFAULT NULL AFTER voter_ip;
-- ALTER TABLE ratings ADD INDEX idx_voter_id (voter_id);
-- ===========================================================

-- -----------------------------------------------------------
-- Contoh data organisasi (untuk database baru)
-- -----------------------------------------------------------
INSERT INTO organizations (name, description, logo_emoji, category) VALUES
('OSIS', 'Organisasi Siswa Intra Sekolah — wadah kegiatan siswa di sekolah.', '📢', 'Organisasi'),
('MPK', 'Majelis Perwakilan Kelas — pengawas kinerja OSIS.', '⚖️', 'Organisasi'),
('Pramuka', 'Kegiatan kepramukaan untuk membentuk karakter dan kemandirian.', '⛺', 'Ekstrakurikuler'),
('Rohis', 'Rohani Islam — kegiatan keagamaan dan kajian Islam.', '🕌', 'Ekstrakurikuler'),
('PMR', 'Palang Merah Remaja — aksi sosial dan pertolongan pertama.', '🩺', 'Ekstrakurikuler'),
('Paskibra', 'Pasukan Pengibar Bendera — latihan baris-berbaris dan upacara.', '🇮🇩', 'Ekstrakurikuler');
