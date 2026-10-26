<?php

namespace App\Services\Meta;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

// One place that calls Meta's Graph API. A failed call becomes a clear BusinessRuleException
// with Meta's own message, so every feature shows the same kind of error.
class MetaGraph
{
    public function get(string $path, ?string $token, array $query = []): Response
    {
        return $this->send('get', $path, $token, $query);
    }

    public function post(string $path, ?string $token, array $body = []): Response
    {
        return $this->send('post', $path, $token, $body);
    }

    public function delete(string $path, ?string $token, array $query = []): Response
    {
        return $this->send('delete', $path, $token, $query);
    }

    private function send(string $method, string $path, ?string $token, array $data): Response
    {
        $url = rtrim(config('services.meta.graph_url'), '/').'/'.config('services.meta.graph_version').'/'.ltrim($path, '/');

        $request = Http::timeout(30)->acceptJson();

        if ($token) {
            $request = $request->withToken($token);
        }

        $response = match ($method) {
            'get' => $request->get($url, $data),
            'delete' => $request->delete($url.($data ? '?'.http_build_query($data) : '')),
            default => $request->post($url, $data),
        };

        if ($response->failed()) {
            throw new BusinessRuleException(__('channels.meta_error', [
                'message' => $response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'HTTP '.$response->status(),
            ]));
        }

        return $response;
    }
}
