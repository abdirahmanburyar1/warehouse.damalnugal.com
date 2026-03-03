<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivedBackorderResource extends JsonResource
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
        return $array;
    }

    /**
     * Resolve display label for source (e.g. "Packing list: PKL-000001-001").
     */
    private function resolveSourceDisplay(): ?string
    {
        if ($this->resource->packing_list_id || $this->resource->packing_list_number) {
            $num = $this->resource->packing_list_number
                ?? ($this->resource->relationLoaded('packingList') && $this->resource->packingList
                    ? $this->resource->packingList->packing_list_number
                    : null);
            return $num ? 'Packing list: ' . $num : null;
        }
        if ($this->resource->transfer_id && $this->resource->relationLoaded('transfer') && $this->resource->transfer) {
            return 'Transfer: ' . ($this->resource->transfer->transferID ?? '');
        }
        if ($this->resource->order_id && $this->resource->relationLoaded('order') && $this->resource->order) {
            return 'Order: ' . ($this->resource->order->order_number ?? '');
        }
        return null;
    }
} 