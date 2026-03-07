<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'asset_tag' => $this->asset_tag,
            'asset_name' => $this->asset_name,
            'serial_number' => $this->serial_number,
            'asset_category_id' => $this->asset_category_id,
            'asset_type_id' => $this->asset_type_id,
            'assignee_id' => $this->assignee_id,
            'status' => $this->status,
            'original_value' => $this->original_value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Asset relationship data
            'asset' => [
                'id' => $this->asset->id ?? null,
                'asset_number' => $this->asset->asset_number ?? null,
                'acquisition_date' => $this->asset->acquisition_date ?? null,
                'fund_source_id' => $this->asset->fund_source_id ?? null,
                'region_id' => $this->asset->region_id ?? null,
                'district_id' => $this->asset->district_id ?? null,
                'facility_id' => $this->asset->facility_id ?? null,
                'sub_location_id' => $this->asset->sub_location_id ?? null,
            ],
            
            // Related model data
            'category' => [
                'id' => $this->category->id ?? null,
                'name' => $this->category->name ?? null,
            ],
            
            'type' => [
                'id' => $this->type->id ?? null,
                'name' => $this->type->name ?? null,
            ],
            
            'assignee' => [
                'id' => $this->assignee->id ?? null,
                'name' => $this->assignee->name ?? null,
            ],
            
            'district' => [
                'id' => $this->asset->district->id ?? null,
                'name' => $this->asset->district->name ?? null,
                'region' => $this->asset->district->region ?? null,
            ],
            
            'facility' => [
                'id' => $this->asset->facility->id ?? null,
                'name' => $this->asset->facility->name ?? null,
                'district' => $this->asset->facility->district ?? null,
            ],
            
            'asset_location' => [
                'id' => $this->asset->facility->id ?? null,
                'name' => $this->asset->facility->name ?? null,
            ],
            
            'sub_location' => [
                'id' => $this->asset->subLocation->id ?? null,
                'name' => $this->asset->subLocation->name ?? null,
            ],
            
            'region' => [
                'id' => $this->asset->region->id ?? null,
                'name' => $this->asset->region->name ?? null,
            ],
            
            'fund_source' => [
                'id' => $this->asset->fundSource->id ?? null,
                'name' => $this->asset->fundSource->name ?? null,
            ],
            
            // Depreciation data (with calculated fields for each row)
            'depreciation_data' => $this->resource->getDepreciationDataForResource(),
            
            // Computed fields for backward compatibility
            'name' => $this->asset_name,
            'tag_no' => $this->asset_tag,
            'asset_tag' => $this->asset_tag,
            'serial_number' => $this->serial_number,
            'asset_category_id' => $this->asset_category_id,
            'type_id' => $this->asset_type_id,
            'assignee_id' => $this->assignee_id,
            'district_id' => $this->asset->district_id ?? null,
            'facility_id' => $this->asset->facility_id ?? null,
            'sub_location_id' => $this->asset->sub_location_id ?? null,
            'fund_source_id' => $this->asset->fund_source_id ?? null,
            'region_id' => $this->asset->region_id ?? null,
            'acquisition_date' => $this->asset->acquisition_date ?? null,
            'cost' => $this->original_value,
            'total_cost' => $this->original_value,
            'original_value' => $this->original_value,
            'status' => $this->status,
            
            // Additional computed fields
            'asset_number' => $this->asset->asset_number ?? null,
            'location_name' => $this->asset->facility->name ?? null,
            'sub_location_name' => $this->asset->subLocation->name ?? null,
            'region_name' => $this->asset->region->name ?? null,
            'fund_source_name' => $this->asset->fundSource->name ?? null,
            'category_name' => $this->category->name ?? null,
            'type_name' => $this->type->name ?? null,
            'assignee_name' => $this->assignee->name ?? null,
        ];
    }
}
