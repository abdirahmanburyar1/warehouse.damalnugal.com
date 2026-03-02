<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Dosage;
use App\Models\EligibleItem;
use App\Models\FacilityType;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;

class ProductsImport implements
    ToModel,
    WithHeadingRow,
    WithChunkReading,
    WithBatchInserts,
    WithValidation,
    SkipsEmptyRows,
    WithEvents
{
    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $errors = [];
    protected $categoryCache = [];
    protected $dosageCache = [];
    protected $facilityTypeCache = [];

    public function __construct()
    {
    }

    public function model(array $row)
    {
        try {
            if (empty($row['item_description'])) {
                $this->skippedCount++;
                return null;
            }

            $itemName = trim($row['item_description']);

            if (strlen($itemName) > 255) {
                $this->errors[] = "Item description too long: " . substr($itemName, 0, 50) . "...";
                $this->skippedCount++;
                return null;
            }

            // Category
            $categoryId = null;
            if (!empty($row['category'])) {
                $category = trim($row['category']);
                if (strlen($category) > 255) {
                    $this->errors[] = "Category name too long: " . substr($category, 0, 50) . "...";
                    $this->skippedCount++;
                    return null;
                }

                if (!isset($this->categoryCache[$category])) {
                    $categoryModel = Category::firstOrCreate(
                        ['name' => $category],
                        ['is_active' => true]
                    );
                    $this->categoryCache[$category] = $categoryModel->id;
                }
                $categoryId = $this->categoryCache[$category];
            }

            // Dosage
            $dosageId = null;
            if (!empty($row['dosage_form'])) {
                $dosageForm = trim($row['dosage_form']);
                if (strlen($dosageForm) > 255) {
                    $this->errors[] = "Dosage form name too long: " . substr($dosageForm, 0, 50) . "...";
                    $this->skippedCount++;
                    return null;
                }

                if (!isset($this->dosageCache[$dosageForm])) {
                    $dosageModel = Dosage::firstOrCreate(['name' => $dosageForm]);
                    $this->dosageCache[$dosageForm] = $dosageModel->id;
                }
                $dosageId = $this->dosageCache[$dosageForm];
            }

            $this->importedCount++;

            $product = Product::updateOrCreate([
                'name' => $itemName,
            ], [
                'name' => $itemName,
                'category_id' => $categoryId,
                'dosage_id' => $dosageId,
                'is_active' => true,
            ]);

            // Handle eligibility levels
            $this->handleEligibilityLevels($product, $row);

        } catch (\Exception $e) {
            $this->errors[] = "Error processing row: " . $e->getMessage();
            $this->skippedCount++;
            throw $e;
        }
    }

    /**
     * Handle eligibility levels for a product
     */
    protected function handleEligibilityLevels(Product $product, array $row)
    {
        if (empty($row['eligibility_level'])) {
            return;
        }

        $eligibilityLevels = trim($row['eligibility_level']);
        
        if (empty($eligibilityLevels)) {
            return;
        }

        // Split by comma and clean up each level
        $levels = array_map('trim', explode(',', $eligibilityLevels));
        $levels = array_filter($levels); // Remove empty values

        // Clear existing eligibility items for this product to avoid duplicates
        EligibleItem::where('product_id', $product->id)->delete();

        // Create new eligibility items
        foreach ($levels as $level) {
            if (!empty($level)) {
                // Check if facility type exists in FacilityType model, create if not
                if (!isset($this->facilityTypeCache[$level])) {
                    $facilityTypeModel = FacilityType::firstOrCreate(
                        ['name' => $level],
                        ['is_active' => true]
                    );
                    $this->facilityTypeCache[$level] = $facilityTypeModel->name;
                }
                
                // Use the exact name from the FacilityType model
                $facilityTypeName = $this->facilityTypeCache[$level];
                
                EligibleItem::create([
                    'product_id' => $product->id,
                    'facility_type' => $facilityTypeName,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'item_description' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'dosage_form' => 'nullable|string|max:255',
            'eligibility_level' => 'nullable|string',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                Log::info('Product import completed', [
                    'imported' => $this->importedCount,
                    'skipped' => $this->skippedCount,
                    'errors' => count($this->errors),
                ]);
            },
        ];
    }

    public function getResults()
    {
        return [
            'imported' => $this->importedCount,
            'skipped' => $this->skippedCount,
            'errors' => $this->errors,
        ];
    }
}
