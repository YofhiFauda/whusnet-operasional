<?php

namespace App\Http\Controllers;

use App\Models\PeriodClosing;
use App\Services\CollectorMonthlyReportService as Report;
use App\Support\BookPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan Bulanan Admin Collector (ADHOC-90). Tipis: validasi request, cek
 * scope, delegasi ke CollectorMonthlyReportService (semua rumus di sana).
 */
class CollectorMonthlyReportController extends Controller
{
    public function index(Request $request, Report $service): View
    {
        [$period, $popId] = $this->filters($request);

        $branches = $service->branchesFor($request->user());
        $visible = $popId ? $branches->where('id', $popId) : $branches;
        $report = $service->report($period, $visible);

        return view('reports.collector-monthly.index', [
            'period' => $period,
            'popId' => $popId,
            'branches' => $branches,
            'rows' => $report['rows'],
            'totals' => $report['totals'],
            'periodLabel' => $this->monthLabel($period),
            'previousLabel' => $this->monthLabel(Report::parsePeriod($period)->subMonth()->format('Y-m')),
            // Tutup buku otomatis saat bulan berganti — tidak ada tombol
            // tutup/buka ulang (BookPeriod).
            'locked' => BookPeriod::isLocked($period),
            'canExport' => $request->user()->hasPermission('collector_report.export'),
            // Rincian per sel (drill-down) — dipakai JS buat tahu kolom mana
            // yang boleh diklik, dan endpoint-nya (query string ditambah
            // client-side dari data-* yang SUDAH ditentukan server, bukan
            // input bebas — sama seperti filter GET reports lain di repo ini).
            'detailable' => Report::detailableColumns(),
            'detailUrl' => route('reports.collector-monthly.detail'),
            'detailExportUrl' => route('reports.collector-monthly.detail-export'),
        ]);
    }

    public function export(Request $request, Report $service): BinaryFileResponse
    {
        [$period, $popId] = $this->filters($request);

        $branches = $service->branchesFor($request->user());

        // pop_id di luar scope → 403, bukan diam-diam dikosongkan.
        abort_if($popId && ! $branches->contains('id', $popId), 403, 'Anda tidak memiliki akses ke POP ini.');

        $report = $service->report($period, $popId ? $branches->where('id', $popId) : $branches);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laporan-admin-collector-'.uniqid().'.xlsx';
        $writer = SimpleExcelWriter::create($path);
        $writer->nameCurrentSheet('Laporan '.$period);

        $this->writeSheet($writer, $report, $period);

        $writer->close();

        return response()->download($path, 'laporan-admin-collector-'.$period.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Rincian baris di balik satu sel angka ("siapa saja yang sudah
     * membayar", dst.) — dipanggil modal via fetch. SELALU live dari DB
     * (lihat docblock `CollectorMonthlyReportService::detail()`), jadi kalau
     * POP-nya sudah ditutup & ada drift, tetap dikembalikan tapi diberi
     * tanda `frozen_differs` supaya modal bisa memperingatkan.
     */
    public function detail(Request $request, Report $service): JsonResponse
    {
        [$rows, $validated, $isClosed] = $this->resolveDetail($request, $service);

        // Klasifikasi (ADHOC-84 §8.2) dipetakan ke label/badge di sini —
        // enum PHP tak berguna langsung buat JS, dan pemetaan warnanya harus
        // satu tempat (PaymentPeriodType), bukan diketik ulang di Blade/JS.
        $rows = array_map(function (array $row) {
            $row['jenis'] = array_map(fn ($label) => [
                'label' => $label->label(),
                'badge_class' => $label->badgeClass(),
            ], $row['jenis'] ?? []);

            return $row;
        }, $rows);

        return response()->json([
            'rows' => $rows,
            'total' => array_sum(array_column($rows, 'nominal')),
            'count' => count($rows),
            // Angka di tabel laporan bisa dibaca dari snapshot beku; rincian
            // di sini selalu live — beri tahu kalau POP-nya sudah ditutup
            // supaya admin tidak bingung kalau jumlahnya tak persis sama.
            'from_closed_period' => $isClosed,
        ]);
    }

    /**
     * Unduh rincian satu sel sebagai XLSX — daftar tagih/kejar untuk audit
     * atau follow-up kolektor, bukan cuma dilihat di modal. Repo ini pakai
     * Excel, bukan CSV, konsisten dengan `export()` di atas dan seluruh
     * laporan lain. Permission sama dengan tombol Export laporan
     * (`collector_report.export`), bukan izin baru — mengunduh sebagian
     * data yang sama, bukan data lain.
     */
    public function detailExport(Request $request, Report $service): BinaryFileResponse
    {
        [$rows, $validated] = $this->resolveDetail($request, $service);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rincian-laporan-'.uniqid().'.xlsx';
        $writer = SimpleExcelWriter::create($path);
        $writer->nameCurrentSheet('Rincian');
        $writer->addHeader(['Pelanggan', 'Akun', 'Referensi', 'Tanggal', 'Nominal', 'Keterangan']);

        foreach ($rows as $row) {
            $writer->addRow([$row['pelanggan'], $row['akun'], $row['referensi'], $row['tanggal'], $row['nominal'], $row['keterangan']]);
        }

        $writer->close();

        $filename = "rincian-{$validated['block']}-{$validated['column']}-{$validated['period']}.xlsx";

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return array{0: list<array{pelanggan: string, akun: string, referensi: string, tanggal: ?string, nominal: float, keterangan: string}>, 1: array{period: string, pop_id: string, block: string, column: string}, 2: bool}
     */
    private function resolveDetail(Request $request, Report $service): array
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'pop_id' => ['required', 'integer'],
            'block' => ['required', 'string'],
            'column' => ['required', 'string'],
        ]);

        $branches = $service->branchesFor($request->user(), (int) $validated['pop_id']);
        abort_if($branches->isEmpty(), 403, 'Anda tidak memiliki akses ke POP ini.');

        try {
            $rows = $service->detail($validated['period'], (int) $validated['pop_id'], $validated['block'], $validated['column']);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $frozen = PeriodClosing::query()
            ->where('period', $validated['period'])
            ->where('pop_id', (int) $validated['pop_id'])
            ->exists();

        return [$rows, $validated, $frozen];
    }

    private function monthLabel(string $period): string
    {
        return Report::parsePeriod($period)->translatedFormat('F Y');
    }

    /**
     * @return array{0: string, 1: ?int}
     */
    private function filters(Request $request): array
    {
        $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'pop_id' => ['nullable', 'integer'],
        ]);

        return [
            (string) ($request->input('period') ?: now()->format('Y-m')),
            $request->filled('pop_id') ? (int) $request->input('pop_id') : null,
        ];
    }

