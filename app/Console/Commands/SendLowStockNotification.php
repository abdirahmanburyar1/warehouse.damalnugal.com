<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\LowStockNotification;
use App\Events\LowStockAlert;

class SendLowStockNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:notify-low-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email notification for low stock items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get all low stock items (reorder_level is in reorder_levels table, not inventories)
        $lowStockItems = Inventory::query()
            ->join('reorder_levels', 'reorder_levels.product_id', '=', 'inventories.product_id')
            ->whereColumn('inventories.quantity', '<=', 'reorder_levels.reorder_level')
            ->where('inventories.quantity', '>', 0)
            ->select('inventories.*')
            ->with(['product'])
            ->get();

        if ($lowStockItems->count() > 0) {
            // Send email notification
            Mail::to('buryar313@gmail.com')->send(new LowStockNotification($lowStockItems));
            // Mail::to('faysal@gmail.com')->send(new LowStockNotification($lowStockItems));
            
            // Broadcast real-time events for each low stock item
            event(new LowStockAlert());
            $this->info("Broadcasted low stock alert for");
            
            $this->info('Low stock notification email sent successfully.');
        } else {
            $this->info('No low stock items found.');
        }

        return Command::SUCCESS;
    }
}
