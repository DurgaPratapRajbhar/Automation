<?php

namespace App\Http\Controllers;

use App\Services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MemberController extends Controller
{
    public function __construct(
        private MemberService $memberService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $members = $this->memberService->getMembers(
            $workspace->id
        );

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }
}