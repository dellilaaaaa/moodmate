<?php
// 1. Pengaturan Error & Session (Wajib di paling atas)
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Jakarta');

// =========================================================================
// [BARU] SAMBUNGKAN JEMBATAN KONEKSI DATABASE MYSQL
// =========================================================================
require_once 'koneksi.php';

// Cek status login dan premium dari session di awal
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$is_premium = isset($_SESSION['is_premium']) && $_SESSION['is_premium'] === true;

$pesan_sistem = "";
$tipe_pesan = "";

// =========================================================================
// [FITUR REAL DB] --- PROSES REGISTRASI USER BARU ---
// =========================================================================
if (isset($_POST['proses_register'])) {
    $reg_username = trim($_POST['username']);
    $reg_email = trim($_POST['email']);
    $reg_password = $_POST['password'] ?? '';

    if (!empty($reg_username) && !empty($reg_email) && !empty($reg_password)) {
        // Enkripsi password demi standar keamanan website beneran
        $password_aman = password_hash($reg_password, PASSWORD_BCRYPT);

        try {
            // Masukkan data ke database dengan status awal Akun Gratis (is_premium = 0)
            $stmt_reg = $pdo->prepare("INSERT INTO users (username, email, password, is_premium) VALUES (?, ?, ?, 0)");
            $stmt_reg->execute([$reg_username, $reg_email, $password_aman]);

            header("Location: " . $_SERVER['PHP_SELF'] . "?status=reg_sukses");
            exit();
        } catch (PDOException $e) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?pesan=reg_gagal");
            exit();
        }
    }
}

// =========================================================================
// [FITUR REAL DB] --- PROSES LOGIN REAL ---
// =========================================================================
if (isset($_POST['proses_login'])) {
    $input_username = trim($_POST['username']);
    $input_password = $_POST['password'] ?? '';

    try {
        // Cari user berdasarkan username di database
        $stmt_user = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt_user->execute([$input_username]);
        $user_data = $stmt_user->fetch();

        // Verifikasi apakah user ditemukan dan password enkripsinya cocok
        if ($user_data && password_verify($input_password, $user_data['password'])) {
            // Jika sukses, ikat data asli database ke dalam Session PHP
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['email'] = $user_data['email'];
            $_SESSION['is_premium'] = ($user_data['is_premium'] == 1);

            // Logika otomatis jika login dipicu tombol beli paket (intent)
            if (isset($_GET['intent_paket'])) {
                $stmt_up = $pdo->prepare("UPDATE users SET is_premium = 1 WHERE id = ?");
                $stmt_up->execute([$user_data['id']]);
                $_SESSION['is_premium'] = true;
            }

            header("Location: " . $_SERVER['PHP_SELF'] . "?status=login_sukses");
            exit();
        } else {
            // Jika salah, balikkan ke halaman dengan status gagal
            header("Location: " . $_SERVER['PHP_SELF'] . "?pesan=login_gagal");
            exit();
        }
    } catch (PDOException $e) {
        die("Eror sistem login database: " . $e->getMessage());
    }
}

// =========================================================================
// Menangkap Status & Pesan Dari URL Untuk Pengendali Pop-Up Modal
// =========================================================================
if (isset($_GET['status']) && $_GET['status'] == 'reg_sukses') {
    $pesan_sistem = "🎉 Pendaftaran berhasil! Silakan login menggunakan akun barumu.";
    $tipe_pesan = "sukses";
} elseif (isset($_GET['pesan']) && $_GET['pesan'] == 'login_gagal') {
    $pesan_sistem = "Username atau password salah! Coba periksa kembali.";
    $tipe_pesan = "error";
} elseif (isset($_GET['pesan']) && $_GET['pesan'] == 'reg_gagal') {
    $pesan_sistem = "Username atau email sudah terdaftar di sistem!";
    $tipe_pesan = "error";
} elseif (isset($_GET['status']) && $_GET['status'] == 'menunggu_konfirmasi') {
    $pesan_sistem = "Pembayaran kamu sedang diverifikasi secara manual oleh Admin. Mohon tunggu maksimal 1x24 jam ya!";
    $tipe_pesan = "sukses";
}

