<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\StoreChannelRequest;
use App\Http\Requests\Channel\UpdateChannelRequest;
use App\Services\ChannelService;
use App\Services\EmbeddedSignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ChannelController extends Controller
{
    public function __construct(
        private ChannelService $channelService,
        private EmbeddedSignupService $embeddedSignupService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->channelService->getChannels($workspace->id),
            // For the "Connect WhatsApp" popup (public values only)
            'embedded_signup' => $this->embeddedSignupService->publicConfig(),
        ]);
    }

    public function show(Request $request, int $channelId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->channelService->getChannel($workspace->id, $channelId),
        ]);
    }

    public function store(StoreChannelRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => 'Channel connected successfully.',
            'data' => $this->channelService->create($workspace->id, $request->validated()),
        ], 201);
    }

    public function update(UpdateChannelRequest $request, int $channelId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => 'Channel updated successfully.',
            'data' => $this->channelService->update($workspace->id, $channelId, $request->validated()),
        ]);
    }

    public function destroy(Request $request, int $channelId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $this->channelService->delete($workspace->id, $channelId);

        return response()->json([
            'success' => true,
            'message' => 'Channel deleted successfully.',
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer'],
            'recipient' => ['required', 'string', 'regex:/^\d{8,15}$/'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        $workspace = $request->attributes->get('workspace');

        try {

            $response = $this->channelService->sendMessage(
                $workspace->id,
                (int) $data['channel_id'],
                $data['recipient'],
                $data['message']
            );

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully.',
                'data' => $response,
            ]);

        } catch (BusinessRuleException $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->status());

        } catch (RuntimeException $e) {

            report($e);   // WhatsApp/Meta failure — log it so we can see why

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (Throwable $e) {

            report($e);   // laravel.log me poori detail jayegi

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }
}
