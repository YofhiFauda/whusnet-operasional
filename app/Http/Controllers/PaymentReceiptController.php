<?php

namespace App\Http\Controllers;

use App\Enums\DepositStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\Receipts\PaymentReceiptService;
use App\Services\Receipts\ReceiptPresenter;
use App\Services\Receipts\ReceiptQrRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kwitansi pembayaran — sumbu DOKUMEN.
 *
 * Empat aksi: cetak (dengan QR), upload bulk, unduh berkas, cocokkan manual.
 * Tak satu pun menyentuh status setoran atau nilai pembayaran — kwitansi
 * adalah bukti bagi pelanggan, bukan bagian dari perhitungan kas (§13.2).
 *
 * docs/kolektor/business-logic.md.
 */
class PaymentReceiptController extends Controller
{
    public function __construct(private readonly PaymentReceiptService $receipts) {}

    /**
     * Halaman cetak untuk seluruh pembayaran satu kolektor yang dipilih admin.
     *
     * Dicetak SESUDAH pembayaran tersimpan — nomornya baru ada setelah itu.
     * Konsekuensinya yang harus disadari: pelanggan tidak menerima apa pun di
     * tempat, jadi kwitansi ini arsip internal untuk sengketa, bukan bukti yang
     * dipegang pelanggan saat itu juga (§13.4).
     */
    public function print(Request $request, User $collector, ReceiptQrRenderer $qr, ReceiptPresenter $presenter): View
    {
        abort_unless($collector->hasRole('kolektor'), 404, 'User ini bukan kolektor.');

        $validated = $request->validate([
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'integer',
        ]);

        // Dua penyaring, dua alasan berbeda:
        //   applyUserScope  → admin cuma mencetak kwitansi POP-nya sendiri;
        //   payment_status  → pembayaran DITOLAK (uang tak pernah sampai
        //                     kantor) tak boleh punya kwitansi. Daftar kandidat
        //                     di Worksheet sudah menyaringnya, tapi id-nya
        //                     datang dari query string — penyaring di layar
        //                     tak pernah cukup jadi satu-satunya penjaga.
        $payments = Payment::query()
            ->applyUserScope()
            ->whereIn('id', $validated['payment_ids'])
            ->where('collected_by', $collector->id)
            ->where('payment_status', PaymentStatus::VALID->value)
            // Uangnya harus sudah sampai kantor DAN diperiksa. Selama setoran
            // masih `menunggu_verifikasi`, uangnya secara resmi belum dihitung
            // siapa pun — kantor belum punya dasar menerbitkan bukti.
            ->whereHas('collectorDeposit', fn ($q) => $q->where('status', '!=', DepositStatus::MENUNGGU_VERIFIKASI->value))
            ->with(['customer', 'invoice.internetPackage', 'pop', 'receiver', 'collector'])
            ->orderBy('payment_number')
            ->get();

        abort_if($payments->isEmpty(), 404, 'Tidak ada pembayaran yang bisa dicetak.');

        $qrByPayment = $payments->mapWithKeys(
            fn (Payment $payment) => [$payment->id => $qr->dataUri($payment->payment_number)]
        );

        // Isi kwitansi datang dari sumber yang sama dengan struk thermal dan
        // lembar A4 (ReceiptPresenter) — yang membedakan ketiganya cuma tata
        // letak, bukan field mana yang kebetulan ditulis di view-nya.
        $kwitansiByPayment = $payments->mapWithKeys(
            fn (Payment $payment) => [$payment->id => $presenter->for($payment)]
        );

        return view('collector-worksheet.receipt-print', compact('collector', 'payments', 'qrByPayment', 'kwitansiByPayment'));
    }

