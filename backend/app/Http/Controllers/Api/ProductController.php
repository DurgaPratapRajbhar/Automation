<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ProductRequest;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            ...$this->products->list($workspace->id, $request->query('search'), (int) $request->query('per_page', config('pagination.per_page')))->toArray(),
        ]);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json(['success' => true, 'message' => __('products.created'), 'data' => $this->products->create($workspace->id, $request->validated())], 201);
    }

    public function update(ProductRequest $request, int $productId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json(['success' => true, 'message' => __('products.updated'), 'data' => $this->products->update($workspace->id, $productId, $request->validated())]);
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->products->delete($workspace->id, $productId);

        return response()->json(['success' => true, 'message' => __('products.deleted')]);
    }
}
