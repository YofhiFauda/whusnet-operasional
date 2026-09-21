<?php

namespace App\Services;

use App\Enums\OwnershipMode;
use App\Enums\TrackingType;
use App\Models\Customer;
use App\Models\Item;

/**
 * Petunjuk perangkat dari data lama pelanggan (ADHOC-88) — BANTUAN untuk
 * teknisi/staf gudang, bukan sumber kebenaran. Migrasi legacy hanya menyimpan
 * SN di `customer_technical_details.router_or_ont_serial`, dan (kalau ada aset
 * sewa) merek sebagai teks bebas di `note`:
 *   "Perangkat: ZTE F609 (dari data aset migrasi)"
 *
 * Label itu berantakan (di data lokal: `ZTE F609`, `ZTE f660`, `router GPON`,
 * `ZTE`, bahkan `1`), jadi pemetaan ke master barang SENGAJA ketat: hanya
 * kalau tepat SATU barang ber-SN yang cocok. Label generik/ambigu/sampah tidak
 * dipetakan — pemakainya tetap memilih model sendiri atau "Modem Legacy".
 */
class LegacyDeviceHintService
{
    /**
     * Label lebih pendek dari ini terlalu generik untuk dipetakan ("ZTE", "1").
     */
    private const MIN_LABEL_LENGTH = 5;

    /**
     * @return array{serial: ?string, label: ?string, item: ?Item}
     */
    public function forCustomer(Customer $customer): array
    {
        $detail = $customer->customerTechnicalDetail;

        $serial = trim((string) $detail?->router_or_ont_serial);
        $label = $this->extractLabel((string) $detail?->note);

        return [
            'serial' => $serial !== '' ? $serial : null,
            'label' => $label,
            'item' => $label !== null ? $this->matchItem($label) : null,
        ];
    }

    private function extractLabel(string $note): ?string
    {
        if (! preg_match('/Perangkat: (.*?) \(dari data aset migrasi\)/u', $note, $matches)) {
            return null;
        }

        $label = trim($matches[1]);

        return $label !== '' ? $label : null;
    }

    /**
     * Cocok kalau label (dinormalisasi: huruf kecil, tanpa spasi/tanda baca)
     * terkandung di nama ATAU kode barang, dan hasilnya tepat satu.
     */
    private function matchItem(string $label): ?Item
    {
        $needle = $this->normalize($label);

        if (mb_strlen($needle) < self::MIN_LABEL_LENGTH) {
            return null;
        }

        $matches = Item::active()
            ->where('tracking_type', TrackingType::SERIALIZED->value)
            ->where('ownership_mode', OwnershipMode::INSTALLABLE->value)
            ->get()
            ->filter(fn (Item $item) => str_contains($this->normalize($item->name), $needle)
                || str_contains($this->normalize($item->code), $needle));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function normalize(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($value));
    }
}
