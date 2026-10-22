<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChannelService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ChannelController extends Controller
{
    public function __construct(
        private ChannelService $channelService
    ) {}

    public function sendMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer'],
            'recipient'  => ['required', 'string', 'regex:/^\d{8,15}$/'],
            'message'    => ['required', 'string', 'max:4096'],
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

        } catch (ModelNotFoundException) {

            return response()->json([
                'success' => false,
                'message' => 'Channel not found.',
            ], 404);

        } catch (RuntimeException $e) {

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