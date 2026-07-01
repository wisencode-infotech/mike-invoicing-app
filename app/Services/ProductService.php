<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

class ProductService
{
    /**
     * @var string[]
     */
    protected const REQUIRED_COLUMNS = ['name', 'unit_price'];

    public function paginateForUser(User $user, ?string $search, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $user->products()
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Product
    {
        return $user->products()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    /**
     * Imports products from a CSV with a header row containing at least
     * "name" and "unit_price" columns. Optional columns: description,
     * tax_rate, active. Invalid rows are skipped and reported rather than
     * failing the whole import — this runs synchronously in the request
     * since product catalogs are expected to be small; large files should
     * revisit the queued ImportProductsCsvJob sketched in the architecture.
     *
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public function importFromCsv(User $user, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        $header = $this->readHeader($handle);
        $columnCount = count($header);

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);

        if ($missing !== []) {
            fclose($handle);

            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Missing required column(s): '.implode(', ', $missing)],
            ];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($row === [null] || $row === []) {
                continue;
            }

            $row = array_slice(array_pad($row, $columnCount, null), 0, $columnCount);
            $columns = array_combine($header, $row);

            $name = trim((string) ($columns['name'] ?? ''));
            $unitPrice = $columns['unit_price'] ?? null;

            if ($name === '' || ! is_numeric($unitPrice)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: name and a numeric unit_price are required.";
                continue;
            }

            $taxRate = $columns['tax_rate'] ?? null;
            $description = trim((string) ($columns['description'] ?? ''));

            $user->products()->create([
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'unit_price' => (float) $unitPrice,
                'tax_rate' => is_numeric($taxRate) ? (float) $taxRate : 0,
                'active' => $this->parseBoolean($columns['active'] ?? '1'),
            ]);

            $imported++;
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @return string[]
     */
    protected function readHeader(mixed $handle): array
    {
        $header = fgetcsv($handle) ?: [];
        $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);

        // Strip a UTF-8 BOM that Excel-exported CSVs often prepend to the
        // first header cell (otherwise "name" would not match "\u{FEFF}name").
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
        }

        return $header;
    }

    protected function parseBoolean(?string $value): bool
    {
        return ! in_array(strtolower(trim((string) $value)), ['0', 'false', 'no', 'inactive', ''], true);
    }
}
