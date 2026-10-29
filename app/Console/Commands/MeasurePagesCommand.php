<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

#[Signature('perf:pages
    {--uri= : (internal) ukur SATU uri di proses ini}
    {--user= : Email user pengukur (default: Owner pertama)}
    {--memory=512M : memory_limit tiap halaman, samakan dengan produksi}
    {--timeout=120 : Detik maksimum per halaman}
    {--slow=1500 : Batas ms sebelum halaman ditandai LAMBAT}
    {--only= : Ukur hanya URI yang memuat teks ini}
    {--with-samples : Ikutkan route ber-parameter (mis. customer, invoice) dengan id contoh dari DB}
    {--include-exports : Ikutkan route export/download/print (default dilewati)}
    {--list : Cuma tampilkan daftar URI yang akan diukur}
    {--json= : Simpan hasil ke file JSON (default: storage/app/perf/pages-WAKTU.json)}')]
#[Description('Ukur waktu, memori puncak, jumlah query & model Eloquent tiap halaman GET (satu proses per halaman, OOM tertangkap).')]
class MeasurePagesCommand extends Command
{
    private const RESULT_MARKER = 'PERF_RESULT:';

    /** SQL identik berulang sebanyak ini dalam satu request = kandidat N+1. */
    private const REPEAT_MIN = 20;

    /** SELECT selambat ini (ms) di-EXPLAIN untuk cari full scan / filesort. */
    private const EXPLAIN_MIN_MS = 50;

    /** Prefix/URI yang bukan halaman aplikasi, atau efek sampingnya tidak aman untuk diukur massal. */
    private const SKIP_PREFIXES = ['api/', '_', 'horizon', 'telescope', 'broadcasting', 'sanctum', 'storage', 'livewire', 'docs', 'login', 'logout', 'register', 'password', 'portal', 'staff-portal'];

    private const EXPORT_HINTS = ['export', 'download', 'print', 'pdf', 'excel', 'csv', 'template', 'qr/'];

    /**
     * Nama parameter route → tabel contoh. Nama lain dicoba lewat plural snake_case.
     *
     * @var array<string, string>
     */
    private const SAMPLE_TABLES = [
        'fopTask' => 'fop_tasks',
        'fop_task' => 'fop_tasks',
        'node' => 'network_nodes',
        'networkNode' => 'network_nodes',
        'bucket' => '',
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Ditolak: jangan jalankan pengukur massal di produksi.');

            return self::FAILURE;
        }

        if ($this->option('uri')) {
            return $this->measureOne((string) $this->option('uri'));
        }

        $targets = $this->targets();

        if ($this->option('list')) {
            foreach ($targets as $target) {
                $this->line($target['uri']);
            }
            $this->info(count($targets).' halaman.');

            return self::SUCCESS;
        }

