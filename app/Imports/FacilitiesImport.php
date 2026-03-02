<?php

namespace App\Imports;

use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\District;
use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use Carbon\Carbon;

class FacilitiesImport implements
    ToCollection,
    WithHeadingRow,
    WithChunkReading,
    WithEvents
{
    protected int $importedCount = 0;
    protected int $skippedCount = 0;
    protected array $errors = [];
    protected array $facilityTypeCache = [];
    protected array $districtCache = [];
    protected array $regionCache = [];
    protected ?int $handledBy = null;

    /** Columns to update when a facility with the same name already exists */
    protected const UPSERT_UPDATE_COLUMNS = [
        'facility_type',
        'district',
        'region',
        'email',
        'phone',
        'address',
        'handled_by',
        'is_active',
        'has_cold_storage',
        'updated_at',
    ];

    public function __construct()
    {
    }

    public function collection(Collection $rows): void
    {
        $this->resolveHandledBy();

        $batch = [];
        $now = Carbon::now();

        foreach ($rows as $row) {
            $rowArray = $row instanceof Collection ? $row->toArray() : (array) $row;
            $prepared = $this->prepareRow($rowArray, $now);
            if ($prepared !== null) {
                $batch[] = $prepared;
                $this->importedCount++;
            }
        }

        if (!empty($batch)) {
            Facility::upsert($batch, ['name'], self::UPSERT_UPDATE_COLUMNS);
        }
    }

    protected function resolveHandledBy(): void
    {
        if ($this->handledBy !== null) {
            return;
        }
        $user = \App\Models\User::first();
        if ($user) {
            $this->handledBy = $user->id;
        } else {
            $user = \App\Models\User::create([
                'name' => 'System Admin',
                'username' => 'system_admin',
                'email' => 'admin@warehouse.com',
                'password' => bcrypt('password'),
                'title' => 'System Administrator',
                'is_active' => true,
            ]);
            $this->handledBy = $user->id;
        }
    }

    /**
     * Prepare a single row for upsert. Returns attribute array or null if skipped.
     */
    protected function prepareRow(array $row, Carbon $now): ?array
    {
        if (empty($row['facility_name'])) {
            $this->skippedCount++;
            return null;
        }

        $facilityName = trim($row['facility_name']);

        if (strlen($facilityName) > 255) {
            $this->errors[] = "Facility name too long: " . substr($facilityName, 0, 50) . "...";
            $this->skippedCount++;
            return null;
        }

        // Facility type
        $facilityType = $this->resolveFacilityType($row, $facilityName);
        if ($facilityType === null) {
            return null;
        }

        // Region
        $region = $this->resolveRegion($row, $facilityName);
        if ($region === null) {
            return null;
        }

        // District (may derive region)
        $district = $this->resolveDistrict($row, $region, $facilityName);
        if ($district === null) {
            return null;
        }

        if (empty($region)) {
            $region = 'Default Region';
            $regionModel = Region::firstOrCreate(['name' => $region]);
            $region = $regionModel->name;
        }

        // Email
        $email = null;
        if (!empty($row['email'])) {
            $email = trim($row['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Invalid email format for facility: " . $facilityName;
                $this->skippedCount++;
                return null;
            }
        }

        // Phone
        $phone = null;
        if (!empty($row['phone'])) {
            $phone = trim($row['phone']);
            if (strlen($phone) > 20) {
                $this->errors[] = "Phone number too long for facility: " . $facilityName;
                $this->skippedCount++;
                return null;
            }
        }

        if (empty($facilityType) || empty($district) || empty($region) || $this->handledBy === null) {
            $this->errors[] = "Missing required fields for facility: " . $facilityName;
            $this->skippedCount++;
            return null;
        }

        return [
            'name' => $facilityName,
            'facility_type' => $facilityType,
            'district' => $district,
            'region' => $region,
            'email' => $email,
            'phone' => $phone,
            'address' => !empty($row['address']) ? trim($row['address']) : null,
            'handled_by' => $this->handledBy,
            'is_active' => true,
            'has_cold_storage' => false,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
    }

    protected function resolveFacilityType(array $row, string $facilityName): ?string
    {
        if (empty($row['facility_type'])) {
            return null;
        }
        $typeName = trim($row['facility_type']);
        if (strlen($typeName) > 255) {
            $this->errors[] = "Facility type name too long: " . substr($typeName, 0, 50) . "...";
            $this->skippedCount++;
            return null;
        }
        if (!isset($this->facilityTypeCache[$typeName])) {
            $model = FacilityType::firstOrCreate(
                ['name' => $typeName],
                ['is_active' => true]
            );
            $this->facilityTypeCache[$typeName] = $model->name;
        }
        return $this->facilityTypeCache[$typeName];
    }

    protected function resolveRegion(array $row, string $facilityName): ?string
    {
        if (!empty($row['region'])) {
            $regionName = trim($row['region']);
            if (strlen($regionName) > 255) {
                $this->errors[] = "Region name too long: " . substr($regionName, 0, 50) . "...";
                $this->skippedCount++;
                return null;
            }
            if (!isset($this->regionCache[$regionName])) {
                $model = Region::firstOrCreate(['name' => $regionName]);
                $this->regionCache[$regionName] = $model->name;
            }
            return $this->regionCache[$regionName];
        }
        return null;
    }

    protected function resolveDistrict(array $row, ?string $region, string $facilityName): ?string
    {
        if (empty($row['district'])) {
            return null;
        }
        $districtName = trim($row['district']);
        if (strlen($districtName) > 255) {
            $this->errors[] = "District name too long: " . substr($districtName, 0, 50) . "...";
            $this->skippedCount++;
            return null;
        }

        if (empty($region)) {
            $derivedRegion = Region::where('name', 'like', '%' . $districtName . '%')->first();
            if ($derivedRegion) {
                $region = $derivedRegion->name;
            } else {
                $region = 'Region ' . $districtName;
                $regionModel = Region::firstOrCreate(['name' => $region]);
                $region = $regionModel->name;
            }
        }

        if (!isset($this->districtCache[$districtName])) {
            $districtModel = District::firstOrCreate(
                ['name' => $districtName],
                ['region' => $region]
            );
            $this->districtCache[$districtName] = $districtModel->name;
        }
        return $this->districtCache[$districtName];
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                Log::info('Facilities import completed', [
                    'imported' => $this->importedCount,
                    'skipped' => $this->skippedCount,
                    'errors' => count($this->errors),
                ]);
            },
        ];
    }

    public function getResults(): array
    {
        return [
            'imported' => $this->importedCount,
            'skipped' => $this->skippedCount,
            'errors' => $this->errors,
        ];
    }
}
