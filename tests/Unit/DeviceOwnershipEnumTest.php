<?php

namespace Tests\Unit;

use App\Enums\DeviceAcquisitionSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DeviceOwnershipEnumTest extends TestCase
{
    #[Test]
    public function label_dan_badge_variant_sesuai_spec(): void
    {
        $this->assertSame('Sewa', DeviceAcquisitionSource::SEWA->label());
        $this->assertSame('Beli Sendiri', DeviceAcquisitionSource::BELI_SENDIRI->label());
        $this->assertSame('Beli dari Gudang', DeviceAcquisitionSource::BELI_GUDANG->label());
        $this->assertSame('info', DeviceAcquisitionSource::SEWA->badgeVariant());
        $this->assertSame('warning', DeviceAcquisitionSource::BELI_SENDIRI->badgeVariant());
        $this->assertSame('success', DeviceAcquisitionSource::BELI_GUDANG->badgeVariant());
    }
}
