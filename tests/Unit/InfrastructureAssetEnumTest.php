<?php

namespace Tests\Unit;

use App\Enums\InventoryTransactionType;
use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InfrastructureAssetEnumTest extends TestCase
{
    #[Test]
    public function every_ownership_mode_case_has_a_label(): void
    {
        foreach (OwnershipMode::cases() as $case) {
            $this->assertNotEmpty($case->label());
        }
    }

    #[Test]
    public function every_serial_status_case_has_a_label_and_badge_variant(): void
    {
        foreach (SerialStatus::cases() as $case) {
            $this->assertNotEmpty($case->label());
            $this->assertNotEmpty($case->badgeVariant());
        }
    }

    #[Test]
    public function every_inventory_transaction_type_case_has_a_label(): void
    {
        foreach (InventoryTransactionType::cases() as $case) {
            $this->assertNotEmpty($case->label());
        }
    }

    #[Test]
    public function infrastructure_asset_ownership_mode_has_expected_value(): void
    {
        $this->assertSame('infrastructure_asset', OwnershipMode::INFRASTRUCTURE_ASSET->value);
    }

    #[Test]
    public function deployed_serial_status_has_expected_value(): void
    {
        $this->assertSame('deployed', SerialStatus::DEPLOYED->value);
    }

    #[Test]
    public function deploy_and_retrieve_infra_transaction_types_have_expected_values(): void
    {
        $this->assertSame('deploy', InventoryTransactionType::DEPLOY->value);
        $this->assertSame('retrieve_infra', InventoryTransactionType::RETRIEVE_INFRA->value);
    }
}
