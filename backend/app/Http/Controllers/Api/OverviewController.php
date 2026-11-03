<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BusinessOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    // The Dashboard's business numbers and workflows; its own permission, since it shows more than the promises
    public function show(Request $request, BusinessOverviewService $overview): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $overview->get($request->attributes->get('workspace')->id)]);
    }
}
