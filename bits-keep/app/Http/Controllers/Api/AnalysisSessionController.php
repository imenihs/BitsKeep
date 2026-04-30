<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AnalysisSession;
use Illuminate\Http\Request;

class AnalysisSessionController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'tool_id' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'component_id' => ['nullable', 'integer', 'exists:components,id'],
        ]);

        $sessions = AnalysisSession::query()
            ->with(['project:id,name', 'component:id,part_number', 'creator:id,name', 'updater:id,name'])
            ->when(isset($validated['tool_id']), fn ($query) => $query->where('tool_id', $validated['tool_id']))
            ->when(isset($validated['project_id']), fn ($query) => $query->where('project_id', $validated['project_id']))
            ->when(isset($validated['component_id']), fn ($query) => $query->where('component_id', $validated['component_id']))
            ->latest()
            ->get();

        return ApiResponse::success($sessions);
    }

    public function store(Request $request)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate($this->rules());
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $session = AnalysisSession::create($validated);

        return ApiResponse::created($this->loadSession($session));
    }

    public function show(AnalysisSession $analysisSession)
    {
        return ApiResponse::success($this->loadSession($analysisSession));
    }

    public function update(Request $request, AnalysisSession $analysisSession)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate($this->rules(true));
        $validated['updated_by'] = $request->user()->id;

        $analysisSession->update($validated);

        return ApiResponse::success($this->loadSession($analysisSession));
    }

    public function destroy(Request $request, AnalysisSession $analysisSession)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $analysisSession->delete();

        return ApiResponse::noContent();
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'tool_id' => [$required, 'string', 'max:255'],
            'title' => [$required, 'string', 'max:255'],
            'verdict' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'input_payload' => ['nullable', 'array'],
            'result_payload' => ['nullable', 'array'],
            'candidate_links' => ['nullable', 'array'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'component_id' => ['nullable', 'integer', 'exists:components,id'],
            'bom_line_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function loadSession(AnalysisSession $session): AnalysisSession
    {
        return $session->load(['project:id,name', 'component:id,part_number', 'creator:id,name', 'updater:id,name']);
    }
}
