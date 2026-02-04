<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Warehouse;
use App\Models\Location;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Carbon\Carbon;

class UploadInventory implements 
    ToModel, 
    WithHeadingRow, 
    WithChunkReading, 
    WithBatchInserts, 
    SkipsEmptyRows
{
    public int $createdCount = 0;
    public int $missingProductRows = 0;
    /** @var array<string,true> */
    private array $missingProductsSet = [];
    /** @var string[] */
    public array $missingProductsSample = [];

    public function model(array $row)
    {
        try {
            // Required fields (support multiple header variations)
            $itemName = $row['item'] ?? $row['Item'] ?? null;
            $quantity = $row['quantity'] ?? $row['Quantity'] ?? null;
            $batchNumber = $row['batch_no'] ?? $row['Batch No'] ?? $row['BATCH_NO'] ?? $row['batch_number'] ?? null;
            $expiryDateValue = $row['expiry_date'] ?? $row['Expiry Date'] ?? $row['EXPIRY_DATE'] ?? $row['expiry'] ?? null;

            // Quantity may be "0" which empty() treats as falsey; allow 0.
            if (empty($itemName) || $quantity === null || $quantity === '' || empty($batchNumber) || empty($expiryDateValue)) {
                return null;
            }

            $product = $this->getProduct((string) $itemName);
            if (!$product) {
                return null;
            }
            $inventory = $this->getInventory($product->id);

            $expiryDate = $this->parseExpiryDate($expiryDateValue);
            $batchNumber = trim((string) $batchNumber);

            // Optional fields (MOH-style template)
            $source = trim((string) ($row['source'] ?? $row['Source'] ?? $row['SOURCE'] ?? '')) ?: 'Excel Import';
            $uom = trim((string) ($row['uom'] ?? $row['UoM'] ?? $row['UOM'] ?? $row['unit'] ?? '')) ?: null;
            $barcode = trim((string) ($row['barcode'] ?? $row['Barcode'] ?? $row['BARCODE'] ?? '')) ?: null;
            $locationValue = $row['location'] ?? $row['Location'] ?? $row['LOCATION'] ?? null;
            $locationValue = is_string($locationValue) ? trim(preg_replace('/\s+/', ' ', $locationValue)) : null;

            // Warehouse: support new template column, but fallback to legacy warehouse_id=1
            $warehouseName = $row['warehouse'] ?? $row['Warehouse'] ?? $row['WAREHOUSE'] ?? null;
            $warehouseId = 1;
            $warehouseDisplayName = null;
            if ($warehouseName !== null && $warehouseName !== '') {
                $warehouseDisplayName = trim(preg_replace('/\s+/', ' ', (string) $warehouseName));
                $warehouse = Warehouse::where('name', $warehouseDisplayName)->first();
                if (!$warehouse) {
                    throw new \Exception("Warehouse '{$warehouseDisplayName}' not found in database. Please add this warehouse first before importing.", 422);
                }
                $warehouseId = $warehouse->id;
            } else {
                $warehouse = Warehouse::find($warehouseId);
                $warehouseDisplayName = $warehouse?->name;
            }

            // Unit cost
            $unitCostRaw = $row['unit_cost'] ?? $row['Unit Cost'] ?? $row['UNIT_COST'] ?? $row['UnitCost'] ?? null;
            $unitCost = $unitCostRaw !== null && $unitCostRaw !== '' ? (float) $unitCostRaw : 0.0;
            $rowQuantity = (float) $quantity;
            $rowTotalCost = $unitCost * $rowQuantity;

            // Ensure (warehouse name + location string) exists in locations table (location stored as string)
            if (!empty($locationValue) && !empty($warehouseDisplayName)) {
                Location::firstOrCreate(
                    ['location' => $locationValue, 'warehouse' => $warehouseDisplayName],
                    ['location' => $locationValue, 'warehouse' => $warehouseDisplayName]
                );
            }

            // Wrap row write in a transaction to avoid partial updates
            DB::transaction(function () use (
                $inventory,
                $product,
                $warehouseId,
                $batchNumber,
                $expiryDate,
                $locationValue,
                $barcode,
                $uom,
                $source,
                $rowQuantity,
                $unitCost,
                $rowTotalCost
            ) {
                $existingItem = InventoryItem::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->where('batch_number', $batchNumber)
                    ->where('expiry_date', $expiryDate)
                    ->where('location', $locationValue)
                    ->first();

                if ($existingItem) {
                    $existingItem->increment('quantity', $rowQuantity);

                    // Best-effort metadata fill/update
                    if (empty($existingItem->source) && !empty($source)) {
                        $existingItem->source = $source;
                    }
                    if (empty($existingItem->uom) && !empty($uom)) {
                        $existingItem->uom = $uom;
                    }
                    if (empty($existingItem->barcode) && !empty($barcode)) {
                        $existingItem->barcode = $barcode;
                    }
                    if (!empty($unitCost) && (empty($existingItem->unit_cost) || (float) $existingItem->unit_cost <= 0)) {
                        $existingItem->unit_cost = $unitCost;
                    }
                    if (!empty($rowTotalCost)) {
                        $existingItem->total_cost = ((float) ($existingItem->total_cost ?? 0)) + $rowTotalCost;
                    }
                    $existingItem->save();
                } else {
                    InventoryItem::create([
                        'inventory_id' => $inventory->id,
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouseId,
                        'quantity' => $rowQuantity,
                        'expiry_date' => $expiryDate,
                        'batch_number' => $batchNumber,
                        'barcode' => $barcode,
                        'location' => $locationValue,
                        'uom' => $uom,
                        'source' => $source,
                        'unit_cost' => $unitCost ?: null,
                        'total_cost' => $rowTotalCost ?: null,
                    ]);
                }

                // Update product-level inventory quantity (int column; increment with rounded qty)
                $inventory->increment('quantity', (int) round($rowQuantity));
                $this->createdCount++;
            });

            return null;

        } catch (\Throwable $e) {
            Log::error('Inventory import error', [
                'error' => $e->getMessage(),
                'row' => $row
            ]);

            // Bubble up 422-style errors (e.g., missing warehouse) to stop import and rollback outer transaction.
            if ((int) $e->getCode() === 422) {
                throw $e;
            }

            return null;
        }
    }


    protected function getProduct($itemName)
    {
        $product = Product::where('name', $itemName)->first();
        if ($product) {
            return $product;
        }

        $this->recordMissingProduct($itemName);
        return null;
    }

    protected function recordMissingProduct(string $itemName): void
    {
        $cleanName = trim(preg_replace('/\s+/', ' ', $itemName));
        if ($cleanName === '') return;

        $this->missingProductRows++;

        if (!isset($this->missingProductsSet[$cleanName])) {
            $this->missingProductsSet[$cleanName] = true;
            if (count($this->missingProductsSample) < 25) {
                $this->missingProductsSample[] = $cleanName;
            }
        }
    }

    protected function getInventory($productId)
    {
        $inventory = Inventory::where('product_id', $productId)->first();
        if (!$inventory) {
            $inventory = Inventory::create([
                'product_id' => $productId,
                'quantity' => 0,
            ]);
        }

        return $inventory;
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
        return 100; // Increased chunk size for better performance
    }

    public function batchSize(): int
    {
        return 50; // Reduced batch size to avoid memory issues
    }
}
