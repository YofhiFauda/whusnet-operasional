<?php

namespace App\Services;

use App\Exceptions\NetworkAssignmentException;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerTechnicalDetail;
use App\Models\Distribution;
use App\Models\Pop;
use App\Models\WebhookOutbox;
use Illuminate\Support\Facades\DB;

/**
 * Business logic dua endpoint API baru (docs/api/api-pop-distribusi/business-logic.md):
 * - `assignNetwork()` — `POST /api/v1/installations/network-assignment`
 * - `assignDevice()`  — `POST /api/v1/installations/network-device`
 *
 * Awalnya SATU endpoint (rev 1-10, lihat keputusan.md §8), dipecah jadi dua
 * (rev 12) atas keputusan pemilik produk supaya keduanya bisa berkembang
 * independen ke depannya — bukan karena ada masalah teknis dengan versi
 * gabungan (versi gabungan itu sendiri sudah benar, lihat keputusan.md §19
 * untuk analisis lengkap kenapa akhirnya tetap dipecah).
 *
 * Kedua method BERBAGI infrastruktur (resolve pelanggan dari
 * `idempotency_key`, row lock, dedup key+hash, audit log, bentuk respons) —
 * dua method di satu class, BUKAN dua class terpisah, supaya infrastruktur
 * itu (terutama dedup & audit) tetap satu sumber kebenaran.
 *
 * Aturan validasi Mini POP/Distribusi WAJIB sejalan dengan
 * `CustomerNetworkAssignmentController::update()` (jalur staf, `:67-140`) —
 * kalau salah satu diubah, cek yang lain (keputusan.md §4).
 */
class NetworkAssignmentService
{
    /**
     * Status yang belum boleh di-assign Mini POP/Distribusi — SAMA PERSIS
     * `CustomerNetworkAssignmentController::BLOCKED_STATUSES`. Pemasangan
     * belum mulai, jadi belum ada dasar teknis buat nentuin OLT/Distribusi
     * mana. Kalau daftar di sana berubah, ubah juga di sini.
     */
    private const BLOCKED_STATUSES = [
        'registered',
        'waiting_survey',
        'survey_in_progress',
        'surveyed',
        'waiting_acc',
        'waiting_installation',
        'rejected',
    ];

    /**
     * `POST /api/v1/installations/network-assignment`. Konfirmasi Mini POP +
     * Distribusi untuk satu pelanggan — `mini_pop_code`+`distribution_code`
     * WAJIB bareng (tidak ada lagi mode "perangkat-saja" di endpoint ini
     * sejak dipecah, lihat `assignDevice()`).
     *
     * @param  array{idempotency_key: string, mini_pop_code: string, distribution_code: string}  $validated
     * @return array{mini_pop_code: ?string, distribution_code: ?string, cid?: string}
     */
    public function assignNetwork(array $validated, string $ip): array
    {
        $key = $validated['idempotency_key'];
        $hash = $this->hashBody($validated);

        return DB::transaction(function () use ($validated, $key, $hash, $ip): array {
            $customer = $this->resolveAndLockCustomer($key);

            // Dedup di-scope ke key+hash (BUKAN key doang) — idempotency_key
            // yang sama dipakai ulang kalau network-assignment dan
            // network-device dikonfirmasi lewat request terpisah
            // (business-logic.md §"Idempotency"). Cek ini WAJIB setelah row
            // lock di atas: itu yang menutup race dua retry beruntun yang
            // datang nyaris bersamaan (keputusan.md §11).
            if ($this->alreadyProcessed($key, $hash)) {
                return $this->buildResponse($customer);
            }

            [$miniPop, $distribution] = $this->resolveMiniPopAndDistribution(
                $customer,
                $validated['mini_pop_code'],
                $validated['distribution_code']
            );

            $oldValues = $this->snapshotCustomer($customer);

            $this->applyAssignment($customer, $miniPop, $distribution);

            $this->writeAuditLog($customer, 'network_assignment', $oldValues, [], [], $key, $hash, $ip);

            return $this->buildResponse($customer);
        });
    }

