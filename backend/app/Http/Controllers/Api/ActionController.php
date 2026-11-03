<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Actions\DecideActionRequest;
use App\Services\Actions\ActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function __construct(private ActionService $actions) {}

    // ?status=proposed (default) | executed | rejected | escalated | all
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            ...$this->actions->list(
                $workspace->id,
                ActionStatus::tryFrom($request->query('status', ActionStatus::Proposed->value)),
                (int) $request->query('per_page', config('pagination.per_page')),
            )->toArray(),
        ]);
    }

    // The owner's answer: approve (it is done now) or reject
    public function update(DecideActionRequest $request, int $actionId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $approve = $request->validated('decision') === 'approve';

        $data = $approve
            ? $this->actions->approve($workspace->id, $actionId, $request->user()->id)
            : $this->actions->reject($workspace->id, $actionId, $request->user()->id);

        return response()->json(['success' => true, 'message' => __($approve ? 'actions.approved' : 'actions.rejected'), 'data' => $data]);
    }
}