    /**
     * Empat blok bertumpuk dalam satu sheet, urutan & judul kolom mengikuti
     * template Excel (`docs/plan/billing/LAPORAN ADMIN COLLECTOR 2026_DIAH.xlsx`).
     *
     * @param  array{rows: list<array<string, mixed>>, totals: array<string, array<string, float|int>>}  $report
     */
    private function writeSheet(SimpleExcelWriter $writer, array $report, string $period): void
    {
        $border = new Border(
            new BorderPart(Border::TOP, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
        );
        $title = (new Style)->setFontBold()->setFontSize(14);
        $section = (new Style)->setFontBold();
        $head = (new Style)->setFontBold()->setBackgroundColor('8EA9DB')->setBorder($border)->setCellAlignment(CellAlignment::CENTER);
        $cell = (new Style)->setBorder($border)->setFormat('#,##0')->setCellAlignment(CellAlignment::RIGHT);
        $text = (new Style)->setBorder($border);
        $pct = (new Style)->setBorder($border)->setFormat('0.0"%"')->setCellAlignment(CellAlignment::RIGHT);
        $total = (new Style)->setFontBold()->setBackgroundColor('C6E0B4')->setBorder($border)->setFormat('#,##0')->setCellAlignment(CellAlignment::RIGHT);
        $totalText = (new Style)->setFontBold()->setBackgroundColor('C6E0B4')->setBorder($border);
        $totalPct = (new Style)->setFontBold()->setBackgroundColor('C6E0B4')->setBorder($border)->setFormat('0.0"%"')->setCellAlignment(CellAlignment::RIGHT);

        $add = fn (array $values, ?Style $style = null) => $writer->addRow(Row::fromValues($values, $style));
        $addStyled = fn (array $values, array $styles) => $writer->addRow(Row::fromValuesWithStyles($values, null, $styles));

        $bulan = $this->monthLabel($period);
        $lalu = $this->monthLabel(Report::parsePeriod($period)->subMonth()->format('Y-m'));

        $add(['LAPORAN ADMIN COLLECTOR — '.mb_strtoupper($bulan)], $title);
        $add(['']);

        // Tiap blok: [judul blok, header kolom, fn(figures)→nilai kolom, jenis kolom (t=teks n=angka p=persen), footer piutang?]
        $blocks = [
            [
                'TAGIHAN '.mb_strtoupper($bulan),
                ['No', 'OLT', 'Tagihan Terbit', 'Dimuka', 'Diskon', 'Bulanan', 'Total Pembayaran', 'Piutang', 'Pendapatan %', 'Piutang %'],
                fn (array $f) => [
                    $f['tagihan']['tagihan_terbit'], $f['tagihan']['dimuka'], $f['tagihan']['diskon'], $f['tagihan']['bulanan'],
                    $f['tagihan']['total_pembayaran'], $f['tagihan']['piutang'],
                    Report::percentage($f['tagihan']['total_pembayaran'], $f['tagihan']['tagihan_terbit']),
                    Report::percentage($f['tagihan']['piutang'], $f['tagihan']['tagihan_terbit']),
                ],
                'nnnnnnpp',
            ],
            [
                'PIUTANG BULAN LALU (s.d. '.mb_strtoupper($lalu).')',
                ['No', 'OLT', 'Piutang Bulan Lalu', 'Sudah Dibayar', 'Belum Dibayar', 'Sudah Dibayar %', 'Belum Dibayar %', 'Piutang tak Tertagih', 'Sisa Piutang'],
                fn (array $f) => [
                    $f['piutang_lalu']['pembuka'], $f['piutang_lalu']['sudah_dibayar'], $f['piutang_lalu']['belum_dibayar'],
                    Report::percentage($f['piutang_lalu']['sudah_dibayar'], $f['piutang_lalu']['pembuka']),
                    Report::percentage($f['piutang_lalu']['belum_dibayar'], $f['piutang_lalu']['pembuka']),
                    $f['piutang_lalu']['tak_tertagih'],
                    max(0, $f['piutang_lalu']['belum_dibayar'] - $f['piutang_lalu']['tak_tertagih']),
                ],
                'nnnppnn',
            ],
            [
                'PELANGGAN ('.mb_strtoupper($bulan).')',
                ['No', 'OLT', 'Total Pelanggan', 'Bayar Dimuka', 'Sudah Bayar', 'Belum Bayar'],
                fn (array $f) => [$f['pelanggan']['total'], $f['pelanggan']['dimuka'], $f['pelanggan']['sudah_bayar'], $f['pelanggan']['belum_bayar']],
                'nnnn',
            ],
            [
                'UANG DITERIMA '.mb_strtoupper($bulan),
                ['No', 'OLT', 'Bulanan', 'Piutang', 'Lebih Bayar', 'Aktivasi', 'Lainnya', 'Dikembalikan', 'Total Uang Diterima'],
                fn (array $f) => [
                    $f['uang_diterima']['bulanan'], $f['uang_diterima']['piutang'], $f['uang_diterima']['lebih_bayar'],
                    $f['uang_diterima']['aktivasi'], $f['uang_diterima']['lainnya'],
                    // Snapshot sebelum kolom ini ada tidak punya kuncinya.
                    -($f['uang_diterima']['dikembalikan'] ?? 0),
                    $f['uang_diterima']['total'],
                ],
                'nnnnnnn',
            ],
        ];

        foreach ($blocks as [$judul, $header, $values, $kinds]) {
            $add([$judul], $section);
            $add($header, $head);

            foreach ($report['rows'] as $i => $row) {
                $addStyled(
                    [$i + 1, $row['pop']->name, ...$values($row['figures'])],
                    $this->rowStyles($kinds, $text, $cell, $pct),
                );
            }

            $addStyled(
                ['', 'Total', ...$values($report['totals'])],
                $this->rowStyles($kinds, $totalText, $total, $totalPct),
            );
            $add(['']);
        }

        $options = $writer->getWriter()->getOptions();
        $options->setColumnWidth(6, 1);
        $options->setColumnWidth(22, 2);
        $options->setColumnWidth(18, 3, 4, 5, 6, 7, 8, 9, 10);
    }

    /**
     * @return array<int, Style>
     */
    private function rowStyles(string $kinds, Style $text, Style $number, Style $percent): array
    {
        $styles = [0 => $text, 1 => $text];

        foreach (str_split($kinds) as $i => $kind) {
            $styles[$i + 2] = match ($kind) {
                'p' => $percent,
                default => $number,
            };
        }

        return $styles;
    }
}