    /**
     * `POST /api/v1/installations/network-device`. Memperbarui kredensial
     * PPPoE dan/atau detail titik sambung OLT untuk pelanggan yang SUDAH
     * punya Mini POP/Distribusi tersimpan (lewat `assignNetwork()`
     * sebelumnya) — kredensial jaringan tidak ada gunanya tanpa itu
     * (business-logic.md §"Alur nyata").
     *
     * @param  array{idempotency_key: string, perangkat: array<string, mixed>}  $validated
     * @return array{mini_pop_code: ?string, distribution_code: ?string, cid?: string, perangkat?: array<string, mixed>}
     */
    public function assignDevice(array $validated, string $ip): array
    {
        $key = $validated['idempotency_key'];
        $hash = $this->hashBody($validated);

        $perangkat = array_filter(
            $validated['perangkat'] ?? [],
            fn ($v) => $v !== null && $v !== ''
        );

        $deviceFields = array_intersect_key($perangkat, array_flip([
            'pppoe_username', 'pppoe_password',
        ]));
        $techFields = array_intersect_key($perangkat, array_flip([
            'olt_number', 'olt_slot', 'olt_port', 'vlan',
        ]));

        // Dipakai buat echo balik ke response (dan jalur retry dedup di
        // bawah) — TANPA pppoe_password, sama larangan yang berlaku di
        // writeAuditLog(). Dihitung dari $perangkat ASLI (sebelum
        // device_type default disisipkan di bawah) supaya respons cuma
        // berisi apa yang benar-benar dikirim Website B, bukan default
        // internal yang mereka gak pernah tahu.
        $perangkatEcho = array_diff_key(
            array_merge($deviceFields, $techFields),
            ['pppoe_password' => true]
        );

        return DB::transaction(function () use ($key, $hash, $ip, $deviceFields, $techFields, $perangkatEcho): array {
            $customer = $this->resolveAndLockCustomer($key);

            if ($this->alreadyProcessed($key, $hash)) {
                return $this->buildResponse($customer, $perangkatEcho);
            }

            if (! $customer->mini_pop_id || ! $customer->distribution_id) {
                throw NetworkAssignmentException::unprocessable(
                    'Belum ada assignment Mini POP/Distribusi tersimpan — konfirmasi lewat POST /api/v1/installations/network-assignment terlebih dahulu.'
                );
            }

            $oldValues = $this->snapshotCustomer($customer);

            if ($deviceFields !== []) {
                // customer_devices.device_type NOT NULL tanpa default — kolom
                // ini di luar kontrak `perangkat` (endpoint ini gak pernah
                // tahu tipe fisik perangkat dari Website B). Kalau baris
                // belum ada sama sekali, isi 'other' biar INSERT gak gagal;
                // kalau baris SUDAH ada (mis. teknisi sudah isi device_type
                // sebenarnya), JANGAN ditimpa. Ditulis ke variabel TERPISAH
                // ($deviceFieldsForWrite), bukan $deviceFields langsung —
                // $deviceFields juga dipakai buat audit log & echo respons,
                // dan device_type default ini bukan bagian kontrak yang
                // perlu tampil di keduanya.
                $deviceFieldsForWrite = $deviceFields;

                if (! CustomerDevice::where('customer_id', $customer->id)->exists()) {
                    $deviceFieldsForWrite['device_type'] = 'other';
                }

                $customer->customerDevice()->updateOrCreate(
                    ['customer_id' => $customer->id],
                    $deviceFieldsForWrite
                );
            }

            if ($techFields !== []) {
                CustomerTechnicalDetail::updateOrCreate(
                    ['customer_id' => $customer->id],
                    $techFields
                );
            }

            $this->writeAuditLog($customer, 'network_device_update', $oldValues, $deviceFields, $techFields, $key, $hash, $ip);

            return $this->buildResponse($customer, $perangkatEcho);
        });
    }

    private function alreadyProcessed(string $key, string $hash): bool
    {
        return AuditLog::where('idempotency_key', $key)->where('request_hash', $hash)->exists();
    }