    /**
     * Upload banyak kwitansi sekaligus. Tiap berkas diantrekan untuk dibaca;
     * hasilnya muncul di tab Kwitansi.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'files' => 'required|array|min:1|max:100',
            'files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
            // Diisi browser dari jumlah berkas yang BENAR-BENAR dipilih admin,
            // sebelum PHP menyentuhnya. Lihat pemeriksaan di bawah.
            'files_count' => 'nullable|integer|min:1',
        ], [
            'files.max' => 'Maksimal 100 berkas per upload — bagi jadi beberapa kali kalau lebih.',
        ]);

        // PHP memotong unggahan di `max_file_uploads` TANPA error: berkas ke-21
        // dan seterusnya (default 20) hilang begitu saja, dan admin mengira
        // semuanya sudah masuk. `docker/php/local.ini` sudah menaikkannya ke
        // 100, tapi batas itu ada di lapisan yang tidak ikut ter-deploy bersama
        // kode ini — server lain, image lama, atau php.ini yang berbeda akan
        // diam-diam kembali memotong. Pemeriksaan ini yang membuat pemotongan
        // itu bersuara, di mana pun kodenya dijalankan.
        $dipilih = (int) ($validated['files_count'] ?? 0);
        $diterima = count($validated['files']);

        if ($dipilih > $diterima) {
            $batas = (int) ini_get('max_file_uploads');

            throw ValidationException::withMessages([
                'files' => "Hanya {$diterima} dari {$dipilih} berkas yang sampai ke server (batas max_file_uploads = {$batas}). Tidak ada yang diproses — unggah ulang maksimal {$batas} berkas sekali kirim.",
            ]);
        }

        $stored = 0;
        foreach ($validated['files'] as $file) {
            $this->receipts->store($file, $request->user());
            $stored++;
        }

        // Pesan menyebut ANGKA dan menunjuk ke panel, TAPI tidak mengklaim
        // "sedang dibaca": pembacaan lewat lapisan teks selesai dalam ~1 detik,
        // jadi sering sudah kelar sebelum halaman ini selesai dimuat ulang.
        // Versi lama berkata "sedang dibaca" sementara panelnya berkata "tidak
        // ada pembacaan yang berjalan" — dua pesan yang saling membantah, dan
        // admin menyimpulkan tidak terjadi apa-apa.
        return back()->with('success', "{$stored} berkas diunggah. Hasil pembacaannya ada di panel Status Pembacaan Kwitansi di atas. Ini tidak mengubah saldo maupun setoran kolektor.");
    }

    /**
     * Penghitung status kwitansi milik satu kolektor — dipakai panel progres.
     *
     * Ada karena pembacaan berjalan di queue: setelah unggah, halaman berisi
     * baris `pending` yang berubah sendiri beberapa detik kemudian tanpa ada
     * yang memberi tahu admin. Sebelumnya layarnya diam total — admin menekan
     * Unggah lalu tidak melihat apa pun berubah.
     *
     * Aturan aksesnya SAMA persis dengan daftar berkasnya
     * (`PaymentReceipt::scopeForWorksheet()`), bukan query kedua yang ditulis
     * ulang — penghitung yang menyimpang dari daftarnya adalah cara tercepat
     * membocorkan keberadaan berkas milik cabang lain lewat angka.
     */
    public function progress(Request $request, User $collector): JsonResponse
    {
        abort_unless($collector->hasRole('kolektor'), 404, 'User ini bukan kolektor.');

        $counts = PaymentReceipt::query()
            ->forWorksheet($collector, $request->user())
            ->selectRaw('status, COUNT(*) as jml')
            ->groupBy('status')
            ->pluck('jml', 'status');

        $antre = (int) $counts->get(ReceiptStatus::PENDING->value, 0)
            + (int) $counts->get(ReceiptStatus::PROCESSING->value, 0);

        return response()->json([
            'antre' => $antre,
            'selesai' => $antre === 0,
            'status' => [
                'pending' => (int) $counts->get(ReceiptStatus::PENDING->value, 0),
                'processing' => (int) $counts->get(ReceiptStatus::PROCESSING->value, 0),
                'matched' => (int) $counts->get(ReceiptStatus::MATCHED->value, 0),
                'mismatch' => (int) $counts->get(ReceiptStatus::MISMATCH->value, 0),
                'failed' => (int) $counts->get(ReceiptStatus::FAILED->value, 0),
            ],
            'total' => (int) $counts->sum(),
        ]);
    }

    /**
     * Berkas TIDAK pernah dilayani lewat URL publik. Disk `local` + gerbang
     * POP scope di sini adalah satu-satunya jalan masuk (aturan repo untuk
     * lampiran berisi data pelanggan).
     */
    public function download(PaymentReceipt $receipt): StreamedResponse
    {
        $this->authorizeReceipt($receipt);

        abort_unless(Storage::disk('local')->exists($receipt->path), 404, 'Berkas kwitansi tidak ditemukan.');

        return Storage::disk('local')->download($receipt->path, $receipt->original_filename);
    }

    public function matchManually(Request $request, PaymentReceipt $receipt): RedirectResponse
    {
        $this->authorizeReceipt($receipt);

        $validated = $request->validate([
            'payment_id' => 'required|integer|exists:payments,id',
        ]);

        $payment = Payment::query()
            ->applyUserScope()
            ->whereKey($validated['payment_id'])
            ->first();

        if (! $payment) {
            return back()->withErrors(['receipt' => 'Pembayaran tidak ditemukan atau di luar scope POP Anda.']);
        }

        try {
            $this->receipts->matchManually($receipt, $payment, $request->user());
        } catch (\Throwable $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('success', "Kwitansi {$receipt->original_filename} dicocokkan ke {$payment->payment_number}.");
    }

    public function detach(Request $request, PaymentReceipt $receipt): RedirectResponse
    {
        $this->authorizeReceipt($receipt);

        $this->receipts->detach($receipt, $request->user());

        return back()->with('success', "Kwitansi {$receipt->original_filename} dilepas dan bisa dicocokkan ulang.");
    }

    /**
     * Berkas ber-`pop_id` → POP scope berlaku penuh, termasuk sesudah kaitannya
     * dilepas (`pop_id` sengaja dipertahankan saat detach — melepasnya akan
     * melebarkan akses ke dokumen yang POP-nya sudah pernah diketahui).
     *
     * Berkas TANPA `pop_id` belum pernah diketahui miliknya sehingga tak bisa
     * di-scope. Yang menjaganya: hanya pengunggahnya sendiri, atau pemegang
     * akses seluruh POP. Sebelumnya siapa pun ber-permission halaman bisa
     * membukanya.
     */
    private function authorizeReceipt(PaymentReceipt $receipt): void
    {
        if ($receipt->pop_id === null) {
            $viewer = request()->user();

            abort_unless(
                (int) $receipt->uploaded_by === $viewer->id
                    || $viewer->hasRole('owner', 'atasan')
                    || app(EffectiveAccessService::class)->hasAllPopAccess($viewer),
                403,
                'Kwitansi ini belum tercocokkan dan diunggah orang lain.'
            );

            return;
        }

        abort_unless(
            PaymentReceipt::query()->applyUserScope()->whereKey($receipt->id)->exists(),
            403,
            'Kwitansi ini milik POP di luar scope Anda.'
        );
    }
}
