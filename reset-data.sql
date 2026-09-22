-- ============================================================
-- RESET DATA — Hapus OSIS & MPK LAMA dari database live
-- Database: if0_41894075_suarasmk (InfinityFree)
-- ============================================================
-- ⚠️  PERHATIAN! Baca dulu sebelum menjalankan:
--
-- Di database kamu sekarang ada 2 organisasi:
--   id 1 = OSIS  (dengan 8 penilaian lama)
--   id 2 = MPK   (dengan 8 penilaian lama)
--
-- ATURAN: menghapus organisasi OTOMATIS menghapus SEMUA
-- penilaiannya (_foreign key ON DELETE CASCADE_). Data yang
-- sudah terhapus TIDAK BISA dikembalikan. Export dulu kalau
-- ragu: Admin Panel → Export CSV.
--
-- Jalankan di: phpMyAdmin → pilih database → tab SQL
-- ============================================================

-- ---- OPSI A: Hapus OSIS & MPK lama BESERTA semua penilaiannya ----
-- (dipakai kalau mau mulai bersamaan dengan data baru dari Admin
--  Panel → Tambah Organisasi, atau seed 6 organisasi di bawah)
DELETE FROM `organizations` WHERE `name` IN ('OSIS', 'MPK');


-- ---- OPSI B (ALTERNATIF): Pertahankan penilaian, hanya reset isi ----
-- Kalau kamu TIDAK mau kehilangan 16 penilaian yang sudah masuk,
-- jangan jalankan DELETE di atas. Cukup perbarui deskripsinya saja
-- (hapus tanda "--" di baris bawah, lalu jalankan):
--
-- UPDATE `organizations` SET `description` = 'Organisasi Siswa Intra Sekolah — wadah kegiatan siswa di sekolah.', `logo_emoji` = '📢', `category` = 'Organisasi' WHERE `name` = 'OSIS';
-- UPDATE `organizations` SET `description` = 'Majelis Perwakilan Kelas — pengawas kinerja OSIS.', `logo_emoji` = '⚖️', `category` = 'Organisasi' WHERE `name` = 'MPK';


-- ---- OPSI C (BONUS): Seed 6 organisasi baru setelah Opsi A ----
-- Hapus tanda "--" di setiap baris bawah ini jika ingin langsung
-- terisi 6 organisasi (OSIS & MPK baru + 4 ekskul) tanpa lewat Admin:
--
-- INSERT INTO `organizations` (`name`, `description`, `logo_emoji`, `category`) VALUES
-- ('OSIS', 'Organisasi Siswa Intra Sekolah — wadah kegiatan siswa di sekolah.', '📢', 'Organisasi'),
-- ('MPK', 'Majelis Perwakilan Kelas — pengawas kinerja OSIS.', '⚖️', 'Organisasi'),
-- ('Pramuka', 'Kegiatan kepramukaan untuk membentuk karakter dan kemandirian.', '⛺', 'Ekstrakurikuler'),
-- ('Rohis', 'Rohani Islam — kegiatan keagamaan dan kajian Islam.', '🕌', 'Ekstrakurikuler'),
-- ('PMR', 'Palang Merah Remaja — aksi sosial dan pertolongan pertama.', '🩺', 'Ekstrakurikuler'),
-- ('Paskibra', 'Pasukan Pengibar Bendera — latihan baris-berbaris dan upacara.', '🇮🇩', 'Ekstrakurikuler');
