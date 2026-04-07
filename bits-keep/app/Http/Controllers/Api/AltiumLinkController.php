<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AltiumLibrary;
use App\Models\Component;
use App\Models\ComponentAltiumLink;
use Illuminate\Http\Request;

/**
 * Altium連携 API（SCR-014）
 * ライブラリ管理 + 部品リンク管理
 */
class AltiumLinkController extends Controller
{
    // ── ライブラリ管理 ────────────────────────────────────

    // GET /api/altium/libraries
    public function libraries()
    {
        $libs = AltiumLibrary::orderBy('type')->orderBy('name')->get();
        return ApiResponse::success($libs);
    }

    // POST /api/altium/libraries
    public function storeLibrary(Request $request)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:SchLib,PcbLib'],
            'path' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string'],
        ]);

        $lib = AltiumLibrary::create($validated);
        return ApiResponse::created($lib);
    }

    // PUT /api/altium/libraries/{library}
    public function updateLibrary(Request $request, AltiumLibrary $library)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:SchLib,PcbLib'],
            'path' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string'],
        ]);

        $library->update($validated);
        return ApiResponse::success($library);
    }

    // DELETE /api/altium/libraries/{library}
    public function destroyLibrary(Request $request, AltiumLibrary $library)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        // 紐づきリンクのフィールドをNULLにしてから削除（migration で nullOnDelete 指定済み）
        $library->delete();
        return ApiResponse::noContent();
    }

    // ── 部品リンク ─────────────────────────────────────────

    // GET /api/components/{component}/altium-link
    public function show(Component $component)
    {
        $link = $component->altiumLink?->load(['schLibrary', 'pcbLibrary']);
        return ApiResponse::success($link);
    }

    // PUT /api/components/{component}/altium-link
    public function upsert(Request $request, Component $component)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'sch_library_id' => ['nullable', 'exists:altium_libraries,id'],
            'sch_symbol'     => ['nullable', 'string', 'max:255'],
            'pcb_library_id' => ['nullable', 'exists:altium_libraries,id'],
            'pcb_footprint'  => ['nullable', 'string', 'max:255'],
        ]);

        $link = ComponentAltiumLink::updateOrCreate(
            ['component_id' => $component->id],
            $validated
        );

        return ApiResponse::success($link->load(['schLibrary', 'pcbLibrary']));
    }

    // DELETE /api/components/{component}/altium-link
    public function destroy(Request $request, Component $component)
    {
        if (! $request->user()->isEditor()) {
            return ApiResponse::forbidden();
        }

        $component->altiumLink?->delete();
        return ApiResponse::noContent();
    }
}
