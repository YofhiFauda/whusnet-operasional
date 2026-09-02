<?php

namespace App\Http\Controllers;

use App\Enums\CashDepositStatus;
use App\Models\CashDeposit;
use App\Models\User;
use App\Services\AdminCashBalanceService;
use App\Services\CashDepositService;
use App\Services\OwnerCashBalanceService;
use App\Support\ReasonValidationRule;
use App\Support\RupiahInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Setoran Kas — **lembar kerja PENERIMA** (Owner/atasan) atas uang yang masuk
 * dari para admin.
 *
 * Bukan halaman kas admin. Admin punya halamannya sendiri di Worksheet Admin:
 * di sana dia melihat saldonya, menyetorkannya, dan membaca riwayat
 * setorannya. Di sini dia tak punya tampilan apa pun — halaman ini menyajikan
 * uang milik SELURUH admin dalam scope, lengkap sampai nama pelanggan, dan itu
 * bukan pemandangan yang perlu dibuka penyetor.
 *
 * Isinya tiga hal, sesuai urutan pekerjaan penerima:
 *   1. Analisa penerimaan — brankas tunai, masuk bank per rekening, dan yang
 *      masih dalam perjalanan;
 *   2. Setoran masuk beserta RINCIAN SUMBERNYA — dari kolektor mana, pelanggan
 *      siapa, berapa, atau pembayaran manual di loket;
 *   3. Aksi: verifikasi, tutup selisih, unduh bukti.
 *
 * Permission feature sendiri (`cash_deposit.*`), bukan menumpang
 * `collector_worksheet.*` — kalau menumpang, tiap admin yang berwenang
 * memverifikasi kolektor otomatis berwenang menutup setoran kasnya sendiri.
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §4.5, §10, §11, §12.
 */
class CashDepositController extends Controller
{
    public function __construct(
        private readonly AdminCashBalanceService $balance,
        private readonly OwnerCashBalanceService $ownerBalance,
        private readonly CashDepositService $deposits,
    ) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();

        // Halaman ini MURNI lembar kerja penerima (Owner/atasan): mengelola
        // uang yang MASUK dari para admin. Ia sengaja tidak lagi menampilkan
        // saldo admin yang BELUM disetor — itu posisi kas orang lain, urusan
        // Worksheet Admin, dan tak ada keputusan di halaman ini yang
        // bergantung padanya.
        //
        // Tiga angka analisa penerimaan, tak pernah dijumlahkan (§11.1).
        $ownerBrankas = $this->ownerBalance->saldoBrankas($viewer);
        $ownerBank = $this->ownerBalance->masukBank($viewer);
        $ownerDalamPerjalanan = $this->ownerBalance->dalamPerjalanan($viewer);

        // Setoran yang MASUK — dari admin mana pun dalam scope POP pembaca.
        // `realDeposits()` membuang sentinel titik nol; ia bukan setoran (§7).
        //
        // Yang menunggu diperiksa selalu di atas: itu satu-satunya baris yang
        // menuntut tindakan, dan uang fisiknya sudah berpindah tangan sementara
        // belum ada yang menghitungnya.
        $status = in_array($request->query('status'), ['menunggu', 'selesai'], true)
            ? $request->query('status')
            : null;

        $deposits = CashDeposit::query()
            ->realDeposits()
            ->applyUserScope($viewer)
            ->when($status === 'menunggu', fn ($q) => $q->where('status', CashDepositStatus::MENUNGGU_VERIFIKASI->value))
            ->when($status === 'selesai', fn ($q) => $q->where('status', '!=', CashDepositStatus::MENUNGGU_VERIFIKASI->value))
            ->with([
                'depositor:id,name',
                'verifier:id,name',
                'pop:id,name',
                // Rincian sumber — inti permintaan Owner: uangnya dari kolektor
                // mana, pelanggan siapa, berapa. Semuanya turunan dari relasi;
                // tak satu pun angka di bawah ini disimpan.
                'collectorDeposits.collector:id,name',
                'collectorDeposits.payments.customer:id,full_name',
                'manualPayments.customer:id,full_name',
                'manualPayments.receiver:id,name',
            ])
            ->orderByRaw("CASE WHEN status = '".CashDepositStatus::MENUNGGU_VERIFIKASI->value."' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $jumlahMenunggu = CashDeposit::query()
            ->realDeposits()
            ->applyUserScope($viewer)
            ->where('status', CashDepositStatus::MENUNGGU_VERIFIKASI->value)
            ->count();

        // Banner titik nol. Tanpa ini, Owner membaca saldo hari pertama sebagai
        // seluruh riwayat perusahaan padahal uang lama sudah lama masuk bank
        // (§7.4).
        $zeroPointNote = CashDeposit::query()
            ->where('status', CashDepositStatus::SALDO_AWAL->value)
            ->value('note');

        return view('cash-deposits.index', compact(
            'deposits', 'status', 'jumlahMenunggu', 'zeroPointNote',
            'ownerBrankas', 'ownerBank', 'ownerDalamPerjalanan',
        ));
    }

