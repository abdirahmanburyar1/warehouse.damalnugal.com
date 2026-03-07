<?php

namespace App\Imports;

use App\Models\WarehouseAmc;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

/**
 * Import warehouse monthly consumption from Excel into warehouse_amcs table.
 * Same pattern as facilities MonthlyConsumptionImport: Item column + month columns (Jan 2026, Feb 2026, ...).
 * Empty or missing quantity → 0. Non-empty → update or create warehouse_amcs row.
 */
class WarehouseAmcImport implements
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    SkipsEmptyRows
{
    /** @var array<string,string> heading_key => YYYY-MM */
    protected array $monthColumns = [];

    protected string $importId;
    protected ?string $storedFilePath;

    public int $processedRows = 0;
    public int $importedCount = 0;
    public int $updatedCount = 0;
    public array $errors = [];

    public function __construct(string $importId, ?string $storedFilePath = null)
    {
        $this->importId = $importId;
        $this->storedFilePath = $storedFilePath;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        if (empty($this->monthColumns)) {
            $this->detectMonthColumns($rows->first());
        }

        foreach ($rows as $row) {
            $this->processRow($row);
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    protected function detectMonthColumns($row): void
    {
        $array = is_array($row) ? $row : $row->toArray();
        foreach ($array as $key => $value) {
            if ($key === null || $key === '') {
                continue;
            }
            $normalizedKey = is_string($key) ? trim(strtolower($key)) : (string) $key;
            if (in_array($normalizedKey, ['item', 'items', 'product', 'amc'], true)) {
                continue;
            }
            $monthYear = $this->parseMonthKey($normalizedKey);
            if ($monthYear !== null) {
                $this->monthColumns[$key] = $monthYear;
            }
        }
    }

    /**
     * Parse header key to YYYY-MM. Handles "jan 2026", "jan-2026", "jan_2026", "Jan 2026", etc.
     */
    protected function parseMonthKey(string $key): ?string
    {
        $clean = str_replace(['_', '-', '/'], ' ', $key);
        $clean = preg_replace('/\s+/', ' ', $clean ?? '') ?? '';
        $clean = trim($clean);
        if ($clean === '') {
            return null;
        }

        $monthMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        $normalizeYear = static function (int $year): int {
            if ($year < 100) {
                return 2000 + $year;
            }
            return $year;
        };

        if (preg_match('/^([a-z]+)\s+(\d{2}|\d{4})$/i', $clean, $m)) {
            $monthName = strtolower(substr($m[1], 0, 3));
            if (!isset($monthMap[$monthName])) {
                return null;
            }
            $year = $normalizeYear((int) $m[2]);
            return sprintf('%04d-%02d', $year, $monthMap[$monthName]);
        }

        if (preg_match('/^(\d{4})\s+(\d{1,2})$/', $clean, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            if ($month < 1 || $month > 12) {
                return null;
            }
            return sprintf('%04d-%02d', $year, $month);
        }

        return null;
    }

    protected function processRow($row): void
    {
        $this->processedRows++;
        $data = is_array($row) ? $row : $row->toArray();

        $itemName = trim((string) ($data['item'] ?? $data['items'] ?? $data['product'] ?? ''));
        if ($itemName === '') {
            return;
        }

        $product = Product::where('name', $itemName)->first();
        if (!$product) {
            if (count($this->errors) < 20) {
                $this->errors[] = "Product not found: {$itemName}";
            }
            return;
        }

        foreach ($this->monthColumns as $columnKey => $monthYear) {
            if (!array_key_exists($columnKey, $data)) {
                $quantity = 0;
            } else {
                $quantity = $this->cleanQuantity($data[$columnKey]);
            }

            $record = WarehouseAmc::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'month_year' => $monthYear,
                ],
                [
                    'quantity' => (int) round($quantity),
                ]
            );

            if ($record->wasRecentlyCreated) {
                $this->importedCount++;
            } else {
                $this->updatedCount++;
            }
        }
    }

    protected function cleanQuantity($value): float
    {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            return 0.0;
        }
        $cleaned = preg_replace('/[^0-9.-]/', '', (string) $value);
        if ($cleaned === '' || $cleaned === '-') {
            return 0.0;
        }
        $q = (float) $cleaned;
        return max(0.0, $q);
    }

    public function getResults(): array
    {
        return [
            'imported' => $this->importedCount,
            'updated' => $this->updatedCount,
            'skipped' => 0,
            'errors' => $this->errors,
        ];
    }
}
