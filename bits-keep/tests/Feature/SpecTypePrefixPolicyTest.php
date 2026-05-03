<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecTypePrefixPolicyTest extends TestCase
{
    use RefreshDatabase;
    /**
     * 目的: 「spec type rejects unknown prefix candidate」の仕様を検証する。
     * 機能: 入力、APIレスポンス、永続化結果をアサーションで固定する。
     * 入力: なし。
     * 出力: 検証結果をPHPUnitアサーションへ渡す。
     * 動作条件: RefreshDatabaseまたはテスト用設定で実行されること。
     * 副作用: テストDB、HTTPセッション、モック状態を利用する。
     */
    public function test_spec_type_rejects_unknown_prefix_candidate(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/spec-types', [
                'name' => 'Unknown Prefix Voltage',
                'name_ja' => '未知接頭語電圧',
                'value_type' => 'numeric',
                'unit' => 'V',
                'suggest_prefixes' => ['k', 'bad'],
                'spec_scope' => 'common',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('suggest_prefixes');
    }
}
