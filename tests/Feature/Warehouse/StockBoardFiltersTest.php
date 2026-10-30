<?php

namespace Tests\Feature\Warehouse;

use App\Support\BoardFilters;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StockBoardFiltersTest extends TestCase
{
    #[Test]
    public function from_request_menormalkan_input(): void
    {
        $filters = BoardFilters::fromRequest(Request::create('/', 'GET', [
            'category_id' => 'abc', 'tracking' => 'sembarang', 'search' => 'a%b', 'low_stock_only' => '1',
        ]), 5);

        $this->assertSame(5, $filters->popId);
        $this->assertNull($filters->categoryId);
        $this->assertNull($filters->tracking);
        $this->assertSame('ab', $filters->search);
        $this->assertTrue($filters->lowOnly);

        $valid = BoardFilters::fromRequest(Request::create('/', 'GET', ['category_id' => '7', 'tracking' => 'roll']), null);
        $this->assertSame(7, $valid->categoryId);
        $this->assertSame('roll', $valid->tracking);
        $this->assertFalse($valid->lowOnly);
    }

    #[Test]
    public function search_berbentuk_array_diabaikan_bukan_error(): void
    {
        $filters = BoardFilters::fromRequest(Request::create('/', 'GET', ['search' => ['x']]), null);

        $this->assertSame('', $filters->search);
    }

    #[Test]
    public function is_filtering_false_bila_hanya_pop_dipilih(): void
    {
        $this->assertFalse((new BoardFilters(3, null, null, '', false))->isFiltering());
        $this->assertTrue((new BoardFilters(null, null, null, 'x', false))->isFiltering());
        $this->assertTrue((new BoardFilters(null, null, null, '', true))->isFiltering());
    }
}
