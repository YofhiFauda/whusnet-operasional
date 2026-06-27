<?php

namespace Tests\Feature\Services;

use App\Enums\ActionCode;
use App\Models\Action;
use App\Models\Feature;
use App\Models\Permission;
use App\Services\PermissionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PermissionGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed features and actions manually for test
        $feature = Feature::create([
            'code' => 'test_feature',
            'name' => 'Test Feature',
            'is_active' => true,
            'type' => \App\Enums\FeatureType::ROOT,
            'sort_order' => 1
        ]);

        Action::create([
            'code' => ActionCode::VIEW->value,
            'name' => 'View',
            'description' => 'View Data'
        ]);

        Action::create([
            'code' => ActionCode::CREATE->value,
            'name' => 'Create',
            'description' => 'Create Data'
        ]);
        
        // Override config
        Config::set('rbac.allowed_actions', [
            'test_feature' => [
                ActionCode::VIEW->value,
                ActionCode::CREATE->value,
            ],
            'invalid_feature' => [
                ActionCode::VIEW->value
            ]
        ]);
    }

    public function test_generate_permissions_successfully()
    {
        $service = new PermissionGeneratorService();
        $summary = $service->generate();

        $this->assertEquals(1, $summary['total_features_processed']);
        $this->assertEquals(2, $summary['permissions_created']);
        $this->assertEquals(0, $summary['permissions_skipped']);
        $this->assertCount(1, $summary['errors']); // invalid_feature not found

        $this->assertDatabaseHas('permissions', [
            'code' => 'test_feature.view'
        ]);
        
        $this->assertDatabaseHas('permissions', [
            'code' => 'test_feature.create'
        ]);
    }

    public function test_generator_is_idempotent()
    {
        $service = new PermissionGeneratorService();
        
        // First run
        $service->generate();
        $this->assertEquals(2, Permission::count());

        // Second run
        $summary = $service->generate();
        $this->assertEquals(2, Permission::count());
        $this->assertEquals(0, $summary['permissions_created']);
        $this->assertEquals(2, $summary['permissions_skipped']);
    }
}
