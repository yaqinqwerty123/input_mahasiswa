<?php
// ==========================================================
// daftar_mhs.php  ->  taruh di folder yang sama dengan aksi.php
// Halaman tunggal (tanpa login) untuk mahasiswa input data,
// hanya bisa dibuka lewat link bertoken & dalam rentang tanggal.
// Field disamakan dengan Google Form "Data Mahasiswa Praktek".
// ==========================================================
session_start();
include __DIR__ . "/koneksi.php";   // koneksi.php satu folder dengan file ini
date_default_timezone_set('Asia/Jakarta');

// Password acak 5 karakter (huruf kecil + angka, minimal 1 huruf & 1 angka).
// Huruf/angka yang mirip (0/o, 1/l/i) sengaja dibuang biar gampang dibaca & diketik.
function buatPassword($panjang = 5) {
    $huruf = 'abcdefghjkmnpqrstuvwxyz';
    $angka = '23456789';
    $semua = $huruf . $angka;

    $pick = function ($set) {
        $max = strlen($set) - 1;
        $i = function_exists('random_int') ? random_int(0, $max) : mt_rand(0, $max);
        return $set[$i];
    };

    $chars = array($pick($huruf), $pick($angka));
    while (count($chars) < $panjang) $chars[] = $pick($semua);

    // acak urutan (Fisher-Yates)
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = function_exists('random_int') ? random_int(0, $i) : mt_rand(0, $i);
        $tmp = $chars[$i]; $chars[$i] = $chars[$j]; $chars[$j] = $tmp;
    }
    return implode('', $chars);
}

$token = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['token']) : '';

// ---------- validasi token ----------
function cekLink($token) {
    if ($token == '') return array(false, 'Link tidak valid.');
    $t = mysql_real_escape_string($token);
    $q = mysql_query("SELECT * FROM link_input_mhs WHERE token = '$t' LIMIT 1");
    if (!$q || mysql_num_rows($q) == 0) return array(false, 'Link tidak ditemukan.');
    $r = mysql_fetch_assoc($q);
    if ($r['status'] != 1) return array(false, 'Link ini sudah dinonaktifkan.');
    $today = date('Y-m-d');
    if ($today < $r['mulai'])   return array(false, 'Link belum aktif. Baru bisa dipakai mulai ' . date('d-m-Y', strtotime($r['mulai'])) . '.');
    if ($today > $r['selesai']) return array(false, 'Link sudah kedaluwarsa (berakhir ' . date('d-m-Y', strtotime($r['selesai'])) . ').');
    return array(true, $r);
}

list($linkOk, $linkInfo) = cekLink($token);

// ---------- flash message & isian lama (kalau submit gagal) ----------
$flashErrors = array();
$flashSukses = array();
$old = array();
if (isset($_SESSION['dm_errors'])) { $flashErrors = $_SESSION['dm_errors']; unset($_SESSION['dm_errors']); }
if (isset($_SESSION['dm_sukses'])) { $flashSukses = $_SESSION['dm_sukses']; unset($_SESSION['dm_sukses']); }
if (isset($_SESSION['dm_old']))    { $old = $_SESSION['dm_old'];            unset($_SESSION['dm_old']); }
$flashDup = array();
if (isset($_SESSION['dm_dup']))    { $flashDup = $_SESSION['dm_dup'];       unset($_SESSION['dm_dup']); }

