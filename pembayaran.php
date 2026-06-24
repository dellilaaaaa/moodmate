<?php
session_start();
require_once 'koneksi.php';

// Proteksi: Jika user belum login, tendang kembali ke dashboard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: dashboard.php");
    exit;
}

// Ambil paket yang dipilih dari URL (default ke weekly jika kosong)
$paket = $_GET['paket'] ?? 'weekly';
$harga = 'Rp5.000';
$nama_paket = 'Super Weekly';

if ($paket == 'monthly') {
    $harga = 'Rp15.000';
    $nama_paket = 'Super Monthly';
} elseif ($paket == 'threemonth') {
    $harga = 'Rp35.000';
    $nama_paket = 'Super 3-Month';
}

// Logika ketika user menekan tombol "Saya Sudah Transfer"
if (isset($_POST['konfirmasi_bayar'])) {
    $user_id = $_SESSION['user_id'];
    
    // QUERY UPDATE: Ubah status user di database menjadi PREMIUM (Simulasi sukses setelah bayar)
    $stmt = $pdo->prepare("UPDATE users SET is_premium = 1 WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // Perbarui data session aktif agar dashboard langsung mendeteksi status premiumnya
    $_SESSION['is_premium'] = true;
    
    // Alihkan kembali ke dashboard dengan membawa status sukses
    header("Location: dashboard.php?status=premium_sukses");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metode Pembayaran - MoodMate</title>
    <style>
        :root {
            --dark-blue: #2c3e50;
            --logo-blue: #3498db;
            --logo-orange: #e67e22;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .invoice-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        .icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .harga-tag {
            font-size: 2rem;
            font-weight: bold;
            color: #27ae60;
            margin: 15px 0;
            background: #eafaf1;
            padding: 10px;
            border-radius: 8px;
            display: inline-block;
        }
        .instruksi-box {
            background: #f8f9fa;
            border: 1px dashed #ccc;
            padding: 15px;
            border-radius: 8px;
            text-align: left;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .btn-confirm {
            background: var(--logo-blue);
            color: white;
            border: none;
            padding: 12px 20px;
            width: 100%;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.2s;
        }
        .btn-confirm:hover {
            background: #2980b9;
        }
        .btn-batal {
            display: block;
            margin-top: 15px;
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<div class="invoice-card">
    <div class="icon">💳</div>
    <h3 style="margin: 0; color: var(--dark-blue);">Satu Langkah Lagi!</h3>
    <p style="color: #666; font-size: 0.9rem; margin: 5px 0 20px 0;">Kamu memilih paket <strong><?php echo $nama_paket; ?></strong></p>
    
    <div class="harga-tag"><?php echo $harga; ?></div>

    <div class="instruksi-box">
        <strong style="color: var(--dark-blue); display: block; margin-bottom: 8px;">Silakan transfer ke salah satu akun berikut:</strong>
        • <strong>DANA / GoPay:</strong> 0812-3456-7890 (a.n MoodMate App)<br>
        • <strong>Bank BCA:</strong> 8720-1122-33 (a.n PT MoodMate Indonesia)
        <span style="display: block; margin-top: 10px; font-size: 0.8rem; color: var(--logo-orange); font-weight: bold;">
            *Catatan: Pastikan nominal transfer pas dan sesuai!
        </span>
    </div>

    <!-- Form untuk memproses konfirmasi pembayaran -->
    <form method="POST">
        <button type="submit" name="konfirmasi_bayar" class="btn-confirm">Saya Sudah Bayar / Transfer</button>
    </form>

    <a href="dashboard.php" class="btn-batal">← Batalkan & Kembali</a>
</div>

</body>
</html>