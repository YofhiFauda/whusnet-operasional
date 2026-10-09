<?php

namespace Tests\Feature;

use App\Enums\ItemCondition;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Analisa UI/UX gudang Fase 5 (§U4/V6) — badge kondisi & status disatukan jadi
 * satu komponen dengan warna dari design token .badge-*. Test mengunci
 * pemetaan varian dan label standar supaya tidak lagi di-hardcode beda-beda.
 */
class WarehouseBadgeTest extends TestCase
{
    #[Test]
    #[DataProvider('kondisiVariants')]
    public function item_condition_badge_variant(ItemCondition $condition, string $expected): void
    {
        $this->assertSame($expected, $condition->badgeVariant());
    }

    public static function kondisiVariants(): array
    {
        return [
            'baru' => [ItemCondition::NEW, 'success'],
            'bekas baik' => [ItemCondition::USED_GOOD, 'info'],
            'bekas rusak' => [ItemCondition::USED_DAMAGED, 'error'],
        ];
    }

    #[Test]
    #[DataProvider('serialVariants')]
    public function serial_status_badge_variant(SerialStatus $status, string $expected): void
    {
        $this->assertSame($expected, $status->badgeVariant());
    }

    public static function serialVariants(): array
    {
        return [
            'available' => [SerialStatus::AVAILABLE, 'success'],
            'issued' => [SerialStatus::ISSUED, 'info'],
            'transferred' => [SerialStatus::TRANSFERRED, 'warning'],
            'quarantine' => [SerialStatus::QUARANTINE, 'error'],
            'scrapped' => [SerialStatus::SCRAPPED, 'error'],
        ];
    }

    #[Test]
    public function setiap_serial_status_punya_variant_valid(): void
    {
        $valid = ['success', 'info', 'warning', 'error', 'neutral'];
        foreach (SerialStatus::cases() as $status) {
            $this->assertContains($status->badgeVariant(), $valid, "SerialStatus {$status->value} punya variant tak dikenal");
        }
        foreach (RollStatus::cases() as $status) {
            $this->assertContains($status->badgeVariant(), $valid, "RollStatus {$status->value} punya variant tak dikenal");
        }
    }

    #[Test]
    public function condition_badge_baru_render_label_dan_kelas_success(): void
    {
        $html = Blade::render('<x-warehouse.condition-badge :condition="$c" :checked="true" />', [
            'c' => ItemCondition::NEW,
        ]);

        $this->assertStringContainsString('Baru', $html);
        $this->assertStringContainsString('badge-success', $html);
    }

    #[Test]
    public function condition_badge_bekas_belum_dicek_render_warning(): void
    {
        $html = Blade::render('<x-warehouse.condition-badge :condition="$c" :checked="false" />', [
            'c' => ItemCondition::USED_GOOD,
        ]);

        $this->assertStringContainsString('Belum Dicek', $html);
        $this->assertStringContainsString('badge-warning', $html);
    }

    #[Test]
    public function condition_badge_bekas_sudah_dicek_render_info(): void
    {
        $html = Blade::render('<x-warehouse.condition-badge :condition="$c" :checked="true" />', [
            'c' => ItemCondition::USED_GOOD,
        ]);

        $this->assertStringContainsString('Sudah Dicek', $html);
        $this->assertStringContainsString('badge-info', $html);
    }

    #[Test]
    public function condition_badge_rusak_render_error(): void
    {
        $html = Blade::render('<x-warehouse.condition-badge :condition="$c" />', [
            'c' => ItemCondition::USED_DAMAGED,
        ]);

        $this->assertStringContainsString('Rusak', $html);
        $this->assertStringContainsString('badge-error', $html);
    }

    #[Test]
    public function condition_badge_null_render_tidak_dilacak_neutral(): void
    {
        $html = Blade::render('<x-warehouse.condition-badge :condition="null" />');

        $this->assertStringContainsString('Tidak dilacak', $html);
        $this->assertStringContainsString('badge-neutral', $html);
    }

    #[Test]
    public function status_badge_render_label_dan_variant_dari_enum(): void
    {
        $html = Blade::render('<x-warehouse.status-badge :status="$s" />', [
            's' => SerialStatus::QUARANTINE,
        ]);

        $this->assertStringContainsString('Karantina', $html);
        $this->assertStringContainsString('badge-error', $html);
    }

    #[Test]
    public function status_badge_string_mentah_jatuh_ke_neutral(): void
    {
        $html = Blade::render('<x-warehouse.status-badge status="legacy_unknown" />');

        $this->assertStringContainsString('legacy_unknown', $html);
        $this->assertStringContainsString('badge-neutral', $html);
    }
}