    /**
     * Admin menyetorkan seluruh saldo tunainya. Penyetor = `auth()->user()`,
     * TIDAK PERNAH dari body: kalau id penyetor boleh datang dari klien, admin
     * A bisa menutup saldo admin B.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => 'required|in:tunai_brankas,transfer_bank',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:50',
            'reference_no' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:1000',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'idempotency_key' => 'nullable|string|max:191',
            // PENANDA halaman asal, bukan URL. Tujuannya dipilih dari daftar
            // tertutup di kembaliKe() — URL redirect yang datang mentah dari
            // klien adalah open-redirect.
            'redirect_to' => 'nullable|in:worksheet',
        ]);

        $tujuan = $this->kembaliKe($validated['redirect_to'] ?? null);

        // Bukti transfer memuat nomor rekening perusahaan → disk `local`
        // (privat), bukan `public`. Diakses hanya lewat download() yang
        // memeriksa permission + POP scope.
        $proofPath = $request->hasFile('proof')
            ? $request->file('proof')->store('cash-deposits/'.$request->user()->id, 'local')
            : null;

        try {
            $deposit = $this->deposits->submit(
                $request->user(),
                [
                    'channel' => $validated['channel'],
                    'bank_name' => $validated['bank_name'] ?? null,
                    'account_number' => $validated['account_number'] ?? null,
                    'reference_no' => $validated['reference_no'] ?? null,
                    'proof_path' => $proofPath,
                    'note' => $validated['note'] ?? null,
                ],
                $validated['idempotency_key'] ?? null,
            );
        } catch (\Throwable $e) {
            // Berkas yang terlanjur terunggah dibuang kalau setorannya gagal —
            // kalau tidak, disk berisi bukti transfer yang tak pernah punya
            // baris setoran, dan tak ada satu pun layar yang bisa menemukannya.
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

            return redirect($tujuan)->withErrors(['cash_deposit' => $e->getMessage()]);
        }

        return redirect($tujuan)
            ->with('success', "Setoran kas {$deposit->deposit_number} terkirim. Menunggu pemeriksaan — saldo tunai Anda kembali nol.");
    }

    /**
     * Tujuan redirect sesudah setor, dipilih dari daftar TERTUTUP.
     *
     * Formnya hidup di Worksheet Admin (§9), jadi admin harus mendarat kembali
     * di halaman kerjanya — bukan terlempar ke halaman arsip yang bukan tempat
     * dia bekerja. Yang datang dari klien hanya PENANDA (`worksheet`), tak
     * pernah URL: URL redirect yang datang mentah dari form adalah
     * open-redirect, dan halaman ini dipakai orang yang sedang memegang uang.
     */
    private function kembaliKe(?string $penanda): string
    {
        return $penanda === 'worksheet'
            ? route('collector-worksheet.index')
            : route('cash-deposits.index');
    }

    public function verify(Request $request, CashDeposit $deposit): RedirectResponse
    {
        // Pemeriksa mengetik `1.250.000`. Titik ribuan wajib dinormalkan di
        // sini juga — nominal yang salah baca melahirkan selisih palsu.
        $request->merge(RupiahInput::parseKeys($request->only(['declared_amount']), 'declared_amount'));

        $validated = $request->validate([
            'declared_amount' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->deposits->verify(
                $deposit,
                $request->user(),
                (float) $validated['declared_amount'],
                $validated['note'] ?? null,
            );
        } catch (\Throwable $e) {
            return $this->back($deposit)->withErrors(['cash_deposit' => $e->getMessage()]);
        }

        return $this->back($deposit)->with('success', "Setoran kas {$deposit->deposit_number} selesai diperiksa.");
    }

    public function writeOff(Request $request, CashDeposit $deposit): RedirectResponse
    {
        $validated = $request->validate([
            'write_off_reason' => ReasonValidationRule::required(1000),
        ]);

        try {
            $this->deposits->writeOff($deposit, $request->user(), $validated['write_off_reason']);
        } catch (\Throwable $e) {
            return $this->back($deposit)->withErrors(['cash_deposit' => $e->getMessage()]);
        }

        return $this->back($deposit)->with('success', "Selisih setoran kas {$deposit->deposit_number} ditutup.");
    }

    /**
     * Unduh bukti setoran. Tidak pernah dilayani lewat URL publik yang bisa
     * ditebak — sama seperti lampiran tiket.
     */
    public function download(Request $request, CashDeposit $deposit): StreamedResponse
    {
        abort_if(blank($deposit->proof_path), 404, 'Setoran ini tidak punya bukti terunggah.');

        $viewer = $request->user();

        // Pemegang `create` tanpa `view` adalah PENYETOR, bukan pemeriksa: dia
        // cuma berhak atas berkas yang dia unggah sendiri. Tanpa pembatas ini,
        // rute yang sengaja dibuka untuknya (supaya bisa mengambil buktinya
        // kembali) berubah jadi jalan membaca bukti setoran admin lain —
        // termasuk nomor rekening tujuan yang bukan urusannya.
        if (! $viewer->hasPermission('cash_deposit.view')) {
            abort_unless(
                (int) $deposit->depositor_id === $viewer->id,
                403,
                'Anda hanya bisa mengunduh bukti setoran kas Anda sendiri.'
            );
        }

        abort_unless(
            $this->balance->isVisibleTo($deposit->depositor ?? $viewer, $viewer),
            403,
            'Bukti setoran ini di luar scope POP Anda.'
        );

        abort_unless(Storage::disk('local')->exists($deposit->proof_path), 404);

        return Storage::disk('local')->response(
            $deposit->proof_path,
            "bukti-{$deposit->deposit_number}.".pathinfo($deposit->proof_path, PATHINFO_EXTENSION)
        );
    }

    /**
     * Kembali ke posisi kas PENYETOR, bukan ke halaman pemeriksa: sesudah
     * memeriksa, yang ingin dilihat adalah setoran yang barusan ditutup.
     */
    private function back(CashDeposit $deposit): RedirectResponse
    {
        return redirect()->route('cash-deposits.index', ['admin_id' => $deposit->depositor_id]);
    }
}
