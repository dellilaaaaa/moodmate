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
    $stmt_mood = $pdo->prepare("SELECT mood, waktu FROM riwayat_mood WHERE user_id = ? ORDER BY waktu ASC LIMIT 7");
    $stmt_mood->execute([$current_user_id]);
    $riwayat_mood_db = $stmt_mood->fetchAll(PDO::FETCH_ASSOC);

    foreach ($riwayat_mood_db as $row) {
        $nilai_mood = $row['mood'];
        $data_emoji_harian[] = [
            'tanggal' => date("d M", strtotime($row['waktu'])),
            'label'   => $nilai_mood,
            'tinggi'  => ($nilai_mood == 'Senang') ? 130 : (($nilai_mood == 'Biasa') ? 90 : (($nilai_mood == 'Lelah') ? 65 : 45))
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


// =========================================================================
// 4. [FIXED] AMBIL DATA HABIT TRACKER DARI TABEL log_habit_harian (TANPA KOLOM STATUS)
// =========================================================================
$total_habit_hari_ini = 0;
$persentase_habit = 0;

try {
    $hari_ini = date('Y-m-d');
    
    // Menghitung berapa banyak entri habit yang dicatat hari ini
    $stmt_total_habit = $pdo->prepare("SELECT COUNT(*) FROM log_habit_harian WHERE user_id = ? AND DATE(tanggal) = ?");
    $stmt_total_habit->execute([$current_user_id, $hari_ini]);
    $total_habit_hari_ini = intval($stmt_total_habit->fetchColumn());

    // Asumsi target harian adalah 4 habit aktivitas
    $target_harian = 4; 
    if ($total_habit_hari_ini > 0) {
        $persentase_habit = min(100, round(($total_habit_hari_ini / $target_harian) * 100));
    }
} catch (PDOException $e) {
    die("Gagal memuat log habit harian: " . $e->getMessage());
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
            --body-bg: #f8fafc; 
            --dark-blue: #1d3557;
            --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.03);
            --habit-green: #2ec4b6;
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
        .sidebar-logo { margin-bottom: 30px; text-align: center; font-weight: bold; font-size: 1.5rem; color: var(--logo-teal); }
        .nav-menu a { color: #bdc3c7; text-decoration: none; padding: 12px; display: block; border-radius: 8px; margin-bottom: 5px; font-size: 0.95rem; transition: 0.3s; }
        .nav-menu a:hover, .nav-menu a.active { background: rgba(255,255,255,0.1); color: var(--logo-teal); font-weight: bold; }
        .badge-pro { background: var(--premium-gold); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; float: right; margin-top: 2px; }

        .main-content { flex: 1; padding: 40px; overflow-y: auto; color: var(--dark-blue); box-sizing: border-box; }
        .header-title h2 { margin: 0; font-size: 1.7rem; font-weight: 700; color: var(--dark-blue); }
        .header-title p { margin: 5px 0 30px 0; color: #7f8c8d; font-size: 0.95rem; }

        .dashboard-container { display: grid; grid-template-columns: 1.8fr 1fr; gap: 30px; max-width: 1200px; }
        .card { background: white; border-radius: 20px; padding: 25px; box-shadow: var(--card-shadow); margin-bottom: 0; border: 1px solid #f1f5f9; position: relative; }

        .chart-container {
            height: 200px;
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            gap: 10px;
            margin-top: 35px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 15px;
        }
        .bar-wrapper { display: flex; flex-direction: column; align-items: center; flex: 1; max-width: 60px; }
        .bar-visual { 
            background: linear-gradient(180deg, #6fbab7, #3d7e96); 
            width: 100%; 
            border-radius: 8px 8px 0 0; 
            position: relative;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 10px rgba(111, 186, 183, 0.2);
        }
        .bar-visual:hover {
            transform: scale(1.05);
            filter: brightness(1.1);
        }
        .bar-emoji { position: absolute; top: -30px; left: 50%; transform: translateX(-50%); font-size: 1.4rem; }

        .mini-chart-box {
            background: #f1f5f9;
            height: 40px;
            border-radius: 12px;
            margin-top: 15px;
            position: relative;
            overflow: hidden;
        }
        .mini-bar {
            background: linear-gradient(90deg, #3d7e96, #6fbab7);
            height: 100%;
            display: flex;
            align-items: center;
            padding-left: 15px;
            color: white;
            font-size: 0.8rem;
            font-weight: bold;
            border-radius: 12px;
            transition: width 0.5s ease;
        }
        
        .mini-bar.habit-bar {
            background: linear-gradient(90deg, #2a9d8f, var(--habit-green));
        }

        .diagnosis-card { border-left: 5px solid var(--logo-teal); display: flex; flex-direction: column; gap: 10px; }
        .quote-box { background: #fdfaf2; border: 1px solid #fde7c3; border-radius: 14px; padding: 18px; margin-top: 10px; font-style: italic; font-size: 0.9rem; color: #7e5109; line-height: 1.5; }

        .score-card { text-align: center; padding: 35px 25px; }
        .score-card h3 { margin: 0; font-size: 0.8rem; color: #7f8c8d; letter-spacing: 1px; text-transform: uppercase; }
        .score-big { font-size: 3.2rem; font-weight: 800; margin: 15px 0 8px 0; color: var(--dark-blue); }
        .score-sub { font-size: 1.2rem; color: #94a3b8; font-weight: 500; }
        
        .status-badge { 
            background: #fef3c7; 
            color: #d97706; 
            padding: 6px 20px; 
            border-radius: 30px; 
            font-size: 0.85rem; 
            font-weight: 700; 
            display: inline-block; 
        }

        .action-card { background: var(--dark-bg); color: white; border: none; }
        .action-card ul { padding-left: 20px; margin: 15px 0 0 0; font-size: 0.85rem; color: #94a3b8; line-height: 1.6; }
        .action-card li { margin-bottom: 10px; }
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
            <h2>📊 Analisis Mood & Aktivitas Lanjutan</h2>
            <p>Laporan grafik statistik emosional terpadu akun premium milikmu.</p>
        </div>

        <div class="dashboard-container">
            <!-- PANEL KIBARAN KIRI -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                
                <div class="card">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 1.3rem;">📅</span>
                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;">Tren Grafik Perasaan Harian</h3>
                    </div>
                    <p style="color: #7f8c8d; font-size: 0.85rem; margin: 6px 0 0 0;">Fluktuasi emosimu berdasarkan check-in pagi hari dari dashboard utama.</p>
                    
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
                                    <span style="font-size: 0.75rem; color: #7f8c8d; margin-top: 10px; font-weight: 500;"><?php echo $emoji['tanggal']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="font-size: 0.9rem; color: #94a3b8; align-self: center; margin-bottom: 50px;">Belum ada riwayat check-in emoji dari Dashboard.</p>
                        <?php endif; ?>
                    </div>

                    <p style="font-size: 0.85rem; margin-top: 20px; color: #475569; line-height: 1.5; background: #f8fafc; padding: 12px 16px; border-radius: 12px;">
                        💡 <strong>Insight Harian:</strong> Terakhir kali kamu memperbarui perasaan sebagai <strong>Merasa <?php echo htmlspecialchars($mood_terakhir); ?></strong> pada tanggal <strong><?php echo $tanggal_terakhir_mood; ?></strong>.
                    </p>
                </div>

                <div class="card diagnosis-card">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.3rem;">💡</span>
                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;">Diagnosis & Motivasi Emosimu</h3>
                    </div>
                    <p style="color: #475569; font-size: 0.9rem; line-height: 1.6; margin: 5px 0 0 0;">
                        Grafik mingguan menunjukkan dinamika emosional yang wajar. Melacak fluktuasi ini membantu kamu mendeteksi kelelahan mental (*burnout*) lebih dini.
                    </p>
                    <div class="quote-box">
                        <strong>Pesan Hangat MoodMate:</strong> "Gak apa-apa kok kalau lagi merasa lelah, Buddy. Roda emosi itu berputar. Malam ini, coba kurangi screen time, seduh teh hangat, dan tidur lebih cepat ya. Kamu sudah berusaha keras minggu ini. ☕"
                    </div>
                </div>
            </div>

            <!-- PANEL KIBARAN KANAN -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                
                <div class="card score-card">
                    <h3>Skor Tes Terakhir</h3>
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

                <div class="card" style="padding: 22px 25px;">
                    <h4 style="margin: 0; font-size: 0.85rem; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px;">📈 Persentase Beban Stres</h4>
                    <div class="mini-chart-box">
                        <div class="mini-bar" style="width: <?php echo max(8, $skor_tes_mingguan); ?>%;">
                            <?php echo $skor_tes_mingguan; ?>% (<?php echo $tanggal_terakhir_tes; ?>)
                        </div>
                    </div>
                </div>

                <!-- CARD TRACKER HABIT HARIAN -->
                <div class="card" style="padding: 22px 25px;">
                    <h4 style="margin: 0; font-size: 0.85rem; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px;">✅ Progress Habit Hari Ini</h4>
                    <div class="mini-chart-box">
                        <div class="mini-bar habit-bar" style="width: <?php echo max(8, $persentase_habit); ?>%;">
                            <?php echo $persentase_habit; ?>% Progress
                        </div>
                    </div>
                    <p style="font-size: 0.8rem; color: #7f8c8d; margin: 10px 0 0 0;">
                        Kamu sudah mencatat <strong><?php echo $total_habit_hari_ini; ?></strong> aktivitas habit pada hari ini.
                    </p>
                </div>

                <div class="card action-card">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.2rem;">🎯</span>
                        <h4 style="margin: 0; font-size: 1rem; font-weight: 700;">Premium Action Plan</h4>
                    </div>
                    <ul>
                        <li>Lakukan teknik pernapasan kotak (*Box Breathing*) 4x4 saat merasa cemas.</li>
                        <li>Gunakan fitur <strong>Sesi Curhat</strong> hari ini untuk merilis unek-unek yang menumpuk.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>