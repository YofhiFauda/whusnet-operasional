<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptMatchMethod;
use App\Enums\ReceiptStatus;
use App\Enums\ScopeType;
use App\Jobs\MatchPaymentReceipt;
use App\Models\CollectorDeposit;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CollectorDepositService;
use App\Services\Receipts\PaymentReceiptService;
use App\Services\Receipts\ReceiptQrRenderer;
use Database\Seeders\DatabaseSeeder;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kwitansi (Fase 4) — sumbu DOKUMEN.
 *
 * Yang dijaga di sini:
 *   1. pencocokan otomatis lewat QR yang sistem sendiri cetak (deterministik);
 *   2. berkas yang tak terbaca TIDAK hilang — ia menunggu manusia, bukan
 *      dicocokkan asal;
 *   3. sumbu dokumen tak pernah menyentuh sumbu kas: setoran terverifikasi
 *      tanpa menunggu kwitansi, dan kwitansi tak mengubah nilai pembayaran;
 *   4. berkasnya privat — tak pernah dilayani lewat URL publik.
 */
class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $kolektor;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = $this->createPop('RCP1');
        $this->kolektor = $this->createUser('kolektor', $this->pop);
        $this->admin = $this->createUser('pop_admin', $this->pop);
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => 'POP-'.$code,
            'pop_code' => $code,
            'registration_prefix' => 'C'.substr($code, -1),
            'cid_prefix' => 'D'.substr($code, -1),
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createUser(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
        $user->pops()->attach($pop->id);

        return $user;
    }

    /**
     * Buat pembayaran milik kolektor ini.
     *
     * SENGAJA membuat Payment langsung, bukan lewat rute bayar: sebagian
     * skenario di file ini butuh pembayaran di POP LAIN (untuk menguji scope
     * kwitansi), dan rute bayar dengan benar menolak kolektor menagih di luar
     * scope-nya (guard Fase 1). Memakai rute di sini akan menguji guard itu
     * lagi, bukan menguji kwitansi.
     */
    private function collect(string $code, float $amount = 150000, ?Pop $pop = null): Payment
    {
        $pop ??= $this->pop;

        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => $this->kolektor->id,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. '.$code,
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $amount,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $amount,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-'.$code,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => $amount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'invoice_status' => 'belum_dibayar',
        ]);

        $payment = Payment::create([
            'payment_number' => Payment::generatePaymentNumber(now()->format('Y-m-d')),
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => now()->format('Y-m-d'),
            'collected_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'amount' => $amount,
            'received_by' => $this->kolektor->id,
            'collected_by' => $this->kolektor->id,
            'payment_status' => PaymentStatus::VALID->value,
        ]);

        $invoice->recalculateFromPayments();

        return $payment;
    }

    /**
     * Bawa pembayaran sampai setorannya diperiksa kantor.
     *
     * Kwitansi hanya boleh terbit sesudah titik ini — dokumen kantor, bukan
     * dokumen lapangan. Helper ini memakai jalur resmi (setor → verifikasi)
     * supaya syaratnya benar-benar diuji, bukan ditembus lewat model.
     */
    private function collectAndDeposit(string $code, float $amount = 150000, ?Pop $pop = null): Payment
    {
        $payment = $this->collect($code, $amount, $pop);

        $deposit = app(CollectorDepositService::class)->submit($this->kolektor);
        app(CollectorDepositService::class)->verify($deposit, $this->admin, (float) $deposit->computedAmount());

        return $payment->fresh();
    }

    /** Kwitansi tiruan: gambar PNG berisi QR dengan payload yang diminta. */
    private function fakeReceiptImage(string $payload, string $filename = 'kwitansi.png'): UploadedFile
    {
        $png = Builder::create()
            ->writer(new PngWriter)
            ->data($payload)
            ->size(400)
            ->margin(16)
            ->build()
            ->getString();

        $path = tempnam(sys_get_temp_dir(), 'rcp').'.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, $filename, 'image/png', null, true);
    }

    // ================= CETAK =================

    public function test_print_page_shows_qr_and_plain_payment_number(): void
    {
        $payment = $this->collectAndDeposit('C-RCP-PRINT');

        $response = $this->actingAs($this->admin)->get(route('payment-receipts.print', [
            'collector' => $this->kolektor->id,
            'payment_ids' => [$payment->id],
        ]));

        $response->assertOk();
        // Nomor dicetak sebagai TEKS — ini yang dibaca OCR saat QR sobek, dan
        // dibaca manusia saat OCR pun gagal.
        $response->assertSee($payment->payment_number);
        // QR ditanam sebagai data URI SVG, tak butuh berkas sementara.
        $response->assertSee('data:image/svg+xml', false);
    }

    /**
     * Header/footer bawaan browser (tanggal, judul, URL localhost:8000/...)
     * dicetak di kotak margin halaman — tidak bisa disembunyikan lewat selector
     * apa pun, cuma lenyap kalau marginnya nol. Kwitansi yang mencantumkan URL
     * internal sistem adalah dokumen yang diserahkan ke pelanggan.
     */
    public function test_halaman_cetak_menolak_header_footer_bawaan_browser(): void
    {
        $payment = $this->collectAndDeposit('C-RCP-PAGE');

        $response = $this->actingAs($this->admin)->get(route('payment-receipts.print', [
            'collector' => $this->kolektor->id,
            'payment_ids' => [$payment->id],
        ]));

        $response->assertOk();
        $response->assertSee('margin: 0;', false);
        // Margin nol memindahkan jarak ke tepi kertas jadi tanggung jawab body/page.
        // Tanpa ini kwitansi mepet tepi dan terpotong printer.
        $response->assertSee('padding: 4mm 4mm !important;', false);
    }

    /**
     * Kwitansi cuma untuk uang yang benar-benar diterima. Pembayaran DITOLAK
     * berarti uangnya tak pernah sampai kantor — mencetak kertas resmi yang
     * menyatakan pelanggan sudah bayar untuk uang yang kantor sendiri sudah
     * tolak adalah dokumen yang melawan catatannya sendiri.
     */
    public function test_rejected_payment_cannot_be_printed(): void
    {
        $payment = $this->collectAndDeposit('C-RCP-TOLAK');
        $payment->update(['payment_status' => PaymentStatus::DITOLAK->value]);

        $this->actingAs($this->admin)->get(route('payment-receipts.print', [
            'collector' => $this->kolektor->id,
            'payment_ids' => [$payment->id],
        ]))->assertNotFound();
    }

    public function test_rejected_payment_is_not_offered_as_print_candidate(): void
    {
        $valid = $this->collectAndDeposit('C-RCP-VALID');
        $rejected = $this->collect('C-RCP-DITOLAK');
        $rejected->update(['payment_status' => PaymentStatus::DITOLAK->value]);

        $response = $this->actingAs($this->admin)->get(route('collector-worksheet.show', [
            'collector' => $this->kolektor->id,
            'tab' => 'kwitansi',
        ]));

        $response->assertOk();
        $candidates = $response->viewData('receiptCandidates')->pluck('id');
        $this->assertTrue($candidates->contains($valid->id));
        $this->assertFalse($candidates->contains($rejected->id));
    }

    /**
     * Keputusan user 2026-08-08: kwitansi adalah dokumen KANTOR. Selama uangnya
     * masih di tas kolektor — setoran belum diperiksa — kantor belum punya
     * dasar menerbitkan bukti apa pun.
     */
    public function test_payment_cannot_be_printed_before_its_deposit_is_checked(): void
    {
        $payment = $this->collect('C-RCP-BELUMSETOR');

        // Belum disetor sama sekali.
        $this->actingAs($this->admin)->get(route('payment-receipts.print', [
            'collector' => $this->kolektor->id,
            'payment_ids' => [$payment->id],
        ]))->assertNotFound();

        // Sudah disetor, tapi kantor belum memeriksa.
        app(CollectorDepositService::class)->submit($this->kolektor);

        $this->actingAs($this->admin)->get(route('payment-receipts.print', [
            'collector' => $this->kolektor->id,
            'payment_ids' => [$payment->id],
        ]))->assertNotFound();
    }

    /**
     * Setoran yang berakhir KURANG SETOR tetap boleh dicetak kwitansinya:
     * kantor sudah memeriksanya, dan pelanggan yang membayar penuh tak boleh
     * kehilangan buktinya cuma karena kolektor kurang menyetor.
     */
    public function test_short_deposit_still_allows_printing(): void
    {
        $payment = $this->collect('C-RCP-KURANG');

        $deposit = app(CollectorDepositService::class)->submit($this->kolektor);
        app(CollectorDepositService::class)->verify(
            $deposit,
            $this->admin,
            (float) $deposit->computedAmount() - 30000,
            note: 'Kolektor kurang setor 30rb.'
        );

        $this->actingAs($this->admin)->get(route('payment-receipts.print', [
            'collector' => $this->kolektor->id,
            'payment_ids' => [$payment->id],
        ]))->assertOk();
    }

    public function test_print_rejects_payment_outside_admin_pop_scope(): void
    {
        $otherPop = $this->createPop('RCP9');
        $payment = $this->collect('C-RCP-OUT', pop: $otherPop);

        $adminLuar = $this->createUser('pop_admin', $this->pop);

        $this->actingAs($adminLuar)->get(route('payment-receipts.print', [
            'collector' => $this->kolektor->id,
            'payment_ids' => [$payment->id],
        ]))->assertNotFound();
    }

    public function test_qr_payload_is_the_bare_payment_number(): void
    {
        // Bukan URL: kertas yang sudah dicetak tak boleh terikat domain yang
        // bisa berubah, dan pembacanya adalah sistem ini sendiri.
        $uri = app(ReceiptQrRenderer::class)->dataUri('PAY-202608-0042');

        $this->assertStringStartsWith('data:image/svg+xml', $uri);
    }

    // ================= UPLOAD =================

    public function test_upload_stores_file_privately_and_queues_reading(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)->post(route('payment-receipts.store'), [
            'files' => [$this->fakeReceiptImage('PAY-202608-0001')],
        ])->assertRedirect();

        $receipt = PaymentReceipt::query()->firstOrFail();
        $this->assertSame(ReceiptStatus::PENDING, $receipt->status);
        Storage::disk('local')->assertExists($receipt->path);
        Queue::assertPushed(MatchPaymentReceipt::class);
    }

    public function test_upload_terpotong_php_ditolak_bukan_diproses_separuh(): void
    {
        // PHP memotong unggahan di `max_file_uploads` TANPA error apa pun.
        // Admin memilih 100 berkas, 20 sampai, sisanya lenyap — dan pesan
        // sukses tetap muncul seolah semuanya masuk. Di sini dipalsukan dengan
        // mengirim `files_count` lebih besar dari jumlah berkas yang tiba.
        Queue::fake();

        $this->actingAs($this->admin)
            ->from(route('collector-worksheet.show', $this->kolektor))
            ->post(route('payment-receipts.store'), [
                'files' => [$this->fakeReceiptImage('PAY-202608-0001')],
                'files_count' => 5,
            ])
            ->assertSessionHasErrors('files');

        $this->assertSame(0, PaymentReceipt::count());
        Queue::assertNothingPushed();
    }

    public function test_upload_utuh_dengan_files_count_cocok_tetap_diproses(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)->post(route('payment-receipts.store'), [
            'files' => [
                $this->fakeReceiptImage('PAY-202608-0001'),
                $this->fakeReceiptImage('PAY-202608-0002'),
            ],
            'files_count' => 2,
        ])->assertRedirect();

        $this->assertSame(2, PaymentReceipt::count());
    }

    // ================= PANEL PROGRES =================

    public function test_progress_menghitung_status_berkas_kolektor_ini(): void
    {
        // Pembacaan berjalan di queue, jadi halaman butuh sumber angka yang
        // bisa ditanya berulang tanpa memuat ulang seluruh daftar.
        Queue::fake();

        $this->actingAs($this->admin)->post(route('payment-receipts.store'), [
            'files' => [
                $this->fakeReceiptImage('PAY-202608-0001'),
                $this->fakeReceiptImage('PAY-202608-0002', 'kwitansi-2.png'),
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('payment-receipts.progress', $this->kolektor));

        $response->assertOk()
            ->assertJsonPath('antre', 2)
            ->assertJsonPath('selesai', false)
            ->assertJsonPath('status.pending', 2)
            ->assertJsonPath('total', 2);
    }

    public function test_progress_tidak_membocorkan_berkas_yatim_admin_lain(): void
    {
        // Berkas yang belum tercocokkan tak punya `pop_id` sehingga tak bisa
        // di-scope; penggantinya kepemilikan unggahan. Angka pun harus ikut
        // aturan itu — penghitung yang lebih longgar dari daftarnya
        // membocorkan keberadaan berkas cabang lain lewat angka.
        Queue::fake();

        $adminLain = $this->createUser('pop_admin', $this->createPop('RCP9'));

        $this->actingAs($adminLain)->post(route('payment-receipts.store'), [
            'files' => [$this->fakeReceiptImage('PAY-202608-0001')],
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('payment-receipts.progress', $this->kolektor))
            ->assertOk()
            ->assertJsonPath('antre', 0)
            ->assertJsonPath('selesai', true)
            ->assertJsonPath('total', 0);
    }

    public function test_progress_menolak_user_yang_bukan_kolektor(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('payment-receipts.progress', $this->admin))
            ->assertNotFound();
    }

    public function test_identical_file_uploaded_twice_creates_one_row(): void
    {
        Queue::fake();

        $file = $this->fakeReceiptImage('PAY-202608-0002');
        $again = $this->fakeReceiptImage('PAY-202608-0002');

        $this->actingAs($this->admin)->post(route('payment-receipts.store'), ['files' => [$file]]);
        $this->actingAs($this->admin)->post(route('payment-receipts.store'), ['files' => [$again]]);

        $this->assertSame(1, PaymentReceipt::count());
    }

    // ================= PENCOCOKAN OTOMATIS =================

    public function test_qr_matches_receipt_to_its_payment(): void
    {
        $payment = $this->collect('C-RCP-QR');

        $receipt = app(PaymentReceiptService::class)->store(
            $this->fakeReceiptImage($payment->payment_number),
            $this->admin
        );

        app(PaymentReceiptService::class)->match($receipt->fresh());

        $receipt->refresh();
        $this->assertSame(ReceiptStatus::MATCHED, $receipt->status);
        $this->assertSame(ReceiptMatchMethod::QR, $receipt->match_method);
        $this->assertSame($payment->id, (int) $receipt->payment_id);
        // POP disalin dari payment — inilah yang membuat berkas ikut POP scope.
        $this->assertSame($payment->pop_id, $receipt->pop_id);
    }

    /**
     * Nomor terbaca tapi tak menunjuk pembayaran mana pun. TIDAK boleh
     * dicocokkan asal — inilah gerbang yang menahan QR salah cetak maupun
     * halusinasi OCR.
     */
    public function test_unknown_number_becomes_mismatch_not_a_wrong_match(): void
    {
        $this->collect('C-RCP-MIS');

        $receipt = app(PaymentReceiptService::class)->store(
            $this->fakeReceiptImage('PAY-209912-9999'),
            $this->admin
        );

        app(PaymentReceiptService::class)->match($receipt->fresh());

        $receipt->refresh();
        $this->assertSame(ReceiptStatus::MISMATCH, $receipt->status);
        $this->assertNull($receipt->payment_id);
        $this->assertSame('PAY-209912-9999', $receipt->detected_number);
    }

    public function test_unreadable_file_is_marked_failed_and_kept(): void
    {
        $receipt = app(PaymentReceiptService::class)->store(
            UploadedFile::fake()->create('coretan.pdf', 12, 'application/pdf'),
            $this->admin
        );

        app(PaymentReceiptService::class)->match($receipt->fresh());

        $receipt->refresh();
        $this->assertSame(ReceiptStatus::FAILED, $receipt->status);
        // Berkasnya TETAP ada — dokumen yang tak terbaca adalah pekerjaan yang
        // tertinggal, bukan sesuatu yang boleh hilang diam-diam.
        Storage::disk('local')->assertExists($receipt->path);
    }

    public function test_ocr_is_skipped_when_no_api_key_configured(): void
    {
        config(['services.gemini.key' => null]);

        $receipt = app(PaymentReceiptService::class)->store(
            UploadedFile::fake()->create('tanpa-qr.pdf', 10, 'application/pdf'),
            $this->admin
        );

        app(PaymentReceiptService::class)->match($receipt->fresh());

        // Tanpa API key, fitur tetap utuh: berkas jatuh ke penanganan manusia,
        // bukan error, dan tak ada biaya yang keluar.
        $this->assertSame(ReceiptStatus::FAILED, $receipt->fresh()->status);
    }

    // ================= PENCOCOKAN MANUAL =================

    public function test_admin_can_match_failed_receipt_manually(): void
    {
        $payment = $this->collect('C-RCP-MANUAL');

        $receipt = app(PaymentReceiptService::class)->store(
            UploadedFile::fake()->create('buram.pdf', 10, 'application/pdf'),
            $this->admin
        );
        app(PaymentReceiptService::class)->match($receipt->fresh());

        $this->actingAs($this->admin)->post(route('payment-receipts.match', $receipt->id), [
            'payment_id' => $payment->id,
        ])->assertRedirect();

        $receipt->refresh();
        $this->assertSame(ReceiptStatus::MATCHED, $receipt->status);
        $this->assertSame(ReceiptMatchMethod::MANUAL, $receipt->match_method);
        $this->assertSame($this->admin->id, (int) $receipt->matched_by);
    }

    public function test_manual_match_rejects_payment_outside_pop_scope(): void
    {
        $otherPop = $this->createPop('RCP8');
        $payment = $this->collect('C-RCP-SCOPE', pop: $otherPop);

        $adminLuar = $this->createUser('pop_admin', $this->pop);

        $receipt = app(PaymentReceiptService::class)->store(
            UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            $adminLuar
        );
        app(PaymentReceiptService::class)->match($receipt->fresh());

        $this->actingAs($adminLuar)->post(route('payment-receipts.match', $receipt->id), [
            'payment_id' => $payment->id,
        ])->assertSessionHasErrors('receipt');

        $this->assertNull($receipt->fresh()->payment_id);
    }

    public function test_matched_receipt_can_be_detached_for_recorrection(): void
    {
        $payment = $this->collect('C-RCP-DETACH');

        $receipt = app(PaymentReceiptService::class)->store(
            $this->fakeReceiptImage($payment->payment_number),
            $this->admin
        );
        app(PaymentReceiptService::class)->match($receipt->fresh());

        $this->actingAs($this->admin)->post(route('payment-receipts.detach', $receipt->id))->assertRedirect();

        $receipt->refresh();
        $this->assertNull($receipt->payment_id);
        $this->assertSame(ReceiptStatus::MISMATCH, $receipt->status);
    }

    // ================= AKSES BERKAS =================

    public function test_receipt_file_is_served_only_through_controller(): void
    {
        $payment = $this->collect('C-RCP-DL');

        $receipt = app(PaymentReceiptService::class)->store(
            $this->fakeReceiptImage($payment->payment_number),
            $this->admin
        );
        app(PaymentReceiptService::class)->match($receipt->fresh());

        // Disk privat: tak ada URL publik yang bisa ditebak.
        $this->assertStringStartsWith('receipts/', $receipt->fresh()->path);

        $this->actingAs($this->admin)
            ->get(route('payment-receipts.download', $receipt->id))
            ->assertOk();
    }

    public function test_admin_from_other_pop_cannot_download_matched_receipt(): void
    {
        $otherPop = $this->createPop('RCP7');
        $payment = $this->collect('C-RCP-PRIV', pop: $otherPop);

        $receipt = app(PaymentReceiptService::class)->store(
            $this->fakeReceiptImage($payment->payment_number),
            $this->admin
        );
        app(PaymentReceiptService::class)->match($receipt->fresh());

        $this->assertSame($otherPop->id, $receipt->fresh()->pop_id);

        // Admin ber-scope POP RCP1 saja.
        $this->actingAs($this->admin)
            ->get(route('payment-receipts.download', $receipt->id))
            ->assertForbidden();
    }

    // ================= SUMBU KAS TIDAK TERSENTUH =================

    /**
     * Inti §13.2: verifikasi setoran TIDAK menunggu kwitansi. Rancangan awal
     * menggantung status verifikasi pada selesainya OCR — itu menyandera uang
     * yang sudah dihitung dua orang di meja pada dokumen yang bisa gagal dibaca.
     */
    public function test_deposit_verification_does_not_wait_for_any_receipt(): void
    {
        $this->collect('C-RCP-KAS');

        $this->actingAs($this->kolektor)->post(route('collector-worklist.deposit'))->assertSessionHasNoErrors();
        $deposit = CollectorDeposit::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('collector-deposits.verify', $deposit->id), ['declared_amount' => 150000])
            ->assertSessionHasNoErrors();

        $this->assertSame(DepositStatus::TERVERIFIKASI, $deposit->fresh()->status);
        $this->assertSame(0, PaymentReceipt::count());
    }

    public function test_matching_a_receipt_never_changes_payment_amount(): void
    {
        $payment = $this->collect('C-RCP-AMOUNT');
        $before = (float) $payment->amount;

        $receipt = app(PaymentReceiptService::class)->store(
            $this->fakeReceiptImage($payment->payment_number),
            $this->admin
        );
        app(PaymentReceiptService::class)->match($receipt->fresh());

        $this->assertSame($before, (float) $payment->fresh()->amount);
    }

    /**
     * Regresi (review Fase 4 #3): satu pembaca yang MELEDAK tak boleh
     * menghentikan rantai. `Zxing\QrReader` melempar untuk gambar yang GD-nya
     * tak bisa buka — dan `getimagesize()` tetap mengenalinya, jadi penjaga
     * isImage() lolos. Waktu exception-nya merambat, OCR (yang justru ada
     * untuk kasus "QR tak terbaca") tak pernah dicoba.
     */
    public function test_a_throwing_reader_does_not_abort_the_chain(): void
    {
        // Berkas ber-header PNG tapi isinya rusak: getimagesize() mengenali,
        // decoder gagal membukanya.
        $path = tempnam(sys_get_temp_dir(), 'brk').'.png';
        $valid = (string) Builder::create()->writer(new PngWriter)->data('PAY-202608-0009')->size(200)->build()->getString();
        file_put_contents($path, substr($valid, 0, 60).str_repeat("\x00", 400));

        $receipt = app(PaymentReceiptService::class)->store(
            new UploadedFile($path, 'rusak.png', 'image/png', null, true),
            $this->admin
        );

        // Habiskan jatah percobaan supaya statusnya final, bukan dilempar ulang
        // untuk di-retry queue.
        $receipt->update(['attempts' => PaymentReceiptService::MAX_ATTEMPTS]);
        app(PaymentReceiptService::class)->match($receipt->fresh());

        // Yang penting: berakhir dengan status yang jelas & berkasnya utuh,
        // bukan exception yang lolos ke pemanggil.
        $this->assertTrue($receipt->fresh()->status->needsAttention());
        Storage::disk('local')->assertExists($receipt->path);
    }

    /**
     * Regresi (review Fase 4 #9): kegagalan teknis harus DILEMPAR selama jatah
     * percobaan masih ada, supaya `$tries` pada job bukan konfigurasi mati.
     */
    public function test_technical_failure_is_rethrown_while_retries_remain(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'brk2').'.png';
        $valid = (string) Builder::create()->writer(new PngWriter)->data('PAY-202608-0010')->size(200)->build()->getString();
        file_put_contents($path, substr($valid, 0, 60).str_repeat("\x00", 400));

        $receipt = app(PaymentReceiptService::class)->store(
            new UploadedFile($path, 'rusak2.png', 'image/png', null, true),
            $this->admin
        );

        $this->expectException(\Throwable::class);
        app(PaymentReceiptService::class)->match($receipt->fresh());
    }

    /**
     * Regresi (review Fase 4 #5): `detach()` dulu menolkan `pop_id`, dan
     * gerbang akses melewatkan berkas ber-`pop_id` null — jadi melepas kaitan
     * malah MELEBARKAN akses ke dokumen yang POP-nya sudah diketahui.
     */
    public function test_detach_keeps_pop_so_access_does_not_widen(): void
    {
        $payment = $this->collect('C-RCP-DETPOP');

        $receipt = app(PaymentReceiptService::class)->store(
            $this->fakeReceiptImage($payment->payment_number),
            $this->admin
        );
        app(PaymentReceiptService::class)->match($receipt->fresh());

        $this->actingAs($this->admin)->post(route('payment-receipts.detach', $receipt->id))->assertRedirect();

        $this->assertSame($payment->pop_id, $receipt->fresh()->pop_id);

        $adminLuar = $this->createUser('pop_admin', $this->createPop('RCP6'));
        $this->actingAs($adminLuar)
            ->get(route('payment-receipts.download', $receipt->id))
            ->assertForbidden();
    }

    /**
     * Regresi (review Fase 4 #6 & gerbang berkas yatim): kwitansi yang belum
     * tercocokkan tak punya `pop_id` sehingga tak bisa di-scope — yang menjaga
     * adalah kepemilikan unggahan.
     */
    public function test_unmatched_receipt_is_private_to_its_uploader(): void
    {
        $receipt = app(PaymentReceiptService::class)->store(
            UploadedFile::fake()->create('yatim.pdf', 10, 'application/pdf'),
            $this->admin
        );

        $adminLain = $this->createUser('pop_admin', $this->pop);

        $this->actingAs($adminLain)
            ->get(route('payment-receipts.download', $receipt->id))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('payment-receipts.download', $receipt->id))
            ->assertOk();
    }

    // ================= RBAC =================

    public function test_kolektor_cannot_touch_receipts(): void
    {
        $this->assertFalse($this->kolektor->hasPermission('collector_worksheet.upload'));
        $this->assertFalse($this->kolektor->hasPermission('collector_worksheet.print'));

        $this->actingAs($this->kolektor)->post(route('payment-receipts.store'), [
            'files' => [$this->fakeReceiptImage('PAY-202608-0003')],
        ])->assertForbidden();
    }
}
