<?php

namespace Tests\Feature;

use App\Models\SpecGroup;
use App\Models\SpecType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommonSpecScopeTest extends TestCase
{
    private const COMMON_SCOPE_MIGRATION = '2026_04_28_141300_split_common_spec_types_from_spec_groups.php';
    /**
     * 目的: 「common scope migration converts legacy common group members and removes group」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_common_scope_migration_converts_legacy_common_group_members_and_removes_group(): void
    {
        $this->migrateUntilBeforeCommonScopeMigration();

        $now = now();
        $commonGroupId = DB::table('spec_groups')->insertGetId([
            'name' => '共通',
            'description' => 'legacy common group',
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $localGroupId = DB::table('spec_groups')->insertGetId([
            'name' => 'BJT',
            'description' => 'local group',
            'sort_order' => 20,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'トランジスタ',
            'color' => '#38bdf8',
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templateId = DB::table('spec_templates')->insertGetId([
            'spec_group_id' => $commonGroupId,
            'name' => '共通基本',
            'description' => 'legacy common template',
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $knownCommonSpecId = DB::table('spec_types')->insertGetId([
            'name' => '動作温度',
            'name_ja' => '動作温度',
            'base_unit' => 'degC',
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $customCommonSpecId = DB::table('spec_types')->insertGetId([
            'name' => '共通独自項目',
            'name_ja' => '共通独自項目',
            'base_unit' => 'count',
            'sort_order' => 20,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $localSpecId = DB::table('spec_types')->insertGetId([
            'name' => 'コレクタ電流',
            'name_ja' => 'コレクタ電流',
            'base_unit' => 'A',
            'sort_order' => 30,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('category_spec_group')->insert([
            'category_id' => $categoryId,
            'spec_group_id' => $commonGroupId,
            'sort_order' => 10,
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('spec_group_spec_type')->insert([
            [
                'spec_group_id' => $commonGroupId,
                'spec_type_id' => $knownCommonSpecId,
                'sort_order' => 10,
                'is_required' => true,
                'is_recommended' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'spec_group_id' => $commonGroupId,
                'spec_type_id' => $customCommonSpecId,
                'sort_order' => 20,
                'is_required' => false,
                'is_recommended' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'spec_group_id' => $localGroupId,
                'spec_type_id' => $localSpecId,
                'sort_order' => 10,
                'is_required' => true,
                'is_recommended' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->runCommonScopeMigration();

        foreach ([$knownCommonSpecId, $customCommonSpecId] as $specTypeId) {
            $this->assertDatabaseHas('spec_types', [
                'id' => $specTypeId,
                'spec_scope' => SpecType::SCOPE_COMMON,
                'owner_spec_group_id' => null,
            ]);
        }
        $this->assertDatabaseHas('spec_types', [
            'id' => $localSpecId,
            'spec_scope' => SpecType::SCOPE_GROUP_LOCAL,
            'owner_spec_group_id' => $localGroupId,
        ]);
        $this->assertDatabaseMissing('spec_groups', ['id' => $commonGroupId]);
        $this->assertDatabaseMissing('spec_groups', ['name' => '共通']);
        $this->assertDatabaseMissing('spec_group_spec_type', ['spec_group_id' => $commonGroupId]);
        $this->assertDatabaseMissing('category_spec_group', ['spec_group_id' => $commonGroupId]);
        $this->assertDatabaseHas('spec_templates', [
            'id' => $templateId,
            'spec_group_id' => null,
        ]);
    }
    /**
     * 目的: 「spec group and suggestion apis hide common group」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_spec_group_and_suggestion_apis_hide_common_group(): void
    {
        $this->migrateFresh();
        $admin = $this->createAdminUser();

        $commonGroup = SpecGroup::create([
            'name' => '共通',
            'description' => 'should not be selectable',
            'sort_order' => 10,
        ]);
        $localGroup = SpecGroup::create([
            'name' => 'BJT',
            'description' => 'selectable group',
            'sort_order' => 20,
        ]);
        $commonSpec = SpecType::create([
            'name' => '動作温度',
            'name_ja' => '動作温度',
            'spec_scope' => SpecType::SCOPE_COMMON,
            'base_unit' => 'degC',
            'sort_order' => 10,
        ]);
        $localSpec = SpecType::create([
            'name' => 'コレクタ電流',
            'name_ja' => 'コレクタ電流',
            'spec_scope' => SpecType::SCOPE_GROUP_LOCAL,
            'owner_spec_group_id' => $localGroup->id,
            'base_unit' => 'A',
            'sort_order' => 20,
        ]);
        $commonGroup->specTypes()->attach($commonSpec->id, ['sort_order' => 10, 'is_recommended' => true]);
        $localGroup->specTypes()->attach($localSpec->id, ['sort_order' => 10, 'is_recommended' => true]);

        $groupsResponse = $this->actingAs($admin)
            ->getJson('/api/spec-groups?with_spec_types=1')
            ->assertOk()
            ->assertJsonPath('success', true);
        $groupNames = collect($groupsResponse->json('data'))->pluck('name');

        $this->assertTrue($groupNames->contains('BJT'));
        $this->assertFalse($groupNames->contains('共通'));

        $suggestionsResponse = $this->actingAs($admin)
            ->getJson("/api/spec-suggestions?category_ids[]={$localGroup->id}")
            ->assertOk()
            ->assertJsonPath('success', true);
        $suggestedGroupNames = collect($suggestionsResponse->json('data.groups'))->pluck('name');

        $this->assertTrue($suggestedGroupNames->contains('BJT'));
        $this->assertFalse($suggestedGroupNames->contains('共通'));
        $this->assertSame([$localGroup->id], $suggestionsResponse->json('data.recommended_group_ids'));
    }
    /**
     * 目的: 「spec type api keeps common scope ownerless and filters group local specs by owner」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_spec_type_api_keeps_common_scope_ownerless_and_filters_group_local_specs_by_owner(): void
    {
        $this->migrateFresh();
        $admin = $this->createAdminUser();
        $ownerGroup = SpecGroup::create([
            'name' => 'BJT',
            'description' => 'owner group',
            'sort_order' => 10,
        ]);

        $commonResponse = $this->actingAs($admin)->postJson('/api/spec-types', [
            'name' => '動作温度',
            'name_ja' => '動作温度',
            'spec_scope' => SpecType::SCOPE_COMMON,
            'owner_spec_group_id' => $ownerGroup->id,
            'base_unit' => 'degC',
            'sort_order' => 10,
        ]);
        $commonResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.spec_scope', SpecType::SCOPE_COMMON)
            ->assertJsonPath('data.owner_spec_group_id', null);

        $localResponse = $this->actingAs($admin)->postJson('/api/spec-types', [
            'name' => 'コレクタ電流',
            'name_ja' => 'コレクタ電流',
            'spec_scope' => SpecType::SCOPE_GROUP_LOCAL,
            'owner_spec_group_id' => $ownerGroup->id,
            'base_unit' => 'A',
            'sort_order' => 20,
        ]);
        $localResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.spec_scope', SpecType::SCOPE_GROUP_LOCAL)
            ->assertJsonPath('data.owner_spec_group_id', $ownerGroup->id);

        $commonSpecId = $commonResponse->json('data.id');
        $localSpecId = $localResponse->json('data.id');
        $this->assertDatabaseHas('spec_types', [
            'id' => $commonSpecId,
            'spec_scope' => SpecType::SCOPE_COMMON,
            'owner_spec_group_id' => null,
        ]);
        $this->assertDatabaseHas('spec_group_spec_type', [
            'spec_group_id' => $ownerGroup->id,
            'spec_type_id' => $localSpecId,
        ]);

        $commonList = $this->actingAs($admin)
            ->getJson('/api/spec-types?summary=1&scope=common')
            ->assertOk()
            ->json('data');
        $localList = $this->actingAs($admin)
            ->getJson("/api/spec-types?summary=1&scope=group_local&owner_spec_group_id={$ownerGroup->id}")
            ->assertOk()
            ->json('data');

        $this->assertContains($commonSpecId, collect($commonList)->pluck('id'));
        $this->assertNotContains($localSpecId, collect($commonList)->pluck('id'));
        $this->assertContains($localSpecId, collect($localList)->pluck('id'));
        $this->assertNotContains($commonSpecId, collect($localList)->pluck('id'));
    }
    /**
     * 目的: migratefreshの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
    private function migrateFresh(): void
    {
        $this->artisan('migrate:fresh')->assertSuccessful();
    }
    /**
     * 目的: migrateuntilbeforecommonscopemigrationの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
    private function migrateUntilBeforeCommonScopeMigration(): void
    {
        $paths = collect(glob(database_path('migrations/*.php')) ?: [])
            ->filter( fn (string $path) => basename($path) < self::COMMON_SCOPE_MIGRATION)
            ->values()
            ->all();

        $this->artisan('migrate:fresh', [
            '--path' => $paths,
            '--realpath' => true,
        ])->assertSuccessful();
    }
    /**
     * 目的: runcommonscopemigrationの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
    private function runCommonScopeMigration(): void
    {
        $this->artisan('migrate', [
            '--path' => [database_path('migrations/'.self::COMMON_SCOPE_MIGRATION)],
            '--realpath' => true,
        ])->assertSuccessful();
    }
    /**
     * 目的: createadminuserの仕様を検証する。
     * 機能: HTTP/API/画面構造/DB状態をアサーションで固定する。
     * 入力: なし。
     * 出力: なし。
     * 動作条件: テスト用DBと認証/権限fixtureが準備されていること。
     * 副作用: テストDB、HTTPセッション、モック、アサーション状態を利用する。
     */
    private function createAdminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
