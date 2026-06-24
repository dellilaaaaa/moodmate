<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Perbaikan koneksi database jika diperlukan untuk sinkronisasi riwayat_tes kedepannya
require_once 'koneksi.php';

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// 1. CEK APAKAH USER SUDAH PERNAH TES DALAM 7 HARI TERAKHIR
$sudah_tes_minggu_ini = false;
$sisa_hari = 0;

if (isset($_SESSION['terakhir_tes'])) {
    $tanggal_tes = strtotime($_SESSION['terakhir_tes']);
    $tanggal_sekarang = time();
    
    // Hitung selisih hari (60 detik * 60 menit * 24 jam = 1 hari)
    $selisih_hari = ($tanggal_sekarang - $tanggal_tes) / (60 * 60 * 24);
    
    // Jika belum lewat 7 hari, set true (kunci akses tes)
    if ($selisih_hari < 7) {
        $sudah_tes_minggu_ini = true;
        // Hitung sisa hari untuk ditampilkan ke user
        $sisa_hari = 7 - ceil($selisih_hari); 
    }
}

// =========================================================================
// 2. PROSES JIKA USER KLIK SIMPAN (SUDAH LOGIN) -> MASUK KE TABEL hasil_tes
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] == 'simpan_ke_dashboard') {
    $skor = isset($_GET['score']) ? intval($_GET['score']) : 0;
    $current_user_id = $_SESSION['user_id'] ?? 1; // Sesuaikan dengan session user login-mu

    try {
        // MURNI: Simpan hasil tes ke tabel hasil_tes, bukan riwayat_mood!
        $stmt_insert = $pdo->prepare("INSERT INTO hasil_tes (user_id, skor, tanggal_tes) VALUES (?, ?, NOW())");
        $stmt_insert->execute([$current_user_id, $skor]);

        // Catat pengunci tanggal tes di session
        $_SESSION['terakhir_tes'] = date("Y-m-d");

        // Alihkan ke halaman analisis dengan parameter sukses
        header("Location: analisis.php?sukses_simpan_tes=1");
        exit();

    } catch (PDOException $e) {
        die("Gagal menyimpan hasil tes ke database: " . $e->getMessage());
    }
}

