<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Services\Meta\MetaGraph;

// WhatsApp message templates live at Meta (per WABA); we only pass the shop's requests through.
// Meta approves each template before it can be sent.
class WhatsAppTemplateService
{
    public function __construct(
        private ChannelRepositoryInterface $channelRepository,
        private MetaGraph $graph
    ) {}

    public function list(int $workspaceId, int $channelId): array
    {
        [$wabaId, $token] = $this->account($workspaceId, $channelId);

        $templates = $this->graph->get("{$wabaId}/message_templates", $token, [
            'fields' => 'id,name,status,category,language,components',
            'limit' => 100,
        ])->json('data') ?? [];

        return array_map(fn (array $template) => $this->present($template), $templates);
    }

    public function create(int $workspaceId, int $channelId, array $data): array
    {
        [$wabaId, $token] = $this->account($workspaceId, $channelId);

        // Check Meta's rules here first, so the shop gets a clear message without a round trip
        $count = $this->placeholderCount($data['body']);
        $examples = array_values($data['examples'] ?? []);

        if (count($examples) !== $count) {
            throw new BusinessRuleException(__('templates.examples_mismatch', ['count' => $count]));
        }

        $body = ['type' => 'BODY', 'text' => $data['body']];
        if ($count > 0) {
            $body['example'] = ['body_text' => [$examples]];
        }

        $result = $this->graph->post("{$wabaId}/message_templates", $token, [
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => $data['category'],
            'components' => [$body],
        ])->json();

        return [
            'id' => $result['id'] ?? null,
            'name' => $data['name'],
            'status' => $result['status'] ?? 'PENDING',
            'category' => $result['category'] ?? $data['category'],
            'language' => $data['language'],
            'body' => $data['body'],
        ];
    }

    public function delete(int $workspaceId, int $channelId, string $name): void
    {
        [$wabaId, $token] = $this->account($workspaceId, $channelId);

        $this->graph->delete("{$wabaId}/message_templates", $token, ['name' => $name]);
    }

    // The shop's WABA id and token, only for a connected channel of this workspace
    private function account(int $workspaceId, int $channelId): array
    {
        $channel = $this->channelRepository->findConnectedInWorkspace($workspaceId, $channelId);

        if (! $channel || ! $channel->external_account_id || ! $channel->credential?->access_token) {
            throw new BusinessRuleException(__('templates.channel_not_found'), 404);
        }

        return [$channel->external_account_id, $channel->credential->access_token];
    }

    // {{1}}, {{2}}... must be numbered 1..n with no gaps
    private function placeholderCount(string $body): int
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        $numbers = array_values(array_unique(array_map('intval', $matches[1])));
        sort($numbers);

        if ($numbers !== [] && $numbers !== range(1, count($numbers))) {
            throw new BusinessRuleException(__('templates.placeholders_order'));
        }

        return count($numbers);
    }

    private function present(array $template): array
    {
        $body = collect($template['components'] ?? [])->firstWhere('type', 'BODY');

        return [
            'id' => $template['id'] ?? null,
            'name' => $template['name'] ?? null,
            'status' => $template['status'] ?? null,
            'category' => $template['category'] ?? null,
            'language' => $template['language'] ?? null,
            'body' => $body['text'] ?? null,
        ];
    }
}
