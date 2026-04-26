<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Category;
use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComponentDetailRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_basic_detail_route_stores_image_and_datasheet(): void
    {
        $user = User::factory()->create(['role' => 'editor']);
        $this->actingAs($user);
        $group = PackageGroup::create([
            'name' => 'TEST-GROUP-' . now()->format('Hisv'),
            'sort_order' => 10,
        ]);
        $package = Package::create([
            'package_group_id' => $group->id,
            'name' => 'TEST-PKG-' . now()->format('Hisv'),
            'sort_order' => 10,
        ]);
        $category = Category::create([
            'name' => 'TEST-CAT-' . now()->format('Hisv'),
            'sort_order' => 10,
        ]);
        $component = Component::create([
            'part_number' => 'BK-DETAIL-ROUTE-' . now()->format('Hisv'),
            'manufacturer' => 'Codex',
            'common_name' => '詳細編集保存確認',
            'description' => 'detail route smoke',
            'procurement_status' => 'active',
            'threshold_new' => 0,
            'threshold_used' => 0,
            'package_id' => $package->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $component->categories()->sync([$category->id]);

        $storedPaths = [];

        try {
            $response = $this->post("/api/components/{$component->id}", [
                '_method' => 'PUT',
                'part_number' => $component->part_number,
                'manufacturer' => $component->manufacturer,
                'common_name' => $component->common_name,
                'description' => $component->description,
                'procurement_status' => $component->procurement_status,
                'threshold_new' => $component->threshold_new,
                'threshold_used' => $component->threshold_used,
                'primary_location_id' => $component->primary_location_id,
                'category_ids' => [$category->id],
                'package_group_id' => $group->id,
                'package_id' => $component->package_id,
                'image' => UploadedFile::fake()->image('detail-basic.png', 50, 50),
                'datasheets' => [
                    UploadedFile::fake()->create('detail-basic.pdf', 32, 'application/pdf'),
                ],
            ]);

            $response->assertOk()->assertJsonPath('success', true);

            $component->refresh()->load('datasheets');
            $this->assertNotNull($component->image_path);
            $this->assertNotNull($component->datasheet_path);
            $this->assertTrue(Storage::disk('public')->exists($component->image_path));
            $this->assertTrue(Storage::disk('public')->exists($component->datasheet_path));
            $this->get('/files/public/' . $component->image_path)->assertOk();
            $this->get('/files/public/' . $component->datasheet_path)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            $storedPaths[] = $component->image_path;
            $storedPaths[] = $component->datasheet_path;
        } finally {
            foreach (array_filter($storedPaths) as $path) {
                Storage::disk('public')->delete($path);
            }
            $component->datasheets()->delete();
            $component->categories()->detach();
            $component->forceDelete();
            $package->forceDelete();
            $group->forceDelete();
            $category->forceDelete();
        }
    }

    public function test_attributes_route_adds_custom_field(): void
    {
        $user = User::factory()->create(['role' => 'editor']);
        $this->actingAs($user);
        $component = Component::create([
            'part_number' => 'BK-ATTR-ROUTE-' . now()->format('Hisv'),
            'manufacturer' => 'Codex',
            'common_name' => '属性追加確認',
            'procurement_status' => 'active',
            'threshold_new' => 0,
            'threshold_used' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->patchJson("/api/components/{$component->id}/attributes", [
            'attributes' => [
                ['key' => '動作確認属性', 'value' => '追加確認'],
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $component->refresh()->load('customAttributes');
        $this->assertSame('動作確認属性', $component->customAttributes->last()?->key);
        $this->assertSame('追加確認', $component->customAttributes->last()?->value);
        $component->customAttributes()->delete();
        $component->forceDelete();
    }
}
