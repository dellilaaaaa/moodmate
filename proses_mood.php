<?php
// 1. Wajib jalankan session di paling atas agar server bisa mencatat
session_start();

// 2. Set zona waktu ke WIB (Jakarta) agar jam catatannya akurat
date_default_timezone_set('Asia/Jakarta');

// 3. Cek, apakah ada kiriman data 'status' dari emoji yang diklik?
if (isset($_GET['status'])) {
    $mood_user = $_GET['status']; // Isinya bisa 'senang', 'sedih', atau 'biasa'
    
    // Konversi tulisan mood menjadi skor angka untuk bahan grafik nanti
    $skor = 50; // default jika netral
    if ($mood_user == 'senang') {
        $skor = 100;
    } elseif ($mood_user == 'sedih') {
        $skor = 20;
    }

    // 4. MASUKKAN KE DALAM KOTAK PENYIMPANAN (SESSION)
    // Kita buat array baru di dalam session bernama 'riwayat_mood'
    $_SESSION['riwayat_mood'][] = [
        'tanggal' => date('d M Y'), // Mengambil tanggal hari ini (contoh: 24 Jun 2026)
        'mood' => $mood_user,
        'skor' => $skor
    ];
}

// 5. Setelah selesai mencatat, lempar user kembali ke Dashboard otomatis
header("Location: dashboard.php?pesan=mood_berhasil_dicatat");
exit();
?>