    /**
     * @return array{mini_pop_id: ?int, distribution_id: ?int, cid: ?string}
     */
    private function snapshotCustomer(Customer $customer): array
    {
        return [
            'mini_pop_id' => $customer->mini_pop_id,
            'distribution_id' => $customer->distribution_id,
            'cid' => $customer->cid,
        ];
    }

    /**
     * Respons selalu mencerminkan state TERSIMPAN saat ini — bukan cuma apa
     * yang diproses request ini — supaya Website B punya bukti balik
     * `mini_pop_code`/`distribution_code` beneran kepasang, bukan cuma
     * "200 OK" tanpa konfirmasi. Dipakai kedua endpoint (`assignNetwork()`
     * dan `assignDevice()`) dan baik jalur retry dedup maupun jalur sukses
     * baru — satu bentuk respons yang sama, konsisten di seluruh API ini.
     *
     * `cid` cuma MUNCUL kalau pelanggan sudah `active`/`suspended` —
     * `customers.cid` sudah final tersimpan (aturan CID tidak berubah,
     * spesifikasi-pop-distribusi-cid.md: REQ ID murni sebelum aktif,
     * keputusan.md §18). Kalau belum, key `cid` **dihilangkan total** dari
     * respons — bukan `null`, bukan nilai preview. *Presence* key `cid` =
     * sinyal satu-satunya yang perlu Website B baca: ada artinya final, gak
     * ada artinya belum.
     *
     * Key `perangkat` SAMA prinsipnya — cuma muncul kalau `assignDevice()`
     * yang manggil dan ada field yang diproses (`assignNetwork()` selalu
     * lewat default `[]`, gak pernah nyentuh perangkat, jadi key ini gak
     * pernah muncul di respons endpoint #2 — dua endpoint sengaja beda
     * bentuk respons, bukan bug). `pppoe_password` sudah difilter sebelum
     * sampai sini (lihat pemanggil) — jangan tambahkan lagi di sini.
     *
     * @param  array<string, mixed>  $perangkat
     * @return array{mini_pop_code: ?string, distribution_code: ?string, cid?: string, perangkat?: array<string, mixed>}
     */
    private function buildResponse(Customer $customer, array $perangkat = []): array
    {
        $customer->loadMissing(['miniPop', 'distribution']);

        $response = [
            'mini_pop_code' => $customer->miniPop?->pop_code,
            'distribution_code' => $customer->distribution?->code,
        ];

        if ($customer->cid !== null) {
            $response['cid'] = $customer->cid;
        }

        if ($perangkat !== []) {
            $response['perangkat'] = $perangkat;
        }

        return $response;
    }

    /**
     * Resolve pelanggan dari `idempotency_key` lewat `webhook_outbox`
     * (bukan `customer_id`/`cid` — larangan keras lintas-API, lihat
     * database-schema.md), lalu kunci barisnya (`lockForUpdate()`) supaya dua
     * request key yang sama datang nyaris bersamaan tidak saling menimpa.
     */
    private function resolveAndLockCustomer(string $key): Customer
    {
        $customerId = WebhookOutbox::where('idempotency_key', $key)
            ->whereNotNull('customer_id')
            ->value('customer_id');

        if (! $customerId) {
            throw NetworkAssignmentException::notFound(
                'Pelanggan tidak ditemukan untuk idempotency_key ini.'
            );
        }

        $customer = Customer::where('id', $customerId)->lockForUpdate()->first();

        if (! $customer) {
            throw NetworkAssignmentException::notFound(
                'Pelanggan tidak ditemukan untuk idempotency_key ini.'
            );
        }

        return $customer;
    }

