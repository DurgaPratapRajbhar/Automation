<?php

namespace App\Services\Contacts;

use App\Exceptions\BusinessRuleException;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns an Excel/CSV of people into customers (the contact list used by campaigns).
 * The business chooses which column is the phone and which the name; every other column is kept with the contact
 * so a message can use it later ("{amount}", "{date}").
 */
class ContactImportService
{
    private const SAMPLE_ROWS = 5;

    private const MAX_PROBLEMS_SHOWN = 50;

    private const MAX_EXTRA_COLUMNS = 30;

    private const MAX_CELL_LENGTH = 500;

    public function __construct(
        private SpreadsheetReader $reader,
        private CustomerRepositoryInterface $customers
    ) {}

    /** What is in the file, and which columns look like the phone and the name. Saves nothing. */
    public function preview(UploadedFile $file): array
    {
        ['headers' => $headers, 'rows' => $rows] = $this->reader->read($file);

        $phoneColumn = $this->guessPhoneColumn($headers, $rows);

        return [
            'headers' => $headers,
            'total_rows' => count($rows),
            'sample' => array_map(
                fn (array $row) => array_combine($headers, $row),
                array_slice(array_values($rows), 0, self::SAMPLE_ROWS)
            ),
            'suggested' => [
                'phone_column' => $phoneColumn,
                'name_column' => $this->guessNameColumn($headers, $phoneColumn),
            ],
        ];
    }

    /**
     * Creates new customers and updates the ones with the same phone. $consent = "the business has these people's
     * permission to message them on WhatsApp"; without it they are saved but cannot get campaigns.
     */
    public function import(int $workspaceId, UploadedFile $file, string $phoneColumn, ?string $nameColumn, bool $consent): array
    {
        ['headers' => $headers, 'rows' => $rows] = $this->reader->read($file);

        $phoneIndex = array_search($phoneColumn, $headers, true);
        $nameIndex = $nameColumn === null ? false : array_search($nameColumn, $headers, true);

        if ($phoneIndex === false || ($nameColumn !== null && $nameIndex === false)) {
            throw new BusinessRuleException(__('contacts.column_not_found'), 422);
        }

        $extraIndexes = array_slice(
            array_values(array_diff(array_keys($headers), [$phoneIndex, $nameIndex])),
            0,
            self::MAX_EXTRA_COLUMNS
        );

        $problems = [];
        $valid = []; // phone => ['row' => n, 'name' => .., 'data' => [..]]

        foreach ($rows as $number => $row) {
            $phone = $this->normalizePhone($row[$phoneIndex] ?? '');

            if ($phone === null) {
                $problems[] = ['row' => $number, 'reason' => __('contacts.bad_phone', ['value' => Str::limit($row[$phoneIndex] ?? '', 30)])];

                continue;
            }

            if (isset($valid[$phone])) {
                $problems[] = ['row' => $number, 'reason' => __('contacts.duplicate_in_file', ['row' => $valid[$phone]['row']])];

                continue;
            }

            $valid[$phone] = [
                'row' => $number,
                'name' => Str::limit(trim((string) ($nameIndex === false ? '' : ($row[$nameIndex] ?? ''))), 100, ''),
                'data' => $this->extraData($headers, $row, $extraIndexes),
            ];
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($workspaceId, $valid, $consent, &$created, &$updated) {
            $existing = $this->customers->findByPhones($workspaceId, array_keys($valid));

            foreach ($valid as $phone => $person) {
                $customer = $existing->get($phone);

                if ($customer) {
                    $changes = ['data' => [...($customer->data ?? []), ...$person['data']]];

                    if ($person['name'] !== '') {
                        $changes['name'] = $person['name'];
                    }
                    // Someone who asked us to stop is never switched back on by an upload
                    if ($consent && ! $customer->opted_out_at) {
                        $changes['whatsapp_opt_in'] = true;
                    }

                    $this->customers->update($customer, $changes);
                    $updated++;

                    continue;
                }

                $this->customers->create([
                    'workspace_id' => $workspaceId,
                    'name' => $person['name'] !== '' ? $person['name'] : $phone,
                    'phone' => $phone,
                    'data' => $person['data'] ?: null,
                    'whatsapp_opt_in' => $consent,
                ]);
                $created++;
            }
        });

        return [
            'total_rows' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'skipped' => count($problems),
            'problems' => array_slice($problems, 0, self::MAX_PROBLEMS_SHOWN),
        ];
    }

    /**
     * "+91 98765-43210", "098765 43210", "9876543210" -> "919876543210". null when it cannot be a phone number.
     * A 10-digit number gets the default country code; a longer one is taken to have its country code already.
     */
    public function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        $country = (string) config('campaigns.default_country_code');

        if (strlen($digits) === 11 && $digits[0] === '0') {
            $digits = substr($digits, 1); // local style with a leading zero
        }

        if (strlen($digits) === 10) {
            $digits = $country.$digits;
        }

        if (! preg_match('/^\d{10,15}$/', $digits)) {
            return null;
        }

        // Indian mobile numbers start with 6-9 after the country code
        if ($country === '91' && str_starts_with($digits, '91') && strlen($digits) === 12 && ! preg_match('/^91[6-9]/', $digits)) {
            return null;
        }

        return $digits;
    }

    private function extraData(array $headers, array $row, array $indexes): array
    {
        $data = [];

        foreach ($indexes as $index) {
            $value = trim((string) ($row[$index] ?? ''));

            if ($value !== '') {
                $data[$headers[$index]] = Str::limit($value, self::MAX_CELL_LENGTH, '');
            }
        }

        return $data;
    }

    // The column whose title says phone, or else the one where most rows look like a phone number
    private function guessPhoneColumn(array $headers, array $rows): ?string
    {
        foreach ($headers as $title) {
            if (preg_match('/phone|mobile|whatsapp|contact|number|mob\b|फोन|मोबाइल|नंबर/iu', $title)) {
                return $title;
            }
        }

        $sample = array_slice($rows, 0, 20);

        foreach ($headers as $index => $title) {
            $matches = count(array_filter($sample, fn ($row) => $this->normalizePhone($row[$index] ?? '') !== null));

            if ($sample && $matches / count($sample) >= 0.7) {
                return $title;
            }
        }

        return null;
    }

    private function guessNameColumn(array $headers, ?string $phoneColumn): ?string
    {
        foreach ($headers as $title) {
            if ($title !== $phoneColumn && preg_match('/name|naam|customer|client|student|party|नाम/iu', $title)) {
                return $title;
            }
        }

        return null;
    }
}
