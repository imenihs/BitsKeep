<?php

namespace Tests\Feature;

use App\Models\AnalysisSession;
use App\Models\Component;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_store_show_update_filter_and_delete_analysis_session(): void
    {
        $editor = User::factory()->create([
            'role' => 'editor',
            'is_active' => true,
        ]);
        $otherEditor = User::factory()->create([
            'role' => 'editor',
            'is_active' => true,
        ]);
        $project = Project::create([
            'name' => 'Analyzer Project',
            'created_by' => $editor->id,
        ]);
        $component = Component::create([
            'part_number' => 'ANL-001',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        AnalysisSession::create([
            'tool_id' => 'other-tool',
            'title' => 'Unrelated',
            'project_id' => $project->id,
            'component_id' => $component->id,
            'created_by' => $otherEditor->id,
            'updated_by' => $otherEditor->id,
        ]);

        $createResponse = $this->actingAs($editor)->postJson('/api/analysis-sessions', [
            'tool_id' => 'bom-review',
            'title' => 'BOM Review',
            'verdict' => 'warning',
            'summary' => 'Two candidate substitutions found.',
            'input_payload' => ['bom_line' => ['mpn' => 'ANL-001']],
            'result_payload' => ['risk_score' => 72],
            'candidate_links' => [['label' => 'Candidate A', 'component_id' => $component->id]],
            'project_id' => $project->id,
            'component_id' => $component->id,
            'bom_line_key' => 'line-001',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tool_id', 'bom-review')
            ->assertJsonPath('data.input_payload.bom_line.mpn', 'ANL-001')
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.component.id', $component->id)
            ->assertJsonPath('data.creator.id', $editor->id);

        $sessionId = $createResponse->json('data.id');
        $this->assertDatabaseHas('analysis_sessions', [
            'id' => $sessionId,
            'tool_id' => 'bom-review',
            'title' => 'BOM Review',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $this->actingAs($editor)->getJson("/api/analysis-sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.result_payload.risk_score', 72)
            ->assertJsonPath('data.candidate_links.0.label', 'Candidate A');

        $this->actingAs($editor)->getJson("/api/analysis-sessions?tool_id=bom-review&project_id={$project->id}&component_id={$component->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sessionId);

        $this->actingAs($otherEditor)->putJson("/api/analysis-sessions/{$sessionId}", [
            'title' => 'BOM Review Updated',
            'verdict' => 'pass',
            'summary' => 'Candidates accepted.',
            'result_payload' => ['risk_score' => 12],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'BOM Review Updated')
            ->assertJsonPath('data.updated_by', $otherEditor->id)
            ->assertJsonPath('data.updater.id', $otherEditor->id);

        $this->assertDatabaseHas('analysis_sessions', [
            'id' => $sessionId,
            'title' => 'BOM Review Updated',
            'updated_by' => $otherEditor->id,
        ]);

        $this->actingAs($editor)->deleteJson("/api/analysis-sessions/{$sessionId}")
            ->assertNoContent();

        $this->assertSoftDeleted('analysis_sessions', [
            'id' => $sessionId,
        ]);
    }

    public function test_non_editor_cannot_write_analysis_sessions(): void
    {
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
        ]);
        $editor = User::factory()->create([
            'role' => 'editor',
            'is_active' => true,
        ]);
        $session = AnalysisSession::create([
            'tool_id' => 'bom-review',
            'title' => 'Existing',
            'created_by' => $editor->id,
            'updated_by' => $editor->id,
        ]);

        $this->actingAs($viewer)->postJson('/api/analysis-sessions', [
            'tool_id' => 'bom-review',
            'title' => 'Denied',
        ])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->actingAs($viewer)->putJson("/api/analysis-sessions/{$session->id}", [
            'title' => 'Denied',
        ])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->actingAs($viewer)->deleteJson("/api/analysis-sessions/{$session->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('analysis_sessions', [
            'id' => $session->id,
            'title' => 'Existing',
            'deleted_at' => null,
        ]);
    }
}
