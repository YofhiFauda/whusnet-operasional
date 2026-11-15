<?php

namespace Tests\Unit;

use App\Console\Commands\Concerns\ParsesLegacySqlDump;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Regresi 2026-11-10: alamat `'luworo rt;09 rw;02'` di caru_db (PE000070)
 * memotong statement INSERT `pengguna` dan 121 pelanggan sesudahnya hilang
 * dari migrasi tanpa error. Pacitan kena hal sama (PE000237, 116 pelanggan).
 */
class LegacySqlDumpTitikKomaDalamStringTest extends TestCase
{
    private function parse(string $sql, string $table): array
    {
        $parser = new class
        {
            use ParsesLegacySqlDump;

            public function run(string $sql, string $table): array
            {
                return $this->parseTableData($sql, $table);
            }
        };

        return $parser->run($sql, $table);
    }

    #[Test]
    public function titik_koma_di_dalam_string_tidak_memotong_baris_sesudahnya(): void
    {
        $sql = <<<'SQL'
INSERT INTO `pengguna` (`IDPENGGUNA`, `ALMT`) VALUES
('PE000070', 'luworo rt;09 rw;02 pilangkenceng'),
('PE000071', 'ds.luworo rt,11 rw 02'),
('PE000072', 'Luworo rt 9,rw 02');
SQL;

        $rows = $this->parse($sql, 'pengguna');

        $this->assertSame(['PE000070', 'PE000071', 'PE000072'], array_column($rows, 'IDPENGGUNA'));
        $this->assertSame('luworo rt;09 rw;02 pilangkenceng', $rows[0]['ALMT']);
    }

    #[Test]
    public function pemisah_tuple_di_dalam_string_tidak_memecah_baris(): void
    {
        $sql = "INSERT INTO `t` (`A`, `B`) VALUES ('1', 'x), (y'), ('2', 'z');";

        $rows = $this->parse($sql, 't');

        $this->assertCount(2, $rows);
        $this->assertSame('x), (y', $rows[0]['B']);
        $this->assertSame('2', $rows[1]['A']);
    }

    #[Test]
    public function escape_kutip_dan_baris_baru_didekode_bukan_jadi_huruf_rn(): void
    {
        $sql = "INSERT INTO `t` (`A`, `B`, `C`) VALUES ('Jum\\'at', 'O''Neil', 'Kabel 150\\r\\nRouter 1');";

        $rows = $this->parse($sql, 't');

        $this->assertSame("Jum'at", $rows[0]['A']);
        $this->assertSame("O'Neil", $rows[0]['B']);
        $this->assertSame("Kabel 150\r\nRouter 1", $rows[0]['C']);
    }

    #[Test]
    public function beberapa_statement_insert_tabel_sama_digabung_dan_tabel_lain_diabaikan(): void
    {
        $sql = <<<'SQL'
INSERT INTO `t` (`A`) VALUES ('1'), ('2');
INSERT INTO `t_lain` (`A`) VALUES ('99');
INSERT INTO `t` (`A`) VALUES ('3');
SQL;

        $this->assertSame(['1', '2', '3'], array_column($this->parse($sql, 't'), 'A'));
    }

    #[Test]
    public function null_tanpa_kutip_dan_string_null_jadi_null(): void
    {
        $rows = $this->parse("INSERT INTO `t` (`A`, `B`, `C`, `D`) VALUES (NULL, 'null', 0, '');", 't');

        $this->assertNull($rows[0]['A']);
        $this->assertNull($rows[0]['B']);
        $this->assertSame('0', $rows[0]['C']);
        $this->assertSame('', $rows[0]['D']);
    }

    #[Test]
    public function jumlah_nilai_tidak_sama_kolom_gagal_keras_bukan_diam_diam(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parse("INSERT INTO `t` (`A`, `B`) VALUES ('1', '2', '3');", 't');
    }

    #[Test]
    public function statement_tanpa_penutup_gagal_keras(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parse("INSERT INTO `t` (`A`) VALUES ('1'), ('2", 't');
    }
}
