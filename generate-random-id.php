<?php

/**
 * Fungsi untuk membuat Ticket ID berbasis Hash SHA-256
 *
 * @param  string  $userId  ID dari User (misal: C1X4ARQ000004)
 * @param  string  $timestamp  Timestamp / Tanggal (misal: 25072026 atau YmdHis)
 * @param  string  $taskId  ID dari Task/Tugas (misal: 0001)
 * @param  string  $prefix  Awalan ID Tiket (opsional, default: TKT)
 * @param  int  $length  Panjang karakter hash yang diambil (default: 12)
 */
function generateTicketId(string $userId, string $timestamp, string $taskId, string $prefix = 'TK', int $length = 12): string
{
    // 1. Gabungkan string dengan separator ':' untuk menghindari ambiguitas
    $rawData = $userId.':'.$timestamp.':'.$taskId;

    // 2. Buat Hash menggunakan SHA-256
    $hashFull = hash('sha256', $rawData);

    // 3. Potong hash sesuai panjang yang diinginkan dan ubah ke huruf kapital (UPPERCASE)
    $shortHash = strtoupper(substr($hashFull, 0, $length));

    // 4. Gabungkan prefix dengan short hash
    return $prefix.'-'.$shortHash;
}

// ==========================================
// CONTOH PENGGUNAAN
// ==========================================

$userId = 'C1X4ARQ000004_JENANGAN_Ardiyanto Cahyo Nugroho';
$timestamp = '25072026155000'; // Disarankan sertakan JamMenitDetik agar tidak bentrok
$taskId = 'JENANGAN';

// 1. Opsi Default (Output 12 Karakter Hash + Prefix)
$ticketId = generateTicketId($userId, $timestamp, $taskId);
echo 'Ticket ID (Default) : '.$ticketId."\n";
// Output contoh: TKT-8F1E6B34A2D7

// 2. Opsi dengan Prefix Kustom & Format Blok (Misal: 16 Karakter Hash)
$ticketIdCustom = generateTicketId($userId, $timestamp, $taskId, 'TICK', 16);
echo 'Ticket ID (Custom)  : '.$ticketIdCustom."\n";
// Output contoh: TICK-8F1E6B34A2D7F802
