<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiquidateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $array = parent::toArray($request);
        $array['source_display'] = $this->resolveSourceDisplay();
        $array['liquidated_by_name'] = $this->resource->liquidatedBy?->name;
        return $array;
    }

    /**
     * Resolve display label for source (e.g. "Packing list: PKL-000001-001").
     */
    private function resolveSourceDisplay(): string
    {
        $source = $this->resource->source ?? '';
        if ($this->resource->relationLoaded('packingList') && $this->resource->packingList) {
            return 'Packing list: ' . ($this->resource->packingList->packing_list_number ?? $source);
        }
        if ($this->resource->relationLoaded('transfer') && $this->resource->transfer) {
            return 'Transfer: ' . ($this->resource->transfer->transferID ?? $source);
        }
        if ($this->resource->relationLoaded('order') && $this->resource->order) {
            return 'Order: ' . ($this->resource->order->order_number ?? $source);
        }
        return ucfirst(str_replace('_', ' ', $source ?: 'N/A'));
    }
}
