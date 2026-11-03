<?php

namespace App\Services\Agents;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Agents\Marketing\MarketerInterface;
use App\Services\AiUsageService;
use Illuminate\Support\Str;

// Writes the offer message of a campaign from the owner's few words and the real products.
// It only writes a draft: the owner reads it, changes it, and sends it to WhatsApp for approval.
class MarketingAgent
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaces,
        private ProductRepositoryInterface $products,
        private MarketerInterface $marketer,
        private AiUsageService $usage,
    ) {}

    /** @return array{name: string, category: string, language: string, body: string, examples: string[]} */
    public function draft(int $workspaceId, string $brief): array
    {
        if (! $this->usage->allowed($workspaceId)) {
            throw new BusinessRuleException(__('marketing.not_available'), 403);
        }

        $result = $this->marketer->draft(
            $this->workspaces->findById($workspaceId)->name,
            $this->products->activeForWorkspace($workspaceId)->map(fn (Product $product) => [
                'name' => $product->name,
                'price' => $product->price,
                'unit' => $product->unit,
            ])->all(),
            $brief,
        ) ?? throw new BusinessRuleException(__('marketing.failed'), 503);

        $body = $this->withOptOut($this->withGreeting($result['body']), $result['language']);

        return [
            'name' => $this->templateName($result['name']),
            'category' => 'MARKETING',
            'language' => $result['language'],
            'body' => $body,
            // Meta needs a sample for the {{1}} (the customer's name)
            'examples' => [config('marketing.name_example')],
        ];
    }

    // Exactly one blank, {{1}}, the customer's name: the model may use none, or others
    private function withGreeting(string $body): string
    {
        $body = preg_replace('/\{\{\s*\d+\s*\}\}/', '{{1}}', $body);

        return str_contains($body, '{{1}}') ? $body : "Namaste {{1}}!\n\n".$body;
    }

    // Marketing must say how to stop: our line is always added, and the text stays inside Meta's limit and never ends in half a blank
    private function withOptOut(string $body, string $language): string
    {
        $line = config("marketing.opt_out_line.{$language}") ?? config('marketing.opt_out_line.en');
        $room = config('marketing.body_max_length') - mb_strlen($line) - 2;
        $body = Str::limit(trim($body), $room, '');
        $open = strrpos($body, '{{');

        if ($open !== false && ! str_contains(substr($body, $open), '}}')) {
            $body = rtrim(substr($body, 0, $open));
        }

        return $body."\n\n".$line;
    }

    // Small letters, numbers and _ (Meta's rule); a short prefix keeps campaign templates together
    private function templateName(string $name): string
    {
        return Str::limit('offer_'.preg_replace('/[^a-z0-9_]+/', '_', Str::lower($name)), 60, '');
    }
}