// =========================================================================
// [UPGRADE] --- PROSES BELI PAKET ---
// =========================================================================
if (isset($_GET['beli_paket'])) {
    if (!$is_logged_in) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?action=req_login&intent_paket=" . $_GET['beli_paket']);
        exit();
    } else {
        try {
            $current_user_id = $_SESSION['user_id'] ?? 1;

            // Update status premium user di database secara permanen
            $stmt_premium = $pdo->prepare("UPDATE users SET is_premium = 1 WHERE id = ?");
            $stmt_premium->execute([$current_user_id]);

            $_SESSION['is_premium'] = true;
            header("Location: " . $_SERVER['PHP_SELF'] . "?status=paket_aktif");
            exit();
        } catch (PDOException $e) {
            die("Gagal memperbarui paket di database: " . $e->getMessage());
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Re-evaluasi status setelah action di atas
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$is_premium = isset($_SESSION['is_premium']) && $_SESSION['is_premium'] === true;

// =========================================================================
// [FIXED] Jurnal Premium Hanya Terkunci Jika User Sudah Login tapi Bukan Pro
// =========================================================================
$fitur_terkunci = false;
if ($is_logged_in) {
    $fitur_terkunci = (!$is_premium);
}

// 2. LOGIKA BACKEND UTAMA
$hari_ini_tgl = date("Y-m-d");
$sudah_isi = false;
$pesan_header = "Selamat pagi! 😊";
$sub_pesan = "Halo Buddy! Kamu belum mencatat perasaanmu hari ini. Yuk, ceritakan sedikit!";

// =========================================================================
// [UPGRADE] PENGECEKAN ANTI-SPAM HARIAN DINAMIS BERDASARKAN USER YANG LOG IN
// =========================================================================
if ($is_logged_in) {
    try {
        $current_user_id = $_SESSION['user_id'] ?? 1;
        // Mencari apakah hari ini ID user tersebut sudah menginput emoji ke database
        $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM riwayat_mood WHERE user_id = ? AND DATE(waktu) = ?");
        $stmt_cek->execute([$current_user_id, $hari_ini_tgl]);
        $jumlah_record = $stmt_cek->fetchColumn();

        if ($jumlah_record > 0) {
            $sudah_isi = true;
            $pesan_header = "Luar Biasa! ✨";
            $sub_pesan = "Terima kasih sudah check-in mood hari ini. Catatanmu sudah terekam aman di database MySQL.";
        }
    } catch (PDOException $e) {
        // Diamkan jika database belum sinkron
    }
}

// =========================================================================
// ✨ 1. LOGIKA INISIALISASI DATA HABIT UNTUK HARI INI
// =========================================================================
$current_user_id = $_SESSION['user_id'] ?? 1;
$hari_ini = date("Y-m-d");
$habit_hari_ini = ['habit_1' => 0, 'habit_2' => 0, 'habit_3' => 0]; // Nilai fallback aman

try {
    $stmt_habit = $pdo->prepare("SELECT * FROM log_habit_harian WHERE user_id = ? AND tanggal = ?");
    $stmt_habit->execute([$current_user_id, $hari_ini]);
    $data_habit = $stmt_habit->fetch(PDO::FETCH_ASSOC);

    if (!$data_habit) {
        // Jika hari baru, buat baris baru di DB dengan status 0 semua
        $stmt_init = $pdo->prepare("INSERT INTO log_habit_harian (user_id, tanggal, habit_1, habit_2, habit_3) VALUES (?, ?, 0, 0, 0)");
        $stmt_init->execute([$current_user_id, $hari_ini]);

        // Ambil ulang datanya
        $stmt_habit->execute([$current_user_id, $hari_ini]);
        $data_habit = $stmt_habit->fetch(PDO::FETCH_ASSOC);
    }

    if ($data_habit) {
        $habit_hari_ini = $data_habit;
    }
} catch (PDOException $e) {
    // Diamkan agar tidak merusak halaman utama jika tabel belum di-migrate
}

// =========================================================================
// ✨ 2. LOGIKA PROSES KLIK CENTANG HABIT (TOGGLE)
// =========================================================================
if (isset($_GET['toggle_habit'])) {
    $habit_ke = $_GET['toggle_habit']; // nilainya: habit_1, habit_2, atau habit_3

    if (in_array($habit_ke, ['habit_1', 'habit_2', 'habit_3'])) {
        // Balikkan nilai status centang
        $status_baru = $habit_hari_ini[$habit_ke] == 1 ? 0 : 1;

        try {
            $stmt_update = $pdo->prepare("UPDATE log_habit_harian SET $habit_ke = ? WHERE user_id = ? AND tanggal = ?");
            $stmt_update->execute([$status_baru, $current_user_id, $hari_ini]);

            // Redirect kembali ke dashboard.php tanpa query string habit agar rapi
            header("Location: dashboard.php");
            exit();
        } catch (PDOException $e) {
            die("Gagal mengupdate habit: " . $e->getMessage());
        }
    }
}

// =========================================================================
// PROSES TANGKAP KLIK EMOJI HARIAN (TERIKAT USER ID DATABASE)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] == 'input_mood') {

    if (!$is_logged_in) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?pesan=harus_login");
        exit();
    }

    if ($sudah_isi) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=mood_tercatat");
        exit();
    }

    $skor_mood = isset($_GET['score']) ? intval($_GET['score']) : null;
    $label_mood = isset($_GET['label']) ? htmlspecialchars($_GET['label']) : '';
    $waktu_sekarang = date('Y-m-d H:i:s');

    try {
        $stmt_insert_mood = $pdo->prepare("INSERT INTO riwayat_mood (user_id, skor, label, waktu) VALUES (?, ?, ?, ?)");
        $stmt_insert_mood->execute([$current_user_id, $skor_mood, $label_mood, $waktu_sekarang]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?status=mood_tercatat");
        exit();
    } catch (PDOException $e) {
        die("Gagal mencatat mood: " . $e->getMessage());
    }
}

