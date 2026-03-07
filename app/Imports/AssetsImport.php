<?php

namespace App\Imports;

use App\Models\Asset;
use App\Models\AssetItem;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\FundSource;
use App\Models\Region;
use App\Models\Facility;
use App\Models\SubLocation;
use App\Models\Assignee;
use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Contracts\Queue\ShouldQueue;

class AssetsImport implements ToCollection, WithHeadingRow, WithChunkReading, SkipsOnError, ShouldQueue
{
    use SkipsErrors;

    protected $userId;

    public function __construct($userId = null)
    {
        $this->userId = $userId ?? auth()->id();
    }

    public function collection(Collection $rows)
    {        

        
        foreach ($rows as $index => $row) {            
            // Custom validation - check required fields
            if (empty($row['asset_tag']) || empty($row['asset_name'])) {
                continue;
            }
            
            // Validate other required fields
            $requiredFields = ['category', 'type', 'fund_source', 'region', 'asset_location', 'sub_location'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($row[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                continue;
            }

            try {
                DB::transaction(function () use ($row, $index) {
                    // Create or get related models
                    $category = AssetCategory::firstOrCreate(['name' => trim($row['category'])]);
                    // Check if AssetType exists by name first, then create if needed
                    $type = AssetType::where('name', trim($row['type']))->first();
                    if (!$type) {
                        $type = AssetType::create([
                            'name' => trim($row['type']),
                            'asset_category_id' => $category->id
                        ]);
                    }
                    
                    $region = Region::firstOrCreate(['name' => trim($row['region'])]);
                    $fundSource = FundSource::firstOrCreate(['name' => trim($row['fund_source'])]);
                    
                    $assetLocation = Facility::where('name', trim($row['asset_location']))->first();
                    if (!$assetLocation) {
                        $assetLocation = Facility::create([
                            'name' => trim($row['asset_location']),
                            'district' => 'Unknown',
                            'region' => trim($row['region']) ?? 'Unknown',
                            'facility_type' => 'Other',
                            'has_cold_storage' => 0,
                            'is_active' => 1
                        ]);
                    }
                    $subLocation = SubLocation::firstOrCreate([
                        'name' => trim($row['sub_location']),
                        'facility_id' => $assetLocation->id,
                    ]);

                    // Create or get assignee
                    $assignee = null;
                    if (!empty($row['assignee'])) {
                        $assignee = Assignee::firstOrCreate(
                            ['name' => trim($row['assignee'])],
                            [
                                'email' => null,
                                'phone' => null,
                                'department' => null
                            ]
                        );
                    }

                    // Parse acquisition date
                    $acquisitionDate = null;
                    if (!empty($row['acquisition_date'])) {
                        try {
                            $dateValue = $row['acquisition_date'];
                            
                            // Handle different data types that Excel might send
                            if (is_numeric($dateValue)) {
                                // Handle Excel date serial numbers
                                $acquisitionDate = Date::excelToDateTimeObject($dateValue);
                            } elseif (is_object($dateValue) && method_exists($dateValue, 'format')) {
                                // Already a DateTime object
                                $acquisitionDate = \Carbon\Carbon::instance($dateValue);
                            } else {
                                // Convert to string and parse
                                $dateString = (string) $dateValue;
                                $dateString = trim($dateString);
                                                                
                                // Handle various date formats
                                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateString)) {
                                    // Format: M/D/YYYY or MM/DD/YYYY
                                    $acquisitionDate = \Carbon\Carbon::createFromFormat('n/j/Y', $dateString);
                                } elseif (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $dateString)) {
                                    // Format: YYYY-MM-DD
                                    $acquisitionDate = \Carbon\Carbon::parse($dateString);
                                } elseif (preg_match('/^\d{1,2}-\d{1,2}\/\d{4}$/', $dateString)) {
                                    // Format: M-D/YYYY or MM-DD/YYYY
                                    $acquisitionDate = \Carbon\Carbon::createFromFormat('n-j/Y', $dateString);
                                } else {
                                    // Try Carbon's automatic parsing for other formats
                                    $acquisitionDate = \Carbon\Carbon::parse($dateString);
                                }
                            }
                        } catch (\Exception $e) {
                            $acquisitionDate = now();
                        }
                    } else {
                        $acquisitionDate = now();
                    }

                    // Map status to valid enum values
                    $status = $this->mapStatus($row['status'] ?? 'in_use');

                    // Create the asset
                    $asset = Asset::create([
                        'acquisition_date' => $acquisitionDate,
                        'organization' => auth()->user()->organization ?? 'PSI', // Default to PSI if no organization
                        'fund_source_id' => $fundSource->id,
                        'region_id' => $region->id,
                        'facility_id' => $assetLocation->id,
                        'sub_location_id' => $subLocation->id,
                        'status' => $status,
                        'submitted_by' => $this->userId,
                        'submitted_at' => now(),
                    ]);

                    // Create the asset item
                    AssetItem::create([
                        'asset_id' => $asset->id,
                        'asset_tag' => trim($row['asset_tag']),
                        'asset_name' => trim($row['asset_name']),
                        'serial_number' => !empty($row['serial_number']) ? trim($row['serial_number']) : 'SN-' . uniqid(),
                        'asset_category_id' => $category->id,
                        'asset_type_id' => $type->id,
                        'assignee_id' => $assignee?->id,
                        'status' => $status,
                        'original_value' => is_numeric($row['original_value']) ? (float)$row['original_value'] : 0,
                    ]);
                });

            } catch (\Throwable $e) {
                throw $e;
            }
        }
    }

    public function chunkSize(): int
    {
        return 50;
    }

    /**
     * Map user-friendly status to valid enum values
     */
    private function mapStatus(?string $status): string
    {
        if (empty($status)) {
            return 'in_use';
        }

        $statusMap = [
            'active' => 'functioning',
            'functioning' => 'functioning',
            'in use' => 'in_use',
            'in_use' => 'in_use',
            'inactive' => 'not_functioning',
            'not functioning' => 'not_functioning',
            'not_functioning' => 'not_functioning',
            'maintenance' => 'maintenance',
            'retired' => 'retired',
            'disposed' => 'disposed',
            'pending_approval' => 'pending_approval',
        ];

        return $statusMap[strtolower(trim($status))] ?? 'in_use';
    }
}
