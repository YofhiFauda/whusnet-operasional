<?php

namespace App\Console\Commands\Operational;

use App\Models\Customer;
use App\Models\CustomerBillingWaiver;
use App\Models\CustomerCableCrossing;
use App\Models\DeviceRetrievalLog;
use App\Models\InventorySerialSale;
use App\Models\Invoice;
use App\Models\NetworkPortAssignment;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Jaring pengaman khusus untuk app:import-legacy-sql: bongkar hasil import
 * satu Cabang (+ Mini POP di bawahnya) kalau ketahuan salah SEBELUM ada
 * aktivitas nyata (tiket, task, pembayaran baru, dst) menumpuk di atasnya.
 *
 * Sengaja TIDAK menghapus sembarang customer di Cabang itu — hanya yang
 * jelas hasil import (`old_customer_id` terisi) dan SELURUH
 * invoice/payment-nya juga hasil import (`old_invoice_id`/`old_payment_id`
 * terisi). Begitu ada satu saja jejak aktivitas pasca-import (tiket, task,
 * billing waiver, penjualan serial, port assignment, cable crossing,
 * penarikan alat, atau invoice/payment BARU yang bukan dari dump legacy),
 * customer itu dikunci dari rollback dan wajib ditangani manual — command
 * ini tidak pernah menebak "aman dihapus" untuk data yang sudah disentuh
 * sistem setelah migrasi.
 *
 * Pop Cabang/Mini POP sendiri TIDAK ikut dihapus (sengaja dipertahankan)
 * supaya reimport bisa langsung masuk ke Pop yang sama tanpa membangun
 * ulang hierarki.
 */
class RollbackLegacyCustomersCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:rollback-legacy-customers
                        {pop_code : pop_code Cabang (atau Mini POP) yang mau di-rollback datanya}
                        {--force : Eksekusi beneran. Tanpa ini command cuma dry-run (preview, tidak menghapus apa pun)}';

    /**
     * @var string
     */
    protected $description = 'Rollback (hapus) hasil app:import-legacy-sql untuk satu Cabang/Mini POP — hanya customer+invoice+payment yang murni hasil import dan belum tersentuh aktivitas pasca-migrasi';

    public function handle(): int
    {
        $popCode = strtoupper(trim((string) $this->argument('pop_code')));
        $pop = Pop::where('pop_code', $popCode)->first();

        if (! $pop) {
            $this->error("Pop dengan pop_code '{$popCode}' tidak ditemukan.");

            return self::FAILURE;
        }

        $popIds = $this->descendantPopIds($pop);

        $candidates = Customer::query()
            ->whereNotNull('old_customer_id')
            ->whereIn('pop_id', $popIds)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("Tidak ada customer hasil import (old_customer_id terisi) di bawah Pop {$pop->name} ({$popCode}).");

            return self::SUCCESS;
        }

        $clean = [];
        $blocked = [];

        foreach ($candidates as $customer) {
            $reasons = $this->blockingReasons($customer);

            if ($reasons === []) {
                $clean[] = $customer;
            } else {
                $blocked[$customer->id] = [$customer, $reasons];
            }
        }

        $this->info("Pop target: {$pop->name} ({$popCode}), termasuk ".(count($popIds) - 1).' Pop anak.');
        $this->info('Kandidat customer hasil import: '.$candidates->count());
        $this->line('- Aman di-rollback: '.count($clean));
        $this->line('- Terkunci (ada aktivitas pasca-import): '.count($blocked));

        if ($blocked !== []) {
            $this->warn('Customer berikut TIDAK akan disentuh — tangani manual:');
            $rows = [];
            foreach ($blocked as [$customer, $reasons]) {
                $rows[] = [$customer->customer_code, $customer->full_name, implode(', ', $reasons)];
            }
            $this->table(['Kode', 'Nama', 'Alasan terkunci'], $rows);
        }

        if ($clean === []) {
            $this->warn('Tidak ada customer yang aman di-rollback.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN — belum ada data yang dihapus. Jalankan ulang dengan --force untuk eksekusi beneran:');
            $this->line("php artisan app:rollback-legacy-customers {$popCode} --force");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($clean): void {
            foreach ($clean as $customer) {
                Payment::where('customer_id', $customer->id)->delete();
                Invoice::where('customer_id', $customer->id)->delete();
                $customer->delete();
            }
        });

        $this->info(count($clean).' customer hasil import berhasil di-rollback (dihapus beserta invoice, payment, dan data turunannya).');

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function descendantPopIds(Pop $pop): array
    {
        $ids = [$pop->id];
        foreach ($pop->children as $child) {
            $ids = array_merge($ids, $this->descendantPopIds($child));
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function blockingReasons(Customer $customer): array
    {
        $reasons = [];

        if (Invoice::where('customer_id', $customer->id)->whereNull('old_invoice_id')->exists()) {
            $reasons[] = 'punya invoice baru (bukan hasil import)';
        }

        if (Payment::where('customer_id', $customer->id)->whereNull('old_payment_id')->exists()) {
            $reasons[] = 'punya payment baru (bukan hasil import)';
        }

        if (Ticket::where('customer_id', $customer->id)->exists()) {
            $reasons[] = 'punya tiket';
        }

        if (Task::where('customer_id', $customer->id)->exists()) {
            $reasons[] = 'punya task teknisi';
        }

        if (CustomerBillingWaiver::where('customer_id', $customer->id)->exists()) {
            $reasons[] = 'punya billing waiver';
        }

        if (InventorySerialSale::where('customer_id', $customer->id)->exists()) {
            $reasons[] = 'punya penjualan serial inventaris';
        }

        if (NetworkPortAssignment::where('customer_id', $customer->id)->exists()) {
            $reasons[] = 'punya port assignment jaringan';
        }

        if (CustomerCableCrossing::where('customer_id', $customer->id)->exists()) {
            $reasons[] = 'punya data penyeberangan kabel';
        }

        if (DeviceRetrievalLog::where('customer_id', $customer->id)->exists()) {
            $reasons[] = 'punya log penarikan alat';
        }

        return $reasons;
    }
}
