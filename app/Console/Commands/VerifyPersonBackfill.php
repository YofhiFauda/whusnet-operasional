<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Person;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi hasil backfill layer `persons` (rancangan-fase4-persons.md §3).
 *
 * Dijalankan SETELAH `migrate:fresh` + import ulang legacy. Memeriksa invarian
 * gel.1 dan idempotensi anti-footgun (§3.2): satu person per legacy_key, tidak
 * ada customer yatim (person_id null), dan backfill 1:1 di hari pertama.
 *
 * Command ini read-only — aman dijalankan kapan pun.
 */
class VerifyPersonBackfill extends Command
{
    protected $signature = 'persons:verify-backfill';

    protected $description = 'Verifikasi invarian backfill persons setelah import ulang legacy (read-only).';

    public function handle(): int
    {
        $ok = true;

        $customerCount = Customer::count();
        $personCount = Person::count();
        $orphan = Customer::whereNull('person_id')->count();
        $withLegacyKey = Person::whereNotNull('legacy_key')->count();

        $this->line('');
        $this->info('=== Ringkasan ===');
        $this->line("customers            : {$customerCount}");
        $this->line("persons              : {$personCount}");
        $this->line("persons w/ legacy_key: {$withLegacyKey}");

        // 1. Tidak boleh ada customer tanpa person (invarian gel.1).
        if ($orphan > 0) {
            $this->error("GAGAL: {$orphan} customer punya person_id NULL. Setiap customer wajib punya person.");
            $ok = false;
        } else {
            $this->info('OK  : semua customer punya person_id.');
        }

        // 2. legacy_key harus unik (unique index menjamin di DB, tapi cek eksplisit
        //    supaya kegagalan terbaca jelas, bukan lewat exception insert).
        $dupKeys = Person::whereNotNull('legacy_key')
            ->select('legacy_key', DB::raw('COUNT(*) as c'))
            ->groupBy('legacy_key')
            ->having('c', '>', 1)
            ->count();
        if ($dupKeys > 0) {
            $this->error("GAGAL: {$dupKeys} legacy_key ganda. Idempotensi import ulang bocor (§3.2).");
            $ok = false;
        } else {
            $this->info('OK  : legacy_key unik — import ulang idempoten.');
        }

        // 3. Backfill hari pertama = 1:1. Person > customer mustahil (tiap person
        //    lahir dari minimal satu customer di gel.1). Person < customer hanya
        //    sah kalau merge sudah jalan (gel.2). Di gel.1 keduanya harus sama.
        if ($personCount > $customerCount) {
            $this->error("GAGAL: person ({$personCount}) > customer ({$customerCount}) — ada person tanpa customer.");
            $ok = false;
        } elseif ($personCount < $customerCount) {
            $this->warn("INFO: person ({$personCount}) < customer ({$customerCount}). Wajar HANYA jika merge gel.2 sudah jalan; di gel.1 murni seharusnya 1:1.");
        } else {
            $this->info('OK  : backfill 1:1 (person == customer).');
        }

        // 4. UUID wajib terisi & unik (unique index menjamin; cek kosong saja).
        $blankUuid = Person::whereNull('uuid')->orWhere('uuid', '')->count();
        if ($blankUuid > 0) {
            $this->error("GAGAL: {$blankUuid} person tanpa uuid.");
            $ok = false;
        } else {
            $this->info('OK  : semua person punya uuid.');
        }

        // 5. Info tambahan: kandidat merge (satu orang > satu person) supaya owner
        //    tahu berapa banyak yang menunggu halaman merge gel.2. BUKAN kegagalan.
        $sameNikName = DB::table('customers')
            ->join('persons', 'customers.person_id', '=', 'persons.id')
            ->whereNotNull('customers.identity_number')
            ->where('customers.identity_number', '!=', '')
            ->select('customers.identity_number', 'customers.full_name', DB::raw('COUNT(DISTINCT persons.id) as pc'))
            ->groupBy('customers.identity_number', 'customers.full_name')
            ->having('pc', '>', 1)
            ->get();
        $this->line('');
        $this->info('=== Info kandidat merge (gel.2, bukan kegagalan) ===');
        $this->line('Grup NIK+nama identik yang tersebar di >1 person: '.$sameNikName->count());

        $this->line('');
        if ($ok) {
            $this->info('SEMUA INVARIAN gel.1 LOLOS.');

            return self::SUCCESS;
        }

        $this->error('ADA INVARIAN YANG GAGAL — jangan lanjut ke gel.2 sebelum dibereskan.');

        return self::FAILURE;
    }
}
