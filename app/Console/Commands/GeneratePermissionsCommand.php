<?php

namespace App\Console\Commands;

use App\Services\PermissionGeneratorService;
use Illuminate\Console\Command;

class GeneratePermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rbac:generate-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate feature-action based permissions dynamically';

    /**
     * Execute the console command.
     */
    public function handle(PermissionGeneratorService $service)
    {
        $this->info('Starting permission generation...');

        try {
            $summary = $service->generate();

            $this->info("Generation completed.");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Features Processed', $summary['total_features_processed']],
                    ['Permissions Created', $summary['permissions_created']],
                    ['Permissions Skipped (Exist)', $summary['permissions_skipped']],
                ]
            );

            if (!empty($summary['errors'])) {
                $this->error("Encountered " . count($summary['errors']) . " errors:");
                foreach ($summary['errors'] as $error) {
                    $this->line(" - $error");
                }
                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to generate permissions: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
