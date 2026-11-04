<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function csvFile(string $content, string $name = 'people.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

// A small real .xlsx. $rows = list of rows; a cell that is an int/float is stored as a number, a string as text.
// $shared = true writes text into sharedStrings.xml (how Excel does it), false writes it inline.
function xlsxFile(array $rows, bool $shared = true, string $name = 'people.xlsx'): UploadedFile
{
    $strings = [];
    $sheetRows = '';

    foreach ($rows as $r => $row) {
        $cells = '';
        foreach (array_values($row) as $c => $value) {
            $ref = chr(65 + $c).($r + 1);

            if ($value instanceof DateTimeInterface) {
                // Excel keeps a date as the number of days since 1899-12-30, shown as a date by style 1 (see styles.xml below)
                $days = (int) Carbon::create(1899, 12, 30)->diffInDays($value);
                $cells .= "<c r=\"{$ref}\" s=\"1\"><v>{$days}</v></c>";
            } elseif (is_int($value) || is_float($value)) {
                $cells .= "<c r=\"{$ref}\"><v>{$value}</v></c>";
            } elseif ($shared) {
                $strings[] = $value;
                $cells .= "<c r=\"{$ref}\" t=\"s\"><v>".(count($strings) - 1).'</v></c>';
            } else {
                $cells .= "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>".htmlspecialchars($value).'</t></is></c>';
            }
        }
        $sheetRows .= '<row r="'.($r + 1)."\">{$cells}</row>";
    }

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14"/></cellXfs></styleSheet>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');

    if ($shared) {
        $items = implode('', array_map(fn ($text) => '<si><t>'.htmlspecialchars($text).'</t></si>', $strings));
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$items.'</sst>');
    }
    $zip->close();

    return UploadedFile::fake()->createWithContent($name, file_get_contents($path));
}

function importAs(TestCase $test, array $headers, UploadedFile $file, array $fields = []): TestResponse
{
    return $test->post('/api/contact-imports', ['file' => $file, ...$fields], $headers + ['Accept' => 'application/json']);
}

// A business that has the Campaigns module switched on (registerOwner turns every module on)
function campaignOwnerHeaders(TestCase $test, string $email = 'owner@example.com'): array
{
    $owner = registerOwner($test, $email);

    return headersFor($owner['token'], $owner['workspace_id']);
}

const PEOPLE_CSV = "Customer Name,Mobile,Amount,Due Date\nRamesh,98765 43210,1200,10-Nov\nSita,+91 91234-56789,500,12-Nov\n";

