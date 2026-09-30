<?php
/**
 * config.php - Koneksi database SIM Mahasiswa UMB
 * Kompatibel dengan index.php (memakai variabel $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS)
 * dan menyediakan fungsi db() untuk file lain (login.php, dashboard.php, dst).
 *
 * Pemakaian di file lain:
 *   require __DIR__ . '/config.php';
 *   $pdo = db();
 *   $stmt = $pdo->prepare("SELECT * FROM pengguna WHERE username = ?");
 *   $stmt->execute([$username]);
 */

$DB_HOST = 'sql313.infinityfree.com';
$DB_NAME = 'if0_43046786_simmahasiswa';
$DB_USER = 'if0_43046786';
$DB_PASS = 'kasseky12';

date_default_timezone_set('Asia/Jakarta');

/**
 * Mengembalikan satu koneksi PDO yang dipakai ulang selama permintaan berjalan.
 * Melempar PDOException jika koneksi gagal.
 */
function db()
{
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        // Samakan zona waktu sesi MySQL dengan PHP (WIB)
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}