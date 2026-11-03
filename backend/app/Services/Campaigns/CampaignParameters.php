<?php

namespace App\Services\Campaigns;

use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * Works out what goes into the blanks ({{1}}, {{2}}...) of a template for one person.
 * Each blank has a source: the person's name or phone, a column of the Excel the business uploaded, or fixed text.
 */
class CampaignParameters
{
    public const SOURCES = ['name', 'phone', 'column', 'text'];

    private const MAX_LENGTH = 500;

    /** One value per blank, in order; '' where the person has nothing for that blank */
    public function valuesFor(array $sources, Customer $customer): array
    {
        return array_map(fn (array $source) => $this->clean($this->value($source, $customer)), array_values($sources));
    }

    /** What the business sees when a value is missing: the Excel column, or "name" / "phone" */
    public function label(array $source): string
    {
        return $source['source'] === 'column' ? (string) $source['value'] : $source['source'];
    }

    private function value(array $source, Customer $customer): string
    {
        return (string) match ($source['source']) {
            'name' => $customer->name,
            'phone' => $customer->phone,
            'column' => ($customer->data ?? [])[$source['value']] ?? '',
            default => $source['value'] ?? '',
        };
    }

    // WhatsApp refuses a blank value with line breaks, tabs or long runs of spaces
    private function clean(string $value): string
    {
        $value = trim(preg_replace('/\s*[\r\n\t]+\s*|\s{4,}/', ' ', $value));

        return Str::limit($value, self::MAX_LENGTH, '');
    }
}
