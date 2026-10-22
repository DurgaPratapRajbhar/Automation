<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $search = $request->query('search');

        return response()->json([
            'success' => true,
            ...$this->customerService->list(
                $workspace->id,
                is_string($search) ? trim($search) : null,
                (int) $request->query('per_page', config('pagination.per_page'))
            )->toArray(),
        ]);
    }

    public function show(Request $request, int $customerId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->customerService->getCustomer($workspace->id, $customerId),
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('customers.created'),
            'data' => $this->customerService->create($workspace->id, $request->validated()),
        ], 201);
    }

    public function update(UpdateCustomerRequest $request, int $customerId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('customers.updated'),
            'data' => $this->customerService->update($workspace->id, $customerId, $request->validated()),
        ]);
    }

    public function destroy(Request $request, int $customerId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $this->customerService->delete($workspace->id, $customerId);

        return response()->json([
            'success' => true,
            'message' => __('customers.deleted'),
        ]);
    }
}
