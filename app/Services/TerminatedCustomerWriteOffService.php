<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Enums\WorkflowTransition;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Hapus buku otomatis utang pelanggan putus yang masa tenggangnya habis
 * (ADHOC-105). Rancangan: docs/plan/billing/rancangan-piutang-tak-tertagih-
 * otomatis-pelanggan-putus.md.
 *
 * Masa tenggang dihitung dari `customers.terminated_at`, bukan dari
 * `billing_period` tiap invoice: sisa bulan pemutusan (M) + seluruh bulan M+1.
 * Job berjalan tanggal 1 bulan M+2, dan SEMUA invoice belum lunas pelanggan itu
 * (denda + piutang lama, apa pun tipenya) dihapus buku sebagai satu bundel.
 *
 * Yang dipakai ulang apa adanya: `InvoiceWriteOffService::writeOff()`. Denda
 * putus langganan punya `billing_period` terisi (bulan pemutusan), jadi sudah
 * berstatus piutang saat job jalan dan lolos guard `isPiutang()` tanpa
 * perubahan.
 */
class TerminatedCustomerWriteOffService
{
    public const REASON = 'Auto write-off: pelanggan putus, grace period habis (ADHOC-105)';

    /** @var Collection<int, User>|null */
    private ?Collection $approvers = null;

    public function __construct(
        private readonly InvoiceWriteOffService $writeOffs,
        private readonly EffectiveAccessService $access,
    ) {}

    /**
     * Pelanggan putus yang masa tenggangnya sudah lewat pada `$asOf`.
     *
     * `terminated_at` di bulan M memenuhi syarat begitu `$asOf` sudah di bulan
     * M+2 atau setelahnya, yaitu `terminated_at` sebelum awal bulan `$asOf`
     * dikurangi satu bulan. Pelanggan legacy tanpa `terminated_at` dilewati
     * (masa tenggangnya tak bisa dihitung).
     *
     * @return Collection<int, Customer>
     */
    public function eligibleCustomers(Carbon $asOf): Collection
    {
        $cutoff = $asOf->copy()->startOfMonth()->subMonthNoOverflow();

        return Customer::query()
            ->where('status', WorkflowTransition::TERMINATED->value)
            ->whereNotNull('terminated_at')
            ->where('terminated_at', '<', $cutoff)
            ->whereHas('invoices', fn ($q) => $this->autoWriteOffCandidates($q))
            ->orderBy('id')
            ->get();
    }

    /**
     * Invoice yang boleh dihapus buku otomatis: belum lunas DAN belum pernah
     * dikembalikan admin. Invoice yang pernah dikembalikan (`write_off_reversed_at`
     * terisi, baik periode terkunci maupun berjalan) sengaja dilewati — kalau
     * tidak, tombol Kembalikan cuma bertahan sampai tanggal 1 berikutnya karena
     * job menghapus bukunya lagi. Sesudahnya invoice itu ditagih sampai lunas
     * atau dihapus buku manual oleh admin (opsi A, keputusan user 2026-09-28).
     *
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    private function autoWriteOffCandidates(Builder $query): Builder
    {
        return $query
            ->whereIn('invoice_status', Invoice::OUTSTANDING_STATUSES)
            ->whereNull('write_off_reversed_at');
    }

    /**
     * Hapus buku semua invoice belum lunas pelanggan itu. Satu invoice yang
     * ditolak `writeOff()` (mis. invoice manual ber-`billing_period` bulan
     * berjalan yang bukan piutang) dilog lalu dilewati — tidak menghentikan
     * invoice lain. Invoice itu ikut terproses pada run bulan berikutnya.
     *
     * Notifikasi dikirim sekali per pelanggan, sesudah semua invoicenya
     * diproses, dan hanya bila ada yang berhasil dihapus buku.
     *
     * @return array{count: int, total: float, skipped: int}
     */
    public function writeOffCustomer(Customer $customer, User $actor, bool $dryRun = false): array
    {
        $invoices = $this->autoWriteOffCandidates(Invoice::query()->where('customer_id', $customer->id))
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get();

        $count = 0;
        $skipped = 0;
        $amounts = [];

        foreach ($invoices as $invoice) {
            if ($dryRun) {
                // Cermin guard `InvoiceWriteOffService::writeOff()` (piutang DAN
                // masih bersisa) supaya hitungan simulasi sama dengan eksekusi.
                if ($invoice->isPiutang() && ! Money::isZero($invoice->remaining_amount)) {
                    $count++;
                    $amounts[] = $invoice->remaining_amount;
                } else {
                    $skipped++;
                }

                continue;
            }

            try {
                $written = $this->writeOffs->writeOff($invoice, $actor, self::REASON);
                $count++;
                $amounts[] = $written->written_off_amount;
            } catch (ValidationException $e) {
                $skipped++;
                Log::warning('Auto write-off pelanggan putus: invoice dilewati', [
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'errors' => $e->errors(),
                ]);
            }
        }

        $total = Money::sum($amounts);

        if (! $dryRun && $count > 0) {
            $this->notify($customer, $count, $total);
        }

        return ['count' => $count, 'total' => $total, 'skipped' => $skipped];
    }

