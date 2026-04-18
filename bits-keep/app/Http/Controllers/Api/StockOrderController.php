<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockOrder;
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
}
