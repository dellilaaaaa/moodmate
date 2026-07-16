<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Jakarta');

// Proteksi Login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: dashboard.php?pesan=harus_login");
    exit();
}

require_once 'koneksi.php';
$current_user_id = $_SESSION['user_id'] ?? 1;
$hari_ini = date("Y-m-d");

// --- LOGIKA 1: TAMBAH HABIT BARU ---
if (isset($_POST['tambah_habit'])) {
    $nama_habit = htmlspecialchars(trim($_POST['nama_habit']));
    if (!empty($nama_habit)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO riwayat_habit (user_id, nama_habit, status_selesai, tanggal) VALUES (?, ?, 0, ?)");
            $stmt->execute([$current_user_id, $nama_habit, $hari_ini]);
            header("Location: habits.php?status=tambah_sukses");
            exit();
        } catch (PDOException $e) {
            die("Gagal menambah habit: " . $e->getMessage());
        }
    }
}

// --- LOGIKA 2: UBAH STATUS (CHECK/UNCHECK) ---
if (isset($_GET['action']) && $_GET['action'] == 'toggle') {
    $habit_id = intval($_GET['id']);
    $status_sekarang = intval($_GET['status']);
    $status_baru = ($status_sekarang === 1) ? 0 : 1;

    try {
        $stmt = $pdo->prepare("UPDATE riwayat_habit SET status_selesai = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status_baru, $habit_id, $current_user_id]);
        header("Location: habits.php");
        exit();
    } catch (PDOException $e) {
        die("Gagal memperbarui status: " . $e->getMessage());
    }
}

// --- LOGIKA 3: HAPUS HABIT ---
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $habit_id = intval($_GET['id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM riwayat_habit WHERE id = ? AND user_id = ?");
        $stmt->execute([$habit_id, $current_user_id]);
        header("Location: habits.php?status=hapus_sukses");
        exit();
    } catch (PDOException $e) {
        die("Gagal menghapus habit: " . $e->getMessage());
    }
}

// --- AMBIL DATA HABIT HARI INI ---
try {
    $stmt = $pdo->prepare("SELECT * FROM riwayat_habit WHERE user_id = ? AND tanggal = ? ORDER BY id DESC");
    $stmt->execute([$current_user_id, $hari_ini]);
    $daftar_habit = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gagal memuat data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MoodMate - Habit Tracker</title>
    <style>
        :root {
            --dark-bg: #121212;
            --soft-blue: #f1faee;
            --dark-blue: #1d3557;
            --logo-teal: #6fbab7;
        }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; display: flex; background-color: var(--soft-blue); color: var(--dark-blue); height: 100vh; }
        .sidebar { width: 250px; background-color: var(--dark-bg); color: white; padding: 20px; display: flex; flex-direction: column; }
        .sidebar-logo { margin-bottom: 30px; text-align: center; font-weight: bold; font-size: 1.5rem; color: var(--logo-teal); }
        .nav-menu a { color: #bdc3c7; text-decoration: none; padding: 12px; display: block; border-radius: 8px; margin-bottom: 5px; }
        .nav-menu a.active, .nav-menu a:hover { background: rgba(255, 255, 255, 0.1); color: var(--logo-teal); font-weight: bold; }
        
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); max-width: 600px; margin-top: 20px; }
        .input-group { display: flex; gap: 10px; margin-bottom: 20px; }
        input[type="text"] { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; }
        .btn { background: var(--dark-blue); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; }
        
        .habit-list { display: flex; flex-direction: column; gap: 12px; }
        .habit-item { display: flex; align-items: center; justify-content: space-between; padding: 15px; border: 1px solid #eef2f3; border-radius: 10px; background: #fff; }
        .habit-item.done { background: #e8f8f0; border-color: #2ecc71; text-decoration: line-through; color: #7f8c8d; }
        .btn-check { background: #2ecc71; padding: 6px 12px; font-size: 0.85rem; }
        .btn-uncheck { background: #f39c12; padding: 6px 12px; font-size: 0.85rem; }
        .btn-delete { background: #e74c3c; padding: 6px 12px; font-size: 0.85rem; margin-left: 5px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-logo">MoodMate</div>
        <nav class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="tracking.php">Tes Emosional</a>
            <a href="habits.php" class="active">Habit Tracker ✨</a>
            <?php if (isset($_SESSION['is_premium']) && $_SESSION['is_premium'] === true): ?>
                <a href="analisis.php">Analisis Mood</a>
                <a href="curhat.php">Sesi Curhat</a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="main-content">
        <h2>🎯 Habit Tracker Harian</h2>
        <p>Disiplin kecil setiap hari membangun kesehatan mental yang kokoh. Catatan tanggal: <strong><?= date("d M Y", strtotime($hari_ini)) ?></strong></p>

        <div class="card">
            <form method="POST">
                <div class="input-group">
                    <input type="text" name="nama_habit" placeholder="Misal: Minum air 2L, Meditasi 10 menit..." required>
                    <button type="submit" name="tambah_habit" class="btn">Tambah</button>
                </div>
            </form>

            <div class="habit-list">
                <?php if (empty($daftar_habit)): ?>
                    <p style="text-align: center; color: #7f8c8d; font-size: 0.9rem;">Belum ada habit yang ditambahkan untuk hari ini. Yuk buat!</p>
                <?php else: ?>
                    <?php foreach ($daftar_habit as $h): ?>
                        <div class="habit-item <?= $h['status_selesai'] == 1 ? 'done' : '' ?>">
                            <span><?= htmlspecialchars($h['nama_habit']) ?></span>
                            <div>
                                <a href="?action=toggle&id=<?= $h['id'] ?>&status=<?= $h['status_selesai'] ?>" class="btn <?= $h['status_selesai'] == 1 ? 'btn-uncheck' : 'btn-check' ?>">
                                    <?= $h['status_selesai'] == 1 ? 'Batal Centang' : 'Selesai ✓' ?>
                                </a>
                                <a href="?action=delete&id=<?= $h['id'] ?>" class="btn btn-delete" onclick="return confirm('Hapus habit ini?')">Hapus</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>