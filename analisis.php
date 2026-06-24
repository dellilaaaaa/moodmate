<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Jakarta');

// =========================================================================
// 1. BARIKADE PROTEKSI PREMIUM
// =========================================================================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: dashboard.php?pesan=harus_login");
    exit();
}

if (!isset($_SESSION['is_premium']) || $_SESSION['is_premium'] !== true) {
    header("Location: dashboard.php?status=butuh_premium");
    exit();
}

require_once 'koneksi.php'; // Hubungkan database
$current_user_id = $_SESSION['user_id'] ?? 1;

// =========================================================================
// 2. AMBIL DATA EMOTION HARIAN DARI TABEL riwayat_mood
// =========================================================================
$data_emoji_harian = [];
try {
    $stmt_mood = $pdo->prepare("SELECT mood, waktu FROM riwayat_mood WHERE user_id = ? ORDER BY waktu ASC");
    $stmt_mood->execute([$current_user_id]);
    $riwayat_mood_db = $stmt_mood->fetchAll(PDO::FETCH_ASSOC);

    foreach ($riwayat_mood_db as $row) {
        $data_emoji_harian[] = [
            'tanggal' => date("d M", strtotime($row['waktu'])),
            'label'   => $row['mood'],
            'tinggi'  => ($row['mood'] == 'Senang') ? 130 : (($row['mood'] == 'Biasa') ? 90 : (($row['mood'] == 'Lelah') ? 65 : 45))
        ];
    }
} catch (PDOException $e) {
    die("Gagal memuat riwayat mood harian: " . $e->getMessage());
}

$data_terakhir = !empty($data_emoji_harian) ? end($data_emoji_harian) : null;
$mood_terakhir = $data_terakhir ? $data_terakhir['label'] : 'Belum Check-in';
$tanggal_terakhir_mood = $data_terakhir ? $data_terakhir['tanggal'] : date('d M');


// =========================================================================
// 3. AMBIL DATA SKOR TES DARI TABEL hasil_tes
// =========================================================================
$skor_tes_mingguan = 0;
$tanggal_terakhir_tes = date('d M');

