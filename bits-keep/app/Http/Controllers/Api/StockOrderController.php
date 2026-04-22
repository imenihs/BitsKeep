<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\StockOrder;
use App\Services\StockOrderNotionExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StockOrder::query();

        if ($request->has('component_id')) {
            $query->where('component_id', $request->input('component_id'));
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->with(['component', 'supplier', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'component_id' => 'required|exists:components,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'quantity' => 'required|integer|min:1',
            'status' => 'required|in:pending,received,cancelled',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date',
            'received_date' => 'nullable|date',
        ]);

        $validated['created_by'] = auth()->id();

        $order = StockOrder::create($validated);

        return response()->json($order->load(['component', 'supplier', 'createdBy']), 201);
    }

    public function show(StockOrder $order): JsonResponse
    {
        return response()->json($order->load(['component', 'supplier', 'createdBy']));
    }

    public function update(Request $request, StockOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'component_id' => 'sometimes|exists:components,id',
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'quantity' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:pending,received,cancelled',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date',
            'received_date' => 'nullable|date',
        ]);

        $order->update($validated);

        return response()->json($order->load(['component', 'supplier', 'createdBy']));
    }

    public function destroy(StockOrder $order): JsonResponse
    {
        $order->delete();

        return response()->json(null, 204);
    }

    public function pendingByComponent(int $componentId): JsonResponse
    {
        $orders = StockOrder::where('component_id', $componentId)
            ->where('status', 'pending')
            ->with(['supplier', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    public function exportNotion(Request $request, StockOrderNotionExportService $service): JsonResponse
    {
        $validated = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.part_number' => ['required', 'string', 'max:255'],
            'items.*.supplier_part_number' => ['nullable', 'string', 'max:255'],
            'items.*.purchase_unit_label' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $result = $service->exportSupplierOrder(
                $validated['supplier_name'],
                $validated['items'],
                $request->user()?->name ?? 'BitsKeep'
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), [], 503);
        }

        return ApiResponse::success($result, 'Notionへ出力しました');
    }
}
