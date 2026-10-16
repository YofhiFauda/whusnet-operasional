<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Services\InfrastructureMapService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InfrastructureMapController extends Controller
{
    public function __construct(private readonly InfrastructureMapService $map) {}

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'pop_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:topology,utilization,free'],
            'q' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);
        $data = $this->map->build(
            $request->user(),
            isset($filters['pop_id']) ? (int) $filters['pop_id'] : null,
            $filters['sort'] ?? 'topology'
        );
        $search = mb_strtolower(trim($filters['q'] ?? ''));
        $type = $filters['type'] ?? 'all';

        return response()->streamDownload(function () use ($data, $search, $type): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }

            fputcsv($stream, ['Node', 'Nama node', 'Tipe', 'Lokasi', 'Port terpakai', 'Total port', 'Status', 'Barang', 'Jenis', 'SN / Lot', 'Jumlah', 'Tanggal pasang', 'Teknisi', 'Task']);
            foreach ($data['inventoryNodes'] as $node) {
                $index = $data['nodeIndex'][$node->id];
                if (($type !== 'all' && $index['type'] !== $type)
                    || ($search !== '' && ! str_contains($index['search'], $search))) {
                    continue;
                }

                $assets = $data['assetsByNode'][$node->id] ?: [[]];
                foreach ($assets as $asset) {
                    fputcsv($stream, [
                        $node->code,
                        $node->name,
                        $node->nodeType?->name,
                        $data['pathsByNode'][$node->id],
                        $node->used_ports_count,
                        $node->ports_count,
                        $node->status,
                        $asset['name'] ?? '',
                        $asset['kind'] ?? '',
                        $asset['reference'] ?? '',
                        $asset['amount'] ?? '',
                        $asset['installed_at'] ?? '',
                        $asset['technician'] ?? '',
                        $asset['task'] ?? '',
                    ]);
                }
            }
            fclose($stream);
        }, 'inventaris-infrastruktur-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'pop_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:topology,utilization,free'],
        ]);

        return view('network.map.index', $this->map->build(
            $request->user(),
            isset($filters['pop_id']) ? (int) $filters['pop_id'] : null,
            $filters['sort'] ?? 'topology'
        ));
    }
}
