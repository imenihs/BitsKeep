<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\SpecGroup;
use App\Models\SpecType;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComponentDetailRouteSmokeTest extends TestCase
{
    use RefreshDatabase;
    /**
     * 目的: setupの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
    /**
     * 目的: 「basic detail route stores image and datasheet」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
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
        $category = SpecGroup::create([
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
    /**
     * 目的: 「attributes route adds custom field」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
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
    /**
     * 目的: 「specs route accepts tolerance value and returns master order」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_specs_route_accepts_tolerance_value_and_returns_master_order(): void
    {
        $user = User::factory()->create(['role' => 'editor']);
        $this->actingAs($user);

        $group = SpecGroup::create([
            'name' => '容量部品',
            'sort_order' => 10,
        ]);
        $capacitance = SpecType::create([
            'name' => '容量',
            'name_ja' => '容量',
            'base_unit' => 'F',
            'spec_scope' => SpecType::SCOPE_GROUP_LOCAL,
            'owner_spec_group_id' => $group->id,
            'spec_kind' => SpecType::KIND_NORMAL,
            'sort_order' => 100,
        ]);
        $tolerance = SpecType::create([
            'name' => '容量許容差',
            'name_ja' => '容量許容差',
            'base_unit' => '%',
            'spec_scope' => SpecType::SCOPE_COMMON,
            'spec_kind' => SpecType::KIND_TOLERANCE,
            'tolerance_settings' => [
                'default_mode' => 'grade',
                'default_unit' => '%',
                'allowed_units' => ['%', 'pF'],
                'grade_options' => [
                    ['label' => 'J', 'value' => 5, 'unit' => '%'],
                    ['label' => 'K', 'value' => 10, 'unit' => '%'],
                ],
            ],
            'sort_order' => 10,
        ]);
        $group->specTypes()->attach([
            $capacitance->id => ['sort_order' => 10],
            $tolerance->id => ['sort_order' => 20],
        ]);

        $component = Component::create([
            'part_number' => 'BK-SPEC-ORDER-' . now()->format('Hisv'),
            'manufacturer' => 'Codex',
            'common_name' => 'スペック並び確認',
            'procurement_status' => 'active',
            'threshold_new' => 0,
            'threshold_used' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $component->categories()->sync([$group->id]);

        $response = $this->patchJson("/api/components/{$component->id}/specs", [
            'specs' => [
                [
                    'spec_type_id' => $tolerance->id,
                    'value_profile' => 'typ',
                    'value_typ' => '5',
                    'unit' => '%',
                ],
                [
                    'spec_type_id' => $capacitance->id,
                    'value_profile' => 'typ',
                    'value_typ' => '10',
                    'unit' => 'uF',
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.specs.0.spec_type_id', $capacitance->id)
            ->assertJsonPath('data.specs.1.spec_type_id', $tolerance->id)
            ->assertJsonPath('data.specs.1.value', '5')
            ->assertJsonPath('data.specs.1.unit', '%')
            ->assertJsonPath('data.specs.1.value_numeric_typ', 5);

        $this->getJson("/api/components/{$component->id}")
            ->assertOk()
            ->assertJsonPath('data.specs.0.spec_type_id', $capacitance->id)
            ->assertJsonPath('data.specs.1.spec_type_id', $tolerance->id);
    }
    /**
     * 目的: 「component detail tolerance value kind uses select surface」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_component_detail_tolerance_value_kind_uses_select_surface(): void
    {
        $detailBlade = file_get_contents(resource_path('views/app/component-detail.blade.php'));
        $createBlade = file_get_contents(resource_path('views/app/component-create.blade.php'));

        foreach ([$detailBlade, $createBlade] as $blade) {
            $this->assertStringContainsString('<select v-if="isToleranceSpecRow(spec)"', $blade);
            $this->assertStringContainsString('aria-label="値種別: 許容差"', $blade);
            $this->assertStringContainsString('<option value="tolerance">許容差</option>', $blade);
        }
        $this->assertStringNotContainsString(
            '<div v-if="isToleranceSpecRow(spec)" class="inline-flex',
            $detailBlade.$createBlade
        );
    }
}