    /**
     * @return array{0: Pop, 1: Distribution}
     */
    private function resolveMiniPopAndDistribution(Customer $customer, string $miniPopCode, string $distributionCode): array
    {
        if (in_array($customer->status, self::BLOCKED_STATUSES, true)) {
            throw NetworkAssignmentException::unprocessable(
                'Pelanggan belum masuk tahap pemasangan — Mini POP/Distribusi belum bisa di-assign.'
            );
        }

        $miniPop = Pop::where('pop_code', $miniPopCode)
            ->where('type', 'mini_pop')
            ->where('parent_id', $customer->pop_id)
            ->first();

        if (! $miniPop) {
            throw NetworkAssignmentException::unprocessable(
                'Mini POP yang dipilih tidak valid untuk Cabang POP pelanggan ini.'
            );
        }

        $distribution = Distribution::where('code', $distributionCode)
            ->where('pop_id', $miniPop->id)
            ->first();

        if (! $distribution) {
            throw NetworkAssignmentException::unprocessable(
                'Distribusi yang dipilih tidak sesuai dengan Mini POP yang dipilih.'
            );
        }

        return [$miniPop, $distribution];
    }

    private function applyAssignment(Customer $customer, Pop $miniPop, Distribution $distribution): void
    {
        $customer->mini_pop_id = $miniPop->id;
        $customer->distribution_id = $distribution->id;

        // CID tidak auto-update sendiri — tersimpan statis di customer.cid,
        // bukan dihitung ulang tiap saat. Persis
        // CustomerNetworkAssignmentController::update() baris :118.
        if (in_array($customer->status, ['active', 'suspended'], true)) {
            // Wajib eager-load SEBELUM generateComplexCid() dipanggil —
            // Pop::resolveMiniPopSegment() membaca customer->miniPop dan
            // customer->customerTechnicalDetail, Model::preventLazyLoading()
            // aktif di dev/test (AppServiceProvider) jadi akses lazy di sini
            // akan melempar LazyLoadingViolationException.
            $customer->loadMissing(['pop', 'miniPop', 'customerTechnicalDetail', 'distribution']);

            if ($customer->pop) {
                $customer->cid = $customer->pop->generateComplexCid($customer, $customer->distribution);
            }
        }

        $customer->save();
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $deviceFields
     * @param  array<string, mixed>  $techFields
     */
    private function writeAuditLog(Customer $customer, string $action, array $oldValues, array $deviceFields, array $techFields, string $key, string $hash, string $ip): void
    {
        $newValues = [
            'mini_pop_id' => $customer->mini_pop_id,
            'distribution_id' => $customer->distribution_id,
            'cid' => $customer->cid,
        ];

        // pppoe_password TIDAK PERNAH masuk audit log mentah — sama larangan
        // yang berlaku di CustomerDevice::$auditHidden (jalur staf). Baris
        // audit OTOMATIS dari trait RecordsAuditLogs di CustomerDevice sudah
        // menegakkan ini sendiri (auditHidden mencakup pppoe_password), tapi
        // baris audit KHUSUS endpoint ini (di bawah) dibangun manual dari
        // $deviceFields, jadi guard-nya wajib ditegakkan manual juga di sini.
        foreach ($deviceFields as $fieldKey => $value) {
            $newValues[$fieldKey] = $fieldKey === 'pppoe_password' ? '[diubah]' : $value;
        }

        // Field OLT/VLAN bukan data sensitif — beda kelas dari kredensial,
        // boleh apa adanya (business-logic.md §Audit).
        foreach ($techFields as $fieldKey => $value) {
            $newValues[$fieldKey] = $value;
        }

        AuditLog::create([
            'user_id' => null,
            'module' => 'API Eksternal — Topologi Jaringan',
            'action' => $action,
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => 'API — Website B integration',
            'idempotency_key' => $key,
            'request_hash' => $hash,
            'created_at' => now(),
        ]);
    }

    /**
     * Hash isi body yang sudah dinormalisasi (urutan key diurutkan rekursif)
     * supaya body yang secara isi identik menghasilkan hash sama, apa pun
     * urutan field yang dikirim klien.
     *
     * @param  array<string, mixed>  $validated
     */
    private function hashBody(array $validated): string
    {
        return hash('sha256', json_encode($this->normalize($validated)));
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            ksort($value);

            return array_map(fn ($v) => $this->normalize($v), $value);
        }

        return $value;
    }
}