// 3. PROSES JIKA USER BARU DAFTAR DI BAWAH HASIL TES (BELUM LOGIN)
if (isset($_POST['proses_login_tes'])) {
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = htmlspecialchars($_POST['username']);
    $_SESSION['email'] = htmlspecialchars($_POST['email']);
    $_SESSION['is_premium'] = false; 
    
    $skor_terakhir = isset($_POST['saved_score']) ? intval($_POST['saved_score']) : 0;
    
    $_SESSION['riwayat_tes'][] = [
        'tanggal' => date("d M"),
        'skor' => $skor_terakhir
    ];
    
    // CATAT TANGGAL TES HARI INI KE SESSION
    $_SESSION['terakhir_tes'] = date("Y-m-d");
    
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MoodMate - Tes Emosional Lengkap</title>
    <style>
        /* CSS bawaan dipertahankan 100% tanpa ubahan visual */
        :root {
            --logo-teal: #6fbab7;
            --logo-orange: #ffcc99;
            --dark-blue: #1d3557;
            --soft-blue: #f1faee;
            --dark-bg: #121212;
            --premium-gold: #f39c12;
            --white: #ffffff;
        }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; display: flex; background-color: var(--soft-blue); color: var(--dark-blue); height: 100vh; }
        
        .sidebar { width: 250px; background-color: var(--dark-bg); color: white; padding: 20px; display: flex; flex-direction: column; }
        .sidebar-logo { margin-bottom: 30px; text-align: center; font-weight: bold; font-size: 1.5rem; color: var(--logo-teal); }
        .nav-menu a { color: #bdc3c7; text-decoration: none; padding: 12px; display: block; border-radius: 8px; margin-bottom: 5px; }
        .nav-menu a.active { background: rgba(255,255,255,0.1); color: var(--logo-teal); }

        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); max-width: 650px; margin: 0 auto 30px; }
        
        .question-box { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid var(--logo-teal); }
        .question-text { font-weight: bold; margin-top: 0; margin-bottom: 12px; font-size: 0.95rem; }
        .options-group { display: flex; flex-direction: column; gap: 8px; }
        .options-group label { display: flex; align-items: center; gap: 10px; background: white; padding: 10px; border-radius: 6px; cursor: pointer; border: 1px solid #e0e0e0; transition: 0.2s; font-size: 0.9rem; }
        .options-group label:hover { background: var(--soft-blue); border-color: var(--logo-teal); }
        
        .btn-submit { background: var(--dark-blue); color: white; border: none; padding: 14px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%; font-size: 1rem; text-decoration: none; display: inline-block; text-align: center; box-sizing: border-box; }
        .btn-submit:hover { opacity: 0.9; }

        .result-container { text-align: left; }
        .score-badge { display: inline-block; background: var(--dark-blue); color: white; padding: 8px 15px; border-radius: 20px; font-weight: bold; font-size: 1.1rem; margin-bottom: 15px; }
        .category-title { font-size: 1.4rem; font-weight: bold; margin: 0 0 10px 0; }
        .bullet-list { padding-left: 20px; line-height: 1.5; font-size: 0.9rem; }

        .recommendation-box { background: linear-gradient(135deg, #1d3557, #3d7e96); color: white; padding: 20px; border-radius: 12px; margin-top: 30px; }
        .quick-login-form { background: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 8px; margin-top: 15px; }
        .quick-login-form label { font-size: 0.8rem; font-weight: bold; display: block; margin-top: 8px; color: var(--logo-orange); }
        .quick-login-form input { width: 100%; padding: 8px; margin-top: 4px; border: none; border-radius: 6px; box-sizing: border-box; }
        .btn-quick-reg { background: var(--premium-gold); color: white; border: none; padding: 10px; border-radius: 6px; font-weight: bold; width: 100%; margin-top: 15px; cursor: pointer; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-logo">MoodMate</div>
        <nav class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="tracking.php" class="active">Tes Emosional</a>

            <?php 
            if (isset($_SESSION['logged_in']) && isset($_SESSION['is_premium']) && $_SESSION['is_premium'] === true) {
                echo '<a href="analisis.php">Analisis Mood <span style="background:gold; color:black; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">PRO</span></a>';
            } else {
                echo '<a href="dashboard.php?status=butuh_premium" style="opacity: 0.6;">Analisis Mood 🔒</a>';
            }
            ?>

            <?php 
            if (isset($_SESSION['logged_in']) && isset($_SESSION['is_premium']) && $_SESSION['is_premium'] === true) {
                echo '<a href="curhat.php">Sesi Curhat <span style="background:gold; color:black; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">PRO</span></a>';
            } else {
                echo '<a href="dashboard.php?status=butuh_premium" style="opacity: 0.6;">Sesi Curhat 🔒</a>';
            }
            ?>
        </nav>
    </div>

    <div class="main-content">
        <h2 style="text-align: center; margin-top: 0;">🧠 Tes Gangguan Emosional (Gratis)</h2>

        <div class="card">
            
            <div id="hasilTesContainer" style="display:none;" class="result-container">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div class="score-badge">Skor Anda: <span id="textSkor">0</span> / 100</div>
                    <div class="category-title" id="textKategori">Memuat Hasil...</div>
                </div>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <strong>Deskripsi Kondisi:</strong>
                    <p id="textDeskripsi" style="font-size: 0.9rem; line-height: 1.5; color: #444; margin-top: 5px;"></p>
                </div>

                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <strong>Saran Penanganan:</strong>
                    <ul id="listSaran" class="bullet-list" style="margin-top: 5px;"></ul>
                </div>

                <div id="areaUserLogin" style="display: none;">
                    <p style="color: green; font-weight: bold; font-size: 0.9rem; text-align: center;">✓ Analisis selesai! Amankan datamu ke grafik mingguan.</p>
                    <a href="#" id="linkSimpanDashboard" class="btn-submit" style="margin-top: 10px;">Simpan Hasil & Lihat Grafik</a>
                </div>

                <div id="areaUserTamu" style="display: none;">
                    <div class="recommendation-box">
                        <h4 style="margin: 0 0 8px 0; color: var(--logo-orange); font-size: 1.05rem;">🔒 Simpan Hasil & Buka Grafik MoodMate</h4>
                        <p style="margin: 0; font-size: 0.85rem; line-height: 1.4; color: #e0e0e0;">
                            Skor di atas akan hilang jika ditutup. Yuk buat akun gratis sekarang, otomatis skor ini langsung masuk jadi grafik perkembangan emosimu!
                        </p>
                        
                        <form method="POST" action="" class="quick-login-form">
                            <input type="hidden" name="saved_score" id="savedScoreInput" value="0">
                            
                            <label>Username Baru</label>
                            <input type="text" name="username" placeholder="Contoh: buddy_sehat" required>
                            
                            <label>Alamat Email</label>
                            <input type="email" name="email" placeholder="nama@email.com" required>
                            
                            <label>Password</label>
                            <input type="password" name="password" placeholder="Buat kata sandi" required>
                            
                            <button type="submit" name="proses_login_tes" class="btn-quick-reg">Simpan Hasil & Buat Akun</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($sudah_tes_minggu_ini && $is_logged_in): ?>
                <div style="text-align: center; padding: 20px; color: #7f8c8d;">
                    <span style="font-size: 3rem;">⏳</span>
                    <h3 style="color: var(--dark-blue); margin-top: 15px;">Akses Tes Terkunci</h3>
                    <p style="font-size: 0.95rem; line-height: 1.5;">Anda baru saja melakukan pengisian tes emosional baru-baru ini.<br>Untuk melihat akurasi perkembangan emosi yang optimal, silakan kembali dalam <strong><?= $sisa_hari; ?> hari</strong> lagi.</p>
                    <a href="dashboard.php" class="btn-submit" style="margin-top: 15px; width: auto; padding: 10px 25px;">Kembali ke Dashboard</a>
                </div>
            <?php else: ?>
                <form id="form25Pertanyaan" onsubmit="hitungSkorLokal(event)">
                    <?php
                    $pertanyaan = [
                        1 => "Dalam beberapa hari terakhir, apakah Anda merasa sedih tanpa alasan yang jelas?",
                        2 => "Apakah Anda merasa sulit menikmati aktivitas yang biasanya Anda sukai?",
                        3 => "Apakah Anda sering merasa cemas terhadap hal-hal yang belum tentu terjadi?",
                        4 => "Apakah Anda merasa sulit berkonsentrasi saat belajar atau bekerja?",
                        5 => "Apakah Anda merasa mudah tersinggung atau marah?",
                        6 => "Apakah Anda merasa kelelahan meskipun tidak melakukan aktivitas berat?",
                        7 => "Apakah Anda sering memikiran masalah yang sama berulang kali (overthinking)?",
                        8 => "Apakah Anda merasa kesulitan untuk rileks atau menenangkan pikiran?",
                        9 => "Apakah Anda merasa kurang percaya diri terhadap diri sendiri?",
                        10 => "Apakah Anda merasa tidak memiliki cukup dukungan dari orang sekitar?",
                        11 => "Apakah Anda merasa tertekan oleh tugas, pekerjaan, atau tanggung jawab sehari-hari?",
                        12 => "Apakah Anda merasa sulit mengendalikan emosi ketika menghadapi masalah?",
                        13 => "Apakah Anda sering merasa khawatir terhadap masa depan?",
                        14 => "Apakah Anda merasa kesepian meskipun berada di sekitar banyak orang?",
                        15 => "Apakah Anda mengalami perubahan suasana hati yang cukup drastis?",
                        16 => "Apakah Anda merasa sulit tidur karena banyak pikiran?",
                        17 => "Apakah Anda merasa kurang termotivasi untuk melakukan aktivitas sehari-hari?",
                        18 => "Apakah Anda sering membandingkan diri dengan orang lain?",
                        19 => "Apakah Anda merasa tidak puas dengan pencapaian diri sendiri?",
                        20 => "Apakah Anda sering menyalahkan diri sendiri atas hal-hal kecil?",
                        21 => "Apakah Anda merasa sulit menceritakan perasaan kepada orang lain?",
                        22 => "Apakah Anda merasa kewalahan menghadapi masalah yang sedang terjadi?",
                        23 => "Apakah Anda merasa emosi negatif memengaruhi produktivitas Anda?",
                        24 => "Apakah Anda merasa membutuhkan waktu sendiri untuk memulihkan kondisi emosional?",
                        25 => "Apakah Anda merasa kondisi emosional Anda saat ini mengganggu kehidupan sehari-hari?"
                    ];

                    foreach ($pertanyaan as $index => $soal) {
                        echo '<div class="question-box">';
                        echo '  <p class="question-text">' . $index . '. ' . $soal . '</p>';
                        echo '  <div class="options-group">';
                        echo '      <label><input type="radio" name="soal_' . $index . '" value="0" required> Tidak Pernah</label>';
                        echo '      <label><input type="radio" name="soal_' . $index . '" value="1"> Jarang</label>';
                        echo '      <label><input type="radio" name="soal_' . $index . '" value="2"> Kadang-kadang</label>';
                        echo '      <label><input type="radio" name="soal_' . $index . '" value="3"> Sering</label>';
                        echo '      <label><input type="radio" name="soal_' . $index . '" value="4"> Sangat Sering</label>';
                        echo '  </div>';
                        echo '</div>';
                    }
                    ?>
                    <button type="submit" class="btn-submit">Lihat Hasil Analisis</button>
                </form>
            <?php endif; ?>

        </div>
    </div>

    <script>
        var sudahLogin = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

        function hitungSkorLokal(event) {
            event.preventDefault();
            let totalSkor = 0;
            let totalPertanyaan = 25;
            
            for (let i = 1; i <= totalPertanyaan; i++) {
                let opsiTerpilih = document.querySelector(`input[name="soal_${i}"]:checked`);
                if (opsiTerpilih) {
                    totalSkor += parseInt(opsiTerpilih.value);
                }
            }
            
            // Memastikan penanganan kalkulasi skor berada di rentang skala maks 100 sesuai UI/badge Anda
            tampilkanHasilSkor(totalSkor);
        }

        function tampilkanHasilSkor(score) {
            // Melakukan toggle view container secara aman tanpa merusak CSS/layout asal
            let formElemen = document.getElementById('form25Pertanyaan');
            if(formElemen) {
                formElemen.style.display = 'none';
            }
            
            document.getElementById('hasilTesContainer').style.display = 'block';
            document.getElementById('textSkor').innerText = score;

            let kategori = "", deskripsi = "", saran = [];

            if (score <= 20) {
                kategori = "🌱 Kondisi Emosional Stabil";
                deskripsi = "Saat ini kondisi emosional Anda cenderung baik dan stabil. Anda mampu menghadapi tekanan sehari-hari dengan cukup sehat.";
                saran = ["Pertahankan kebiasaan positif.", "Tetap lakukan refleksi diri.", "Luangkan waktu untuk aktivitas yang Anda sukai."];
            } else if (score <= 40) {
                kategori = "😊 Kondisi Emosional Cukup Baik";
                deskripsi = "Terdapat beberapa tekanan atau kekhawatiran yang Anda rasakan, tetapi masih dalam batas yang wajar.";
                saran = ["Mulai rutin melakukan mood tracking.", "Jaga pola tidur dan istirahat.", "Ceritakan perasaan kepada orang yang dipercaya."];
            } else if (score <= 60) {
                kategori = "😐 Perlu Perhatian Emosional";
                deskripsi = "Anda sedang menghadapi tekanan emosional yang cukup terasa dan mulai memengaruhi keseharian.";
                saran = ["Luangkan waktu untuk self-care.", "Kurangi aktivitas yang membuat stres berlebihan.", "Gunakan jurnal harian untuk memahami pemicu emosi."];
            } else if (score <= 80) {
                kategori = "😟 Kondisi Emosional Sedang Tertekan";
                deskripsi = "Tekanan emosional yang Anda alami cukup tinggi dan berpotensi memengaruhi produktivitas serta kualitas hidup.";
                saran = ["Prioritaskan kesehatan mental Anda.", "Cari dukungan dari teman, keluarga, atau orang terpercaya.", "Pertimbangkan berkonsultasi dengan profesional jika kondisi berlangsung lama."];
            } else {
                kategori = "🚨 Kondisi Emosional Membutuhkan Dukungan Lebih Lanjut";
                deskripsi = "Hasil menunjukkan bahwa Anda sedang mengalami tekanan emosional yang cukup berat.";
                saran = ["Jangan menghadapi semuanya sendirian.", "Hubungi orang yang Anda percaya.", "Pertimbangkan mencari bantuan profesional seperti psikolog atau konselor."];
            }

            document.getElementById('textKategori').innerText = kategori;
            document.getElementById('textDeskripsi').innerText = deskripsi;
            
            let listHtml = "";
            saran.forEach(i => listHtml += `<li>${i}</li>`);
            document.getElementById('listSaran').innerHTML = listHtml;

            // Membuka area user secara tepat berdasarkan variabel status login session
            if (sudahLogin) {
                document.getElementById('areaUserLogin').style.display = 'block';
                document.getElementById('linkSimpanDashboard').href = `tracking.php?action=simpan_ke_dashboard&score=${score}`;
            } else {
                document.getElementById('areaUserTamu').style.display = 'block';
                document.getElementById('savedScoreInput').value = score;
            }
            
            // Scroll otomatis ke bagian atas card agar hasil tes langsung terlihat jelas oleh pengguna
            document.getElementById('hasilTesContainer').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>