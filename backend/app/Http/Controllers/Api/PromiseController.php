<?php

namespace App\Http\Controllers\Api;

use App\Enums\PromiseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promises\StorePromiseRequest;
use App\Http\Requests\Promises\UpdatePromiseRequest;
use App\Services\DailySummaryService;
use App\Services\PromiseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromiseController extends Controller
{
    public function __construct(private PromiseService $promises, private DailySummaryService $summary) {}

    // Counts for the Dashboard card
    public function summary(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json(['success' => true, 'data' => $this->summary->get($workspace->id)]);
    }

    // ?status=open (default) | done | missed | all
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $status = $request->query('status', PromiseStatus::Open->value);

        return response()->json([
            'success' => true,
            ...$this->promises->list(
                $workspace->id,
                PromiseStatus::tryFrom($status),
                (int) $request->query('per_page', config('pagination.per_page')),
            )->toArray(),
        ]);
    }

    public function store(StorePromiseRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('promises.added'),
            'data' => $this->promises->create($workspace->id, $request->validated()),
        ], 201);
    }

    public function update(UpdatePromiseRequest $request, int $promiseId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('promises.updated'),
            'data' => $this->promises->setStatus($workspace->id, $promiseId, PromiseStatus::from($request->validated('status'))),
        ]);
    }
}
