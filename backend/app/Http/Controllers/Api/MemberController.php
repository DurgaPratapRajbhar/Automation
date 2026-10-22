<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreMemberRequest;
use App\Http\Requests\Member\UpdateMemberRequest;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(
        private MemberService $memberService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->memberService->getMembers($workspace->id),
        ]);
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $member = $this->memberService->add(
            $workspace->id,
            $request->attributes->get('workspace_role'),
            $request->validated('email'),
            $request->validated('name'),
            $request->validated('password'),
            (int) $request->validated('role_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Member added successfully.',
            'data' => $member,
        ], 201);
    }

    public function update(UpdateMemberRequest $request, int $userId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $member = $this->memberService->updateRole(
            $workspace->id,
            $request->attributes->get('workspace_role'),
            $request->user()->id,
            $userId,
            (int) $request->validated('role_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Member role updated successfully.',
            'data' => $member,
        ]);
    }

    public function destroy(Request $request, int $userId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $this->memberService->remove(
            $workspace->id,
            $request->attributes->get('workspace_role'),
            $request->user()->id,
            $userId
        );

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully.',
        ]);
    }
}