function old($k) {
    global $old;
    return isset($old[$k]) ? htmlspecialchars($old[$k], ENT_QUOTES, 'UTF-8') : '';
}
function oldRaw($k) {
    global $old;
    return isset($old[$k]) ? $old[$k] : '';
}
function upper($s) {
    return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
}
// Format: "Senin, 12 Januari 2026" (manual, tidak bergantung setlocale server)
function tglIndo($datetime) {
    $ts = strtotime($datetime);
    if (!$datetime || !$ts) return 'tanggal tidak tercatat';
    $hari  = array('Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu');
    $bulan = array(1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember');
    return $hari[(int)date('w', $ts)] . ', ' . (int)date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}
function buatOpsi($sql, $colVal, $colLabel, $selected) {
    $out = '';
    $q = mysql_query($sql);
    while ($r = mysql_fetch_assoc($q)) {
        $sel = ((string)$r[$colVal] === (string)$selected) ? ' selected' : '';
        $out .= '<option value="' . htmlspecialchars($r[$colVal]) . '"' . $sel . '>'
              . htmlspecialchars($r[$colLabel]) . '</option>';
    }
    return $out;
}
function adaDiDb($sql) {
    $q = mysql_query($sql);
    return ($q && mysql_num_rows($q) > 0);
}

// ---------- proses submit ----------
if ($linkOk && isset($_POST['simpan'])) {

    $errors = array();

    $nim       = isset($_POST['nim']) ? trim($_POST['nim']) : '';
    $nama      = isset($_POST['nama_mhs']) ? trim(preg_replace('/\s+/', ' ', $_POST['nama_mhs'])) : '';
    $tempat    = isset($_POST['tempat_lahir']) ? trim(preg_replace('/\s+/', ' ', $_POST['tempat_lahir'])) : '';
    $tgl       = isset($_POST['tgl_lahir']) ? trim($_POST['tgl_lahir']) : '';
    $jk        = isset($_POST['jk']) ? $_POST['jk'] : '';
    $agama     = (int) (isset($_POST['id_agama']) ? $_POST['id_agama'] : 0);
    $instansi  = (int) (isset($_POST['asal_instansi']) ? $_POST['asal_instansi'] : 0);
    $alamat    = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
    $tlp       = isset($_POST['tlp']) ? trim($_POST['tlp']) : '';
    $email     = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
    $stt_medis = (int) (isset($_POST['stt_medis']) ? $_POST['stt_medis'] : 0);
    $angkatan  = (int) (isset($_POST['angkatan']) ? $_POST['angkatan'] : 0);
    $kelompok  = isset($_POST['kelompok']) ? upper(trim($_POST['kelompok'])) : '';
    $gelombang = isset($_POST['gelombang']) ? $_POST['gelombang'] : '';

    // NIM
    if ($nim == '') {
        $errors[] = 'NIM wajib diisi.';
    } elseif (!preg_match('/^[A-Za-z0-9]{5,30}$/', $nim)) {
        $errors[] = 'NIM hanya boleh huruf & angka (tanpa spasi/simbol), 5-30 karakter.';
    }

    // Nama
    if ($nama == '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    } elseif (strlen($nama) < 3 || strlen($nama) > 100) {
        $errors[] = 'Nama lengkap harus 3-100 karakter.';
    } elseif (!preg_match('/^[\p{L}][\p{L}\s.,\x27\-]*$/u', $nama)) {
        $errors[] = 'Nama lengkap hanya boleh huruf, spasi, titik, koma, apostrof, dan tanda hubung.';
    }

    // Tempat lahir
    if ($tempat == '') {
        $errors[] = 'Tempat lahir wajib diisi.';
    } elseif (strlen($tempat) > 100 || !preg_match('/^[\p{L}][\p{L}\s.,\x27\-]*$/u', $tempat)) {
        $errors[] = 'Tempat lahir hanya boleh huruf (maks. 100 karakter).';
    }

    // Tanggal lahir
    $d = DateTime::createFromFormat('Y-m-d', $tgl);
    if ($tgl == '') {
        $errors[] = 'Tanggal lahir wajib diisi.';
    } elseif (!$d || $d->format('Y-m-d') !== $tgl) {
        $errors[] = 'Tanggal lahir tidak valid.';
    } else {
        $umur = $d->diff(new DateTime('today'));
        if ($d > new DateTime('today')) {
            $errors[] = 'Tanggal lahir tidak boleh di masa depan.';
        } elseif ($umur->y < 15 || $umur->y > 70) {
            $errors[] = 'Tanggal lahir tidak masuk akal, periksa kembali.';
        }
    }

    // Jenis kelamin
    if ($jk != 'L' && $jk != 'P') $errors[] = 'Jenis kelamin wajib dipilih.';

    // Agama
    if ($agama <= 0 || !adaDiDb("SELECT id_agama FROM agama WHERE id_agama = $agama")) {
        $errors[] = 'Agama wajib dipilih.';
    }

    // Instansi
    if ($instansi <= 0 || !adaDiDb("SELECT id_instansi FROM instansi WHERE id_instansi = $instansi AND status = 'Aktif'")) {
        $errors[] = 'Asal instansi / universitas wajib dipilih.';
    }

    // Alamat
    if ($alamat == '') {
        $errors[] = 'Alamat wajib diisi.';
    } elseif (strlen($alamat) < 10) {
        $errors[] = 'Alamat terlalu pendek, tulis alamat selengkapnya (min. 10 karakter).';
    } elseif (strlen($alamat) > 500) {
        $errors[] = 'Alamat maksimal 500 karakter.';
    }

    // No telepon: hanya angka, diawali 08, total 10-13 digit
    if ($tlp == '') {
        $errors[] = 'No. telepon wajib diisi.';
    } elseif (!preg_match('/^[0-9]+$/', $tlp)) {
        $errors[] = 'No. telepon hanya boleh angka, tanpa tanda "-", "+", spasi, atau tanda lain.';
    } elseif (substr($tlp, 0, 2) !== '08') {
        $errors[] = 'No. telepon harus diawali 08 (contoh: 0852xxxxxxxx), bukan +62 / 62.';
    } elseif (strlen($tlp) < 10 || strlen($tlp) > 13) {
        $errors[] = 'No. telepon harus 10-13 digit.';
    }

    // Email
    if ($email == '') {
        $errors[] = 'Email wajib diisi.';
    } elseif (strlen($email) > 50 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid (maks. 50 karakter).';
    }

    // Status medis
    if ($stt_medis <= 0 || !adaDiDb("SELECT id FROM status_medis WHERE id = $stt_medis")) {
        $errors[] = 'Status medis wajib dipilih.';
    }

    // Angkatan
    if ($angkatan <= 0 || !adaDiDb("SELECT id_th FROM angkatan WHERE id_th = $angkatan AND status = 1")) {
        $errors[] = 'Angkatan wajib dipilih.';
    }

    // Kelompok: huruf besar + angka (B1, A2, C3, D4) atau "-"
    if ($kelompok == '') {
        $errors[] = 'Kelompok wajib diisi (isi "-" jika tidak ada).';
    } elseif (!preg_match('/^([A-Z][0-9]{1,2}|-)$/', $kelompok)) {
        $errors[] = 'Kelompok harus huruf besar + angka, contoh: B1, A2, C3, D4. Isi "-" jika tidak ada.';
    }

    // Gelombang
    if (!in_array($gelombang, array('1', '2', '3'), true)) {
        $errors[] = 'Gelombang wajib dipilih.';
    }

    // Cek link & duplikat NIM
    if (empty($errors)) {
        list($ok2, ) = cekLink($token);
        if (!$ok2) $errors[] = 'Link sudah tidak berlaku.';
    }
    if (empty($errors)) {
        $nim_e = mysql_real_escape_string(strtoupper($nim));
        $qDup = mysql_query("
            SELECT m.nama_mhs, m.timestap, i.nama AS nama_instansi
            FROM mahasiswa m
            LEFT JOIN instansi i ON i.id_instansi = m.asal_instansi
            WHERE m.nim = '$nim_e'
            LIMIT 1
        ");
        if ($qDup && mysql_num_rows($qDup) > 0) {
            $dup = mysql_fetch_assoc($qDup);
            $errors[] = 'NIM ' . strtoupper($nim) . ' sudah terdaftar.';
            $_SESSION['dm_dup'] = array(
                'nim'      => strtoupper($nim),
                'tanggal'  => tglIndo($dup['timestap']),
                'nama'     => $dup['nama_mhs'],
                'instansi' => !empty($dup['nama_instansi']) ? $dup['nama_instansi'] : '-'
            );
        }
    }

    // Simpan
    if (empty($errors)) {
        $nama_e   = mysql_real_escape_string(upper($nama));
        $tempat_e = mysql_real_escape_string(upper($tempat));
        $alamat_e = mysql_real_escape_string($alamat);
        $tlp_e    = mysql_real_escape_string($tlp);
        $email_e  = mysql_real_escape_string($email);
        $kel_e    = mysql_real_escape_string($kelompok);
        $gel_e    = mysql_real_escape_string($gelombang);
        $passPlain = buatPassword(5);
        $pass      = md5($passPlain);

        $sql = "INSERT INTO mahasiswa (
                    nim, password_mhs, nama_mhs, tempat_lahir, tgl_lahir, jk, id_agama,
                    asal_instansi, alamat, tlp, email, status, id_th, stt_medis, kelompok, gelombang
                ) VALUES (
                    '$nim_e', '$pass', '$nama_e', '$tempat_e', '$tgl', '$jk', $agama,
                    '$instansi', '$alamat_e', '$tlp_e', '$email_e', '1', $angkatan, $stt_medis, '$kel_e', '$gel_e'
                )";

        if (mysql_query($sql)) {
            // ditampilkan SEKALI saja di popup, lalu dihapus dari session
            $_SESSION['dm_sukses'] = array('nim' => strtoupper($nim), 'password' => $passPlain);
        } else {
            $errors[] = 'Gagal menyimpan data, silakan coba lagi atau hubungi admin.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['dm_errors'] = $errors;
        // simpan isian biar tidak ngetik ulang
        $_SESSION['dm_old'] = array(
            'nim' => $nim, 'nama_mhs' => $nama, 'tempat_lahir' => $tempat, 'tgl_lahir' => $tgl,
            'jk' => $jk, 'id_agama' => $agama, 'asal_instansi' => $instansi, 'alamat' => $alamat,
            'tlp' => $tlp, 'email' => $email, 'stt_medis' => $stt_medis, 'angkatan' => $angkatan,
            'kelompok' => $kelompok, 'gelombang' => $gelombang
        );
    }

    header('Location: daftar_mhs.php?token=' . urlencode($token));
    exit;
}

// ---------- data dropdown ----------
$optAgama = $optInstansi = $optStatus = $optAngkatan = '';
if ($linkOk) {
    $optAgama    = buatOpsi("SELECT id_agama, nama FROM agama ORDER BY id_agama ASC", 'id_agama', 'nama', oldRaw('id_agama'));
    $optInstansi = buatOpsi("SELECT id_instansi, nama FROM instansi WHERE status = 'Aktif' ORDER BY nama ASC", 'id_instansi', 'nama', oldRaw('asal_instansi'));
    $optStatus   = buatOpsi("SELECT id, nama FROM status_medis ORDER BY nama ASC", 'id', 'nama', oldRaw('stt_medis'));
    $optAngkatan = buatOpsi("SELECT id_th, th_angkatan FROM angkatan WHERE status = 1 ORDER BY th_angkatan ASC", 'id_th', 'th_angkatan', oldRaw('angkatan'));
}

$tglMax = date('Y-m-d', strtotime('-15 years'));
$tglMin = date('Y-m-d', strtotime('-70 years'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Mahasiswa Praktek - RSUD Mohammad Noer</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root { --navy:#0C447C; --navy-dark:#082F57; --bg-soft:#F3F6FA; --err:#dc3545; }
* { box-sizing:border-box; }
body { margin:0; min-height:100vh; padding:24px 16px; font-family:'Poppins','Segoe UI',Arial,sans-serif;
       background:linear-gradient(180deg,#0C447C 0%,#082F57 100%); display:flex; justify-content:center; }
.wrapper { width:100%; max-width:560px; }
.brand { text-align:center; color:#fff; margin-bottom:18px; }
.brand h1 { font-size:18px; margin:0 0 2px; }
.brand p  { font-size:12.5px; margin:0; color:rgba(255,255,255,.85); }
.card { background:#fff; border-radius:18px; padding:26px 22px; box-shadow:0 20px 45px rgba(0,0,0,.25); }
.card h2 { font-size:17px; text-align:center; margin:0 0 4px; }
.card .subtitle { text-align:center; font-size:12.5px; color:#8a93a3; margin:0 0 20px; }
.field { margin-bottom:15px; }
.field > label { display:block; font-size:12.5px; font-weight:600; color:#4a5568; margin-bottom:5px; }
.field > label span { color:var(--err); }
.field .hint { font-size:11.5px; color:#8a93a3; margin:-2px 0 6px; line-height:1.5; }
.field input[type=text], .field input[type=date], .field input[type=email],
.field select, .field textarea {
    width:100%; padding:11px 12px; font-size:14px; font-family:inherit; color:#1f2937;
    border:1.5px solid #E3E8EF; border-radius:10px; background:var(--bg-soft); outline:none;
}
.field input:focus, .field select:focus, .field textarea:focus { border-color:var(--navy); background:#fff; }
.field.has-error input[type=text], .field.has-error input[type=date], .field.has-error input[type=email],
.field.has-error select, .field.has-error textarea { border-color:var(--err); background:#fff6f6; }
.err { display:none; color:var(--err); font-size:11.5px; margin-top:4px; }
.field.has-error .err { display:block; }
.pills { display:flex; gap:10px; flex-wrap:wrap; }
.pills label { margin:0; font-weight:500; cursor:pointer; }
.pills input { position:absolute; opacity:0; pointer-events:none; }
.pills span { display:inline-block; padding:9px 20px; border:1.5px solid #E3E8EF; border-radius:10px;
              background:var(--bg-soft); font-size:14px; color:#4a5568; }
.pills input:checked + span { border-color:var(--navy); background:var(--navy); color:#fff; }
.pills input:focus-visible + span { box-shadow:0 0 0 3px rgba(12,68,124,.25); }
.btn { width:100%; padding:14px; border:none; border-radius:10px; font-size:15px; font-weight:700; color:#fff;
       background:linear-gradient(135deg,var(--navy),var(--navy-dark)); cursor:pointer; margin-top:6px; }
.btn:disabled { opacity:.65; cursor:not-allowed; }
.info { text-align:center; padding:30px 10px; }
.info .icon { font-size:46px; margin-bottom:10px; }
.info h2 { margin-bottom:8px; }
.info p { color:#6b7280; font-size:14px; line-height:1.6; }
.masa { text-align:center; font-size:11.5px; color:#97A3B6; margin-top:14px; }
.swal-list { text-align:left; font-size:14px; margin:0; padding-left:18px; }
</style>
</head>
<body>
<div class="wrapper">
    <div class="brand">
        <h1>RSUD Mohammad Noer</h1>
        <p>Pendataan Mahasiswa Praktek</p>
    </div>

    <div class="card">
    <?php if (!$linkOk) { ?>
        <div class="info">
            <div class="icon">🔒</div>
            <h2>Link Tidak Dapat Diakses</h2>
            <p><?php echo htmlspecialchars($linkInfo); ?></p>
            <p>Silakan hubungi admin untuk mendapatkan link yang baru.</p>
        </div>
    <?php } else { ?>
        <h2>Data Mahasiswa Praktek</h2>
        <p class="subtitle">Silahkan isi formulir berdasarkan data diri yang benar</p>

        <form method="post" id="formDaftar" autocomplete="off" novalidate>
            <input type="hidden" name="simpan" value="1">

            <div class="field"><label>NIM <span>*</span></label>
                <input type="text" name="nim" maxlength="30" value="<?php echo old('nim'); ?>">
                <div class="err"></div></div>

            <div class="field"><label>Nama Lengkap <span>*</span></label>
                <input type="text" name="nama_mhs" maxlength="100" value="<?php echo old('nama_mhs'); ?>">
                <div class="err"></div></div>

            <div class="field"><label>Tempat Lahir <span>*</span></label>
                <input type="text" name="tempat_lahir" maxlength="100" value="<?php echo old('tempat_lahir'); ?>">
                <div class="err"></div></div>

            <div class="field"><label>Tanggal Lahir <span>*</span></label>
                <input type="date" name="tgl_lahir" min="<?php echo $tglMin; ?>" max="<?php echo $tglMax; ?>" value="<?php echo old('tgl_lahir'); ?>">
                <div class="err"></div></div>

            <div class="field"><label>Jenis Kelamin <span>*</span></label>
                <div class="pills">
                    <label><input type="radio" name="jk" value="L" <?php echo oldRaw('jk')=='L' ? 'checked' : ''; ?>><span>L</span></label>
                    <label><input type="radio" name="jk" value="P" <?php echo oldRaw('jk')=='P' ? 'checked' : ''; ?>><span>P</span></label>
                </div>
                <div class="err"></div></div>

            <div class="field"><label>Agama <span>*</span></label>
                <select name="id_agama">
                    <option value="">-- Pilih Agama --</option>
                    <?php echo $optAgama; ?>
                </select>
                <div class="err"></div></div>

            <div class="field"><label>Asal Instansi / Universitas <span>*</span></label>
                <select name="asal_instansi">
                    <option value="">-- Pilih Instansi --</option>
                    <?php echo $optInstansi; ?>
                </select>
                <div class="err"></div></div>

            <div class="field"><label>Alamat <span>*</span></label>
                <textarea name="alamat" rows="3" maxlength="500"><?php echo old('alamat'); ?></textarea>
                <div class="err"></div></div>

            <div class="field"><label>No Telepon <span>*</span></label>
                <div class="hint">Hanya angka, diawali 08 (contoh: 0852xxxxxxxx). Jangan pakai tanda "-" atau "+".</div>
                <input type="text" name="tlp" inputmode="numeric" maxlength="13" placeholder="0852xxxxxxxx" value="<?php echo old('tlp'); ?>">
                <div class="err"></div></div>

            <div class="field"><label>Email <span>*</span></label>
                <input type="email" name="email" maxlength="50" value="<?php echo old('email'); ?>">
                <div class="err"></div></div>

            <div class="field"><label>Status Medis <span>*</span></label>
                <select name="stt_medis">
                    <option value="">-- Pilih Status Medis --</option>
                    <?php echo $optStatus; ?>
                </select>
                <div class="err"></div></div>

            <div class="field"><label>Angkatan <span>*</span></label>
                <select name="angkatan">
                    <option value="">-- Pilih Angkatan --</option>
                    <?php echo $optAngkatan; ?>
                </select>
                <div class="err"></div></div>

            <div class="field"><label>Kelompok <span>*</span></label>
                <div class="hint">Silahkan isi berdasarkan kelompok masing-masing, apabila tidak ada maka di isi dengan "-".<br>
                Contoh pengisian (B1, A2, C3, D4) harus huruf besar.</div>
                <input type="text" name="kelompok" maxlength="3" value="<?php echo old('kelompok'); ?>">
                <div class="err"></div></div>

            <div class="field"><label>Gelombang <span>*</span></label>
                <div class="pills">
                    <label><input type="radio" name="gelombang" value="1" <?php echo oldRaw('gelombang')=='1' ? 'checked' : ''; ?>><span>1</span></label>
                    <label><input type="radio" name="gelombang" value="2" <?php echo oldRaw('gelombang')=='2' ? 'checked' : ''; ?>><span>2</span></label>
                    <label><input type="radio" name="gelombang" value="3" <?php echo oldRaw('gelombang')=='3' ? 'checked' : ''; ?>><span>3</span></label>
                </div>
                <div class="err"></div></div>

            <button type="submit" class="btn" id="btnSimpan">KIRIM DATA</button>
        </form>
        <div class="masa">Link berlaku sampai <?php echo date('d-m-Y', strtotime($linkInfo['selesai'])); ?></div>
    <?php } ?>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('formDaftar');
    if (!form) return;

    // ---------- auto-format saat mengetik ----------
    form.elements['tlp'].addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '');           // buang -, +, spasi, huruf
    });
    form.elements['nim'].addEventListener('input', function () {
        this.value = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    });
    form.elements['kelompok'].addEventListener('input', function () {
        this.value = this.value.replace(/\s/g, '').toUpperCase();
    });
    form.elements['email'].addEventListener('input', function () {
        this.value = this.value.replace(/\s/g, '').toLowerCase();
    });

    // ---------- aturan validasi (sama dengan server) ----------
    var namaRe = /^[A-Za-z\u00C0-\u024F][A-Za-z\u00C0-\u024F\s.,'\-]*$/;

    function val(n) { var el = form.elements[n]; return el ? String(el.value || '').trim() : ''; }

    var rules = {
        nim: function (v) {
            if (!v) return 'NIM wajib diisi.';
            if (!/^[A-Za-z0-9]{5,30}$/.test(v)) return 'NIM hanya huruf & angka, 5-30 karakter.';
        },
        nama_mhs: function (v) {
            if (!v) return 'Nama lengkap wajib diisi.';
            if (v.length < 3) return 'Nama terlalu pendek.';
            if (!namaRe.test(v)) return 'Nama hanya boleh huruf, spasi, titik, koma, apostrof, tanda hubung.';
        },
        tempat_lahir: function (v) {
            if (!v) return 'Tempat lahir wajib diisi.';
            if (!namaRe.test(v)) return 'Tempat lahir hanya boleh huruf.';
        },
        tgl_lahir: function (v) {
            if (!v) return 'Tanggal lahir wajib diisi.';
            var el = form.elements['tgl_lahir'];
            if (el.min && v < el.min) return 'Tanggal lahir tidak masuk akal.';
            if (el.max && v > el.max) return 'Tanggal lahir tidak masuk akal (min. umur 15 tahun).';
        },
        jk: function (v) { if (!v) return 'Jenis kelamin wajib dipilih.'; },
        id_agama: function (v) { if (!v) return 'Agama wajib dipilih.'; },
        asal_instansi: function (v) { if (!v) return 'Asal instansi / universitas wajib dipilih.'; },
        alamat: function (v) {
            if (!v) return 'Alamat wajib diisi.';
            if (v.length < 10) return 'Alamat terlalu pendek, tulis selengkapnya (min. 10 karakter).';
        },
        tlp: function (v) {
            if (!v) return 'No. telepon wajib diisi.';
            if (!/^[0-9]+$/.test(v)) return 'Hanya angka, tanpa "-" atau "+".';
            if (v.indexOf('08') !== 0) return 'Harus diawali 08 (contoh: 0852xxxxxxxx).';
            if (v.length < 10 || v.length > 13) return 'No. telepon harus 10-13 digit.';
        },
        email: function (v) {
            if (!v) return 'Email wajib diisi.';
            if (v.length > 50 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'Format email tidak valid.';
        },
        stt_medis: function (v) { if (!v) return 'Status medis wajib dipilih.'; },
        angkatan: function (v) { if (!v) return 'Angkatan wajib dipilih.'; },
        kelompok: function (v) {
            if (!v) return 'Kelompok wajib diisi (isi "-" jika tidak ada).';
            if (!/^([A-Z][0-9]{1,2}|-)$/.test(v)) return 'Contoh: B1, A2, C3, D4 (huruf besar), atau "-" jika tidak ada.';
        },
        gelombang: function (v) { if (!v) return 'Gelombang wajib dipilih.'; }
    };

    function fieldBox(name) {
        var el = form.elements[name];
        var node = el.length && !el.tagName ? el[0] : el;   // RadioNodeList -> elemen pertama
        return node.closest('.field');
    }

    function cek(name) {
        var msg = rules[name](val(name)) || '';
        var box = fieldBox(name);
        box.classList.toggle('has-error', !!msg);
        box.querySelector('.err').textContent = msg;
        return msg;
    }

    Object.keys(rules).forEach(function (name) {
        var el = form.elements[name];
        var list = (el.length && !el.tagName) ? Array.prototype.slice.call(el) : [el];
        list.forEach(function (node) {
            node.addEventListener('blur', function () { cek(name); });
            node.addEventListener('change', function () { cek(name); });
        });
    });

    form.addEventListener('submit', function (e) {
        var firstErr = null;
        Object.keys(rules).forEach(function (name) {
            if (cek(name) && !firstErr) firstErr = name;
        });
        if (firstErr) {
            e.preventDefault();
            fieldBox(firstErr).scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        document.getElementById('btnSimpan').disabled = true;   // cegah double submit
    });
})();

<?php if (!empty($flashDup)) {
    $dupHtml =
        '<div style="font-size:14px;color:#4a5568;line-height:1.6;text-align:left">' .
            'NIM <b>' . htmlspecialchars($flashDup['nim']) . '</b> sudah terdaftar pada <b>' . htmlspecialchars($flashDup['tanggal']) . '</b>.' .
        '</div>' .
        '<table style="width:100%;margin-top:12px;border-collapse:collapse;font-size:14px;text-align:left;background:#F3F6FA;border-radius:10px;overflow:hidden">' .
            '<tr><td style="padding:10px 12px;color:#6b7280;width:110px;vertical-align:top;border-bottom:1px solid #E3E8EF">Atas nama</td>' .
                '<td style="padding:10px 12px;border-bottom:1px solid #E3E8EF"><b>' . htmlspecialchars($flashDup['nama']) . '</b></td></tr>' .
            '<tr><td style="padding:10px 12px;color:#6b7280;vertical-align:top">Asal instansi</td>' .
                '<td style="padding:10px 12px"><b>' . htmlspecialchars($flashDup['instansi']) . '</b></td></tr>' .
        '</table>' .
        '<div style="margin-top:14px;padding:10px 12px;background:#fff3cd;border-radius:10px;color:#7a5b00;font-size:13px;line-height:1.5;text-align:left">' .
            'Silahkan hubungi <b>admin</b> atau <b>CI praktek</b> untuk mengetahui informasi akun.' .
        '</div>';
?>
Swal.fire({
    icon: 'warning',
    title: 'NIM ini sudah terdaftar',
    html: <?php echo json_encode($dupHtml); ?>,
    confirmButtonText: 'Mengerti',
    confirmButtonColor: '#0C447C'
});
<?php } elseif (!empty($flashErrors)) {
    $li = '';
    foreach ($flashErrors as $m) $li .= '<li>' . htmlspecialchars($m) . '</li>';
?>
Swal.fire({
    icon: 'error',
    title: 'Data belum bisa disimpan',
    html: <?php echo json_encode('<ul class="swal-list">' . $li . '</ul>'); ?>
});
<?php } ?>
<?php if (!empty($flashSukses)) { ?>
(function () {
    var nim = <?php echo json_encode($flashSukses['nim']); ?>;
    var pwd = <?php echo json_encode($flashSukses['password']); ?>;

    Swal.fire({
        icon: 'success',
        title: 'Input Berhasil',
        html:
            '<div style="font-size:14px;color:#4a5568;margin-bottom:12px">Silahkan gunakan akun berikut untuk login:</div>' +
            '<div style="background:#F3F6FA;border:1.5px dashed #0C447C;border-radius:12px;padding:14px 16px;text-align:left;font-size:15px;line-height:2">' +
                '<div><span style="display:inline-block;width:90px;color:#6b7280">Username</span>: <b style="font-family:monospace;font-size:17px">' + nim + '</b></div>' +
                '<div><span style="display:inline-block;width:90px;color:#6b7280">Password</span>: <b style="font-family:monospace;font-size:17px;letter-spacing:1px">' + pwd + '</b></div>' +
            '</div>' +
            '<div style="margin-top:14px;padding:10px 12px;background:#fff3cd;border-radius:10px;color:#7a5b00;font-size:13px;line-height:1.5">' +
                '📸 <b>Jangan lupa screenshot dan simpan!</b><br>Informasi ini hanya muncul sekali dan tidak bisa dilihat lagi setelah ditutup.' +
                '<div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(122,91,0,.25)">' +
                    '🔑 Perubahan <b>password</b> dan <b>data diri</b> dapat dilakukan nanti di menu <b>Profil</b> pada aplikasi SIAKAD.' +
                '</div>' +
            '</div>',
        confirmButtonText: 'Ya, sudah saya simpan',
        confirmButtonColor: '#0C447C',
        allowOutsideClick: false,
        allowEscapeKey: false
    });
})();
<?php } ?>
</script>
</body>
</html>
