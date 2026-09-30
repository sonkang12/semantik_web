<?php
/**
 * login.php - Proses login SIM Mahasiswa UMB
 * Menerima POST dari index.php (username, password, csrf).
 * Butuh config.php (fungsi db()). Kompatibel PHP 7.2+.
 */
session_start();
require __DIR__ . '/config.php';

const MAX_PERCOBAAN = 5;      // percobaan gagal berturut-turut
const KUNCI_DETIK   = 300;    // lama tunggu setelah terkunci (5 menit)

function kembali($kode)
{
    header('Location: index.php?error=' . urlencode($kode) . '#masuk');
    exit;
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Cek token CSRF
$csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    kembali('csrf');
}

// Batasi percobaan login berulang (per sesi)
$now = time();
if (!empty($_SESSION['kunci_sampai']) && $now < $_SESSION['kunci_sampai']) {
    kembali('tunggu');
}

$username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
if ($username === '' || $password === '') {
    kembali('kosong');
}

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT p.id_pengguna, p.id_role, p.id_universitas, p.id_fakultas, p.id_program_studi,
                p.username, p.password_hash, p.nama_lengkap, p.status_aktif, r.kode_role
           FROM pengguna p
           JOIN roles r ON r.id_role = p.id_role
          WHERE p.username = ?
          LIMIT 1"
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Selalu jalankan password_verify agar waktu respons tidak membocorkan
    // apakah nama pengguna terdaftar.
    $hash  = $user ? $user['password_hash'] : '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';
    $cocok = password_verify($password, $hash);

    if (!$user || !$cocok) {
        $_SESSION['gagal'] = (isset($_SESSION['gagal']) ? (int)$_SESSION['gagal'] : 0) + 1;
        if ($_SESSION['gagal'] >= MAX_PERCOBAAN) {
            $_SESSION['kunci_sampai'] = $now + KUNCI_DETIK;
            $_SESSION['gagal'] = 0;
        }
        kembali('salah');
    }

    if ($user['status_aktif'] !== 'Aktif') {
        kembali('nonaktif');
    }

    // Login berhasil
    unset($_SESSION['gagal'], $_SESSION['kunci_sampai']);
    session_regenerate_id(true);

    // Perbarui hash jika algoritma default PHP sudah berubah
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $baru = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE pengguna SET password_hash = ? WHERE id_pengguna = ?")
            ->execute([$baru, $user['id_pengguna']]);
    }

    $pdo->prepare("UPDATE pengguna SET last_login = NOW() WHERE id_pengguna = ?")
        ->execute([$user['id_pengguna']]);

    // Catat ke audit_log
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
    $pdo->prepare(
        "INSERT INTO audit_log (id_pengguna, tabel_nama, record_id, aksi, ip_address, user_agent, waktu)
         VALUES (?, 'pengguna', ?, 'LOGIN', ?, ?, NOW())"
    )->execute([$user['id_pengguna'], (string)$user['id_pengguna'], $ip, $ua]);

    // Data sesi
    $_SESSION['id_pengguna']      = (int)$user['id_pengguna'];
    $_SESSION['username']         = $user['username'];
    $_SESSION['nama_lengkap']     = $user['nama_lengkap'];
    $_SESSION['id_role']          = (int)$user['id_role'];
    $_SESSION['kode_role']        = $user['kode_role'];
    $_SESSION['id_universitas']   = $user['id_universitas'] !== null ? (int)$user['id_universitas'] : null;
    $_SESSION['id_fakultas']      = $user['id_fakultas'] !== null ? (int)$user['id_fakultas'] : null;
    $_SESSION['id_program_studi'] = $user['id_program_studi'] !== null ? (int)$user['id_program_studi'] : null;
    $_SESSION['csrf']             = bin2hex(random_bytes(16));

    header('Location: dashboard.php');
    exit;

} catch (Exception $ex) {
    // Jangan tampilkan detail error ke pengguna; simpan di log server
    error_log('Login error: ' . $ex->getMessage());
    kembali('server');
}