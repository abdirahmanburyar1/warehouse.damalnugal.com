<?php

namespace App\Console\Commands;

use App\Models\InventoryReport;
use App\Models\InventoryReportItem;
use App\Models\MonthlyQuantityReceived;
use App\Models\ReceivedQuantityItem;
use App\Models\IssueQuantityReport;
use App\Models\IssueQuantityItem;
use Illuminate\Console\Command;

class CleanReportData extends Command
{
    protected $signature = 'reports:clean {--month= : Clean only this month (YYYY-MM); omit to clean all}';
    protected $description = 'Clean report data (inventory, monthly received, issue quantity) so schedules can regenerate from scratch';

    public function handle()
    {
        $month = $this->option('month');
        if ($month && !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Invalid month. Use YYYY-MM.');
            return 1;
        }

        if ($month) {
            $this->info("Cleaning report data for month: {$month}");
        } else {
            $this->warn('Cleaning ALL report data (no --month).');
            if (!$this->confirm('Continue?')) {
                return 0;
            }
        }

        $deleted = [];

        if ($month) {
            $report = InventoryReport::where('month_year', $month)->first();
            if ($report) {
                $n = $report->items()->count();
                $report->items()->delete();
                $report->delete();
                $deleted['inventory_report_items'] = $n;
                $deleted['inventory_reports'] = 1;
            }
            $received = MonthlyQuantityReceived::where('month_year', $month)->first();
            if ($received) {
                $n = $received->items()->count();
                $received->items()->delete();
                $received->delete();
                $deleted['received_quantity_items'] = $n;
                $deleted['monthly_quantity_receiveds'] = 1;
            }
            $issued = IssueQuantityReport::where('month_year', $month)->first();
            if ($issued) {
                $n = $issued->items()->count();
                $issued->items()->delete();
                $issued->delete();
                $deleted['issue_quantity_items'] = $n;
                $deleted['issue_quantity_reports'] = 1;
            }
        } else {
            $deleted['inventory_report_items'] = InventoryReportItem::count();
            InventoryReportItem::query()->delete();
            $deleted['inventory_reports'] = InventoryReport::count();
            InventoryReport::query()->delete();
            $deleted['received_quantity_items'] = ReceivedQuantityItem::count();
            ReceivedQuantityItem::query()->delete();
            $deleted['monthly_quantity_receiveds'] = MonthlyQuantityReceived::count();
            MonthlyQuantityReceived::query()->delete();
            $deleted['issue_quantity_items'] = IssueQuantityItem::count();
            IssueQuantityItem::query()->delete();
            $deleted['issue_quantity_reports'] = IssueQuantityReport::count();
            IssueQuantityReport::query()->delete();
        }

        $this->table(['Table', 'Rows deleted'], collect($deleted)->map(fn ($v, $k) => [$k, $v])->values()->toArray());
        $this->info('Done. Run report schedules or: report:monthly-received-quantities --month=YYYY-MM, report:issue-quantities --month=YYYY-MM, inventory:generate-monthly-report --month=YYYY-MM');
        return 0;
    }
}
