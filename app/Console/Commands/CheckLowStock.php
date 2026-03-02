<?php

namespace App\Console\Commands;

use App\Events\LowStockNotification;
use App\Models\Inventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLowStock extends Command
{
    protected $signature = 'inventory:check-low-stock';
    protected $description = 'Check for low stock items and send notifications';

    public function handle()
    {
        // inventories has no reorder_level or is_active; reorder_level lives in reorder_levels (per product_id)
        $lowStockItems = Inventory::query()
            ->join('reorder_levels', 'reorder_levels.product_id', '=', 'inventories.product_id')
            ->whereColumn('inventories.quantity', '<=', 'reorder_levels.reorder_level')
            ->where('inventories.quantity', '>', 0)
            ->select('inventories.*')
            ->get();

        foreach ($lowStockItems as $inventory) {
            try {
                event(new LowStockNotification($inventory));
                Log::info('Broadcasted low stock notification for inventory ID: ' . $inventory->id);
            } catch (\Exception $e) {
                Log::error('Failed to broadcast low stock notification: ' . $e->getMessage());
            }
        }

        $this->info('Low stock check completed. ' . $lowStockItems->count() . ' items found.');
    }
} 