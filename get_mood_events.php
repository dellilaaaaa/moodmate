<?php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];
$events = [];

try {
    // Ambil semua data mood milik user ini
    // DATE(waktu) digunakan agar formatnya Y-m-d (sesuai standar FullCalendar)
    $stmt = $pdo->prepare("SELECT skor, label, DATE(waktu) as tanggal FROM riwayat_mood WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map emoji berdasarkan label mood kamu
    $emoji_map = [
        'Senang' => '😄',
        'Biasa'  => '😐',
        'Lelah'  => '😩',
        'Sedih'  => '😔'
    ];

    foreach ($rows as $row) {
        $label = $row['label'];
        $emoji = $emoji_map[$label] ?? '📝';

        $events[] = [
            'title' => $emoji . ' ' . $label,
            'start' => $row['tanggal'],
            'allDay' => true,
            // Berikan warna background card kalender tipis berdasarkan mood
            'backgroundColor' => ($label == 'Senang') ? '#e8f8f0' : (($label == 'Sedih') ? '#fdeaea' : '#eef2f3'),
            'textColor' => '#1d3557',
            'borderColor' => ($label == 'Senang') ? '#2ecc71' : (($label == 'Sedih') ? '#e74c3c' : '#bdc3c7')
        ];
    }

    echo json_encode($events);
} catch (PDOException $e) {
    echo json_encode([]);
}