        return $this->measureAll($targets);
    }

    // ───────────────────────── mode induk ─────────────────────────

    /** @param  list<array{uri: string, route: string}>  $targets */
    private function measureAll(array $targets): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            $this->error('User pengukur tidak ditemukan (butuh role owner, atau pakai --user=email).');

            return self::FAILURE;
        }

        $this->info('Mengukur '.count($targets)." halaman sebagai {$user->email} (memory_limit {$this->option('memory')}) …");
        $results = [];
        $bar = $this->output->createProgressBar(count($targets));

        foreach ($targets as $target) {
            $results[] = $this->runChild($target, $user->email);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->report($results);

        $path = $this->option('json') ?: storage_path('app/perf/pages-'.now()->format('Ymd-His').'.json');
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Hasil lengkap: {$path}");

        return self::SUCCESS;
    }

    /**
     * @param  array{uri: string, route: string}  $target
     * @return array<string, mixed>
     */
    private function runChild(array $target, string $email): array
    {
        $process = new Process([
            PHP_BINARY, '-d', 'memory_limit='.$this->option('memory'), base_path('artisan'), 'perf:pages',
            '--uri='.$target['uri'], '--user='.$email, '--no-interaction',
        ], base_path(), null, null, (float) $this->option('timeout'));

        $started = microtime(true);

        try {
            $process->run();
        } catch (Throwable $e) {
            return $this->failure($target, 'TIMEOUT', (int) ((microtime(true) - $started) * 1000), $e->getMessage());
        }

        $output = $process->getOutput().$process->getErrorOutput();

        if (preg_match('/'.self::RESULT_MARKER.'(\{.*\})/', $output, $m) === 1) {
            $result = json_decode($m[1], true);
            if (is_array($result)) {
                return $result + ['route' => $target['route']];
            }
        }

        // Tidak ada baris hasil = proses mati sebelum selesai: OOM, fatal, atau exception tak tertangkap.
        $status = str_contains($output, 'Allowed memory size') ? 'OOM' : 'ERROR';

        return $this->failure($target, $status, (int) ((microtime(true) - $started) * 1000), Str::limit(trim(Str::afterLast(trim($output), "\n")), 160));
    }

    /**
     * @param  array{uri: string, route: string}  $target
     * @return array<string, mixed>
     */
    private function failure(array $target, string $status, int $ms, string $note): array
    {
        return ['uri' => $target['uri'], 'route' => $target['route'], 'status' => $status, 'ms' => $ms, 'peak_mb' => null, 'queries' => null, 'models' => null, 'sql_ms' => null, 'slowest' => [], 'note' => $note];
    }

    /** @param  list<array<string, mixed>>  $results */
    private function report(array $results): void
    {
        $slow = (int) $this->option('slow');
        $rows = collect($results)->map(function (array $r) use ($slow) {
            $flags = [];
            if (in_array($r['status'], ['OOM', 'TIMEOUT', 'ERROR'], true)) {
                $flags[] = $r['status'];
            }
            if ($r['ms'] >= $slow) {
                $flags[] = 'LAMBAT';
            }
            if (($r['peak_mb'] ?? 0) >= 128) {
                $flags[] = 'MEMORI';
            }
            if (($r['queries'] ?? 0) >= 100) {
                $flags[] = 'N+1?';
            }
            if (($r['models'] ?? 0) >= 5000) {
                $flags[] = 'MUAT-SEMUA?';
            }

            return $r + ['flags' => implode(' ', $flags)];
        });

        $bad = $rows->filter(fn ($r) => $r['flags'] !== '')->sortByDesc(fn ($r) => in_array($r['status'], ['OOM', 'TIMEOUT'], true) ? PHP_INT_MAX : $r['ms'])->values();

        $this->table(
            ['URI', 'Status', 'ms', 'Memori MB', 'Query', 'Model', 'Tanda'],
            $bad->map(fn ($r) => [Str::limit($r['uri'], 60), $r['status'], $r['ms'], $r['peak_mb'] ?? '-', $r['queries'] ?? '-', $r['models'] ?? '-', $r['flags']])->all(),
        );

        $this->info(sprintf('%d halaman diukur: %d bermasalah, %d OOM, %d timeout, %d error, sisanya wajar.', $rows->count(), $bad->count(), $rows->where('status', 'OOM')->count(), $rows->where('status', 'TIMEOUT')->count(), $rows->where('status', 'ERROR')->count()));

        foreach ($bad->take(10) as $r) {
            foreach (array_slice($r['slowest'] ?? [], 0, 1) as $q) {
                $this->line("  {$r['uri']} → {$q['ms']} ms: {$q['sql']}");
            }
        }

        $this->newLine();
        $this->info('N+1 (SQL identik berulang ≥ '.self::REPEAT_MIN.'x):');
        foreach ($bad as $r) {
            foreach (array_slice($r['repeated'] ?? [], 0, 1) as $q) {
                $this->line("  {$r['uri']} → {$q['count']}x: {$q['sql']}");
            }
        }

        $this->newLine();
        $this->info('Kandidat index (EXPLAIN SELECT ≥ '.self::EXPLAIN_MIN_MS.' ms):');
        foreach ($rows as $r) {
            foreach ($r['explain'] ?? [] as $e) {
                $this->line("  {$r['uri']} → {$e['ms']} ms [".implode('; ', $e['problems'])."]: {$e['sql']}");
            }
        }
    }

    // ───────────────────────── mode anak ─────────────────────────

    private function measureOne(string $uri): int
    {
        $user = $this->resolveUser();
        if (! $user) {
            $this->error('User tidak ditemukan.');

            return self::FAILURE;
        }

        $queries = 0;
        $sqlMs = 0.0;
        $slowest = [];
        $models = 0;

        $signatures = [];
        $rawSlow = [];

        DB::listen(function ($query) use (&$queries, &$sqlMs, &$slowest, &$signatures, &$rawSlow) {
            $queries++;
            $sqlMs += $query->time;
            $slowest[] = ['ms' => round($query->time, 1), 'sql' => Str::limit(preg_replace('/\s+/', ' ', $query->sql), 140)];
            usort($slowest, fn ($a, $b) => $b['ms'] <=> $a['ms']);
            $slowest = array_slice($slowest, 0, 3);

            // Tanda tangan SQL: kueri sama berulang dengan binding beda = kandidat N+1.
            $sig = self::signature($query->sql);
            $signatures[$sig] = ($signatures[$sig] ?? 0) + 1;

            // Simpan SQL mentah + binding cuma untuk SELECT lambat; di-EXPLAIN setelah request selesai.
            if ($query->time >= self::EXPLAIN_MIN_MS && str_starts_with(ltrim(strtolower($query->sql)), 'select')) {
                $rawSlow[] = ['ms' => round($query->time, 1), 'sql' => $query->sql, 'bindings' => $query->bindings];
                usort($rawSlow, fn ($a, $b) => $b['ms'] <=> $a['ms']);
                $rawSlow = array_slice($rawSlow, 0, 3);
            }
        });
        // Jumlah model yang dihidrasi — penanda paling jelas halaman yang "memuat semua baris".
        Event::listen('eloquent.retrieved:*', function () use (&$models) {
            $models++;
        });

        Auth::guard('web')->setUser($user);

        $kernel = app(Kernel::class);
        $started = microtime(true);
        $request = Request::create('/'.ltrim($uri, '/'), 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);

        try {
            $response = $kernel->handle($request);
            $status = (string) $response->getStatusCode();
            $note = $response->isRedirection() ? 'redirect → '.$response->headers->get('Location') : '';
            if ($response->isServerError()) {
                $note = 'HTTP 5xx';
            }
        } catch (Throwable $e) {
            $status = 'ERROR';
            $note = Str::limit(class_basename($e).': '.$e->getMessage(), 160);
        }

        arsort($signatures);
        $repeated = [];
        foreach (array_slice($signatures, 0, 3, true) as $sig => $count) {
            if ($count >= self::REPEAT_MIN) {
                $repeated[] = ['count' => $count, 'sql' => Str::limit($sig, 140)];
            }
        }

        $this->line(self::RESULT_MARKER.json_encode([
            'uri' => $uri,
            'status' => $status,
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'queries' => $queries,
            'models' => $models,
            'sql_ms' => (int) round($sqlMs),
            'slowest' => $slowest,
            'repeated' => $repeated,
            'explain' => $this->explainSlow($rawSlow),
            'note' => $note,
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /** SQL dinormalisasi: literal/angka/daftar IN dilebur supaya kueri identik berbeda-binding jatuh ke satu kunci. */
    public static function signature(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', $sql);
        $sql = preg_replace('/\bin \([^)]*\)/i', 'in (…)', $sql);
        $sql = preg_replace("/'[^']*'/", '?', $sql);

        return preg_replace('/\b\d+\b/', '?', $sql);
    }

    /**
     * EXPLAIN tiap SELECT lambat; tandai full scan, filesort, temporary table.
     *
     * @param  list<array{ms: float, sql: string, bindings: array<int, mixed>}>  $queries
     * @return list<array<string, mixed>>
     */
    private function explainSlow(array $queries): array
    {
        $out = [];
        foreach ($queries as $q) {
            try {
                $plan = DB::select('explain '.$q['sql'], $q['bindings']);
            } catch (Throwable) {
                continue; // driver tanpa EXPLAIN gaya MySQL (sqlite di test) — lewati
            }

            $problems = [];
            foreach ($plan as $row) {
                $row = (array) $row;
                $table = $row['table'] ?? '?';
                if (($row['type'] ?? '') === 'ALL') {
                    $problems[] = "full scan `{$table}` (~".($row['rows'] ?? '?').' baris)';
                }
                if (str_contains((string) ($row['Extra'] ?? ''), 'filesort')) {
                    $problems[] = "filesort `{$table}`";
                }
                if (str_contains((string) ($row['Extra'] ?? ''), 'temporary')) {
                    $problems[] = "temporary table `{$table}`";
                }
            }

            if ($problems !== []) {
                $out[] = ['ms' => $q['ms'], 'sql' => Str::limit(preg_replace('/\s+/', ' ', $q['sql']), 140), 'problems' => $problems];
            }
        }

        return $out;
    }

    // ───────────────────────── daftar halaman ─────────────────────────

    /** @return list<array{uri: string, route: string}> */
    private function targets(): array
    {
        $only = (string) $this->option('only');
        $targets = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            /** @var RoutingRoute $route */
            if (! in_array('GET', $route->methods(), true) || $this->shouldSkip($route)) {
                continue;
            }

            $uri = $this->resolveUri($route);
            if ($uri === null || ($only !== '' && ! str_contains($uri, $only))) {
                continue;
            }

            $targets[$uri] = ['uri' => $uri, 'route' => (string) ($route->getName() ?? $route->uri())];
        }

        ksort($targets);

        return array_values($targets);
    }

    private function shouldSkip(RoutingRoute $route): bool
    {
        $uri = ltrim($route->uri(), '/');

        if ($uri === '') {
            return false;
        }

        // `up` (health check) dicocokkan persis — sebagai prefix ia ikut menelan `update-…`, `upload-…`.
        if ($uri === 'up') {
            return true;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }

        // Hanya halaman ber-sesi staf: route di luar middleware `auth` (portal pelanggan, token publik) bukan target ukur.
        if (! in_array('auth', $route->gatherMiddleware(), true)) {
            return true;
        }

        if (! $this->option('include-exports')) {
            foreach (self::EXPORT_HINTS as $hint) {
                if (str_contains($uri, $hint) || str_contains((string) $route->getName(), $hint)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** URI siap-panggil, atau null kalau ada parameter wajib yang tidak bisa diisi contoh. */
    private function resolveUri(RoutingRoute $route): ?string
    {
        $uri = '/'.ltrim($route->uri(), '/');
        $uri = preg_replace('#/\{[^}]+\?\}#', '', $uri);

        if (! preg_match_all('/\{(\w+)\}/', $uri, $matches)) {
            return $uri;
        }

        if (! $this->option('with-samples')) {
            return null;
        }

        foreach ($matches[1] as $parameter) {
            $sample = $this->sampleId($parameter);
            if ($sample === null) {
                return null;
            }
            $uri = str_replace('{'.$parameter.'}', (string) $sample, $uri);
        }

        return $uri;
    }

    private function sampleId(string $parameter): int|string|null
    {
        $table = self::SAMPLE_TABLES[$parameter] ?? Str::plural(Str::snake($parameter));

        if ($table === '' || ! Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->orderBy('id')->value('id');
    }

    private function resolveUser(): ?User
    {
        $email = (string) $this->option('user');

        return $email !== ''
            ? User::where('email', $email)->first()
            : User::whereHas('role', fn ($q) => $q->where('code', 'owner'))->orderBy('id')->first();
    }
}