    /**
     * Pengguna yang menjalankan job (tanpa user login) — pola sama
     * `CustomerWorkflowService::transition()` yang jatuh ke user id 1.
     */
    public function systemActor(): User
    {
        $actor = User::query()->find(1) ?? User::query()->orderBy('id')->first();

        if (! $actor) {
            throw new RuntimeException('Tidak ada user untuk dicatat sebagai pelaku hapus buku otomatis.');
        }

        return $actor;
    }

    /**
     * Penerima: pendaftar asli pelanggan + user ber-permission `invoices.approve`
     * yang POP scope-nya mencakup pelanggan itu. Audiens diturunkan dari
     * permission (bukan daftar role) dan difilter POP scope — kabar piutang
     * cabang A tidak boleh sampai ke admin cabang B (CLAUDE.md, larangan keras #3).
     *
     * Kegagalan kirim satu penerima tidak boleh membatalkan hapus buku yang
     * sudah tercatat, jadi dibungkus try/catch + report().
     */
    private function notify(Customer $customer, int $count, float $total): void
    {
        $notification = new AppNotification(
            title: 'Piutang Tak Tertagih: '.$customer->full_name,
            message: "{$count} tagihan senilai ".format_rupiah($total).' otomatis dihapus buku karena masa tenggang setelah putus langganan habis. Bisa dikembalikan dari List Putus Langganan.',
            actionUrl: route('customers.terminated'),
            type: NotificationType::WARNING
        );

        foreach ($this->recipients($customer) as $user) {
            try {
                $user->notify($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Customer $customer): Collection
    {
        // Daftar penyetuju sama untuk semua pelanggan dalam satu run, jadi
        // dicari SEKALI; yang dihitung per pelanggan hanya cek POP scope-nya.
        // `with('role')` wajib: hasAllPopAccess() memanggil hasRole() yang
        // membaca relasi role, dan lazy loading dimatikan di aplikasi ini.
        $this->approvers ??= $this->access
            ->usersWithPermission('invoices.approve')
            ->where('status', UserStatus::ACTIVE->value)
            ->with('role')
            ->get();

        $approvers = $this->approvers
            ->filter(fn (User $user) => $this->access->hasAllPopAccess($user)
                || ($customer->pop_id !== null && in_array((int) $customer->pop_id, array_map('intval', $this->access->getAllowedPopIds($user)), true)));

        $creator = $customer->created_by ? User::query()->find($customer->created_by) : null;

        return $approvers
            ->when($creator, fn (Collection $users) => $users->push($creator))
            ->unique('id')
            ->values();
    }
}
