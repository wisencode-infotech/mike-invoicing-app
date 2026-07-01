<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function csvFile(string $content, string $name = 'products.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_guest_cannot_import_products(): void
    {
        $response = $this->post('/products/import', [
            'file' => $this->csvFile("name,unit_price\nWidget,10\n"),
        ]);

        $response->assertRedirect('/login');
    }

    public function test_valid_rows_are_imported(): void
    {
        $user = User::factory()->create();

        $csv = "name,description,unit_price,tax_rate,active\n"
            ."Consulting Hour,One hour,150.00,8.25,1\n"
            ."Widget,,10.00,,0\n";

        $response = $this->actingAs($user)->post('/products/import', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertRedirect(route('products.import.create'));
        $response->assertSessionHas('importResult', function ($result) {
            return $result['imported'] === 2 && $result['skipped'] === 0;
        });

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'Consulting Hour',
            'unit_price' => 150.00,
            'tax_rate' => 8.25,
            'active' => true,
        ]);

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'Widget',
            'unit_price' => 10.00,
            'tax_rate' => 0,
            'active' => false,
        ]);
    }

    public function test_invalid_rows_are_skipped_and_reported(): void
    {
        $user = User::factory()->create();

        $csv = "name,unit_price\n"
            .",10.00\n" // missing name
            ."Bad Price,not-a-number\n" // invalid price
            ."Good Product,25.00\n";

        $response = $this->actingAs($user)->post('/products/import', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertSessionHas('importResult', function ($result) {
            return $result['imported'] === 1 && $result['skipped'] === 2 && count($result['errors']) === 2;
        });

        $this->assertDatabaseHas('products', ['user_id' => $user->id, 'name' => 'Good Product']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_missing_required_columns_reports_an_error_without_importing(): void
    {
        $user = User::factory()->create();

        $csv = "name,description\nWidget,A description\n";

        $response = $this->actingAs($user)->post('/products/import', [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertSessionHas('importResult', function ($result) {
            return $result['imported'] === 0 && ! empty($result['errors']);
        });

        $this->assertDatabaseCount('products', 0);
    }

    public function test_import_requires_a_file(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/products/import', []);

        $response->assertSessionHasErrors('file');
    }

    public function test_import_rejects_non_csv_files(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('products.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->post('/products/import', [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_imported_products_belong_to_the_importing_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->post('/products/import', [
            'file' => $this->csvFile("name,unit_price\nShared Name,20\n"),
        ]);

        $product = \App\Models\Product::where('name', 'Shared Name')->firstOrFail();
        $this->assertSame($user->id, $product->user_id);
        $this->assertNotSame($otherUser->id, $product->user_id);
    }
}
