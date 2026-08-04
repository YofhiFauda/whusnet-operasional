<?php

/**
 * File ini HANYA untuk review/testing — jalankan lokal, jangan dipakai di production.
 * Cara pakai: php review-id-generator.php
 *
 * Pastikan file IdGenerator.php ada di folder yang sama.
 */

require_once __DIR__.'/id-generator-hmac.php';

// Secret dummy KHUSUS untuk testing — di production wajib dari secret manager
// (Vault / AWS Secrets Manager / dsb), dan HARUS konsisten (jangan berubah-ubah)
// supaya ID lama tetap valid.
putenv('ID_HMAC_SECRET=test-secret-jangan-dipakai-di-production-32char');

echo "=== 1. Review format dasar (generate biasa) ===\n\n";

$cid = 'C1X4ARQ000004';
$date = '25-07-2026';
$billId = '0001';

$ticketId = IdGenerator::generate('TCK', null, 'JTS', $cid, $date, $billId);
echo "Ticket ID       : {$ticketId}\n";

$invoiceRegId = IdGenerator::generate('INV', 'REG', 'JTS', $cid, '2026-08', '0105');
echo "Invoice REG ID  : {$invoiceRegId}\n";

$invoiceBlnId = IdGenerator::generate('INV', 'BLN', 'JTS', $cid, '2026-08', '1420');
echo "Invoice BLN ID  : {$invoiceBlnId}\n";

$paymentRegId = IdGenerator::generate('PAY', 'REG', 'JTS', $cid, $date, '0012');
echo "Payment REG ID  : {$paymentRegId}\n";

echo "\n=== 2. Review konsistensi (input sama = output sama) ===\n\n";

$ulang1 = IdGenerator::generate('TCK', null, 'JTS', $cid, $date, $billId);
$ulang2 = IdGenerator::generate('TCK', null, 'JTS', $cid, $date, $billId);
echo "Generate #1     : {$ulang1}\n";
echo "Generate #2     : {$ulang2}\n";
echo 'Konsisten?      : '.($ulang1 === $ulang2 ? 'YA (deterministik)' : 'TIDAK (ada bug!)')."\n";

echo "\n=== 3. Review perubahan kecil pada input -> hash total berubah ===\n\n";

$asli = IdGenerator::generate('TCK', null, 'JTS', $cid, $date, '0001');
$bedaSatuAngka = IdGenerator::generate('TCK', null, 'JTS', $cid, $date, '0002');
echo "refId 0001      : {$asli}\n";
echo "refId 0002      : {$bedaSatuAngka}\n";
echo "Avalanche effect terlihat: hash sama sekali berbeda walau cuma 1 karakter refId yang beda.\n";

echo "\n=== 4. Review validasi format ===\n\n";

var_dump(IdGenerator::isValidFormat($ticketId));
echo "-> {$ticketId} (harusnya true)\n";
var_dump(IdGenerator::isValidFormat('TCK-JTS-XYZ'));
echo "-> 'TCK-JTS-XYZ' (harusnya false)\n";

echo "\n=== 5. Review simulasi collision + auto-retry ===\n\n";

// Simulasi: dummy "database" pakai array in-memory
$dbDummy = [];

$cekTersedia = function (string $candidate) use (&$dbDummy): bool {
    $sudahDipakai = in_array($candidate, $dbDummy, true);
    if (! $sudahDipakai) {
        $dbDummy[] = $candidate; // simulasikan insert ke DB
    }

    return ! $sudahDipakai; // true = belum dipakai / aman
};

$idPertama = IdGenerator::generateUnique('PAY', 'BLN', 'JTS', $cid, '2026-08-03', '0891', $cekTersedia);
echo "ID pertama kali generate (harus sukses langsung): {$idPertama}\n";

echo "\nSelesai review. Total ID unik di 'database dummy': ".count($dbDummy)."\n";
