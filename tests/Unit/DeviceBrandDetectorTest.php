<?php

namespace Tests\Unit;

use App\Support\DeviceBrandDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeviceBrandDetectorTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function uaProvider(): array
    {
        return [
            'xiaomi' => ['Mozilla/5.0 (Linux; Android 13; Redmi Note 12) Chrome/120.0', 'Xiaomi/Redmi/POCO'],
            'poco' => ['Mozilla/5.0 (Linux; Android 13; POCO X5) Chrome/120.0', 'Xiaomi/Redmi/POCO'],
            'oppo cph' => ['Mozilla/5.0 (Linux; Android 13; CPH2501) Chrome/120.0', 'OPPO/realme'],
            'realme rmx' => ['Mozilla/5.0 (Linux; Android 13; RMX3630) Chrome/120.0', 'OPPO/realme'],
            'vivo' => ['Mozilla/5.0 (Linux; Android 13; vivo V29) Chrome/120.0', 'vivo'],
            'samsung sm-' => ['Mozilla/5.0 (Linux; Android 13; SM-A135F) Chrome/120.0', 'Samsung'],
            'iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1', 'iPhone/iPad'],
            'unknown brand' => ['Mozilla/5.0 (X11; Linux x86_64) Chrome/120.0', 'Lainnya'],
            'empty' => [null, 'Tidak diketahui'],
        ];
    }

    #[Test]
    #[DataProvider('uaProvider')]
    public function detects_brand_from_user_agent(?string $ua, string $expected): void
    {
        $this->assertSame($expected, DeviceBrandDetector::detect($ua));
    }
}
