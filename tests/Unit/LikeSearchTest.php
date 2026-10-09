<?php

namespace Tests\Unit;

use App\Support\LikeSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LikeSearchTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function masukanPencarian(): array
    {
        return [
            'wildcard persen dibuang' => ['%%%', ''],
            'underscore dibuang' => ['_', ''],
            'nama dengan wildcard' => ['Budi_ %', 'Budi'],
            'backslash dibuang' => ['a\\b', 'ab'],
            'nama biasa tidak berubah' => ['Masudah Yuni', 'Masudah Yuni'],
            'cid biasa tidak berubah' => ['C1X4ARQ000631', 'C1X4ARQ000631'],
        ];
    }

    #[Test]
    #[DataProvider('masukanPencarian')]
    public function membersihkan_wildcard_sql_dari_masukan(string $masukan, string $hasil): void
    {
        $this->assertSame($hasil, LikeSearch::sanitize($masukan));
    }
}
