<?php

namespace App\Services\Contacts;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first sheet of an Excel (.xlsx) or a .csv file into rows of text.
 * Row 1 is the header. No extra library: an .xlsx is a zip of small XML files.
 * Not supported: old .xls files, formulas (the stored result is read), several sheets (only the first).
 */
class SpreadsheetReader
{
    /** @return array{headers: string[], rows: array<int, string[]>} rows keep their sheet row number as the key */
    public function read(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        $table = match ($extension) {
            'xlsx' => $this->readXlsx($file->getRealPath()),
            'csv', 'txt' => $this->readCsv($file->getRealPath()),
            default => throw new BusinessRuleException(__('contacts.file_type'), 422),
        };

        return $this->tidy($table);
    }

    // ---- CSV ---------------------------------------------------------------------------------------------

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        $firstLine = fgets($handle) ?: '';
        rewind($handle);

        // Excel in some regions writes ";" instead of ","
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $rows = [];
        $number = 0;

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $number++;

            if ($number === 1 && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]); // UTF-8 byte order mark
            }

            $rows[$number] = array_map(fn ($cell) => $this->toUtf8((string) $cell), $row);

            $this->guardRowLimit($number);
        }

        fclose($handle);

        return $rows;
    }

    private function toUtf8(string $text): string
    {
        // Older Excel saves "CSV" in Windows-1252
        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    // ---- XLSX --------------------------------------------------------------------------------------------

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new BusinessRuleException(__('contacts.file_unreadable'), 422);
        }

        $sheetXml = $zip->getFromName($this->firstSheetPath($zip));
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $stylesXml = $zip->getFromName('xl/styles.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new BusinessRuleException(__('contacts.file_unreadable'), 422);
        }

        $strings = $stringsXml === false ? [] : $this->sharedStrings($stringsXml);
        $dateStyles = $stylesXml === false ? [] : $this->dateStyles($stylesXml);
        $sheet = $this->xml($sheetXml);
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $number = (int) $row['r'];
            $cells = [];

            foreach ($row->c as $cell) {
                $cells[$this->columnIndex((string) $cell['r'])] = $this->cellText($cell, $strings, $dateStyles);
            }

            if ($cells) {
                // Fill the gaps of empty cells so every row lines up with the header
                $line = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $index => $text) {
                    $line[$index] = $text;
                }
                $rows[$number] = $line;
            }

            $this->guardRowLimit($number);
        }

        return $rows;
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        // xl/workbook.xml lists the sheets; xl/_rels/workbook.xml.rels says which file each one is
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $book = $this->xml($workbook);
            $first = $book->sheets->sheet[0] ?? null;
            $relationId = $first ? (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';

            foreach ($this->xml($rels)->Relationship ?? [] as $relation) {
                if ($relationId !== '' && (string) $relation['Id'] === $relationId) {
                    return 'xl/'.ltrim(preg_replace('#^/?(xl/)?#', '', (string) $relation['Target']), '/');
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function sharedStrings(string $xml): array
    {
        $strings = [];

        foreach ($this->xml($xml)->si ?? [] as $item) {
            // A string is either <t>text</t> or several <r><t>..</t></r> pieces (rich text)
            $text = (string) ($item->t ?? '');
            foreach ($item->r ?? [] as $piece) {
                $text .= (string) $piece->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    // Which cell styles (numbers in styles.xml) show a date: Excel stores a date as a plain number of days
    private function dateStyles(string $xml): array
    {
        $styles = $this->xml($xml);
        $custom = [];

        foreach ($styles->numFmts->numFmt ?? [] as $format) {
            $custom[(int) $format['numFmtId']] = (string) $format['formatCode'];
        }

        $dates = [];
        $position = 0; // a cell points to its style by this position in <cellXfs>

        foreach ($styles->cellXfs->xf ?? [] as $style) {
            $id = (int) $style['numFmtId'];

            if ($this->isDateFormat($id, $custom[$id] ?? null)) {
                $dates[] = $position;
            }

            $position++;
        }

        return $dates;
    }

    private function isDateFormat(int $id, ?string $code): bool
    {
        // Excel's built-in date formats; anything else with d/m/y/h/s letters (outside quotes and [brackets]) is a custom date
        if ($code === null) {
            return in_array($id, [14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47], true);
        }

        return (bool) preg_match('/[dmyhs]/i', preg_replace('/"[^"]*"|\[[^\]]*\]/', '', $code));
    }

    private function cellText(SimpleXMLElement $cell, array $strings, array $dateStyles): string
    {
        $value = (string) $cell->v;

        return match ((string) $cell['t']) {
            's' => $strings[(int) $value] ?? '',
            'inlineStr' => (string) ($cell->is->t ?? ''),
            'b' => $value === '1' ? 'TRUE' : 'FALSE',
            default => is_numeric($value) && $value >= 1 && in_array((int) $cell['s'], $dateStyles, true)
                ? Carbon::create(1899, 12, 30)->addDays((int) $value)->format('j M Y')
                : $this->number($value),
        };
    }

    // 9876543210 must not turn into 9.87654321E+9; whole numbers stay whole
    private function number(string $value): string
    {
        if ($value === '' || ! is_numeric($value)) {
            return $value;
        }

        $float = (float) $value;

        return floor($float) == $float && abs($float) < 1e15 ? (string) (int) $float : rtrim(rtrim(sprintf('%.10F', $float), '0'), '.');
    }

    // "C5" -> 2 (column index from 0)
    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/', strtoupper($reference), $match);
        $index = 0;

        foreach (str_split($match[0] ?? 'A') as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function xml(string $content): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        // No network, no entities: an uploaded file is never trusted
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if (! $xml) {
            throw new BusinessRuleException(__('contacts.file_unreadable'), 422);
        }

        return $xml;
    }

    // ---- shared ------------------------------------------------------------------------------------------

    private function guardRowLimit(int $number): void
    {
        // +1 for the header, +100 so a file with a few empty rows at the end is not refused
        if ($number > config('campaigns.max_import_rows') + 100) {
            throw new BusinessRuleException(__('contacts.too_many_rows', ['max' => config('campaigns.max_import_rows')]), 422);
        }
    }

    /** Header = first non-empty row; drops empty rows; titles trimmed, empty/duplicate titles made unique */
    private function tidy(array $table): array
    {
        $headers = null;
        $rows = [];

        foreach ($table as $number => $cells) {
            $cells = array_map(fn ($cell) => trim((string) $cell), $cells);

            if (implode('', $cells) === '') {
                continue;
            }

            if ($headers === null) {
                $headers = $this->uniqueTitles($cells);

                continue;
            }

            $rows[$number] = array_pad(array_slice($cells, 0, count($headers)), count($headers), '');
        }

        if (! $headers) {
            throw new BusinessRuleException(__('contacts.file_empty'), 422);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function uniqueTitles(array $titles): array
    {
        $seen = [];

        return array_map(function (string $title, int $index) use (&$seen) {
            $title = $title === '' ? 'Column '.($index + 1) : $title;
            $count = $seen[strtolower($title)] = ($seen[strtolower($title)] ?? 0) + 1;

            return $count > 1 ? "{$title} ({$count})" : $title;
        }, $titles, array_keys($titles));
    }
}
