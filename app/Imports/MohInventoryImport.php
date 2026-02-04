<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\MohInventoryItem;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Carbon\Carbon;

class MohInventoryImport implements 
    ToModel, 
    WithHeadingRow, 
    WithChunkReading, 
    WithBatchInserts, 
    SkipsEmptyRows
{
    public $mohInventoryId;
    public int $createdCount = 0;
    public int $missingProductRows = 0;
    /** @var array<string,true> */
    private array $missingProductsSet = [];
    /** @var string[] */
    public array $missingProductsSample = [];

    public function __construct(int $mohInventoryId)
    {
        $this->mohInventoryId = $mohInventoryId;
    }

    public function model(array $row)
    {
        // Check if required fields are present - try different column name variations
        $itemName = $row['item'] ?? $row['Item'] ?? null;
        $quantity = $row['quantity'] ?? $row['Quantity'] ?? null;
        
        // Quantity may be "0" which empty() treats as falsey; allow 0.
        if (empty($itemName) || $quantity === null || $quantity === '') {
            return null;
        }

        // Get product (will return null if not found, causing row to be skipped)
        $product = $this->getOrCreateProduct($row, (string) $itemName);
        
        // If product doesn't exist, skip this row
        if (!$product) {
            return null;
        }

        // Get warehouse (will throw exception if not found)
        $warehouseName = $row['warehouse'] ?? $row['Warehouse'] ?? $row['WAREHOUSE'] ?? 'Main Warehouse';
        // Clean the warehouse name to remove tabs and extra whitespace
        $warehouseName = trim(preg_replace('/\s+/', ' ', $warehouseName));
        $warehouse = $this->getWarehouse($warehouseName);

        // Parse expiry date - try different column name variations
        $expiryDateValue = $row['expiry_date'] ?? $row['Expiry Date'] ?? $row['EXPIRY_DATE'] ?? $row['expiry'] ?? null;
        $expiryDate = $this->parseExpiryDate($expiryDateValue);

        // Location is stored as a STRING (not an id) across inventory tables.
        // Ensure the (warehouse name + location string) exists in locations table; create it if missing.
        // Wrap the entire row write in a transaction to avoid partial writes.
        DB::transaction(function () use ($row, $warehouse, $product, $quantity, $expiryDate) {
            $locationValue = $row['location'] ?? $row['Location'] ?? $row['LOCATION'] ?? null;
            $locationValue = is_string($locationValue) ? trim(preg_replace('/\s+/', ' ', $locationValue)) : null;
            if (!empty($locationValue)) {
                Location::firstOrCreate(
                    [
                        'location' => $locationValue,
                        'warehouse' => $warehouse->name,
                    ],
                    [
                        'location' => $locationValue,
                        'warehouse' => $warehouse->name,
                    ]
                );
            }

            // Create MOH inventory item with flexible column mapping and data cleaning
            MohInventoryItem::create([
                'moh_inventory_id' => $this->mohInventoryId,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => (int) $quantity,
                'expiry_date' => $expiryDate,
                'batch_number' => trim($row['batch_no'] ?? $row['Batch No'] ?? $row['BATCH_NO'] ?? $row['batch_number'] ?? '') ?: null,
                'barcode' => trim($row['barcode'] ?? $row['Barcode'] ?? $row['BARCODE'] ?? '') ?: null,
                'location' => $locationValue ?: null,
                'notes' => trim($row['notes'] ?? $row['Notes'] ?? $row['NOTES'] ?? '') ?: null,
                'uom' => trim($row['uom'] ?? $row['UoM'] ?? $row['UOM'] ?? $row['unit'] ?? '') ?: null,
                'source' => trim($row['source'] ?? $row['Source'] ?? $row['SOURCE'] ?? '') ?: 'Excel Import',
                'unit_cost' => (float) ($row['unit_cost'] ?? $row['Unit Cost'] ?? $row['UNIT_COST'] ?? $row['unit_cost'] ?? $row['UnitCost'] ?? 0),
                'total_cost' => (float) ($row['unit_cost'] ?? $row['Unit Cost'] ?? $row['UNIT_COST'] ?? $row['unit_cost'] ?? $row['UnitCost'] ?? 0) * (float) $quantity,
            ]);

            $this->createdCount++;
        });
        return null;
    }

    protected function getOrCreateProduct($row, $itemName)
    {
        // Try to find existing product by name
        $product = Product::where('name', $itemName)->first();
        
        if ($product) {
            return $product;
        }

        // If product doesn't exist, record for user feedback and skip this row
        $this->recordMissingProduct($itemName);
        return null;
    }

    protected function recordMissingProduct(string $itemName): void
    {
        $cleanName = trim(preg_replace('/\s+/', ' ', $itemName));
        if ($cleanName === '') {
            return;
        }

        $this->missingProductRows++;

        // Keep unique list in-memory (per import)
        if (!isset($this->missingProductsSet[$cleanName])) {
            $this->missingProductsSet[$cleanName] = true;

            // Avoid unbounded growth: keep first 25 unique names
            if (count($this->missingProductsSample) < 25) {
                $this->missingProductsSample[] = $cleanName;
            }
        }
    }


    protected function getWarehouse($warehouseName)
    {
        // Clean the warehouse name (remove tabs, extra spaces, etc.)
        $cleanName = trim($warehouseName);
                
        $warehouse = Warehouse::where('name', $cleanName)->first();
        
        if (!$warehouse) {
            // If not found, throw exception to stop import
            $errorMessage = "Warehouse '{$cleanName}' not found in database. Please add this warehouse first before importing.";
            
            throw new \Exception($errorMessage);
        } 

        return $warehouse;
    }

    protected function parseExpiryDate($expiryDateValue)
    {
        if (empty($expiryDateValue)) {
            return null; // Return null since the field is nullable
        }

        try {
            // Check if it's an Excel serial number (numeric)
            if (is_numeric($expiryDateValue)) {
                // Convert Excel serial number to date
                // Excel dates are days since 1900-01-01
                $excelDate = (int) $expiryDateValue;
                $unixTimestamp = ($excelDate - 25569) * 86400; // Convert to Unix timestamp
                $date = Carbon::createFromTimestamp($unixTimestamp);
                return $date->format('Y-m-d');
            }

            // Try to parse as "M-y" format (Feb-25, Jun-27) - Default format
            try {
                $date = Carbon::createFromFormat('M-y', $expiryDateValue);
                // Set to first day of the month for month-year format
                return $date->startOfMonth()->format('Y-m-d');
            } catch (\Exception $e) {
                // If M-y format fails, continue to try normal date formats
                // Don't throw exception, just continue
            }

            // Try various normal date formats
            $dateFormats = [
                'd-m-Y',    // 15-02-2025
                'Y-m-d',    // 2025-02-15
                'd/m/Y',    // 15/02/2025
                'Y/m/d',    // 2025/02/15
                'm-d-Y',    // 02-15-2025
                'Y-m-d H:i:s', // 2025-02-15 00:00:00
                'd-m-Y H:i:s', // 15-02-2025 00:00:00
            ];

            foreach ($dateFormats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $expiryDateValue);
                    return $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // Continue to next format
                    continue;
                }
            }
            return null;

        } catch (\Exception $e) {
            return null; // Return null since the field is nullable
        }
    }


    public function chunkSize(): int
    {
        return 100;
    }

    public function batchSize(): int
    {
        return 100;
    }
}
