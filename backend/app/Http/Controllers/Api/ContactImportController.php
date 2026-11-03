<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\ContactImportPreviewRequest;
use App\Http\Requests\Contacts\ContactImportRequest;
use App\Services\Contacts\ContactImportService;
use Illuminate\Http\JsonResponse;

class ContactImportController extends Controller
{
    public function __construct(
        private ContactImportService $importService
    ) {}

    // Looks inside the file; saves nothing
    public function preview(ContactImportPreviewRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->importService->preview($request->file('file')),
        ]);
    }

    public function store(ContactImportRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $result = $this->importService->import(
            $workspace->id,
            $request->file('file'),
            $request->validated('phone_column'),
            $request->validated('name_column'),
            $request->boolean('consent')
        );

        return response()->json([
            'success' => true,
            'message' => __('contacts.imported', ['created' => $result['created'], 'updated' => $result['updated']]),
            'data' => $result,
        ], 201);
    }
}
