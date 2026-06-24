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

// =========================================================================
// 2. LOGIKA SHIFT JAM OPERASIONAL ADMIN AUTOMATION (BERDASARKAN KARAKTER)
// =========================================================================
$jam_sekarang = (int)date('H');

// Konfigurasi 4 Admin - Avatar Jilbab & Non-Jilbab Sudah Disesuaikan
$daftar_admin = [
    [
        'id' => 1,
        'nama' => 'Ijah',
        'avatar' => '🧕', // Jilbab
        'keunggulan' => 'Pendengar yang hangat & penuh empati.',
        'shift_teks' => '06:00 - 12:00 WIB',
        'jam_mulai' => 6,
        'jam_selesai' => 12,
        'no_wa' => '6281234567890' // Ganti nomor WA asli Ijah di sini
    ],
    [
        'id' => 2,
        'nama' => 'Rifaa',
        'avatar' => '🧕', // Jilbab
        'keunggulan' => 'Solutif, taktis, & fokus jalan keluar.',
        'shift_teks' => '12:00 - 18:00 WIB',
        'jam_mulai' => 12,
        'jam_selesai' => 18,
        'no_wa' => '6282345678901' // Ganti nomor WA asli Rifaa di sini
    ],
    [
        'id' => 3,
        'nama' => 'Kai',
        'avatar' => '👩‍💼', // Tanpa Jilbab
        'keunggulan' => 'Santai, friendly, seperti sahabat sendiri.',
        'shift_teks' => '18:00 - 00:00 WIB',
        'jam_mulai' => 18,
        'jam_selesai' => 24,
        'no_wa' => '6283456789012' // Ganti nomor WA asli Kai di sini
    ],
    [
        'id' => 4,
        'nama' => 'Dellila',
        'avatar' => '👩‍🎓', // Tanpa Jilbab
        'keunggulan' => 'Menemani overthinking & deep talk malam.',
        'shift_teks' => '00:00 - 06:00 WIB',
        'jam_mulai' => 0,
        'jam_selesai' => 6,
        'no_wa' => '6284567890123' // Ganti nomor WA asli Dellila di sini
    ]
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MoodMate PRO - Sesi Curhat Privat</title>
    <style>
        :root {
            --logo-teal: #6fbab7;
            --dark-bg: #121212;
            --premium-gold: #f39c12;
            --body-bg: #f3f7f4; 
            --dark-blue: #1d3557;
            --white: #ffffff;
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
        .badge-pro { background: var(--premium-gold); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; float: right; margin-top: 2px; }

        .main-content { flex: 1; padding: 30px; overflow-y: auto; color: var(--dark-blue); }
        .header-title h2 { margin: 0; font-size: 1.5rem; font-weight: 700; color: #0f4c5c; }
        .header-title p { margin: 5px 0 25px 0; color: #7f8c8d; font-size: 0.9rem; }

        .dashboard-container { display: grid; grid-template-columns: 2.3fr 1fr; gap: 25px; max-width: 1200px; }
        .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); margin-bottom: 25px; }

        .admin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .admin-card { 
            border: 2px solid #eef2f5; border-radius: 12px; padding: 15px; 
            position: relative; cursor: pointer; transition: 0.2s; background: #fff;
        }
        .admin-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        
        .admin-card.online { border-color: #2ecc71; background: #fafffa; }
        .admin-card.offline { opacity: 0.6; background: #fafafa; cursor: not-allowed; border-color: #e2e8f0; }
        
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .status-dot.online { background: #2ecc71; }
        .status-dot.offline { background: #e74c3c; }
        
        .badge-status { font-size: 0.7rem; font-weight: bold; position: absolute; top: 15px; right: 15px; padding: 3px 8px; border-radius: 12px; }
        .badge-status.online { background: #e8f8f0; color: #2ecc71; }
        .badge-status.offline { background: #fdedec; color: #e74c3c; }

        .payment-section { background: #fcfcfc; border: 1px dashed #ced6e0; border-radius: 12px; padding: 20px; margin-top: 20px; display: none; }
        
        .btn-wa { 
            background-color: #25d366; color: white; text-decoration: none; display: inline-flex; 
            align-items: center; justify-content: center; gap: 10px; width: 100%; 
            padding: 14px; border-radius: 10px; font-weight: bold; font-size: 0.95rem; margin-top: 15px;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.2); transition: 0.2s; box-sizing: border-box;
        }
        .btn-wa:hover { background-color: #20ba56; transform: translateY(-1px); }

        .rules-card { background: #11141a; color: white; }
        .rules-card ul { padding-left: 15px; margin: 10px 0 0 0; font-size: 0.8rem; color: #a4b0be; line-height: 1.6; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-logo">MoodMate</div>
        <nav class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="tracking.php">Tes Emosional</a>
            <a href="analisis.php">Analisis Mood <span class="badge-pro">PRO</span></a>
            <a href="curhat.php" class="active">Sesi Curhat <span class="badge-pro">PRO</span></a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-title">
            <h2>💬 Sesi Curhat Interaktif PRO</h2>
            <p>Ruang aman bercerita rahasia langsung dengan konselor pilihanmu via WhatsApp Privat.</p>
        </div>

        <div class="dashboard-container">
            
            <div>
                <div class="card">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.2rem;">🤝</span>
                        <h3 style="margin: 0; font-size: 1rem;">Langkah 1: Pilih Kakak Admin Konselor</h3>
                    </div>
                    <p style="color: #7f8c8d; font-size: 0.8rem; margin: 5px 0 0 0;">Klik salah satu admin yang bertanda status <strong>Tersedia</strong> untuk mulai.</p>
                    
                    <div class="admin-grid">
                        <?php foreach($daftar_admin as $adm): 
                            // Perbaikan Logika: Pengecekan rentang jam yang lebih akurat
                            $is_active = false;
                            if ($jam_sekarang >= $adm['jam_mulai'] && $jam_sekarang < $adm['jam_selesai']) {
                                $is_active = true;
                            }
                            
                            $status_class = $is_active ? 'online' : 'offline';
                            $status_label = $is_active ? 'Tersedia' : 'Offline';
                        ?>
                            <div class="admin-card <?php echo $status_class; ?>" 
                                 onclick="<?php echo $is_active ? "pilihAdmin('".$adm['nama']."', '".$adm['no_wa']."')" : "alert('Maaf, Kak ".$adm['nama']." sedang di luar jam shift kerja.')"; ?>">
                                
                                <span class="badge-status <?php echo $status_class; ?>">
                                    <span class="status-dot <?php echo $status_class; ?>"></span><?php echo $status_label; ?>
                                </span>

                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <span style="font-size: 2.2rem; background: #f1f2f6; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                        <?php echo $adm['avatar']; ?>
                                    </span>
                                    <div>
                                        <h4 style="margin: 0; font-size: 0.95rem; color: var(--dark-blue);">Kak <?php echo $adm['nama']; ?></h4>
                                        <span style="font-size: 0.7rem; color: #e67e22; font-weight: bold;">⏱️ Shift: <?php echo $adm['shift_teks']; ?></span>
                                    </div>
                                </div>
                                <p style="font-size: 0.75rem; color: #666; margin: 10px 0 0 0; line-height: 1.4;"><?php echo $adm['keunggulan']; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card payment-section" id="boxPembayaran">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <span style="font-size: 1.2rem;">💳</span>
                        <h3 style="margin: 0; font-size: 1rem;">Langkah 2: Selesaikan Sesi Berbayar (Rp 5.000)</h3>
                    </div>
                    <p style="font-size: 0.8rem; color: #555; margin: 0 0 15px 0;">
                        Anda memilih berkonsultasi dengan <strong id="namaAdminTerpilih" style="color:#0f4c5c;">-</strong>. Biaya administrasi ruangan privat aman terkunci sebesar <strong>Rp 5.000 / Sesi</strong>.
                    </p>

                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; font-size: 0.85rem; color: #333; margin-bottom: 15px;">
                        <div style="font-weight: bold; margin-bottom: 5px; color: var(--premium-gold);">PILIHAN TRANSFER BANK / E-WALLET:</div>
                        • Dana / Gopay / OVO : <strong>0812-3456-7890</strong> (A/N MoodMate Indonesia)<br>
                        • Bank BCA : <strong>8001-2345-67</strong> (A/N MoodMate Co.)
                    </div>

                    <p style="font-size: 0.75rem; color: #e74c3c; margin: 0; font-style: italic;">
                        *Catatan: Harap simpan tangkapan layar (screenshot) bukti transfer sukses untuk dikirimkan langsung ke WhatsApp admin.
                    </p>

                    <a href="#" id="linkWhatsAppAction" target="_blank" class="btn-wa">
                        Hubungkan Sesi Curhat WhatsApp Admin 🚀
                    </a>
                </div>
            </div>

            <div>
                <div class="card" style="text-align: center; background: linear-gradient(135deg, #1d3557, #457b9d); color: white;">
                    <span style="font-size: 0.75rem; font-weight: bold; opacity: 0.8; letter-spacing: 0.5px;">TARIF FLAT PRO</span>
                    <h2 style="font-size: 1.8rem; margin: 8px 0 0 0;">Rp 5.000 <span style="font-size: 0.8rem; font-weight: normal; opacity: 0.7;">/ Sesi</span></h2>
                </div>

                <div class="card" style="padding: 20px;">
                    <h4 style="margin: 0 0 10px 0; font-size: 0.85rem; color: #7f8c8d;">📋 Prosedur Alur Curhat</h4>
                    <ol style="padding-left: 15px; margin: 0; font-size: 0.8rem; color: #555; line-height: 1.7;">
                        <li>Pilih admin yang bertanda <strong>Tersedia</strong> sesuai jam kerja saat ini.</li>
                        <li>Lakukan transfer 5 ribu rupiah ke rekening yang tertera.</li>
                        <li>Klik tombol hijau untuk membuka tautan chat aplikasi WhatsApp.</li>
                        <li>Kirim <strong>Bukti Transfer</strong> lewat WA, admin akan langsung membalas & sesi dimulai!</li>
                    </ol>
                </div>

                <div class="card rules-card">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.1rem;">🔒</span>
                        <h4 style="margin: 0; font-size: 0.85rem;">Safe Space Rules</h4>
                    </div>
                    <ul>
                        <li>Identitas dan isi curhatan kamu dijamin 100% amanah & rahasia.</li>
                        <li>1 Sesi pembayaran dihitung maksimal berdurasi 60 Menit obrolan.</li>
                        <li>Dilarang berkata kasar atau mengandung unsur SARA.</li>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <script>
        function pilihAdmin(nama, nomorWa) {
            document.getElementById('boxPembayaran').style.display = 'block';
            document.getElementById('namaAdminTerpilih').innerText = 'Kak ' + nama;
            
            var isiTeks = "Halo Kak " + nama + ", saya pengguna aplikasi MoodMate PRO. Saya ingin mengajukan Sesi Curhat Eksklusif. Berikut saya lampirkan bukti transfer pembayaran Rp 5.000 saya. Mohon bimbingannya ya Kak!";
            var urlEncodedText = encodeURIComponent(isiTeks);
            
            var linkWaFull = "https://wa.me/" + nomorWa + "?text=" + urlEncodedText;
            document.getElementById('linkWhatsAppAction').setAttribute('href', linkWaFull);

            document.getElementById('boxPembayaran').scrollIntoView({ behavior: 'smooth' });
        }
    </script>

</body>
</html>