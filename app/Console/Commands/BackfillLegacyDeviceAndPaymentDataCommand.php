<?php

namespace App\Console\Commands;

use App\Models\CustomerTechnicalDetail;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLegacyDeviceAndPaymentDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-legacy-device-payment {file? : The path to the legacy sql dump. Default: jetis_db_aplikasi_jetis.sql}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill perangkat (MAC/serial/brand) & data pembayaran asli (metode/penerima/penyetor/biaya pasang) untuk data yang sudah terlanjur ter-migrasi dari 4 tabel legacy yang sebelumnya terlewat';

    public function handle()
    {
        $fileName = $this->argument('file') ?: 'jetis_db_aplikasi_jetis.sql';
        $sqlPath = base_path($fileName);

        if (!file_exists($sqlPath)) {
            $this->error("File {$fileName} does not exist in root directory.");
            return Command::FAILURE;
        }

        $this->info("Membaca {$fileName}...");
        $sql = file_get_contents($sqlPath);

        $penggunaRows = $this->parseTableData($sql, 'pengguna');
        $barangRows = $this->parseTableData($sql, 'barang');
        $merkBarangRows = $this->parseTableData($sql, 'merk_barang');
        $riwayatBarangRows = $this->parseTableData($sql, 'riwayatstatus_penggunabarang');
        $buktiLunasRows = $this->parseTableData($sql, 'apikeuangan_buktitransaksilunas');
        $buktiPemasanganRows = $this->parseTableData($sql, 'apikeuangan_buktitransaksipemasangan');
        $biayaRows = $this->parseTableData($sql, 'biaya_tagihan');

        $this->line("- pengguna: " . count($penggunaRows));
        $this->line("- barang: " . count($barangRows));
        $this->line("- merk_barang: " . count($merkBarangRows));
        $this->line("- riwayatstatus_penggunabarang: " . count($riwayatBarangRows));
        $this->line("- apikeuangan_buktitransaksilunas: " . count($buktiLunasRows));
        $this->line("- apikeuangan_buktitransaksipemasangan: " . count($buktiPemasanganRows));
        $this->line("- biaya_tagihan: " . count($biayaRows));

        $penggunaMap = [];
        foreach ($penggunaRows as $row) {
            if (!empty($row['IDPENGGUNA'])) {
                $penggunaMap[$row['IDPENGGUNA']] = $row;
            }
        }

        $barangByCode = [];
        foreach ($barangRows as $row) {
            if (!empty($row['KODEBARANG'])) {
                $barangByCode[$row['KODEBARANG']] = $row;
            }
        }
        $merkBarangById = [];
        foreach ($merkBarangRows as $row) {
            if (!empty($row['IDMERK'])) {
                $merkBarangById[$row['IDMERK']] = $row;
            }
        }
        $assetByRequest = [];
        foreach ($riwayatBarangRows as $row) {
            $requestId = $row['IDPERMINTAAN'] ?? '';
            if ($requestId === '' || isset($assetByRequest[$requestId])) {
                continue;
            }
            $barang = $barangByCode[$row['KODEBARANG'] ?? ''] ?? null;
            $merk = $merkBarangById[$barang['MERKBARANG'] ?? ''] ?? null;
            $assetByRequest[$requestId] = [
                'brand_label' => trim((string) ($row['MERKBARANG'] ?? '')),
                'mac' => $barang['MACADDRESS'] ?? '',
                'serial' => $merk['SERIALNUMBER'] ?? '',
            ];
        }

        $lunasByTransaction = [];
        foreach ($buktiLunasRows as $row) {
            $transactionId = $row['IDTRANSAKSI'] ?? '';
            if ($transactionId === '') {
                continue;
            }
            $existing = $lunasByTransaction[$transactionId] ?? null;
            if ($existing === null || strcmp((string) $row['TGLBAYAR'], (string) $existing['TGLBAYAR']) < 0) {
                $lunasByTransaction[$transactionId] = $row;
            }
        }

        $installationPaidAt = [];
        foreach ($buktiPemasanganRows as $row) {
            $invoiceCode = $row['IDPERMINTAAN'] ?? '';
            if ($invoiceCode !== '' && !isset($installationPaidAt[$invoiceCode])) {
                $installationPaidAt[$invoiceCode] = $row['TGLBAYAR'] ?? null;
            }
        }

        $deviceUpdated = 0;
        $paymentUpdated = 0;
        $installationInserted = 0;

        DB::transaction(function () use (
            $assetByRequest,
            $lunasByTransaction,
            $installationPaidAt,
            $biayaRows,
            $penggunaMap,
            &$deviceUpdated,
            &$paymentUpdated,
            &$installationInserted
        ) {
            // 1. Backfill device MAC/serial/brand note on existing technical details.
            foreach ($assetByRequest as $requestId => $asset) {
                $detail = CustomerTechnicalDetail::where('old_request_id', $requestId)->first();
                if (!$detail) {
                    continue;
                }

                $changes = [];
                if (empty($detail->router_mac) && !empty($asset['mac'])) {
                    $changes['router_mac'] = $asset['mac'];
                }
                if (empty($detail->router_or_ont_serial) && !empty($asset['serial'])) {
                    $changes['router_or_ont_serial'] = $asset['serial'];
                }
                if (!empty($asset['brand_label']) && !str_contains((string) $detail->note, $asset['brand_label'])) {
                    $assetNote = 'Perangkat: ' . $asset['brand_label'] . ' (dari data aset migrasi)';
                    $changes['note'] = trim((string) $detail->note) !== ''
                        ? $detail->note . ' | ' . $assetNote
                        : $assetNote;
                }

                if ($changes !== []) {
                    $detail->updateQuietly($changes);
                    $deviceUpdated++;
                }
            }

            // 2. Backfill real payment method/receiver/note on existing payments.
            foreach ($lunasByTransaction as $transactionId => $lunas) {
                $payment = Payment::where('old_transaction_id', $transactionId)->first();
                if (!$payment) {
                    continue;
                }

                $changes = [];
                $realMethod = strtolower(trim((string) ($lunas['JENISPEMBAYARAN'] ?? '')));
                $mappedMethod = match ($realMethod) {
                    'tunai', 'cash' => 'cash',
                    'transfer', 'bank_transfer' => 'transfer',
                    'qris' => 'qris',
                    '' => null,
                    default => 'lainnya',
                };
                if ($mappedMethod !== null && $payment->payment_method === 'cash' && $mappedMethod !== 'cash') {
                    $changes['payment_method'] = $mappedMethod;
                }
                if (empty($payment->received_by_old)) {
                    $label = $this->resolveLegacyUserLabel($lunas['IDPENERIMA'] ?? '', $penggunaMap);
                    if ($label !== '') {
                        $changes['received_by_old'] = $label;
                    }
                }
                if (empty($payment->deposited_by_old)) {
                    $label = $this->resolveLegacyUserLabel($lunas['IDPENYETOR'] ?? '', $penggunaMap);
                    if ($label !== '') {
                        $changes['deposited_by_old'] = $label;
                    }
                }
                $noteIsPlaceholder = empty($payment->note) || $payment->note === 'Imported legacy payment';
                if ($noteIsPlaceholder && !empty(trim((string) ($lunas['KET'] ?? '')))) {
                    $changes['note'] = trim((string) $lunas['KET']);
                }

                if ($changes !== []) {
                    $payment->updateQuietly($changes);
                    $paymentUpdated++;
                }
            }

            // 3. Insert missing installation-fee payments.
            foreach ($biayaRows as $row) {
                $invoiceCode = $row['IDBIAYA'];
                $paidAt = $installationPaidAt[$invoiceCode] ?? null;
                $installationFee = (int) ($row['BIAYAPASANG'] ?? 0);

                if ($paidAt === null || $installationFee <= 0) {
                    continue;
                }

                $cust = $row['IDPELANGGAN'] ?? '';
                if (!str_starts_with($cust, 'PE')) {
                    continue;
                }

                $invoice = Invoice::where('old_cost_id', $invoiceCode)->orWhere('old_invoice_id', $invoiceCode)->first();
                if (!$invoice) {
                    continue;
                }

                $alreadyExists = Payment::where('invoice_id', $invoice->id)
                    ->where('note', 'like', '%biaya pasang%')
                    ->exists();
                if ($alreadyExists) {
                    continue;
                }

                $payDate = now()->format('Y-m-d');
                try {
                    $payDate = \Carbon\Carbon::parse($paidAt)->format('Y-m-d');
                } catch (\Exception $e) {
                }

                Payment::create([
                    'payment_number' => 'PAY-' . uniqid(),
                    'old_payment_id' => $invoiceCode . '-PASANG',
                    'old_transaction_id' => $invoiceCode,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'pop_id' => $invoice->pop_id,
                    'billing_period' => \Carbon\Carbon::parse($payDate)->format('Y-m'),
                    'amount' => $installationFee,
                    'payment_date' => $payDate,
                    'payment_method' => 'cash',
                    'payment_status' => \App\Enums\PaymentStatus::VALID->value,
                    'note' => 'Pembayaran biaya pasang (migrasi backfill)',
                ]);
                $installationInserted++;
            }
        });

        $this->info("Selesai. Detail teknis diperbarui: {$deviceUpdated}. Pembayaran diperbarui: {$paymentUpdated}. Pembayaran biaya pasang ditambahkan: {$installationInserted}.");

        return Command::SUCCESS;
    }

    private function parseTableData(string $sql, string $tableName): array
    {
        $insertMatches = [];
        preg_match_all('/INSERT INTO `' . $tableName . '` \(([^\)]+)\) VALUES\s*(.*?);/s', $sql, $insertMatches);

        $rows = [];
        foreach ($insertMatches[1] as $idx => $columnsList) {
            $columnMatches = [];
            preg_match_all('/`([^`]+)`/', $columnsList, $columnMatches);
            $columns = $columnMatches[1];

            $valuesBlock = trim($insertMatches[2][$idx]);

            if (str_starts_with($valuesBlock, '(')) {
                $valuesBlock = substr($valuesBlock, 1);
            }
            if (str_ends_with($valuesBlock, ')')) {
                $valuesBlock = substr($valuesBlock, 0, -1);
            }

            $rowStrings = preg_split('/\),\s*\(/', $valuesBlock);

            foreach ($rowStrings as $rowString) {
                $values = $this->splitSqlValues($rowString);

                $rowData = [];
                foreach ($columns as $index => $colName) {
                    $rowData[$colName] = $values[$index] ?? null;
                }
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    private function splitSqlValues(string $rowString): array
    {
        $values = [];
        $currentVal = '';
        $inString = false;
        $escape = false;

        for ($i = 0; $i < strlen($rowString); $i++) {
            $char = $rowString[$i];

            if ($escape) {
                $currentVal .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === "'") {
                $inString = !$inString;
                continue;
            }

            if ($char === ',' && !$inString) {
                $values[] = $this->cleanParsedValue($currentVal);
                $currentVal = '';
            } else {
                $currentVal .= $char;
            }
        }

        $values[] = $this->cleanParsedValue($currentVal);
        return $values;
    }

    private function cleanParsedValue(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $val = trim($val);
        if (strtolower($val) === 'null') {
            return null;
        }
        return $val;
    }

    private function resolveLegacyUserLabel(mixed $value, array $penggunaMap): string
    {
        $raw = trim((string) $value);
        if ($raw === '' || in_array(strtolower($raw), ['-', 'null', 'n/a'], true)) {
            return '';
        }

        $tokens = preg_split('/\s*(?:,|\/|\||;|\r\n|\n)\s*/', $raw) ?: [$raw];
        $labels = [];

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            if (isset($penggunaMap[$token])) {
                $labels[] = $this->buildLegacyUserName($penggunaMap[$token]) ?: $token;
                continue;
            }

            $labels[] = $token;
        }

        $labels = array_values(array_filter(array_map('trim', $labels), fn ($label) => $label !== ''));

        return $labels !== [] ? implode(', ', array_unique($labels)) : $raw;
    }

    private function buildLegacyUserName(array $row): string
    {
        $name = trim((string) (($row['NAMADEPAN'] ?? '') . ' ' . ($row['NAMABELAKANG'] ?? '')));
        $name = preg_replace('/\s+/', ' ', $name) ?: '';

        return $name !== '' ? $name : trim((string) ($row['IDPENGGUNA'] ?? ''));
    }
}
