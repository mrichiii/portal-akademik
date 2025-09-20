-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 20 Sep 2025 pada 13.59
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `portalsia`
--

DELIMITER $$
--
-- Prosedur
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `insert_matkul` (IN `p_kode` CHAR(8), IN `p_matakuliah` VARCHAR(100), IN `p_dosen` VARCHAR(100), IN `p_wp` ENUM('W','P'), IN `p_sks` INT, IN `p_semester` TINYINT, IN `p_prodi` VARCHAR(10))   BEGIN
    DECLARE v_kelas VARCHAR(20);
    DECLARE v_count INT;
    
    -- Hitung berapa banyak kelas dengan kode yang sama sudah ada
    SELECT COUNT(*) INTO v_count FROM matkul WHERE kode = p_kode;
    
    -- Set nilai kelas dengan format kode-urutan (dimulai dari 1)
    SET v_kelas = CONCAT(p_kode, '-', (v_count + 1));
    
    -- Insert data baru
    INSERT INTO matkul (kode, matakuliah, dosen, kelas, wp, sks, semester, prodi)
    VALUES (p_kode, p_matakuliah, p_dosen, v_kelas, p_wp, p_sks, p_semester, p_prodi);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `insert_matkul_with_classes` (IN `p_kode` CHAR(8), IN `p_matakuliah` VARCHAR(100), IN `p_dosen` VARCHAR(100), IN `p_wp` ENUM('W','P'), IN `p_sks` INT, IN `p_semester` TINYINT, IN `p_prodi` VARCHAR(10))   BEGIN
    DECLARE i INT DEFAULT 1;
    
    -- First delete any existing records with this course code
    DELETE FROM matkul WHERE kode = p_kode;
    
    -- Insert 5 records with different class suffixes
    WHILE i <= 5 DO
        INSERT INTO matkul (kode, matakuliah, dosen, kelas, wp, sks, semester, prodi, created_at, updated_at)
        VALUES (p_kode, p_matakuliah, p_dosen, CONCAT(p_kode, '-', i), p_wp, p_sks, p_semester, p_prodi, NOW(), NOW());
        
        SET i = i + 1;
    END WHILE;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_nilai`
--

CREATE TABLE `data_nilai` (
  `id` int(11) NOT NULL,
  `mahasiswa_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `matkul_id` int(11) NOT NULL,
  `nilai_tugas` decimal(5,2) DEFAULT 0.00,
  `nilai_uts` decimal(5,2) DEFAULT 0.00,
  `nilai_uas` decimal(5,2) DEFAULT 0.00,
  `nilai_akhir` decimal(5,2) GENERATED ALWAYS AS (`nilai_tugas` * 0.3 + `nilai_uts` * 0.3 + `nilai_uas` * 0.4) STORED,
  `grade` char(2) GENERATED ALWAYS AS (case when `nilai_akhir` >= 85 then 'A' when `nilai_akhir` >= 80 then 'A-' when `nilai_akhir` >= 75 then 'B+' when `nilai_akhir` >= 70 then 'B' when `nilai_akhir` >= 65 then 'B-' when `nilai_akhir` >= 60 then 'C+' when `nilai_akhir` >= 55 then 'C' when `nilai_akhir` >= 40 then 'D' else 'E' end) STORED,
  `created_by` int(11) DEFAULT NULL COMMENT 'User yang menginput nilai',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `data_nilai`
--