// Sinkronisasi Parameter URL get sukses
if (isset($_GET['status']) && $_GET['status'] == 'mood_tercatat') {
    $sudah_isi = true;
    $pesan_header = "Luar Biasa! ✨";
    $sub_pesan = "Terima kasih sudah check-in mood hari ini. Catatanmu sudah terekam aman di database MySQL.";
}

// Proses Simpan Data Jurnal (Premium)
if (!$fitur_terkunci && isset($_POST['simpan_mood'])) {
    $note = htmlspecialchars($_POST['jurnal_teks'] ?? '');
    $jam = date("H:i");

    $_SESSION['jurnal_data'][] = [
        'tanggal' => date("d-m-Y"),
        'catatan' => $note,
        'waktu' => $jam
    ];

    header("Location: " . $_SERVER['PHP_SELF'] . "?status=jurnal_tersimpan");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MoodMate - Dashboard</title>
    <!-- FullCalendar CSS CDN -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
    <style>
        :root {
            --logo-teal: #6fbab7;
            --logo-orange: #ffcc99;
            --logo-blue: #3d7e96;
            --dark-bg: #121212;
            --dark-blue: #1d3557;
            --soft-blue: #f1faee;
            --white: #ffffff;
            --premium-gold: #f39c12;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            display: flex;
            background-color: var(--soft-blue);
            color: var(--dark-blue);
            height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: var(--dark-bg);
            color: white;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo {
            margin-bottom: 30px;
            text-align: center;
            font-weight: bold;
            font-size: 1.5rem;
            color: var(--logo-teal);
        }

        .nav-menu a {
            color: #bdc3c7;
            text-decoration: none;
            padding: 12px;
            display: block;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: 0.3s;
            position: relative;
        }

        .nav-menu a.active,
        .nav-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--logo-teal);
        }

        .badge-pro {
            position: absolute;
            right: 10px;
            top: 13px;
            background: var(--premium-gold);
            color: white;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 20px;
            font-weight: bold;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
            margin-left: 8px;
        }

        .badge-free {
            background: #e0e0e0;
            color: #666;
        }

        .badge-premium {
            background: var(--premium-gold);
            color: white;
        }

        .premium-banner {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .free-test-banner {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .free-test-banner:hover {
            transform: translateY(-2px);
            opacity: 0.95;
        }

        .btn-upgrade {
            background: white;
            color: #e67e22;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-test {
            background: white;
            color: #2980b9;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        .welcome-banner {
            background-color: <?php echo $sudah_isi ? 'var(--logo-teal)' : 'var(--dark-blue)'; ?>;
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            position: relative;
        }

        .locked-content {
            filter: blur(3px);
            pointer-events: none;
            user-select: none;
        }

        .lock-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 5;
            color: var(--dark-blue);
            font-weight: bold;
            font-size: 0.9rem;
        }

        .mood-options-new {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .mood-link {
            flex: 1;
            text-decoration: none;
            color: inherit;
            text-align: center;
            background: var(--soft-blue);
            padding: 15px;
            border-radius: 12px;
            transition: 0.3s;
            font-size: 1.3rem;
            font-weight: bold;
        }

        .mood-link:hover {
            background: var(--logo-teal);
            color: white;
            transform: translateY(-3px);
        }

        .mood-link.disabled {
            opacity: 0.6;
            pointer-events: none;
            background: #eef2f3;
        }

        textarea {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            box-sizing: border-box;
            resize: none;
        }

        .btn-save {
            background: var(--dark-blue);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
            text-decoration: none;
        }

        .btn-login-premium {
            width: 100%; 
            margin: 0; 
            background: linear-gradient(135deg, #3d7e96, #1d3557); 
            color: white; 
            border: none; 
            padding: 14px; 
            border-radius: 10px; 
            font-weight: 700; 
            cursor: pointer; 
            font-size: 1rem; 
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(29, 53, 87, 0.25);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .btn-login-premium:hover {
            background: linear-gradient(135deg, #4b94b0, #25446f);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(29, 53, 87, 0.4);
        }

        .btn-login-premium:active {
            transform: translateY(1px);
            box-shadow: 0 2px 10px rgba(29, 53, 87, 0.2);
        }

        .subscription-overlay {
            display: <?php echo (isset($_GET['action']) && $_GET['action'] == 'req_login') ? 'flex' : 'none'; ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(18, 18, 18, 0.7);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
            z-index: 9999;
            overflow-y: auto;
            padding: 20px 0;
        }

        .subscription-card {
            background: #ffffff;
            padding: 30px;
            border-radius: 24px;
            width: 90%;
            max-width: 440px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
            position: relative;
            box-sizing: border-box;
        }

        .close-popup {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.8rem;
            cursor: pointer;
            color: #aaa;
        }

        .sub-header h2 {
            margin: 5px 0;
            color: #111;
            font-size: 1.6rem;
            font-weight: bold;
        }

        .sub-header p {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .plans-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            text-align: left;
        }

        .plan-box {
            border: 2px solid #eef2f3;
            border-radius: 16px;
            padding: 15px;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: inherit;
            transition: 0.25s ease;
        }

        .plan-box:hover {
            border-color: #38ef7d;
            background-color: #f9fffb;
            transform: translateY(-2px);
        }

        .recommended-badge {
            position: absolute;
            top: -10px;
            left: 15px;
            background: linear-gradient(90deg, #11998e, #38ef7d);
            color: white;
            font-size: 0.65rem;
            font-weight: bold;
            padding: 3px 10px;
            border-radius: 10px;
        }

        .plan-info h4 {
            margin: 0 0 4px 0;
            font-size: 1.1rem;
            color: #111;
        }

        .plan-info ul {
            margin: 0;
            padding-left: 18px;
            font-size: 0.8rem;
            color: #666;
        }

        .plan-price {
            text-align: right;
        }

        .plan-price .price {
            font-weight: bold;
            color: #111;
            font-size: 0.95rem;
        }

        .plan-price .durasi {
            font-size: 0.75rem;
            color: #888;
            display: block;
        }

        .switch-login-btn {
            display: inline-block;
            margin-top: 20px;
            font-size: 0.85rem;
            color: var(--logo-blue);
            cursor: pointer;
            text-decoration: underline;
        }

        /* Styling Kalender */
        .fc {
            font-family: 'Segoe UI', sans-serif;
            background: white;
            padding: 15px;
            border-radius: 12px;
        }
        .fc-header-toolbar {
            margin-bottom: 10px !important;
            font-size: 0.85rem;
        }
        .fc-event {
            cursor: pointer;
            padding: 2px 5px;
            font-weight: bold;
            font-size: 0.8rem;
        }
    </style>
</head>

<body>

    <div class="subscription-overlay" id="popupSubscription">
        <div class="subscription-card" style="max-width: 450px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <span class="close-popup" onclick="tutupPopup()">&times;</span>

            <?php if (!empty($pesan_sistem)): ?>
                <div style="padding:10px; margin-bottom:15px; border-radius:8px; font-size:0.85rem; text-align:center; background:<?= $tipe_pesan == 'sukses' ? '#e2fcd4' : '#fce2e2' ?>; color:<?= $tipe_pesan == 'sukses' ? '#2b660a' : '#660a0a' ?>; font-weight: bold;">
                    <?= $pesan_sistem ?>
                </div>
            <?php endif; ?>

            <div id="contentSubscription" style="<?php echo (isset($_GET['action']) && $_GET['action'] == 'req_login') ? 'display:none;' : 'display:block;'; ?>">
                <div class="sub-header">
                    <h2>Subscription</h2>
                    <p>Compare Plans</p>
                </div>

                <div class="plans-container">
                    <div class="plan-box" style="border-color: #38ef7d;">
                        <span class="recommended-badge">RECOMMENDED</span>
                        <div class="plan-info">
                            <h4>Super Weekly</h4>
                            <ul>
                                <li>Unlimited journal energy</li>
                                <li>No ads & Premium badges</li>
                            </ul>
                        </div>
                        <div class="plan-price">
                            <span class="price">Rp5.000</span>
                            <span class="durasi">/minggu</span>
                            <a href="pembayaran.php?paket=weekly" class="btn-upgrade" style="padding: 5px 10px; font-size: 0.75rem; display: inline-block; margin-top: 5px; background: #38ef7d; color: white;">Try</a>
                        </div>
                    </div>

                    <div class="plan-box">
                        <div class="plan-info">
                            <h4>Super Monthly</h4>
                            <ul>
                                <li>Unlimited journal energy</li>
                                <li>Advanced mood analytics</li>
                            </ul>
                        </div>
                        <div class="plan-price">
                            <span style="font-size: 0.75rem; color: #e74c3c; text-decoration: line-through; display: block;">Rp20.000</span>
                            <span class="price" style="color: #27ae60;">Rp15.000</span>
                            <span class="durasi">/bulan</span>
                            <a href="pembayaran.php?paket=monthly" class="btn-upgrade" style="padding: 5px 10px; font-size: 0.75rem; display: inline-block; margin-top: 5px; background: var(--logo-blue); color: white;">Try</a>
                        </div>
                    </div>

                    <div class="plan-box">
                        <div class="plan-info">
                            <h4>Super 3-Month</h4>
                            <ul>
                                <li>Full access for 90 days</li>
                                <li>Best value investment</li>
                            </ul>
                        </div>
                        <div class="plan-price">
                            <span style="font-size: 0.75rem; color: #e74c3c; text-decoration: line-through; display: block;">Rp60.000</span>
                            <span class="price" style="color: #27ae60;">Rp35.000</span>
                            <span class="durasi">/3 bulan</span>
                            <a href="pembayaran.php?paket=threemonth" class="btn-upgrade" style="padding: 5px 10px; font-size: 0.75rem; display: inline-block; margin-top: 5px; background: var(--dark-blue); color: white;">Try</a>
                        </div>
                    </div>
                </div>
                <div class="switch-login-btn" onclick="tampilFormLoginOnly()" style="margin-top: 15px; cursor: pointer; color: var(--logo-blue); font-weight: bold;">Sudah punya akun? Masuk di sini</div>
            </div>

            <div id="contentLoginOnly" style="<?php echo (isset($_GET['action']) && $_GET['action'] == 'req_login') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: var(--dark-blue); text-align: left; font-size: 1.4rem;">Masuk Ke Akun</h3>

                <?php if (isset($_GET['action']) && $_GET['action'] == 'req_login'): ?>
                    <p style="font-size: 0.85rem; color:#e74c3c; font-weight:bold; background:#fdeaea; padding:8px; border-radius:8px; text-align: left; margin-bottom: 15px;">⚠️ Kamu harus membuat akun / login dahulu sebelum mengaktifkan Premium!</p>
                <?php else: ?>
                    <p style="font-size: 0.85rem; color:#666; margin-bottom: 20px; text-align: left;">Masukkan data akun lengkapmu di bawah ini.</p>
                <?php endif; ?>

                <form method="POST" action="dashboard.php" style="text-align: left;">
                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 0.8rem; font-weight: bold; color: #333; display: block;">Username</label>
                        <input type="text" name="username" style="width:100%; padding: 10px; border:1px solid #ddd; border-radius:8px; box-sizing: border-box; margin-top:4px; font-size: 0.9rem;" required>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="font-size: 0.8rem; font-weight: bold; color: #333; display: block;">Password</label>
                        <input type="password" name="password" style="width:100%; padding: 10px; border:1px solid #ddd; border-radius:8px; box-sizing: border-box; margin-top:4px; font-size: 0.9rem;" required>
                    </div>
                    <button type="submit" name="proses_login" class="btn-login-premium">Masuk Sekarang</button>
                </form>

                <div style="display: flex; justify-content: space-between; margin-top: 20px; font-size: 0.85rem;">
                    <span onclick="tampilFormRegister()" style="color: var(--logo-blue); cursor: pointer; font-weight: bold; text-decoration: underline;">Belum punya akun? Daftar</span>
                    <span onclick="tampilSubscriptionPlans()" style="color: #666; cursor: pointer; font-weight: 500;">← Lihat Paket</span>
                </div>
            </div>

            <div id="contentRegister" style="display: none;">
                <h3 style="margin-top: 0; color: var(--dark-blue); text-align: left; font-size: 1.4rem;">Daftar Akun Baru</h3>
                <p style="font-size: 0.85rem; color:#666; margin-bottom: 20px; text-align: left;">Mulai catat kesehatan mentalmu secara rapi dan permanen gratis.</p>

                <form method="POST" action="dashboard.php" style="text-align: left;">
                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 0.8rem; font-weight: bold; color: #333; display: block;">Buat Username</label>
                        <input type="text" name="username" style="width:100%; padding: 10px; border:1px solid #ddd; border-radius:8px; box-sizing: border-box; margin-top:4px; font-size: 0.9rem;" required>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <label style="font-size: 0.8rem; font-weight: bold; color: #333; display: block;">Alamat Email</label>
                        <input type="email" name="email" style="width:100%; padding: 10px; border:1px solid #ddd; border-radius:8px; box-sizing: border-box; margin-top:4px; font-size: 0.9rem;" required>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="font-size: 0.8rem; font-weight: bold; color: #333; display: block;">Buat Password</label>
                        <input type="password" name="password" style="width:100%; padding: 10px; border:1px solid #ddd; border-radius:8px; box-sizing: border-box; margin-top:4px; font-size: 0.9rem;" required>
                    </div>
                    <button type="submit" name="proses_register" class="btn-save" style="width: 100%; margin: 0; background: #f39c12; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 0.95rem; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">Daftar Sekarang</button>
                </form>

                <div style="margin-top: 20px; font-size: 0.85rem; text-align: center;">
                    <span onclick="tampilFormLoginOnly()" style="color: var(--logo-blue); cursor: pointer; font-weight: bold; text-decoration: underline;">Sudah punya akun? Masuk di sini</span>
                </div>
            </div>

        </div>
    </div>

    <div class="sidebar">
        <div class="sidebar-logo">MoodMate</div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="tracking.php">Tes Emosional</a>

            <?php
            if ($is_logged_in && $is_premium) {
                echo '<a href="analisis.php">Analisis Mood <span style="background:gold; color:black; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">PRO</span></a>';
            } else {
                echo '<a href="#" style="opacity: 0.6;" onclick="bukaPopupPremium()">Analisis Mood 🔒</a>';
            }
            ?>

            <?php
            if ($is_logged_in && $is_premium) {
                echo '<a href="curhat.php">Sesi Curhat <span style="background:gold; color:black; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">PRO</span></a>';
            } else {
                echo '<a href="#" style="opacity: 0.6;" onclick="bukaPopupPremium()">Sesi Curhat 🔒</a>';
            }
            ?>
        </nav>
    </div>

    <div class="main-content">
        <header>
            <h2>Dashboard</h2>
            <div style="display: flex; align-items: center;">
                <?php if ($is_logged_in): ?>
                    Halo, <strong><?php echo $_SESSION['username']; ?>!</strong>
                    <?php if ($is_premium): ?>
                        <span class="status-badge badge-premium">⭐ PREMIUM</span>
                    <?php else: ?>
                        <span class="status-badge badge-free">FREE MEMBER</span>
                    <?php endif; ?>
                    <a href="?action=logout" class="btn-save" style="background:#e74c3c; padding: 6px 12px; font-size:0.8rem; margin-left:15px; margin-top:0;">Logout</a>
                <?php else: ?>
                    <button onclick="bukaPopupAuthBiasa()" style="background: white; color: var(--dark-blue); border: 1px solid #ccc; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer;">Login / Upgrade</button>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!$is_premium): ?>
            <div class="premium-banner" onclick="bukaPopupPremium()" style="cursor: pointer;">
                <div>
                    <h4 style="margin: 0 0 5px 0;">MoodMate Premium Aktifkan Sekarang! 🚀</h4>
                    <p style="margin: 0; font-size: 0.85rem;">Mulai dari Rp5.000/minggu. Klik di sini untuk unlock seluruh fitur cerdas dashboard.</p>
                </div>
                <button class="btn-upgrade">Daftar PRO</button>
            </div>
        <?php endif; ?>

        <a href="tracking.php" class="free-test-banner">
            <div>
                <h4 style="margin: 0 0 5px 0;">Cek Kesehatan Mentalmu! 🧠 ✨</h4>
                <p style="margin: 0; font-size: 0.85rem;">Ikuti <strong>Tes Emosional Gratis</strong> selama 1 menit untuk mengetahui tingkat stres dan kecemasanmu saat ini.</p>
            </div>
            <button class="btn-test">Mulai Tes (Gratis)</button>
        </a>

        <div class="welcome-banner">
            <h3><?php echo $pesan_header; ?></h3>
            <p><?php echo $sub_pesan; ?></p>
        </div>

        <div class="dashboard-grid">
            <div class="left-col">

                <div class="card">
                    <h4>Bagaimana perasaanmu saat ini?</h4>
                    <div class="mood-options-new">
                        <a href="?action=input_mood&score=20&label=Senang" class="mood-link <?php echo $sudah_isi ? 'disabled' : ''; ?>">
                            😄<br><span style="font-size: 11px; display:block; margin-top:5px;">Senang</span>
                        </a>
                        <a href="?action=input_mood&score=40&label=Biasa" class="mood-link <?php echo $sudah_isi ? 'disabled' : ''; ?>">
                            😐<br><span style="font-size: 11px; display:block; margin-top:5px;">Biasa</span>
                        </a>
                        <a href="?action=input_mood&score=70&label=Lelah" class="mood-link <?php echo $sudah_isi ? 'disabled' : ''; ?>">
                            😩<br><span style="font-size: 11px; display:block; margin-top:5px;">Lelah</span>
                        </a>
                        <a href="?action=input_mood&score=90&label=Sedih" class="mood-link <?php echo $sudah_isi ? 'disabled' : ''; ?>">
                            😔<br><span style="font-size: 11px; display:block; margin-top:5px;">Sedih</span>
                        </a>
                    </div>
                </div>

                <div class="card" onclick="<?php echo $fitur_terkunci ? 'bukaPopupPremium()' : ''; ?>" style="<?php echo $fitur_terkunci ? 'cursor:pointer;' : ''; ?>">
                    <?php if ($fitur_terkunci): ?> <div class="lock-overlay"><span>🔒 Buka Paket Premium Super</span></div> <?php endif; ?>
                    <div class="<?php echo $fitur_terkunci ? 'locked-content' : ''; ?>">
                        <form method="POST">
                            <h4>Journal Harian</h4>
                            <textarea name="jurnal_teks" rows="4" placeholder="Tuliskan ceritamu hari ini..." required></textarea>
                            <button type="submit" name="simpan_mood" class="btn-save">Simpan Jurnal</button>
                        </form>
                    </div>
                </div>

                <!-- KARTU BARU: MOOD CALENDAR (Sudah ditaruh di luar bungkus struktur journal yang terkunci) -->
                <div class="card" style="margin-top: 20px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                        <span style="font-size: 1.3rem;">📅</span>
                        <h4 style="margin: 0; font-size: 1.1rem; color: var(--dark-blue);">Kalender Mood Kamu</h4>
                    </div>
                    
                    <?php if (!$is_logged_in): ?>
                        <div style="text-align: center; padding: 30px 10px; color: #7f8c8d;">
                            <p style="margin: 0 0 10px 0; font-size: 0.9rem;">Yuk login dulu untuk melihat kalender track mood pribadimu!</p>
                            <button onclick="bukaPopupAuthBiasa()" style="background: var(--logo-blue); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer;">Login Sekarang</button>
                        </div>
                    <?php else: ?>
                        <!-- Tempat kalender akan dirender oleh JS -->
                        <div id='calendar'></div>
                    <?php endif; ?>
                </div>

            </div>

            <div class="right-col">
                <div class="card">
                    <h4>Reminder</h4>
                    <div class="rem-item" style="background: var(--logo-orange); padding: 10px; border-radius: 8px;">
                        <strong>Minum Air Putih!</strong><br>Jangan lupa hidrasi tubuhmu hari ini.
                    </div>
                </div>

                <div class="card" style="background: linear-gradient(135deg, #6fbab7, #3d7e96); color: white; margin-top: 15px;">
                    <h4 style="margin: 0 0 8px 0; font-size: 0.9rem; color: var(--logo-orange);">💡 Quotes Hari Ini</h4>
                    <p style="font-style: italic; font-size: 0.8rem; line-height: 1.4; margin: 0;">
                        "Kamu tidak harus mengendalikan seluruh pikiranmu. Kamu hanya harus berhenti membiarkan pikiranmu mengendalikanmu."
                    </p>
                    <small style="display: block; text-align: right; margin-top: 5px; font-size: 0.7rem; opacity: 0.8;">— MoodMate</small>
                </div>

                <div class="card" onclick="<?php echo $fitur_terkunci ? 'bukaPopupPremium()' : ''; ?>" style="margin-top: 15px; <?php echo $fitur_terkunci ? 'cursor:pointer;' : ''; ?>; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); padding: 25px;">

                    <?php if ($fitur_terkunci): ?>
                        <div class="lock-overlay" style="border-radius: 20px;"><span>🔒 Buka Fitur Habit Tracker (Premium)</span></div>
                    <?php endif; ?>

                    <div class="<?php echo $fitur_terkunci ? 'locked-content' : ''; ?>">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <span style="font-size: 1.4rem;">🎯</span>
                            <h4 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #1d3557;">Habit Tracker Hari Ini</h4>
                        </div>
                        <p style="font-size: 0.85rem; color: #7f8c8d; margin: 0 0 20px 0;">Klik untuk mencentang kebiasaan sehatmu</p>

                        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.9rem;">

                            <style>
                                .habit-item {
                                    display: flex;
                                    align-items: center;
                                    justify-content: space-between;
                                    padding: 14px 18px;
                                    border: 1px solid #eef2f3;
                                    border-radius: 14px;
                                    transition: all 0.25s ease;
                                    gap: 15px;
                                }

                                .habit-item:hover {
                                    transform: translateY(-2px);
                                    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
                                }

                                .habit-text {
                                    font-weight: 500;
                                    color: #2c3e50;
                                    line-height: 1.4;
                                }

                                .habit-status {
                                    display: flex;
                                    align-items: center;
                                    gap: 6px;
                                    font-weight: 700;
                                    white-space: nowrap;
                                    font-size: 0.85rem;
                                }
                            </style>

                            <?php if ($fitur_terkunci): ?>
                                <div class="habit-item" style="background: #fff;">
                                    <span class="habit-text">💧 Minum Air Putih 2L</span>
                                    <span class="habit-status" style="color: #bdc3c7;">⬜ Belum</span>
                                </div>
                            <?php else: ?>
                                <a href="?toggle_habit=habit_1" style="text-decoration: none; display: block;">
                                    <div class="habit-item" style="background: <?= $habit_hari_ini['habit_1'] == 1 ? '#e8f8f0' : '#fff' ?>; border-color: <?= $habit_hari_ini['habit_1'] == 1 ? '#2ecc71' : '#eef2f3' ?>;">
                                        <span class="habit-text">💧 Minum Air Putih 2L</span>
                                        <span class="habit-status" style="color: <?= $habit_hari_ini['habit_1'] == 1 ? '#2ecc71' : '#95a5a6' ?>;">
                                            <?= $habit_hari_ini['habit_1'] == 1 ? '✅ Selesai' : '⬜ Belum' ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endif; ?>

                            <?php if ($fitur_terkunci): ?>
                                <div class="habit-item" style="background: #fff;">
                                    <span class="habit-text">🧘 Meditasi / Journaling 10 Menit</span>
                                    <span class="habit-status" style="color: #bdc3c7;">⬜ Belum</span>
                                </div>
                            <?php else: ?>
                                <a href="?toggle_habit=habit_2" style="text-decoration: none; display: block;">
                                    <div class="habit-item" style="background: <?= $habit_hari_ini['habit_2'] == 1 ? '#e8f8f0' : '#fff' ?>; border-color: <?= $habit_hari_ini['habit_2'] == 1 ? '#2ecc71' : '#eef2f3' ?>;">
                                        <span class="habit-text">🧘 Meditasi / Journaling 10 Menit</span>
                                        <span class="habit-status" style="color: <?= $habit_hari_ini['habit_2'] == 1 ? '#2ecc71' : '#95a5a6' ?>;">
                                            <?= $habit_hari_ini['habit_2'] == 1 ? '✅ Selesai' : '⬜ Belum' ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endif; ?>

                            <?php if ($fitur_terkunci): ?>
                                <div class="habit-item" style="background: #fff;">
                                    <span class="habit-text">🏃 Berolahraga / Fisik</span>
                                    <span class="habit-status" style="color: #bdc3c7;">⬜ Belum</span>
                                </div>
                            <?php else: ?>
                                <a href="?toggle_habit=habit_3" style="text-decoration: none; display: block;">
                                    <div class="habit-item" style="background: <?= $habit_hari_ini['habit_3'] == 1 ? '#e8f8f0' : '#fff' ?>; border-color: <?= $habit_hari_ini['habit_3'] == 1 ? '#2ecc71' : '#eef2f3' ?>;">
                                        <span class="habit-text">🏃 Berolahraga / Fisik</span>
                                        <span class="habit-status" style="color: <?= $habit_hari_ini['habit_3'] == 1 ? '#2ecc71' : '#95a5a6' ?>;">
                                            <?= $habit_hari_ini['habit_3'] == 1 ? '✅ Selesai' : '⬜ Belum' ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function bukaPopupPremium() {
            document.getElementById('popupSubscription').style.display = 'flex';
            tampilSubscriptionPlans();
        }

        function bukaPopupAuthBiasa() {
            document.getElementById('popupSubscription').style.display = 'flex';
            tampilFormLoginOnly();
        }

        function bPopupPremium() {
            bukaPopupPremium();
        }

        function tutupPopup() {
            if (window.location.search.includes('req_login') || window.location.search.includes('status=reg_sukses')) {
                window.location.href = window.location.pathname;
            } else {
                document.getElementById('popupSubscription').style.display = 'none';
            }
        }

        function tampilSubscriptionPlans() {
            document.getElementById('contentSubscription').style.display = 'block';
            document.getElementById('contentLoginOnly').style.display = 'none';
            document.getElementById('contentRegister').style.display = 'none';
        }

        function tampilFormLoginOnly() {
            document.getElementById('contentSubscription').style.display = 'none';
            document.getElementById('contentLoginOnly').style.display = 'block';
            document.getElementById('contentRegister').style.display = 'none';
        }

        function tampilFormRegister() {
            document.getElementById('contentSubscription').style.display = 'none';
            document.getElementById('contentLoginOnly').style.display = 'none';
            document.getElementById('contentRegister').style.display = 'block';
        }

        <?php if (!empty($pesan_sistem)): ?>
            document.getElementById('popupSubscription').style.display = 'flex';
            <?php if (isset($_GET['status']) && $_GET['status'] == 'reg_sukses'): ?>
                tampilFormLoginOnly();
            <?php elseif (isset($_GET['pesan']) && $_GET['pesan'] == 'reg_gagal'): ?>
                tampilFormRegister();
            <?php else: ?>
                tampilFormLoginOnly();
            <?php endif; ?>
        <?php endif; ?>
    </script>

    <!-- FullCalendar JavaScript CDN -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            
            if (calendarEl) {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    locale: 'id',
                    headerToolbar: {
                        left: 'title',
                        right: 'prev,next today'
                    },
                    events: 'get_mood_events.php',
                    height: 'auto',
                    fixedWeekCount: false
                });
                calendar.render();
            }
        });
    </script>
</body>
</html>