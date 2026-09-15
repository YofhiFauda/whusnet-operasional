<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu pelanggan yang pertama kali diverifikasi admin
 * (WorkflowTransition ACTIVE), dikelompokkan per `periode` (bulan
 * verifikasi). Dibuat otomatis oleh CustomerObserver — lihat catatan
 * di sana kenapa cuma sekali per pelanggan. Dipakai tim Busdev.
 */
class CustomerAcquisition extends Model
{
    use HasFactory;

    /**
     * PPN buat kolom "Harga Dikurangi PPN" di modul Busdev — SENGAJA
     * hardcode di sini, TERPISAH dari `customers.tax_percent`/
     * `customer_services.ppn` (field pajak asli yang dipakai billing/tagihan
     * sungguhan). User eksplisit: hitungan ini KHUSUS buat kebutuhan Busdev,
     * bukan representasi PPN sistem — kalau tarifnya berubah, ubah di sini,
     * JANGAN disamakan otomatis ke field pajak billing.
     */
    private const BUSDEV_PPN_PERCENT = 11;

    protected $fillable = [
        'customer_id',
        'periode',
        'verified_at',
        'installation_fee',
        'installation_fee_invoice_id',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'installation_fee' => 'decimal:2',
    ];

    protected $appends = [
        'harga_dikurangi_ppn',
        'omset_sales',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Tagihan INSIDENTAL yang diterbitkan begitu Busdev mengisi "Biaya
     * Instalasi" — lihat CustomerAcquisitionController::updateInstallationFee().
     * NULL selama belum diisi.
     */
    public function installationFeeInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'installation_fee_invoice_id');
    }

    /**
     * Biaya Langganan (`customer_services.total_monthly_bill`) dipotong PPN
     * 11% — rumus tetap sesuai contoh tabel Busdev (mis. Rp150.000 →
     * Rp133.500). Dihitung live, BUKAN disimpan: kalau Biaya Langganan
     * berubah (ganti paket dll), angka ini otomatis ikut, gak nyisa data
     * basi di kolom terpisah.
     */
    public function getHargaDikurangiPpnAttribute(): ?float
    {
        $biayaLangganan = $this->customer?->customerService?->total_monthly_bill;

        if ($biayaLangganan === null) {
            return null;
        }

        return round((float) $biayaLangganan * (1 - self::BUSDEV_PPN_PERCENT / 100), 2);
    }

    /**
     * Omset Sales (Skema 2, Dashboard Omset Sales) — BEDA dari
     * `harga_dikurangi_ppn` di atas. Diklarifikasi ulang user 2026-09-12
     * lewat contoh angka: Biaya Langganan Rp150.000 → Omset Sales
     * Rp16.500. Itu NILAI potongan PPN itu sendiri (150.000 × 11%), BUKAN
     * biaya dikurangi PPN (yang mana hasilnya 133.500, beda metrik).
     * `harga_dikurangi_ppn` TETAP dipakai apa adanya di kolom
     * /customer-acquisitions — jangan disatukan lagi, dua kebutuhan
     * beda pemilik (kolom lama = Busdev pantau biaya net, ini = komisi
     * Sales).
     */
    public function getOmsetSalesAttribute(): ?float
    {
        $biayaLangganan = $this->customer?->customerService?->total_monthly_bill;

        if ($biayaLangganan === null) {
            return null;
        }

        return round((float) $biayaLangganan * (self::BUSDEV_PPN_PERCENT / 100), 2);
    }

    /**
     * Kategori paket pelanggan ini (string apa adanya — `internet_packages.
     * category` bukan FK, lihat catatan di PackageCategory). Butuh
     * `customer.customerService.internetPackage` sudah di-eager-load
     * (pola sama getCleanAddressAttribute() di Customer — accessor tidak
     * boleh memicu query tersembunyi).
     */
    public function packageCategory(): ?PackageCategory
    {
        $categoryName = $this->customer?->customerService?->internetPackage?->category;

        if (! $categoryName) {
            return null;
        }

        // Memoized per-request per nama kategori — halaman list bisa
        // punya puluhan baris, tanpa ini tiap baris query PackageCategory
        // sendiri-sendiri padahal jumlah kategori riil cuma segelintir.
        static $cache = [];

        return $cache[$categoryName] ??= PackageCategory::with('installationFeeApprovalRole')->where('name', $categoryName)->first();
    }

    /**
     * Pelanggan ini butuh "Biaya Instalasi" divalidasi Busdev atau tidak —
     * ditentukan dinamis dari kategori paketnya (Master Kategori Paket),
     * BUKAN dari `customers.customer_type` (Perorangan/Bisnis) — dua field
     * itu bisa gak sinkron, sedangkan yang menentukan biaya nyata adalah
     * paket yang dipilih.
     */
    public function needsInstallationFeeValidation(): bool
    {
        return (bool) $this->packageCategory()?->needsInstallationFeeValidation();
    }

    /**
     * Role yang dikonfigurasi admin (Master Kategori Paket) buat validasi
     * "Biaya Instalasi" baris ini — NULL kalau kategori paketnya gak butuh
     * validasi ini sama sekali.
     */
    public function installationFeeApprovalRole(): ?Role
    {
        return $this->packageCategory()?->installationFeeApprovalRole;
    }

    /**
     * Gerbang aksi tulis "Biaya Instalasi" — DUA jalur, biar Master Kategori
     * Paket cukup dipahami admin non-teknis (pilih ROLE) tapi jalur teknis
     * (Role Matrix biasa) tetap tersedia buat admin/owner:
     *   1. User punya permission `customer_acquisitions.installation_fee.update`
     *      (mis. owner via wildcard '*', atau role lain yang sengaja
     *      diberi lewat Role Matrix) — SELALU lolos, apapun role yang
     *      dikonfigurasi di kategori.
     *   2. ATAU role user PERSIS sama dengan yang dipilih admin di Master
     *      Kategori Paket buat kategori paket pelanggan ini.
     */
    public function canBeValidatedBy(User $user): bool
    {
        if ($user->hasPermission('customer_acquisitions.installation_fee.update')) {
            return true;
        }

        $requiredRole = $this->installationFeeApprovalRole();

        return $requiredRole !== null && $user->role_id === $requiredRole->id;
    }
}