INSERT INTO `data_nilai` (`id`, `mahasiswa_id`, `jadwal_id`, `matkul_id`, `nilai_tugas`, `nilai_uts`, `nilai_uas`, `created_by`, `created_at`, `updated_at`) VALUES
(31, 4, 22, 73, 90.00, 90.00, 90.00, 93, '2025-06-09 06:41:19', '2025-06-09 06:41:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `dosen`
--

CREATE TABLE `dosen` (
  `nip` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `pangkat` varchar(50) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `nohp` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `prodi` varchar(10) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `dosen`
--

INSERT INTO `dosen` (`nip`, `nama`, `pangkat`, `alamat`, `nohp`, `email`, `prodi`, `user_id`, `foto_profil`) VALUES
('0702239005', 'Afifudin, M.Kom', 'Dosen Tidak Tetap', '', '', '', 'si', 93, 'uploads/profil_dosen/dosen_0702239005_1749453365.jpg'),
('0703239001', 'Faisal Muhammad, S.Si., M.Mat', 'Dosen Tidak Tetap', '', '', '', 'si', 95, NULL),
('0703239009', 'Rahmadani Hasibuan, M.E', 'Dosen Tidak Tetap', '', '', '', 'si', NULL, NULL),
('198907102018012002', 'Raissa Amanda Putri, S.Kom, M.TI', 'Dosen Tetap', '', '', '', 'si', 100, NULL),
('199001312019031019', 'Muhammad Dedi Irawan, S.T M.Kom', 'Dosen Tetap', '', '', '', 'si', 97, NULL),
('199008092019031014', 'Adnan Buyung Nasution, M.Kom', 'Dosen Tetap', '', '', '', 'si', 94, NULL),
('199205052020121023', 'Muhammad Ikhsan Rifki, M.T.', 'Dosen Tetap', '', '', '', 'si', 98, NULL),
('199510282022032001', 'Fathiya Nasyifa Sibarani, S.Kom., M.Kom.', 'Dosen Tetap', '', '', '', 'si', 96, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_kuliah`
--

CREATE TABLE `jadwal_kuliah` (
  `id` int(11) NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
  `waktu_mulai` time NOT NULL,
  `waktu_selesai` time NOT NULL,
  `matkul_id` int(11) NOT NULL,
  `ruangan` varchar(20) DEFAULT NULL,
  `dosen_nip` varchar(20) NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `tahun_ajaran` varchar(9) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal_kuliah`
--

INSERT INTO `jadwal_kuliah` (`id`, `hari`, `waktu_mulai`, `waktu_selesai`, `matkul_id`, `ruangan`, `dosen_nip`, `semester`, `tahun_ajaran`) VALUES
(21, 'Kamis', '12:00:00', '13:30:00', 48, 'FST-305', '199008092019031014', 'Genap', '2024/2025'),
(22, 'Senin', '10:30:00', '12:45:00', 73, 'COM-F', '0702239005', 'Genap', '2024/2025'),
(23, 'Senin', '13:30:00', '15:45:00', 66, 'COM-D', '0702239005', 'Genap', '2024/2025'),
(24, 'Selasa', '13:30:00', '15:45:00', 59, 'COM-F', '199001312019031019', 'Genap', '2024/2025'),
(25, 'Rabu', '00:00:00', '13:30:00', 80, 'FST-303', '198907102018012002', 'Genap', '2024/2025'),
(26, 'Rabu', '09:00:00', '10:30:00', 97, 'FST-310', '199510282022032001', 'Genap', '2024/2025'),
(27, 'Rabu', '13:30:00', '15:00:00', 52, 'ADT-FST', '199205052020121023', 'Genap', '2024/2025'),
(28, 'Kamis', '13:30:00', '15:00:00', 87, 'FST-304', '0703239001', 'Genap', '2024/2025'),
(29, 'Jumat', '14:15:00', '15:45:00', 102, 'FST-306', '0703239009', 'Genap', '2024/2025');

-- --------------------------------------------------------

--
-- Struktur dari tabel `krs`
--

CREATE TABLE `krs` (
  `id` int(11) NOT NULL,
  `mahasiswa_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `tahun_ajaran` varchar(9) NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `status` enum('aktif','batal') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `krs`
--

INSERT INTO `krs` (`id`, `mahasiswa_id`, `jadwal_id`, `tahun_ajaran`, `semester`, `status`, `created_at`) VALUES
(21, 1, 22, '2024/2025', 'Genap', 'aktif', '2025-06-05 11:03:27'),
(22, 1, 23, '2024/2025', 'Genap', 'aktif', '2025-06-05 11:03:40'),
(23, 1, 24, '2024/2025', 'Genap', 'aktif', '2025-06-05 11:03:43'),
(24, 1, 25, '2024/2025', 'Genap', 'aktif', '2025-06-05 11:03:46'),
(25, 1, 26, '2024/2025', 'Genap', 'aktif', '2025-06-05 11:03:51'),
(26, 1, 27, '2024/2025', 'Genap', 'aktif', '2025-06-05 11:03:53'),
(27, 1, 21, '2024/2025', 'Genap', 'aktif', '2025-06-05 11:03:58'),
(28, 1, 28, '2024/2025', 'Genap', 'aktif', '2025-06-05 11:04:00'),
(29, 1, 29, '2024/2025', 'Genap', 'aktif', '2025-06-05 11:04:02'),
(30, 4, 23, '2024/2025', 'Genap', 'aktif', '2025-06-08 16:37:57'),
(31, 4, 22, '2024/2025', 'Genap', 'aktif', '2025-06-08 16:38:04'),
(32, 4, 24, '2024/2025', 'Genap', 'aktif', '2025-06-08 16:38:08'),
(33, 4, 25, '2024/2025', 'Genap', 'aktif', '2025-06-08 16:38:12'),
(34, 4, 26, '2024/2025', 'Genap', 'aktif', '2025-06-08 16:38:15'),
(35, 4, 27, '2024/2025', 'Genap', 'aktif', '2025-06-08 16:38:26'),
(36, 4, 21, '2024/2025', 'Genap', 'aktif', '2025-06-08 16:38:45'),
(37, 4, 28, '2024/2025', 'Genap', 'aktif', '2025-06-08 16:38:49'),
(38, 4, 29, '2024/2025', 'Genap', 'aktif', '2025-06-08 16:38:52');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `id` int(11) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `prodi` varchar(10) NOT NULL,
  `alamat` text DEFAULT NULL,
  `nohp` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `semester_aktif` int(11) DEFAULT 1 COMMENT 'Semester aktif mahasiswa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mahasiswa`
--

INSERT INTO `mahasiswa` (`id`, `nim`, `nama`, `prodi`, `alamat`, `nohp`, `email`, `user_id`, `foto_profil`, `semester_aktif`) VALUES
(1, '07022310403', 'Muhammad Richie Hadiansah', 'si', 'Dusun 5 GG Kaplingan', '082172812700', 'mrichieh123@gmail.com', 17, 'uploads/profil_mahasiswa/mahasiswa_07022310403_1749557027.png', 4),
(4, '0702232120', 'Mutia Herman', 'si', 'Denai Butot', '083848079481', '', 63, NULL, 4);

-- --------------------------------------------------------

--
-- Struktur dari tabel `matkul`
--

CREATE TABLE `matkul` (
  `id` int(11) NOT NULL,
  `kode` char(8) DEFAULT NULL,
  `matakuliah` varchar(100) DEFAULT NULL,
  `dosen` varchar(100) DEFAULT NULL,
  `kelas` varchar(20) DEFAULT NULL,
  `wp` enum('W','P') DEFAULT NULL,
  `sks` int(11) DEFAULT NULL,
  `semester` tinyint(4) DEFAULT NULL,
  `prodi` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `matkul`
--

INSERT INTO `matkul` (`id`, `kode`, `matakuliah`, `dosen`, `kelas`, `wp`, `sks`, `semester`, `prodi`, `created_at`, `updated_at`) VALUES
(36, '01070210', 'Sains dan Teknologi Lingkungan', 'Muhammad Ikhsan Rifki, M.T.', '01070210-1', 'W', 2, 4, 'si', '2025-06-05 03:57:14', '2025-06-05 03:57:14'),
(37, '01070212', 'Analisis dan Perancangan Sistem Informasi', 'Muhammad Dedi Irawan, S.T M.Kom', '01070212-1', 'W', 4, 4, 'si', '2025-06-05 03:58:33', '2025-06-05 03:58:33'),
(38, '01070217', 'Pemrograman Berbasis Web Dasar', 'Afifudin, M.Kom', '01070217-1', 'W', 3, 4, 'si', '2025-06-05 03:59:01', '2025-06-05 03:59:01'),
(39, '01070227', 'Pemograman Berbasis Objek', 'Afifudin, M.Kom', '01070227-1', 'W', 4, 4, 'si', '2025-06-05 03:59:22', '2025-06-05 03:59:22'),
(40, '01070234', 'Tata Kelola Teknologi Informasi (IT Governance)', 'Rahmadani Hasibuan, M.E', '01070234-1', 'P', 2, 6, 'si', '2025-06-05 04:00:00', '2025-06-05 04:28:46'),
(41, '01070266', 'Statistika dan Probabilitas', 'Faisal Muhammad, S.Si., M.Mat', '01070266-1', 'W', 2, 4, 'si', '2025-06-05 04:05:37', '2025-06-05 04:36:12'),
(44, '01070267', 'Enterprise Resource Planning (ERP)', 'Adnan Buyung Nasution, M.Kom', '01070267-1', 'W', 2, 4, 'si', '2025-06-05 04:07:46', '2025-06-05 04:09:16'),
(45, '01070267', 'Enterprise Resource Planning (ERP)', 'Adnan Buyung Nasution, M.Kom', '01070267-2', 'W', 2, 4, 'si', '2025-06-05 04:07:46', '2025-06-05 04:09:21'),
(46, '01070267', 'Enterprise Resource Planning (ERP)', 'Adnan Buyung Nasution, M.Kom', '01070267-3', 'W', 2, 4, 'si', '2025-06-05 04:07:46', '2025-06-05 04:09:24'),
(47, '01070267', 'Enterprise Resource Planning (ERP)', 'Adnan Buyung Nasution, M.Kom', '01070267-4', 'W', 2, 4, 'si', '2025-06-05 04:07:46', '2025-06-05 04:09:28'),
(48, '01070267', 'Enterprise Resource Planning (ERP)', 'Adnan Buyung Nasution, M.Kom', '01070267-5', 'W', 2, 4, 'si', '2025-06-05 04:07:46', '2025-06-05 04:09:09'),
(49, '01070210', 'Sains dan Teknologi Lingkungan', 'Muhammad Ikhsan Rifki, M.T.', '01070210-2', 'W', 2, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(50, '01070210', 'Sains dan Teknologi Lingkungan', 'Muhammad Ikhsan Rifki, M.T.', '01070210-3', 'W', 2, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(51, '01070210', 'Sains dan Teknologi Lingkungan', 'Muhammad Ikhsan Rifki, M.T.', '01070210-4', 'W', 2, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(52, '01070210', 'Sains dan Teknologi Lingkungan', 'Muhammad Ikhsan Rifki, M.T.', '01070210-5', 'W', 2, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(56, '01070212', 'Analisis dan Perancangan Sistem Informasi', 'Muhammad Dedi Irawan, S.T M.Kom', '01070212-2', 'W', 4, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(57, '01070212', 'Analisis dan Perancangan Sistem Informasi', 'Muhammad Dedi Irawan, S.T M.Kom', '01070212-3', 'W', 4, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(58, '01070212', 'Analisis dan Perancangan Sistem Informasi', 'Muhammad Dedi Irawan, S.T M.Kom', '01070212-4', 'W', 4, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(59, '01070212', 'Analisis dan Perancangan Sistem Informasi', 'Muhammad Dedi Irawan, S.T M.Kom', '01070212-5', 'W', 4, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(63, '01070217', 'Pemrograman Berbasis Web Dasar', 'Afifudin, M.Kom', '01070217-2', 'W', 3, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(64, '01070217', 'Pemrograman Berbasis Web Dasar', 'Afifudin, M.Kom', '01070217-3', 'W', 3, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(65, '01070217', 'Pemrograman Berbasis Web Dasar', 'Afifudin, M.Kom', '01070217-4', 'W', 3, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(66, '01070217', 'Pemrograman Berbasis Web Dasar', 'Afifudin, M.Kom', '01070217-5', 'W', 3, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(70, '01070227', 'Pemograman Berbasis Objek', 'Afifudin, M.Kom', '01070227-2', 'W', 4, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(71, '01070227', 'Pemograman Berbasis Objek', 'Afifudin, M.Kom', '01070227-3', 'W', 4, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(72, '01070227', 'Pemograman Berbasis Objek', 'Afifudin, M.Kom', '01070227-4', 'W', 4, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(73, '01070227', 'Pemograman Berbasis Objek', 'Afifudin, M.Kom', '01070227-5', 'W', 4, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(77, '01070234', 'Tata Kelola Teknologi Informasi (IT Governance)', 'Rahmadani Hasibuan, M.E', '01070234-2', 'P', 2, 6, 'si', '2025-06-05 04:09:53', '2025-06-05 04:28:57'),
(78, '01070234', 'Tata Kelola Teknologi Informasi (IT Governance)', 'Rahmadani Hasibuan, M.E', '01070234-3', 'P', 2, 6, 'si', '2025-06-05 04:09:53', '2025-06-05 04:29:08'),
(79, '01070234', 'Tata Kelola Teknologi Informasi (IT Governance)', 'Rahmadani Hasibuan, M.E', '01070234-4', 'P', 2, 6, 'si', '2025-06-05 04:09:53', '2025-06-05 04:29:21'),
(80, '01070234', 'Tata Kelola Teknologi Informasi (IT Governance)', 'Rahmadani Hasibuan, M.E', '01070234-5', 'P', 2, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:09:53'),
(84, '01070266', 'Statistika dan Probabilitas', 'Faisal Muhammad, S.Si., M.Mat', '01070266-2', 'W', 2, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:36:06'),
(85, '01070266', 'Statistika dan Probabilitas', 'Faisal Muhammad, S.Si., M.Mat', '01070266-3', 'W', 2, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:35:52'),
(86, '01070266', 'Statistika dan Probabilitas', 'Faisal Muhammad, S.Si., M.Mat', '01070266-4', 'W', 2, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:35:59'),
(87, '01070266', 'Statistika dan Probabilitas', 'Faisal Muhammad, S.Si., M.Mat', '01070266-5', 'W', 2, 4, 'si', '2025-06-05 04:09:53', '2025-06-05 04:35:44'),
(93, '01070226', 'Keamanan Aset Informasi', 'Fathiya Nasyifa Sibarani, S.Kom., M.Kom.', '01070226-1', 'P', 2, 6, 'si', '2025-06-05 04:21:52', '2025-06-05 04:25:44'),
(94, '01070226', 'Keamanan Aset Informasi', 'Fathiya Nasyifa Sibarani, S.Kom., M.Kom.', '01070226-2', 'P', 2, 6, 'si', '2025-06-05 04:21:52', '2025-06-05 04:27:45'),
(95, '01070226', 'Keamanan Aset Informasi', 'Fathiya Nasyifa Sibarani, S.Kom., M.Kom.', '01070226-3', 'P', 2, 6, 'si', '2025-06-05 04:21:52', '2025-06-05 04:27:55'),
(96, '01070226', 'Keamanan Aset Informasi', 'Fathiya Nasyifa Sibarani, S.Kom., M.Kom.', '01070226-4', 'P', 2, 6, 'si', '2025-06-05 04:21:52', '2025-06-05 04:28:05'),
(97, '01070226', 'Keamanan Aset Informasi', 'Fathiya Nasyifa Sibarani, S.Kom., M.Kom.', '01070226-5', 'P', 2, 6, 'si', '2025-06-05 04:21:52', '2025-06-05 04:28:15'),
(98, '01070270', 'Kewirausahaan (Technopreneurship)', 'Rahmadani Hasibuan, M.E', '01070270-1', 'P', 2, 4, 'si', '2025-06-05 04:39:42', '2025-06-05 04:39:42'),
(99, '01070270', 'Kewirausahaan (Technopreneurship)', 'Rahmadani Hasibuan, M.E', '01070270-2', 'P', 2, 4, 'si', '2025-06-05 04:39:42', '2025-06-05 04:39:42'),
(100, '01070270', 'Kewirausahaan (Technopreneurship)', 'Rahmadani Hasibuan, M.E', '01070270-3', 'P', 2, 4, 'si', '2025-06-05 04:39:42', '2025-06-05 04:39:42'),
(101, '01070270', 'Kewirausahaan (Technopreneurship)', 'Rahmadani Hasibuan, M.E', '01070270-4', 'P', 2, 4, 'si', '2025-06-05 04:39:42', '2025-06-05 04:39:42'),
(102, '01070270', 'Kewirausahaan (Technopreneurship)', 'Rahmadani Hasibuan, M.E', '01070270-5', 'P', 2, 4, 'si', '2025-06-05 04:39:42', '2025-06-05 04:39:42');

--
-- Trigger `matkul`
--
DELIMITER $$
CREATE TRIGGER `before_matkul_insert` BEFORE INSERT ON `matkul` FOR EACH ROW BEGIN
    DECLARE v_count INT;
    
    -- Hitung berapa banyak kelas dengan kode yang sama sudah ada
    SELECT COUNT(*) INTO v_count FROM matkul WHERE kode = NEW.kode;
    
    -- Set nilai kelas dengan format kode-urutan (dimulai dari 1)
    SET NEW.kelas = CONCAT(NEW.kode, '-', (v_count + 1));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `program_studi`
--

CREATE TABLE `program_studi` (
  `kode_prodi` varchar(10) NOT NULL,
  `nama_prodi` varchar(100) NOT NULL,
  `fakultas` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `program_studi`
--

INSERT INTO `program_studi` (`kode_prodi`, `nama_prodi`, `fakultas`) VALUES
('bio', 'Biologi', 'Sainst & Teknonogi'),
('fsk', 'Fisika', 'Sainst & Teknonogi'),
('ilkom', 'Ilmu Komputer', 'Sainst & Teknonogi'),
('mm', 'Matematika', 'Sainst & Teknonogi'),
('si', 'Sistem Informasi', 'Sainst & Teknonogi');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','dosen','mahasiswa') NOT NULL,
  `nama` varchar(100) NOT NULL,
  `prodi` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `nama`, `prodi`, `created_at`, `updated_at`) VALUES
(16, 'admin', 'admin', 'admin', 'Administrator', NULL, '2025-05-23 06:52:05', '2025-05-23 06:52:05'),
(17, '0702231043', '12345678', 'mahasiswa', 'Muhammad Richie Hadiansah', 'Sistem Informasi', '2025-05-23 06:52:05', '2025-06-05 06:42:42'),
(54, '0702231047', '0702231047', 'mahasiswa', 'Arfini Tri Agustina', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:42:55'),
(55, '0702232118', '0702232118', 'mahasiswa', 'Mhd. Farraz Fatih', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:42:59'),
(56, '0702233164', '0702233164', 'mahasiswa', 'Khairil sa\'bansyah pane', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:45:57'),
(57, '0702232115', '0702232115', 'mahasiswa', 'Sepira Yunda', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:43:18'),
(58, '0702232122', '0702232122', 'mahasiswa', 'Cindy Anggriani', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:43:22'),
(59, '0702232121', '0702232121', 'mahasiswa', 'Idham Tiofandy Hasibuan', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:43:27'),
(60, '0702231048', '0702231048', 'mahasiswa', 'Laila azizah', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:43:31'),
(61, '0702231041', '0702231041', 'mahasiswa', 'Novilya Musfira Bahri', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:43:38'),
(62, '0702232129', '0702232129', 'mahasiswa', 'Muhammad Faruqi Adri', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:43:42'),
(63, '0702232120', '0702232120', 'mahasiswa', 'Mutia Herman (mylove)', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-08 16:31:33'),
(64, '0702232127', '0702232127', 'mahasiswa', 'Deft Sanjaya', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:45:34'),
(65, '0702233163', '0702233163', 'mahasiswa', 'Rizal Amri Khoirul Hakim Ritonga', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:45:30'),
(66, '0702232126', '0702232126', 'mahasiswa', 'Sherly Pitaloka', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:45:25'),
(67, '0702232113', '0702232113', 'mahasiswa', 'Wilona Ramadhani K', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:45:21'),
(68, '0702232123', '0702232123', 'mahasiswa', 'Muhammad Mushab Umair Daulay', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:45:17'),
(69, '0702232119', '0702232119', 'mahasiswa', 'Hamza Dwi Aulia Wardhana', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:45:14'),
(70, '0702233159', '0702233159', 'mahasiswa', 'Mhd Ayub Ardi', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:45:10'),
(71, '0702233160', '0702233160', 'mahasiswa', 'Nabil Afiq', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:45:06'),
(72, '0702232128', '0702232128', 'mahasiswa', 'M.Noval Revaldi', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:45:02'),
(73, '0702232117', '0702232117', 'mahasiswa', 'Nasrullah Gunawan', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:58'),
(74, '0702232116', '0702232116', 'mahasiswa', 'Amira Salsabila', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:54'),
(75, '0702233165', '0702233165', 'mahasiswa', 'Maharani br saragih', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:50'),
(76, '0702231042', '0702231042', 'mahasiswa', 'Ajeng Triandari', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:45'),
(77, '0702233161', '0702233161', 'mahasiswa', 'Much Nur Syams Simaja', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:41'),
(78, '0702231004', '0702231004', 'mahasiswa', 'Lazuardi Iman Nasution', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:36'),
(79, '0702231046', '0702231046', 'mahasiswa', 'Dzakwan Abbas', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:32'),
(80, '0702232114', '0702232114', 'mahasiswa', 'Muhammad Alvin Nurrahman', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:27'),
(81, '0702231005', '0702231005', 'mahasiswa', 'Awal Bahagia Tanjung', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:24'),
(82, '0702231045', '0702231045', 'mahasiswa', 'Meiranda Siregar', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:20'),
(83, '0702231040', '0702231040', 'mahasiswa', 'Indah Dwi Pancari', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:16'),
(84, '0702233130', '0702233130', 'mahasiswa', 'Fakhri Alauddin Tarihoran', 'Sistem Informasi', '2025-06-05 03:43:57', '2025-06-05 06:44:05'),
(93, '0702239005', '0702239005', 'dosen', 'Afifudin, M.Kom', NULL, '2025-06-05 08:30:50', '2025-06-05 08:30:50'),
(94, '199008092019031014', '199008092019031014', 'dosen', 'Adnan Buyung Nasution, M.Kom', NULL, '2025-06-06 13:15:01', '2025-06-06 13:15:01'),
(95, '0703239001', '0703239001', 'dosen', 'Faisal Muhammad, S.Si., M.Mat', NULL, '2025-06-06 13:15:37', '2025-06-06 13:15:37'),
(96, '199510282022032001', '199510282022032001', 'dosen', 'Fathiya Nasyifa Sibarani, S.Kom., M.Kom.', NULL, '2025-06-06 13:16:18', '2025-06-06 13:16:18'),
(97, '199001312019031019', '199001312019031019', 'dosen', 'Muhammad Dedi Irawan, S.T M.Kom', NULL, '2025-06-06 13:16:53', '2025-06-06 13:16:53'),
(98, '199205052020121023', '199205052020121023', 'dosen', 'Muhammad Ikhsan Rifki, M.T.', NULL, '2025-06-06 13:17:38', '2025-06-06 13:17:38'),
(99, '0703239009', '0703239009', 'dosen', 'Rahmadani Hasibuan, M.E', NULL, '2025-06-06 13:18:13', '2025-06-06 13:18:13'),
(100, '198907102018012002', '198907102018012002', 'dosen', 'Raissa Amanda Putri, S.Kom, M.TI', NULL, '2025-06-06 13:19:12', '2025-06-06 13:19:12');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `data_nilai`
--
ALTER TABLE `data_nilai`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_nilai_mahasiswa` (`mahasiswa_id`,`jadwal_id`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `created_by_2` (`created_by`),
  ADD KEY `matkul_id` (`matkul_id`);

--
-- Indeks untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`nip`),
  ADD UNIQUE KEY `nama_2` (`nama`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `prodi` (`prodi`),
  ADD KEY `nama` (`nama`);

--
-- Indeks untuk tabel `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `matkul_id` (`matkul_id`),
  ADD KEY `dosen_nip` (`dosen_nip`),
  ADD KEY `matkul_id_2` (`matkul_id`);

--
-- Indeks untuk tabel `krs`
--
ALTER TABLE `krs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_krs` (`mahasiswa_id`,`jadwal_id`,`tahun_ajaran`,`semester`),
  ADD KEY `jadwal_id` (`jadwal_id`);

--
-- Indeks untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mahasiswa_user` (`user_id`),
  ADD KEY `prodi` (`prodi`);

--
-- Indeks untuk tabel `matkul`
--
ALTER TABLE `matkul`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prodi` (`prodi`),
  ADD KEY `dosen` (`dosen`);

--
-- Indeks untuk tabel `program_studi`
--
ALTER TABLE `program_studi`
  ADD PRIMARY KEY (`kode_prodi`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `data_nilai`
--
ALTER TABLE `data_nilai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT untuk tabel `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT untuk tabel `krs`
--
ALTER TABLE `krs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `matkul`
--
ALTER TABLE `matkul`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `data_nilai`
--
ALTER TABLE `data_nilai`
  ADD CONSTRAINT `data_nilai_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kuliah` (`id`),
  ADD CONSTRAINT `data_nilai_ibfk_2` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`),
  ADD CONSTRAINT `data_nilai_ibfk_3` FOREIGN KEY (`matkul_id`) REFERENCES `matkul` (`id`),
  ADD CONSTRAINT `fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD CONSTRAINT `dosen_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `dosen_ibfk_2` FOREIGN KEY (`prodi`) REFERENCES `program_studi` (`kode_prodi`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  ADD CONSTRAINT `fk_matkul_id` FOREIGN KEY (`matkul_id`) REFERENCES `matkul` (`id`),
  ADD CONSTRAINT `jadwal_kuliah_ibfk_1` FOREIGN KEY (`dosen_nip`) REFERENCES `dosen` (`nip`);

--
-- Ketidakleluasaan untuk tabel `krs`
--
ALTER TABLE `krs`
  ADD CONSTRAINT `krs_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kuliah` (`id`),
  ADD CONSTRAINT `krs_ibfk_2` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`);

--
-- Ketidakleluasaan untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD CONSTRAINT `fk_mahasiswa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `mahasiswa_ibfk_1` FOREIGN KEY (`prodi`) REFERENCES `program_studi` (`kode_prodi`);

--
-- Ketidakleluasaan untuk tabel `matkul`
--
ALTER TABLE `matkul`
  ADD CONSTRAINT `matkul_ibfk_1` FOREIGN KEY (`prodi`) REFERENCES `program_studi` (`kode_prodi`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
