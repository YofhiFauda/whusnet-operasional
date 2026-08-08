<?php

class BillingSystem
{
    // Secret key untuk memperkuat hash agar tidak mudah ditebak dari luar
    private static $secretKey = 'RAHASIA_SISTEM_KEUANGAN_2026';

    /**
     * Membuat Core Unique Code berdasarkan data transaksi
     */
    private static function generateCoreCode(string $cid, string $date, string $billId): string
    {
        // 1. Standarisasi tanggal ke YYYYMMDD
        $formattedDate = date('Ymd', strtotime($date));

        // 2. Buat Short Checksum (4 Karakter Hash) dari gabungan data + secret key
        $rawData = $cid.':'.$formattedDate.':'.$billId.':'.self::$secretKey;
        $checksum = strtoupper(substr(hash('crc32b', $rawData), 0, 4));

        // 3. Format Core ID
        return "{$cid}-{$formattedDate}-{$billId}-{$checksum}";
    }

    /**
     * Generate ID Penagihan (Invoice)
     */
    public static function generateInvoiceId(string $cid, string $date, string $billId): string
    {
        $coreCode = self::generateCoreCode($cid, $date, $billId);

        return 'INV-'.$coreCode;
    }

    /**
     * Generate ID Pembayaran (Payment) yang SERAGAM dengan Invoice
     */
    public static function generatePaymentId(string $cid, string $date, string $billId): string
    {
        $coreCode = self::generateCoreCode($cid, $date, $billId);

        return 'PAY-'.$coreCode;
    }
}

// ==========================================
// CONTOH PENGGUNAAN
// ==========================================

$cid = 'C1X4ARQ000004';
$date = '25-07-2026';
$billId = '0001';

// 1. Saat Tagihan Terbuat
$invoiceId = BillingSystem::generateInvoiceId($cid, $date, $billId);

// 2. Saat User Melakukan Pembayaran
$paymentId = BillingSystem::generatePaymentId($cid, $date, $billId);

echo 'ID Penagihan (Invoice) : '.$invoiceId."\n";
// Output: INV-C1X4ARQ000004-20260725-0001-E31B

echo 'ID Pembayaran (Payment): '.$paymentId."\n";
// Output: PAY-C1X4ARQ000004-20260725-0001-E31B
