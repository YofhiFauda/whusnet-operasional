<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Pop;
use App\Models\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerReportController extends Controller
{
    /**
     * Display the customer report page with filters.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Pengecekan permission: harus punya salah satu
        if (! $user->hasPermission('reports.view')) {
            abort(403, 'Unauthorized action.');
        }

        // Ambil data filter
        $popId = $request->query('pop_id', '');
        $completenessStatus = $request->query('completeness_status', '');
        $status = $request->query('status', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');

        // POP yang bisa diakses user
        $pops = Pop::forUser()->orderBy('name')->get();
        $allowedPopIds = $pops->pluck('id')->toArray();

        // Jika user memfilter POP tertentu, pastikan POP itu ada di dalam POP yang diizinkan untuknya
        if ($popId !== '') {
            if (! in_array((int) $popId, $allowedPopIds)) {
                $popId = '';
            }
        }

        // Query customers menggunakan scope forUser
        $query = Customer::with(['pop', 'internetPackage', 'subscriptionStatus'])
            ->applyUserScope();

        // Menerapkan filters
        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($completenessStatus !== '') {
            $query->where('data_completeness_status', $completenessStatus);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($startDate !== '') {
            // whereDate() membungkus kolom jadi DATE(registration_date) dan
            // mematikan index. Batasnya ditulis eksplisit startOfDay/endOfDay
            // karena sqlite (dipakai test) menyimpan kolom date sebagai
            // '2026-07-22 00:00:00' — `<= '2026-07-22'` di sana akan membuang
            // seluruh isi hari terakhir tanpa suara.
            $query->where('registration_date', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate !== '') {
            $query->where('registration_date', '<=', Carbon::parse($endDate)->endOfDay());
        }

        // Urutkan berdasarkan tanggal registrasi terbaru
        $customers = $query->orderByDesc('registration_date')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // Ambil list status langganan dinamis
        $statuses = SubscriptionStatus::all();

        return view('reports.customers.index', compact(
            'customers',
            'pops',
            'statuses',
            'popId',
            'completenessStatus',
            'status',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Export customer report to CSV stream.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();
        if (! $user->hasPermission('reports.view')) {
            abort(403, 'Unauthorized action.');
        }

        $popId = $request->query('pop_id', '');
        $completenessStatus = $request->query('completeness_status', '');
        $status = $request->query('status', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');

        // Pastikan input pop_id divalidasi dengan POP yang diizinkan untuk user ini
        $allowedPopIds = Pop::forUser()->pluck('id')->toArray();
        if ($popId !== '') {
            if (! in_array((int) $popId, $allowedPopIds)) {
                abort(403, 'Unauthorized action.');
            }
        }

        // Query data tanpa pagination
        $query = Customer::with(['pop', 'internetPackage', 'subscriptionStatus'])
            ->applyUserScope();

        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($completenessStatus !== '') {
            $query->where('data_completeness_status', $completenessStatus);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($startDate !== '') {
            // whereDate() membungkus kolom jadi DATE(registration_date) dan
            // mematikan index. Batasnya ditulis eksplisit startOfDay/endOfDay
            // karena sqlite (dipakai test) menyimpan kolom date sebagai
            // '2026-07-22 00:00:00' — `<= '2026-07-22'` di sana akan membuang
            // seluruh isi hari terakhir tanpa suara.
            $query->where('registration_date', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate !== '') {
            $query->where('registration_date', '<=', Carbon::parse($endDate)->endOfDay());
        }

        // Query dieksekusi di dalam closure stream pakai `lazy()` — lihat alasan
        // yang sama di InvoiceReportController::export().
        // `id` ditambahkan sebagai tiebreak: registration_date + created_at tidak
        // unik, dan lazy() memotong hasil per-chunk pakai offset. Tanpa urutan
        // yang deterministik, baris bisa terlewat atau kebagi dua antar chunk.
        $query->orderByDesc('registration_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-pelanggan-'.now()->format('YmdHis').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for proper Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Kolom Header
            fputcsv($file, [
                'Kode Pelanggan',
                'ID Pelanggan Lama',
                'CID',
                'Nama Lengkap',
                'No. HP',
                'Gender',
                'Email',
                'POP/Cabang',
                'Paket Internet',
                'Kecepatan Download',
                'Kecepatan Upload',
                'Kelengkapan Data',
                'Status Pelanggan',
                'Tanggal Registrasi',
            ]);

            foreach ($query->lazy(500) as $customer) {
                fputcsv($file, [
                    $customer->customer_code ?? '-',
                    $customer->old_customer_id ?? '-',
                    $customer->cid ?? '-',
                    $customer->full_name,
                    $customer->primary_phone ?? '-',
                    $customer->gender?->label() ?? '-',
                    $customer->email ?? '-',
                    $customer->pop->name ?? '-',
                    $customer->internetPackage->name ?? '-',
                    $customer->internetPackage ? ($customer->internetPackage->download_speed_mbps.' Mbps') : '-',
                    $customer->internetPackage ? ($customer->internetPackage->upload_speed_mbps.' Mbps') : '-',
                    ucfirst(str_replace('_', ' ', $customer->data_completeness_status)),
                    $customer->subscriptionStatus->name ?? ucfirst(str_replace('_', ' ', $customer->status)),
                    $customer->registration_date ? $customer->registration_date->format('Y-m-d') : '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export data pelanggan ke XLSX (format Excel asli, bukan CSV). Filter dan
     * scope sama persis dengan laporan pelanggan. Kolom mengikuti daftar
     * "Export data pelanggan menjadi excel" — `exportHeaderRow()`/`exportDataRow()`.
     */
    public function exportXlsx(Request $request)
    {
        $query = $this->exportXlsxQuery($request);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'data-pelanggan-'.uniqid().'.xlsx';
        $writer = SimpleExcelWriter::create($path);

        $header = $this->exportHeaderRow();

        $query->chunkById(500, function ($customers) use ($writer, $header) {
            $writer->addRows($customers->map(fn (Customer $customer) => array_combine(
                $header,
                $this->exportDataRow($customer)
            ))->all());
        });

        $writer->close();

        return response()->download($path, 'data-pelanggan-'.now()->format('Ymd-His').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * @return Builder<Customer>
     */
    private function exportXlsxQuery(Request $request): Builder
    {
        $user = auth()->user();
        if (! $user->hasPermission('reports.view')) {
            abort(403, 'Unauthorized action.');
        }

        $popId = $request->query('pop_id', '');
        $completenessStatus = $request->query('completeness_status', '');
        $status = $request->query('status', '');
        $startDate = $request->query('start_date', '');
        $endDate = $request->query('end_date', '');

        $allowedPopIds = Pop::forUser()->pluck('id')->toArray();
        if ($popId !== '' && ! in_array((int) $popId, $allowedPopIds)) {
            abort(403, 'Unauthorized action.');
        }

        $query = Customer::with(['pop', 'internetPackage', 'customerService', 'salesUser', 'agent'])
            ->applyUserScope();

        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        if ($completenessStatus !== '') {
            $query->where('data_completeness_status', $completenessStatus);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        // Batas tanggal sengaja startOfDay/endOfDay — alasannya sama dengan export CSV di atas.
        if ($startDate !== '') {
            $query->where('registration_date', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate !== '') {
            $query->where('registration_date', '<=', Carbon::parse($endDate)->endOfDay());
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function exportHeaderRow(): array
    {
        return [
            'ID Pelanggan',
            'Nama Lengkap',
            'Nomor Identitas (NIK KTP)',
            'Jenis Kelamin',
            'Nomor HP Utama (WhatsApp)',
            'Nomor HP Alternatif',
            'NPWP',
            'Alamat Email',
            'Tanggal Registrasi',
            'POP Cabang',
            'Alamat Instalasi Lengkap',
            'Latitude',
            'Longitude',
            'Paket Internet',
            'Jenis Kontrak',
            'Masa Kontrak (Bulan)',
            'Diskon Promosi (Rp)',
            'ID Sales/ID Agent',
        ];
    }

    /**
     * ID pelanggan untuk kolom A. Satu sumber aturan: `Pop::resolveDisplayId()`
     * (dulu disalin di sini, lalu tidak sinkron kalau aturan berubah). Tanpa POP,
     * tidak ada prefix maupun pemotongan REQ ID, jadi pakai kode mentah.
     */
    private function exportCustomerId(Customer $customer): string
    {
        return $customer->pop
            ? $customer->pop->resolveDisplayId($customer)
            : (string) $customer->customer_code;
    }

    /**
     * @return list<string|float|int>
     */
    private function exportDataRow(Customer $customer): array
    {
        $salesOrAgent = array_filter([
            $customer->salesUser?->name,
            $customer->agent?->code,
        ]);

        return [
            $this->exportCustomerId($customer),
            $customer->full_name,
            $customer->identity_number ?? '-',
            $customer->gender?->label() ?? '-',
            $customer->primary_phone ?? '-',
            $customer->alternative_phone ?? '-',
            $customer->npwp ?? '-',
            $customer->email ?? '-',
            $customer->registration_date?->format('Y-m-d') ?? '-',
            $customer->pop?->name ?? '-',
            $customer->address ?? '-',
            $customer->latitude ?? '-',
            $customer->longitude ?? '-',
            $customer->internetPackage?->name ?? '-',
            $customer->customerService?->contract_type ?? '-',
            $customer->contract_period_months ?? '-',
            (int) ($customer->discount_amount ?? 0),
            $salesOrAgent ? implode(' / ', $salesOrAgent) : '-',
        ];
    }
}