test('the preview shows the columns, a few rows and which columns look like phone and name', function () {
    $headers = campaignOwnerHeaders($this);

    $this->post('/api/contact-imports/preview', ['file' => csvFile(PEOPLE_CSV)], $headers + ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.headers', ['Customer Name', 'Mobile', 'Amount', 'Due Date'])
        ->assertJsonPath('data.total_rows', 2)
        ->assertJsonPath('data.sample.0.Customer Name', 'Ramesh')
        ->assertJsonPath('data.suggested.phone_column', 'Mobile')
        ->assertJsonPath('data.suggested.name_column', 'Customer Name');

    expect(Customer::count())->toBe(0); // a preview saves nothing
});

test('a csv is imported: phones are tidied and the other columns are kept for later messages', function () {
    $headers = campaignOwnerHeaders($this);

    importAs($this, $headers, csvFile(PEOPLE_CSV), ['phone_column' => 'Mobile', 'name_column' => 'Customer Name', 'consent' => true])
        ->assertCreated()
        ->assertJsonPath('data.created', 2)
        ->assertJsonPath('data.updated', 0)
        ->assertJsonPath('data.skipped', 0);

    $ramesh = Customer::where('phone', '919876543210')->firstOrFail();

    expect($ramesh->name)->toBe('Ramesh')
        ->and($ramesh->data)->toBe(['Amount' => '1200', 'Due Date' => '10-Nov'])
        ->and($ramesh->whatsapp_opt_in)->toBeTrue()
        ->and(Customer::where('phone', '919123456789')->exists())->toBeTrue();
});

test('an Excel file is imported, with numbers as phones and with Excel-style or inline text', function (bool $shared) {
    $headers = campaignOwnerHeaders($this);
    $file = xlsxFile([['Name', 'Phone', 'City'], ['Anil', 9876543210, 'Azamgarh'], ['Mohan', '09123456789', 'Delhi']], $shared);

    importAs($this, $headers, $file, ['phone_column' => 'Phone', 'name_column' => 'Name'])
        ->assertCreated()
        ->assertJsonPath('data.created', 2);

    expect(Customer::where('phone', '919876543210')->value('name'))->toBe('Anil')
        ->and(Customer::where('phone', '919123456789')->first()->data)->toBe(['City' => 'Delhi']);
})->with([[true], [false]]);

test('a date cell in Excel is read as a date, not as a number of days', function () {
    $headers = campaignOwnerHeaders($this);
    $file = xlsxFile([['Name', 'Phone', 'Last Date'], ['Anil', 9876543210, Carbon::create(2026, 11, 10)]]);

    importAs($this, $headers, $file, ['phone_column' => 'Phone', 'name_column' => 'Name'])->assertCreated();

    expect(Customer::first()->data)->toBe(['Last Date' => '10 Nov 2026']);
});

test('rows that cannot be used are skipped and listed with their row number', function () {
    $headers = campaignOwnerHeaders($this);
    $csv = "Name,Phone\nRamesh,9876543210\nBad Number,12345\nNo Phone,\nSame Again,98765 43210\n";

    importAs($this, $headers, csvFile($csv), ['phone_column' => 'Phone', 'name_column' => 'Name'])
        ->assertCreated()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.skipped', 3)
        ->assertJsonPath('data.problems.0.row', 3)
        ->assertJsonPath('data.problems.1.row', 4)
        ->assertJsonPath('data.problems.2.row', 5)
        ->assertJsonPath('data.problems.2.reason', 'Same phone number as row 2');
});

test('uploading again updates people with the same phone and never switches an opted-out person back on', function () {
    $headers = campaignOwnerHeaders($this);
    $first = "Name,Phone,Amount\nRamesh,9876543210,1200\nSita,9123456789,500\n";
    importAs($this, $headers, csvFile($first), ['phone_column' => 'Phone', 'name_column' => 'Name'])->assertCreated();

    Customer::where('phone', '919123456789')->update(['opted_out_at' => now()]);

    $second = "Name,Phone,Amount,City\nRamesh Kumar,9876543210,900,Delhi\nSita,9123456789,500,Pune\n";
    importAs($this, $headers, csvFile($second), ['phone_column' => 'Phone', 'name_column' => 'Name', 'consent' => true])
        ->assertCreated()
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('data.updated', 2);

    $ramesh = Customer::where('phone', '919876543210')->first();
    $sita = Customer::where('phone', '919123456789')->first();

    expect(Customer::count())->toBe(2)
        ->and($ramesh->name)->toBe('Ramesh Kumar')
        ->and($ramesh->data)->toBe(['Amount' => '900', 'City' => 'Delhi'])
        ->and($ramesh->whatsapp_opt_in)->toBeTrue()
        ->and($sita->whatsapp_opt_in)->toBeFalse();
});

test('without the permission tick the people are saved but cannot get campaigns', function () {
    $headers = campaignOwnerHeaders($this);

    importAs($this, $headers, csvFile("Name,Phone\nRamesh,9876543210\n"), ['phone_column' => 'Phone', 'name_column' => 'Name'])
        ->assertCreated();

    expect(Customer::first()->whatsapp_opt_in)->toBeFalse();
});

test('a person without a name column is saved under the phone number', function () {
    $headers = campaignOwnerHeaders($this);

    importAs($this, $headers, csvFile("Phone\n9876543210\n"), ['phone_column' => 'Phone'])->assertCreated();

    expect(Customer::first()->name)->toBe('919876543210');
});

test('the same phone can be in two different businesses', function () {
    $one = campaignOwnerHeaders($this, 'one@example.com');
    $two = campaignOwnerHeaders($this, 'two@example.com');
    $csv = "Name,Phone\nRamesh,9876543210\n";

    importAs($this, $one, csvFile($csv), ['phone_column' => 'Phone', 'name_column' => 'Name'])->assertCreated();
    importAs($this, $two, csvFile($csv), ['phone_column' => 'Phone', 'name_column' => 'Name'])->assertCreated()
        ->assertJsonPath('data.created', 1);

    expect(Customer::count())->toBe(2);
});

test('bad uploads get a clear refusal', function () {
    $headers = campaignOwnerHeaders($this);
    $fields = ['phone_column' => 'Phone', 'name_column' => 'Name'];

    importAs($this, $headers, csvFile("Name,Phone\nRamesh,9876543210\n"), ['phone_column' => 'Mobile'])
        ->assertUnprocessable()->assertJsonPath('message', 'The column you chose is not in the file. Please choose the columns again.');
    importAs($this, $headers, csvFile('x', 'people.pdf'), $fields)
        ->assertUnprocessable()->assertJsonPath('message', fn ($text) => str_contains($text, 'Excel file (.xlsx)'));
    importAs($this, $headers, csvFile('', 'empty.csv'), $fields)
        ->assertUnprocessable()->assertJsonPath('message', fn ($text) => str_contains($text, 'empty'));
    importAs($this, $headers, UploadedFile::fake()->createWithContent('broken.xlsx', 'not a zip'), $fields)
        ->assertUnprocessable()->assertJsonPath('message', fn ($text) => str_contains($text, 'could not be read'));
    importAs($this, $headers, csvFile("Name,Phone\nRamesh,9876543210\n"), [])
        ->assertUnprocessable()->assertJsonValidationErrors('phone_column');
    $this->post('/api/contact-imports', ['phone_column' => 'Phone'], $headers + ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('file');

    expect(Customer::count())->toBe(0);
});

test('a file with too many people is refused', function () {
    config(['campaigns.max_import_rows' => 3]);
    $headers = campaignOwnerHeaders($this);
    $rows = implode("\n", array_map(fn ($i) => 'Person '.$i.',98765432'.str_pad($i, 2, '0', STR_PAD_LEFT), range(1, 120)));

    importAs($this, $headers, csvFile("Name,Phone\n".$rows), ['phone_column' => 'Phone', 'name_column' => 'Name'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($text) => str_contains($text, 'too many rows'));
});

test('a business that signed up by itself cannot import until the super admin switches Campaigns on', function () {
    [$shop, $shopId] = selfSignedUpShop($this);

    importAs($this, $shop, csvFile(PEOPLE_CSV), ['phone_column' => 'Mobile'])
        ->assertForbidden()
        ->assertJsonPath('module', 'campaigns');

    $this->putJson("/api/admin/workspaces/{$shopId}", [
        'workspace_name' => 'Ramesh Kirana',
        'modules' => ['inbox', 'campaigns'],
    ], superAdminHeaders())->assertOk();

    importAs($this, $shop, csvFile(PEOPLE_CSV), ['phone_column' => 'Mobile', 'name_column' => 'Customer Name'])
        ->assertCreated();
});
