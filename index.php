<?php
/**
 * index.php - Landing page SIM Mahasiswa UMB
 * Kompatibel PHP 7.2+. Form login mengirim POST ke login.php.
 *
 * Koneksi database: buat file config.php di folder yang sama, isi:
 *   <?php
 *   $DB_HOST = 'sql313.infinityfree.com';
 *   $DB_NAME = 'if0_43046786_simmahasiswa';
 *   $DB_USER = 'if0_43046786';
 *   $DB_PASS = 'ISI_PASSWORD_DATABASE';
 * Jika config.php tidak ada atau koneksi gagal, halaman tetap tampil dengan data bawaan.
 */
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$sudahLogin = !empty($_SESSION['id_pengguna']);
$error = isset($_GET['error']) ? (string)$_GET['error'] : '';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Data bawaan (dipakai jika database tidak bisa dibaca)
$univ = ['nama' => 'Universitas Muhammadiyah Bengkulu', 'alamat' => 'Jl. Bali, Kampung Bali', 'kota' => 'Bengkulu'];
$fak  = 'Fakultas Teknik';
$prodi = 'Teknik Informatika (S1)';
$stat = ['mhs' => null, 'prodi' => null, 'fakultas' => null];
$dbRoles = [];

if (is_file(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
    try {
        $pdo = new PDO(
            "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
            $DB_USER, $DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        $r = $pdo->query("SELECT nama_universitas, alamat, kota FROM universitas ORDER BY id_universitas LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($r) { $univ = ['nama' => $r['nama_universitas'], 'alamat' => $r['alamat'], 'kota' => $r['kota']]; }
        $r = $pdo->query("SELECT nama_fakultas FROM fakultas ORDER BY id_fakultas LIMIT 1")->fetchColumn();
        if ($r) { $fak = $r; }
        $r = $pdo->query("SELECT nama_program_studi, jenjang FROM program_studi WHERE status_aktif='Aktif' ORDER BY id_program_studi LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($r) { $prodi = $r['nama_program_studi'] . ' (' . $r['jenjang'] . ')'; }
        $stat['mhs']      = (int)$pdo->query("SELECT COUNT(*) FROM mahasiswa WHERE status_mahasiswa='Aktif'")->fetchColumn();
        $stat['prodi']    = (int)$pdo->query("SELECT COUNT(*) FROM program_studi WHERE status_aktif='Aktif'")->fetchColumn();
        $stat['fakultas'] = (int)$pdo->query("SELECT COUNT(*) FROM fakultas")->fetchColumn();
        $dbRoles = $pdo->query("SELECT kode_role, keterangan FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $ex) {
        // Diamkan; tampilkan data bawaan
    }
}

// Peran yang ditampilkan (hanya 5 peran baku; peran lain tidak dimunculkan)
$roles = [
    ['k' => 'ADMIN', 'n' => 'Admin', 't' => 'Kelola seluruh sistem',
     'd' => 'Mengelola seluruh data dan konfigurasi aplikasi, termasuk akun pengguna dan struktur kampus.',
     's' => ['Semua universitas', 'Semua pengguna', 'Pengaturan aplikasi']],
    ['k' => 'OPERATOR_PRODI', 'n' => 'Operator prodi', 't' => 'Kelola mahasiswa program studi',
     'd' => 'Mengelola data mahasiswa pada program studi yang menjadi kewenangannya.',
     's' => ['Tambah mahasiswa', 'Ubah data', 'Satu program studi']],
    ['k' => 'DEKANAT', 'n' => 'Dekanat', 't' => 'Pantau mahasiswa fakultas',
     'd' => 'Melihat data mahasiswa pada fakultas yang menjadi kewenangannya.',
     's' => ['Hanya lihat', 'Semua prodi dalam fakultas']],
    ['k' => 'REKTORAT', 'n' => 'Rektorat', 't' => 'Pantau mahasiswa universitas',
     'd' => 'Melihat data mahasiswa pada tingkat universitas.',
     's' => ['Hanya lihat', 'Semua fakultas']],
    ['k' => 'MAHASISWA', 'n' => 'Mahasiswa', 't' => 'Lihat data pribadi',
     'd' => 'Melihat rekord data pribadi milik sendiri.',
     's' => ['Hanya lihat', 'Data sendiri']],
];
foreach ($roles as $i => $ro) {
    if (!empty($dbRoles[$ro['k']])) { $roles[$i]['d'] = $dbRoles[$ro['k']]; }
}
$jsRoles = [];
foreach ($roles as $ro) { $jsRoles[] = ['n' => $ro['n'], 't' => $ro['t'], 'd' => $ro['d'], 's' => $ro['s']]; }

$pesanError = [
    'kosong'  => 'Isi nama pengguna dan kata sandi.',
    'salah'   => 'Nama pengguna atau kata sandi salah.',
    'nonaktif'=> 'Akun Anda tidak aktif. Hubungi admin.',
    'csrf'    => 'Sesi berakhir. Muat ulang halaman lalu coba lagi.',
    'tunggu'  => 'Terlalu banyak percobaan. Coba lagi dalam 5 menit.',
    'server'  => 'Sistem sedang bermasalah. Coba lagi beberapa saat.',
];
$tampilError = isset($pesanError[$error]) ? $pesanError[$error] : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>SIM Mahasiswa - <?= e($univ['nama']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@500;700;800&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#F4F7F3; --surface:#FFFFFF; --ink:#12281E; --muted:#4F6357; --line:#D5DFD7;
  --green:#0E5A38; --green-d:#0A4229; --gold:#E0A81E; --gold-soft:#FBEFC9; --on-green:#F4F7F3; --err:#B3261E;
}
@media (prefers-color-scheme:dark){
  :root{--bg:#0C1A13;--surface:#13261C;--ink:#E8F0EA;--muted:#9DB3A5;--line:#264033;--green:#3FAE78;--green-d:#2E8A5D;--gold:#E8B93A;--gold-soft:#3A3013;--on-green:#08130D;--err:#FF8A80}
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:400 16px/1.6 Figtree,system-ui,sans-serif}
h1,h2,h3{font-family:'Bricolage Grotesque',Figtree,sans-serif;line-height:1.1;margin:0;letter-spacing:-.02em}
a{color:inherit}
:focus-visible{outline:3px solid var(--gold);outline-offset:2px}
.wrap{max-width:1120px;margin:0 auto;padding:0 24px}
header{border-bottom:1px solid var(--line)}
.nav{display:flex;align-items:center;justify-content:space-between;height:68px}
.brand{display:flex;align-items:center;gap:12px;font-weight:600;text-decoration:none}
.mark{width:36px;height:36px;border-radius:50%;background:var(--green);display:grid;place-items:center}
.mark i{width:14px;height:14px;border-radius:50%;background:var(--gold);display:block}
.brand small{display:block;font-weight:400;color:var(--muted);font-size:13px;line-height:1.2}
.nav a.link{font-weight:500;text-decoration:none;color:var(--muted);margin-left:24px}
.hero{padding:64px 0 72px}
.hero .wrap{display:grid;grid-template-columns:1.15fr .85fr;gap:56px;align-items:center}
h1{font-size:clamp(38px,5.6vw,64px);font-weight:800;max-width:14ch}
.lead{font-size:18px;color:var(--muted);max-width:46ch;margin:22px 0 28px}
.stats{display:flex;gap:28px;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:20px;color:var(--muted);font-size:15px}
.stats b{display:block;color:var(--ink);font:700 22px 'Bricolage Grotesque',sans-serif}
.login{background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:32px;border-top:6px solid var(--gold)}
.login h2{font-size:26px}
.login p{color:var(--muted);margin:6px 0 22px;font-size:15px}
label{display:block;font-weight:600;font-size:14px;margin:0 0 6px}
input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--ink);font:inherit;margin-bottom:16px}
.btn{display:block;width:100%;padding:13px;border:0;border-radius:10px;background:var(--green);color:var(--on-green);font:600 16px Figtree,sans-serif;cursor:pointer;text-align:center;text-decoration:none}
.btn:hover{background:var(--green-d)}
.msg{min-height:22px;margin:12px 0 0;font-size:14px;color:var(--err)}
.hint{font-size:13px;color:var(--muted);margin-top:14px;border-top:1px solid var(--line);padding-top:14px}
section{padding:72px 0}
.alt{background:var(--surface);border-block:1px solid var(--line)}
h2.t{font-size:clamp(28px,3.6vw,40px);font-weight:700;max-width:22ch}
.sub{color:var(--muted);max-width:56ch;margin:14px 0 36px}
.roles{display:grid;grid-template-columns:260px 1fr;gap:32px}
.tabs{display:flex;flex-direction:column;gap:6px}
.tab{text-align:left;padding:12px 16px;border:1px solid var(--line);border-radius:10px;background:transparent;color:var(--ink);font:500 16px Figtree,sans-serif;cursor:pointer}
.tab[aria-selected="true"]{background:var(--green);color:var(--on-green);border-color:var(--green)}
.panel{border:1px solid var(--line);border-radius:14px;padding:32px;background:var(--bg)}
.panel h3{font-size:26px;margin-bottom:10px}
.panel p{color:var(--muted);max-width:52ch}
.scope{margin-top:22px;display:flex;flex-wrap:wrap;gap:10px}
.chip{background:var(--gold-soft);border-radius:999px;padding:6px 14px;font-size:14px;font-weight:500}
.tree{list-style:none;margin:0;padding:0}
.tree li{display:grid;grid-template-columns:150px 1fr;gap:16px;padding:16px 0;border-top:1px solid var(--line);align-items:baseline}
.tree li:last-child{border-bottom:1px solid var(--line)}
.tree li:nth-child(2){padding-left:24px}.tree li:nth-child(3){padding-left:48px}.tree li:nth-child(4){padding-left:72px}
.tree span{color:var(--muted);font-size:14px}
.tree strong{font:700 20px 'Bricolage Grotesque',sans-serif}
.feat{display:grid;grid-template-columns:repeat(3,1fr);gap:40px}
.feat h3{font-size:20px;margin-bottom:8px}
.feat p{color:var(--muted);margin:0}
footer{background:var(--green);color:var(--on-green);padding:40px 0;font-size:15px}
footer .wrap{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap}
footer b{font-family:'Bricolage Grotesque',sans-serif;font-size:18px}
@media(max-width:860px){
  .hero .wrap,.roles,.feat{grid-template-columns:1fr}
  .tabs{flex-direction:row;flex-wrap:wrap}
  .tree li{grid-template-columns:1fr;gap:2px}
  .tree li:nth-child(n){padding-left:0}
  .nav a.link{display:none}
}
</style>
</head>
<body>
<header>
  <div class="wrap nav">
    <a class="brand" href="index.php"><span class="mark"><i></i></span><span>SIM Mahasiswa<small><?= e($univ['nama']) ?></small></span></a>
    <nav>
      <a class="link" href="#peran">Hak akses</a>
      <a class="link" href="#struktur">Struktur data</a>
      <a class="link" href="#masuk">Masuk</a>
    </nav>
  </div>
</header>

<main>
<div class="hero">
  <div class="wrap">
    <div>
      <h1>Data mahasiswa UMB dalam satu sistem.</h1>
      <p class="lead">Kelola data mahasiswa dari program studi hingga rektorat. Setiap pengguna hanya melihat data sesuai kewenangannya.</p>
      <div class="stats">
        <?php if ($stat['mhs'] !== null): ?>
          <div><b><?= (int)$stat['mhs'] ?></b>mahasiswa aktif</div>
          <div><b><?= (int)$stat['prodi'] ?></b>program studi</div>
          <div><b><?= (int)$stat['fakultas'] ?></b>fakultas</div>
        <?php else: ?>
          <div><b>5</b>peran pengguna</div>
          <div><b>4</b>tingkat data</div>
          <div><b>Tercatat</b>setiap aktivitas masuk dan ubah data</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="login" id="masuk">
      <?php if ($sudahLogin): ?>
        <h2>Anda sudah masuk</h2>
        <p>Lanjutkan ke halaman kerja Anda.</p>
        <a class="btn" href="dashboard.php">Buka dashboard</a>
        <div class="hint"><a href="logout.php">Keluar dari akun</a></div>
      <?php else: ?>
        <h2>Masuk ke akun Anda</h2>
        <p>Mahasiswa masuk dengan NPM sebagai nama pengguna.</p>
        <form method="post" action="login.php" autocomplete="on">
          <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <label for="u">Nama pengguna</label>
          <input id="u" name="username" autocomplete="username" placeholder="Contoh: 20260001" required>
          <label for="p">Kata sandi</label>
          <input id="p" name="password" type="password" autocomplete="current-password" required>
          <button class="btn" type="submit">Masuk</button>
          <div class="msg" role="alert"><?= e($tampilError) ?></div>
        </form>
        <div class="hint">Lupa kata sandi? Hubungi operator program studi Anda.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<section class="alt" id="peran">
  <div class="wrap">
    <h2 class="t">Setiap peran melihat data yang sesuai.</h2>
    <p class="sub">Pilih peran untuk melihat apa yang bisa dilakukan setelah masuk.</p>
    <div class="roles">
      <div class="tabs" role="tablist" id="tabs"></div>
      <div class="panel" id="panel" role="tabpanel" aria-live="polite"></div>
    </div>
  </div>
</section>

<section id="struktur">
  <div class="wrap">
    <h2 class="t">Data tersusun dari universitas sampai mahasiswa.</h2>
    <p class="sub">Struktur ini menentukan siapa boleh melihat apa.</p>
    <ul class="tree">
      <li><span>Universitas</span><strong><?= e($univ['nama']) ?></strong></li>
      <li><span>Fakultas</span><strong><?= e($fak) ?></strong></li>
      <li><span>Program studi</span><strong><?= e($prodi) ?></strong></li>
      <li><span>Mahasiswa</span><strong>NPM, nama, status, dan data pribadi</strong></li>
    </ul>
  </div>
</section>

<section class="alt">
  <div class="wrap feat">
    <div><h3>Status mahasiswa jelas</h3><p>Aktif, cuti, lulus, mengundurkan diri, drop out, atau tidak aktif. Semua tercatat pada satu data.</p></div>
    <div><h3>Data pribadi tetap terjaga</h3><p>Mahasiswa hanya membuka rekordnya sendiri. Kata sandi disimpan dalam bentuk terenkripsi.</p></div>
    <div><h3>Riwayat aktivitas tersimpan</h3><p>Masuk, keluar, tambah, ubah, hapus, dan lihat data dicatat lengkap dengan waktu dan perangkat.</p></div>
  </div>
</section>
</main>

<footer>
  <div class="wrap">
    <div><b>SIM Mahasiswa UMB</b><br><?= e($univ['nama']) ?><br><?= e($univ['alamat']) ?>, <?= e($univ['kota']) ?></div>
    <div>Butuh bantuan akun?<br>Hubungi admin sistem informasi kampus.</div>
  </div>
</footer>

<script>
var R = <?= json_encode($jsRoles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var tabs = document.getElementById('tabs'), panel = document.getElementById('panel');
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function show(i){
  [].forEach.call(tabs.children, function(b,j){ b.setAttribute('aria-selected', j===i); });
  var r = R[i];
  panel.innerHTML = '<h3>'+esc(r.t)+'</h3><p>'+esc(r.d)+'</p><div class="scope">'
    + r.s.map(function(x){ return '<span class="chip">'+esc(x)+'</span>'; }).join('') + '</div>';
}
R.forEach(function(r,i){
  var b = document.createElement('button');
  b.className = 'tab'; b.setAttribute('role','tab'); b.textContent = r.n;
  b.onclick = function(){ show(i); };
  tabs.appendChild(b);
});
show(0);
</script>
</body>
</html>