try {
    $stmt_tes = $pdo->prepare("SELECT skor, tanggal_tes FROM hasil_tes WHERE user_id = ? ORDER BY tanggal_tes DESC LIMIT 1");
    $stmt_tes->execute([$current_user_id]);
    $hasil_tes_db = $stmt_tes->fetch(PDO::FETCH_ASSOC);

    if ($hasil_tes_db) {
        $skor_tes_mingguan = intval($hasil_tes_db['skor']);
        $tanggal_terakhir_tes = date("d M", strtotime($hasil_tes_db['tanggal_tes']));
    }
} catch (PDOException $e) {
    die("Gagal memuat hasil tes dari database: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MoodMate PRO - Analisis Mood</title>
    <style>
        :root {
            --logo-teal: #6fbab7;
            --dark-bg: #121212;
            --premium-gold: #f39c12;
            --body-bg: #f3f7f4; 
            --dark-blue: #1d3557;
        }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            display: flex; 
            background-color: var(--body-bg); 
            height: 100vh;
            overflow: hidden;
        }
        
        .sidebar { width: 250px; background-color: var(--dark-bg); color: white; padding: 20px; display: flex; flex-direction: column; }
        .sidebar-logo { margin-bottom: 30px; text-align: center; font-weight: bold; font-size: 1.5rem; color: #7be0ad; }
        .nav-menu a { color: #bdc3c7; text-decoration: none; padding: 12px; display: block; border-radius: 8px; margin-bottom: 5px; font-size: 0.95rem; }
        .nav-menu a.active { background: rgba(255,255,255,0.1); color: #7be0ad; font-weight: bold; }
        .badge-pro { background: #f39c12; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; float: right; margin-top: 2px; }

        .main-content { flex: 1; padding: 30px; overflow-y: auto; color: var(--dark-blue); }
        .header-title h2 { margin: 0; font-size: 1.5rem; font-weight: 700; color: #0f4c5c; }
        .header-title p { margin: 5px 0 25px 0; color: #7f8c8d; font-size: 0.9rem; }

        .dashboard-container { display: grid; grid-template-columns: 2.3fr 1fr; gap: 25px; max-width: 1200px; }
        .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); margin-bottom: 25px; }

        .chart-container {
            height: 180px;
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 20px;
            margin-top: 30px;
            border-bottom: 1px solid #eaedd5;
            padding-bottom: 10px;
        }
        .bar-wrapper { display: flex; flex-direction: column; align-items: center; width: 60px; }
        .bar-visual { 
            background: #2ecc71; 
            width: 35px; 
            border-radius: 6px 6px 0 0; 
            position: relative;
            transition: height 0.4s ease;
        }
        .bar-emoji { position: absolute; top: -25px; left: 50%; transform: translateX(-50%); font-size: 1.3rem; }

        .mini-chart-box {
            background: #edf2f4;
            height: 45px;
            border-radius: 6px;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }
        .mini-bar {
            background: linear-gradient(90deg, #3a86c8, #4ea8de);
            height: 100%;
            display: flex;
            align-items: center;
            padding-left: 10px;
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .diagnosis-card { border-left: 4px solid #f1c40f; }
        .quote-box { background: #fffcf4; border: 1px dashed #f39c12; border-radius: 8px; padding: 15px; margin-top: 15px; font-style: italic; font-size: 0.85rem; }

        .score-card { text-align: center; }
        .score-card h3 { margin: 0; font-size: 0.75rem; color: #7f8c8d; letter-spacing: 0.5px; }
        .score-big { font-size: 2.5rem; font-weight: bold; margin: 10px 0 5px 0; }
        .score-sub { font-size: 1rem; color: #bdc3c7; font-weight: normal; }
        .status-badge { background: #fff3cd; color: #d68910; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; display: inline-block; }

        .action-card { background: #11141a; color: white; }
        .action-card ul { padding-left: 15px; margin: 10px 0 0 0; font-size: 0.8rem; color: #a4b0be; line-height: 1.6; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-logo">MoodMate</div>
        <nav class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="tracking.php">Tes Emosional</a>
            <a href="analisis.php" class="active">Analisis Mood <span class="badge-pro">PRO</span></a>
            <a href="curhat.php">Sesi Curhat <span class="badge-pro">PRO</span></a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-title">
            <h2>📊 Analisis Mood Lanjutan</h2>
            <p>Laporan grafik harian murni dan pemisahan nilai hasil tes emosional.</p>
        </div>

        <div class="dashboard-container">
            <div>
                <!-- GRAFIK KIRI: DIAMBIL DARI TABEL riwayat_mood -->
                <div class="card">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.2rem;">📅</span>
                        <h3 style="margin: 0; font-size: 1rem;">Tren Grafik Perasaan Harian (Klik Dashboard)</h3>
                    </div>
                    <p style="color: #7f8c8d; font-size: 0.8rem; margin: 5px 0 0 0;">Fluktuasi emosi harianmu berdasarkan tombol cepat yang kamu tekan setiap pagi di dashboard utama.</p>
                    
                    <div class="chart-container">
                        <?php if (!empty($data_emoji_harian)): ?>
                            <?php foreach ($data_emoji_harian as $emoji): ?>
                                <div class="bar-wrapper">
                                    <div class="bar-visual" style="height: <?php echo $emoji['tinggi']; ?>px;">
                                        <span class="bar-emoji">
                                            <?php 
                                            if ($emoji['label'] == 'Senang') echo '😄';
                                            elseif ($emoji['label'] == 'Biasa') echo '😐';
                                            elseif ($emoji['label'] == 'Lelah') echo '😩';
                                            elseif ($emoji['label'] == 'Sedih') echo '😔';
                                            else echo '😊';
                                            ?>
                                        </span>
                                    </div>
                                    <span style="font-size: 0.75rem; color: #7f8c8d; margin-top: 8px;"><?php echo $emoji['tanggal']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="font-size: 0.85rem; color: #94a3b8; align-self: center; margin-bottom: 40px;">Belum ada riwayat check-in emoji dari Dashboard.</p>
                        <?php endif; ?>
                    </div>

                    <p style="font-size: 0.8rem; margin-top: 15px; color: #2c3e50; line-height: 1.4;">
                        💡 <strong>Insight MoodMate:</strong> Hari ini check-in emoji harianmu tercatat sebagai <strong>Merasa <?php echo htmlspecialchars($mood_terakhir); ?></strong> pada tanggal <strong><?php echo $tanggal_terakhir_mood; ?></strong>.
                    </p>
                </div>

                <div class="card diagnosis-card">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.2rem;">💡</span>
                        <h3 style="margin: 0; font-size: 1rem;">Diagnosis & Motivasi Emosimu Hari Ini</h3>
                    </div>
                    <p style="color: #555; font-size: 0.85rem; line-height: 1.5; margin-top: 10px;">
                        Grafikmu minggu ini menunjukkan adanya fluktuasi emosi. Terus pantau check-in emosimu setiap hari untuk melacak kesehatan psikologismu.
                    </p>
                    <div class="quote-box">
                        <strong>Pesan MoodMate:</strong> "Gak apa-apa kok kalau lagi merasa lelah, Buddy. Roda emosi itu berputar. Malam ini, coba kurangi screen time, seduh teh hangat, dan tidur lebih cepat ya. Kamu sudah berusaha keras minggu ini. ☕"
                    </div>
                </div>
            </div>

            <div>
                <!-- PANEL KANAN: SEKARANG REALTIME DIAMBIL DARI TABEL hasil_tes -->
                <div class="card score-card">
                    <h3>SKOR TES TERAKHIR</h3>
                    <div class="score-big"><?php echo $skor_tes_mingguan; ?><span class="score-sub">/100</span></div>
                    <div class="status-badge">
                        <?php 
                        if ($skor_tes_mingguan == 0) echo "Belum Mengikuti Tes";
                        elseif ($skor_tes_mingguan <= 35) echo "Stabil (Kondisi Baik)";
                        elseif ($skor_tes_mingguan <= 65) echo "Fluktuatif (Butuh Istirahat)";
                        else echo "Tertekan (Butuh Hiburan)";
                        ?>
                    </div>
                </div>

                <div class="card" style="padding: 15px 20px;">
                    <h4 style="margin: 0; font-size: 0.8rem; color: #7f8c8d;">📈 Skor Tes Mingguan (25 Soal)</h4>
                    <div class="mini-chart-box">
                        <div class="mini-bar" style="width: <?php echo max(5, $skor_tes_mingguan); ?>%;">
                            <?php echo $skor_tes_mingguan; ?>% (<?php echo $tanggal_terakhir_tes; ?>)
                        </div>
                    </div>
                </div>

                <div class="card action-card">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.1rem;">🎯</span>
                        <h4 style="margin: 0; font-size: 0.85rem;">Premium Action Plan</h4>
                    </div>
                    <ul>
                        <li>Lakukan teknik pernapasan kotak (*Box Breathing*) 4x4 setiap jam 2 siang.</li>
                        <li>Gunakan fitur <strong>Sesi Curhat</strong> hari ini untuk merilis unek-unek yang menumpuk.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>