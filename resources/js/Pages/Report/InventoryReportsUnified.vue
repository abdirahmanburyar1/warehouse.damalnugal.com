<template>
    <Head title="Reports" />
    <AuthenticatedLayout
        title="Reports"
        description="Generate and view all warehouse reports"
        img="/assets/images/report.png"
    >
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Reports
            </h2>
        </template>

        <div class="py-5">
            <!-- Filters: one column layout; Report Period section has Report Period (top), then Year and Month on one row -->
            <div class="bg-emerald-50/90 border border-emerald-200 rounded-lg shadow-sm p-6 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Region</label>
                        <select
                            v-model="filters.region_id"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2"
                        >
                            <option :value="null">Region</option>
                            <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select District</label>
                        <select
                            v-model="filters.district_id"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2"
                            :disabled="!filters.region_id"
                        >
                            <option :value="null">District</option>
                            <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ isProductReport ? 'Select Facility' : 'Select Warehouse/Facility Name' }}
                        </label>
                        <select
                            v-model="filters.warehouse_or_facility"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2 disabled:bg-gray-100 disabled:cursor-not-allowed"
                            :disabled="!filters.region_id || !filters.district_id"
                        >
                            <option :value="''">{{ !filters.region_id ? 'Select Region first' : !filters.district_id ? 'Select District first' : (isProductReport ? 'Facility' : 'Warehouse/Facility') }}</option>
                            <template v-if="isProductReport">
                                <option v-for="f in filteredFacilities" :key="'f-' + f.id" :value="'facility:' + f.id">
                                    {{ f.name }}
                                </option>
                            </template>
                            <template v-else>
                                <optgroup label="Warehouses">
                                    <option v-for="w in filteredWarehouses" :key="'w-' + w.id" :value="'warehouse:' + w.id">
                                        {{ w.name }}
                                    </option>
                                </optgroup>
                                <optgroup label="Facilities">
                                    <option v-for="f in filteredFacilities" :key="'f-' + f.id" :value="'facility:' + f.id">
                                        {{ f.name }}
                                    </option>
                                </optgroup>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Report Type</label>
                        <select
                            v-model="filters.report_type"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2"
                        >
                            <option value="">Report Type</option>
                            <option v-for="rt in reportTypes" :key="rt.value" :value="rt.value">{{ rt.label }}</option>
                        </select>
                    </div>
                    <!-- Report Period column: Report Period (top), Year + Month (one row) -->
                    <div class="flex flex-col gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Report Period</label>
                            <select
                                v-model="filters.report_period"
                                class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2"
                            >
                                <option
                                    v-for="opt in reportPeriodOptionsList"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-row gap-3 items-end flex-wrap">
                            <div class="flex-1 min-w-[100px]">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                <select
                                    v-model="filters.year"
                                    class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2"
                                >
                                    <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>
                            <div v-if="periodOptions.length > 1" class="flex-1 min-w-[100px]">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                                <select
                                    v-model="filters.periodValue"
                                    class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2"
                                >
                                    <option
                                        v-for="opt in periodOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Generate Report button -->
                <div class="mt-4">
                    <button
                        type="button"
                        @click="generateReport"
                        :disabled="generating"
                        class="inline-flex justify-center items-center px-6 py-2.5 bg-emerald-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
                    >
                        <span v-if="generating">Generating...</span>
                        <span v-else>Generate Report</span>
                    </button>
                </div>
            </div>

            <!-- Search -->
            <div class="mb-4">
                <label class="sr-only">Search</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="block w-full rounded-md border-gray-300 pl-10 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm"
                        placeholder="Search Facility/Item Name"
                    />
                </div>
            </div>

            <!-- Report Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                <div v-if="generating" class="p-8 text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600 mx-auto"></div>
                    <p class="mt-4 text-gray-600">Loading report...</p>
                </div>
                <div v-else-if="!hasAnyData && hasGenerated" class="p-8 text-center text-gray-600 max-w-xl mx-auto">
                    <p>{{ reportMessage || 'No data found for the selected filters. Try a different report type or period.' }}</p>
                    <p v-if="filters.report_type === 'warehouse_inventory'" class="mt-3 text-sm text-gray-500">
                        For Warehouse Inventory Report: select Report Period (year and month), then Generate Report. You may optionally filter by Region, District, or Warehouse. If you see no data, generate the monthly reports first in Settings → Report Schedules (Monthly received quantities → Issue quantities → Inventory monthly report).
                    </p>
                    <p v-else-if="filters.report_type === 'facility_monthly_consumption'" class="mt-3 text-sm text-gray-500">
                        For Facility LMIS report: select Report Period (year and month) and at least one Facility (or Region/District), then Generate Report. Data comes from facility monthly reports for the selected period.
                    </p>
                    <p v-else-if="filters.report_type === 'report_submission_rate'" class="mt-3 text-sm text-gray-500">
                        Select Report Period (monthly, bi-monthly, quarterly, six months, or yearly), Year and Month, and at least one Region, District or Facility. Data is based on facility monthly report submission dates.
                    </p>
                </div>

                <!-- Report Submission Rate: table -->
                <div v-else-if="isSubmissionRateReport && filteredSubmissionRateRows.length > 0" class="overflow-x-auto">
                    <table class="min-w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">Facility Name</th>
                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Total Number of Expected Reports to Submit</th>
                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Total Number of Actual Submitted Reports</th>
                                <th class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Report Submission Rate</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Report Submitting Time Status</th>
                            </tr>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-300"></th>
                                <th class="border border-gray-300"></th>
                                <th class="border border-gray-300"></th>
                                <th class="border border-gray-300"></th>
                                <th class="px-3 py-1 text-right text-xs font-medium text-gray-600 border border-gray-300">On-time Report Submission</th>
                                <th class="px-3 py-1 text-right text-xs font-medium text-gray-600 border border-gray-300">Late Report Submission</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <tr v-for="(row, index) in filteredSubmissionRateRows" :key="index" class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">{{ row.facility_name }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.expected }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.actual }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.submission_rate }}%</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.on_time_count }} ({{ row.on_time_pct }}%)</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.late_count }} ({{ row.late_pct }}%)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Product Report: Tabs (Charts | Table) -->
                <div v-else-if="isProductReport && filteredProductRows.length > 0" class="space-y-4">
                    <nav class="flex gap-4" aria-label="Tabs">
                        <button
                            type="button"
                            @click="productReportTab = 'charts'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="productReportTab === 'charts' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Charts
                        </button>
                        <button
                            type="button"
                            @click="productReportTab = 'table'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="productReportTab === 'table' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Table
                        </button>
                    </nav>

                    <!-- Charts tab (PrimeVue Chart) -->
                    <div v-show="productReportTab === 'charts'" class="space-y-10">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                            <!-- Category: vertical bar chart (reference style) -->
                            <div class="bg-white p-8 pt-10">
                                <div class="min-h-[2.5rem] mb-4 flex items-center text-sm font-bold text-black">Chart 1</div>
                                <h3 class="text-center text-lg font-bold text-black mb-10">Category</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="productReportCategoryChartData.labels?.length"
                                        type="bar"
                                        :data="productReportCategoryChartData"
                                        :options="productReportVerticalBarOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No category data</div>
                                </div>
                            </div>
                            <!-- Supply Class: horizontal bar chart (reference style) -->
                            <div class="bg-white p-8 pt-10">
                                <div class="min-h-[2.5rem] mb-4 flex items-center text-sm font-bold text-black">Chart 2</div>
                                <h3 class="text-center text-lg font-bold text-black mb-10">Supply Class</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="productReportSupplyClassChartData.labels?.length"
                                        type="bar"
                                        :data="productReportSupplyClassChartData"
                                        :options="productReportHorizontalBarOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No supply class data</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table tab -->
                    <div v-show="productReportTab === 'table'" class="overflow-x-auto">
                        <table class="min-w-full border-collapse border border-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">Name</th>
                                    <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">Level</th>
                                    <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Total<br>Products</th>
                                    <th v-if="categoryColumns.length" :colspan="categoryColumns.length" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 bg-emerald-50">Category</th>
                                    <th v-if="supplyClassColumns.length" :colspan="supplyClassColumns.length" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 bg-blue-50">Supply Class</th>
                                </tr>
                                <tr class="bg-gray-50">
                                    <th v-for="cat in categoryColumns" :key="'cat-' + cat" class="px-3 py-1 text-right text-xs font-medium text-gray-600 border border-gray-300 bg-emerald-50 whitespace-nowrap">{{ cat }}</th>
                                    <th v-for="sc in supplyClassColumns" :key="'sc-' + sc" class="px-3 py-1 text-right text-xs font-medium text-gray-600 border border-gray-300 bg-blue-50 whitespace-nowrap">{{ sc }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <tr v-for="(row, index) in filteredProductRows" :key="index" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">{{ row.name }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-600 border border-gray-300 capitalize">{{ row.type }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 font-medium">{{ formatNum(row.total_products) }}</td>
                                    <td v-for="cat in categoryColumns" :key="'cat-val-' + cat" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.categories[cat] || 0) }}</td>
                                    <td v-for="sc in supplyClassColumns" :key="'sc-val-' + sc" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.supply_classes[sc] || 0) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-100 font-semibold">
                                <tr>
                                    <td colspan="2" class="px-3 py-2 text-sm text-gray-900 border border-gray-300">Total</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(filteredProductRows.reduce((s, r) => s + (r.total_products || 0), 0)) }}</td>
                                    <td v-for="cat in categoryColumns" :key="'cat-total-' + cat" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(filteredProductRows.reduce((s, r) => s + (r.categories[cat] || 0), 0)) }}</td>
                                    <td v-for="sc in supplyClassColumns" :key="'sc-total-' + sc" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(filteredProductRows.reduce((s, r) => s + (r.supply_classes[sc] || 0), 0)) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Liquidation & Disposal: Tabs (Charts | Table) -->
                <div v-else-if="isLiquidationDisposalReport && filteredRows.length > 0" class="space-y-4">
                    <nav class="flex gap-4" aria-label="Tabs">
                        <button
                            type="button"
                            @click="liquidationDisposalTab = 'charts'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="liquidationDisposalTab === 'charts' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Charts
                        </button>
                        <button
                            type="button"
                            @click="liquidationDisposalTab = 'table'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="liquidationDisposalTab === 'table' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Table
                        </button>
                    </nav>

                    <!-- Liquidation & Disposal Charts tab -->
                    <div v-show="liquidationDisposalTab === 'charts'" class="space-y-10">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                            <div class="bg-white p-8 pt-10">
                                <div class="min-h-[2.5rem] mb-4 flex items-center text-sm font-bold text-black">Chart 1</div>
                                <h3 class="text-center text-lg font-bold text-black mb-10">Liquidation & Disposal Status</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="liquidationDisposalStatusChartData.labels?.length"
                                        type="bar"
                                        :data="liquidationDisposalStatusChartData"
                                        :options="liquidationDisposalChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                            <div class="bg-white p-8 pt-10">
                                <div class="min-h-[2.5rem] mb-4 flex items-center text-sm font-bold text-black">Chart 2</div>
                                <h3 class="text-center text-lg font-bold text-black mb-10">Reasons for Liquidation</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="liquidationReasonsChartData.labels?.length"
                                        type="bar"
                                        :data="liquidationReasonsChartData"
                                        :options="liquidationDisposalChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                            <div class="bg-white p-8 pt-10">
                                <div class="min-h-[2.5rem] mb-4 flex items-center text-sm font-bold text-black">Chart 3</div>
                                <h3 class="text-center text-lg font-bold text-black mb-10">Reasons for Disposal</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="disposalReasonsChartData.labels?.length"
                                        type="bar"
                                        :data="disposalReasonsChartData"
                                        :options="liquidationDisposalChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liquidation & Disposal Table tab -->
                    <div v-show="liquidationDisposalTab === 'table'" class="overflow-x-auto">
                    <table class="min-w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">Warehouse Name</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Total Liquated Items</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Total Disposed Items</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Reasons for Liquidation</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Reasons for Disposal</th>
                            </tr>
                            <tr class="bg-gray-100">
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Item No.</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Total Value</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Item No.</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Total Value</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Missing</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Lost</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Damage</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Expired</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <tr v-for="(row, index) in filteredRows" :key="index" class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">{{ row.warehouse_name }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.total_liquated_item_no) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ (row.total_liquated_value != null && row.total_liquated_value !== '') ? formatCost(row.total_liquated_value) + '$' : '–' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.total_disposed_item_no) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ (row.total_disposed_value != null && row.total_disposed_value !== '') ? formatCost(row.total_disposed_value) + '$' : '–' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.liquidation_missing) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.liquidation_lost) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.disposal_damage) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.disposal_expired) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Facilities Report: Tabs (Charts | Table) -->
                <div v-else-if="isFacilitiesReport && filteredRows.length > 0" class="space-y-4">
                    <nav class="flex gap-4" aria-label="Tabs">
                        <button
                            type="button"
                            @click="facilitiesReportTab = 'charts'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="facilitiesReportTab === 'charts' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Charts
                        </button>
                        <button
                            type="button"
                            @click="facilitiesReportTab = 'table'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="facilitiesReportTab === 'table' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Table
                        </button>
                    </nav>

                    <!-- Facilities Report Charts -->
                    <div v-show="facilitiesReportTab === 'charts'" class="space-y-10">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                            <div class="bg-white p-8 pt-10">
                                <div class="min-h-[2.5rem] mb-4 flex items-center text-sm font-bold text-black">Chart 1</div>
                                <h3 class="text-center text-lg font-bold text-black mb-10">Facility Type</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="facilitiesReportTypeChartData.labels?.length"
                                        type="bar"
                                        :data="facilitiesReportTypeChartData"
                                        :options="facilitiesReportTypeChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                            <div class="bg-white p-8 pt-10">
                                <div class="min-h-[2.5rem] mb-4 flex items-center text-sm font-bold text-black">Chart 2</div>
                                <h3 class="text-center text-lg font-bold text-black mb-10">Facility Activation Status</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="facilitiesReportActivationChartData.labels?.length"
                                        type="doughnut"
                                        :data="facilitiesReportActivationChartData"
                                        :options="facilitiesReportDonutOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Facilities Report Table -->
                    <div v-show="facilitiesReportTab === 'table'" class="overflow-x-auto">
                    <table class="min-w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300 align-middle">District Name</th>
                                <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Total Number of Facilities</th>
                                <th v-if="facilitiesReportTypeColumns.length" :colspan="facilitiesReportTypeColumns.length" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Facility Type</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Facility Activation Status</th>
                                <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Number of Cold Storage Available</th>
                            </tr>
                            <tr class="bg-gray-100">
                                <th v-for="col in facilitiesReportTypeColumns" :key="col" class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300 whitespace-nowrap">{{ col }}</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Active</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Not Active</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <tr v-for="(row, index) in filteredRows" :key="index" class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">{{ row.district_name }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.total_facilities) }}</td>
                                <td v-for="col in facilitiesReportTypeColumns" :key="col" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(facilityTypeValue(row, col)) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.active) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.not_active) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.cold_storage_count) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Order Report: Tabs (Charts | Table) -->
                <div v-else-if="isOrderReport && filteredRows.length > 0" class="space-y-4">
                    <nav class="flex gap-4" aria-label="Tabs">
                        <button
                            type="button"
                            @click="orderReportTab = 'charts'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="orderReportTab === 'charts' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Charts
                        </button>
                        <button
                            type="button"
                            @click="orderReportTab = 'table'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="orderReportTab === 'table' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Table
                        </button>
                    </nav>

                    <div v-show="orderReportTab === 'charts'" class="space-y-10">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                            <div class="bg-white p-8 pt-10">
                                <h3 class="text-center text-lg font-bold text-black mb-10">Order Status</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="orderStatusChartData.labels?.length"
                                        type="bar"
                                        :data="orderStatusChartData"
                                        :options="orderStatusChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                            <div class="bg-white p-8 pt-10">
                                <h3 class="text-center text-lg font-bold text-black mb-10">Order Delivery Status</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="orderDeliveryChartData.labels?.length"
                                        type="bar"
                                        :data="orderDeliveryChartData"
                                        :options="orderDeliveryChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-show="orderReportTab === 'table'" class="overflow-x-auto">
                        <table class="min-w-full border-collapse border border-gray-300">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300 align-middle">Facility Name</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Total Orders</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Completed Orders</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Rejected Orders</th>
                                    <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Order Delivery Status</th>
                                    <th colspan="3" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Order Items Fulfillment Rate</th>
                                    <th colspan="3" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Order QTY Fulfillment Rate</th>
                                </tr>
                                <tr class="bg-gray-100">
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Ontime</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Late</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Good</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Fair</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Poor</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Good</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Fair</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Poor</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <tr v-for="(row, index) in filteredRows" :key="index" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">{{ row.facility_name }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.total_orders) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.completed_orders) }} ({{ row.completed_pct }}%)</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.rejected_orders) }} ({{ row.rejected_pct }}%)</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.delivery_ontime_count) }} ({{ row.delivery_ontime_pct }}%)</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.delivery_late_count) }} ({{ row.delivery_late_pct }}%)</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.items_good_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.items_fair_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.items_poor_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.qty_good_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.qty_fair_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.qty_poor_pct }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Transfer Report: Tabs (Charts | Table) -->
                <div v-else-if="isTransferReport && filteredRows.length > 0" class="space-y-4">
                    <nav class="flex gap-4" aria-label="Tabs">
                        <button
                            type="button"
                            @click="transferReportTab = 'charts'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="transferReportTab === 'charts' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Charts
                        </button>
                        <button
                            type="button"
                            @click="transferReportTab = 'table'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="transferReportTab === 'table' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Table
                        </button>
                    </nav>

                    <div v-show="transferReportTab === 'charts'" class="space-y-10">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                            <div class="bg-white p-8 pt-10">
                                <h3 class="text-center text-lg font-bold text-black mb-10">Transfer Status</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="transferStatusChartData.labels?.length"
                                        type="bar"
                                        :data="transferStatusChartData"
                                        :options="transferStatusChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                            <div class="bg-white p-8 pt-10">
                                <h3 class="text-center text-lg font-bold text-black mb-10">Transfer Type</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="transferTypeChartData.labels?.length"
                                        type="bar"
                                        :data="transferTypeChartData"
                                        :options="transferTypeChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white p-8 pt-10">
                            <h3 class="text-center text-lg font-bold text-black mb-10">Transfer Reason</h3>
                            <div class="min-h-[220px] w-full mt-2 max-w-2xl mx-auto" style="position: relative;">
                                <Chart
                                    v-if="transferReasonChartData.labels?.length"
                                    type="bar"
                                    :data="transferReasonChartData"
                                    :options="transferReasonChartOptions"
                                    :plugins="chartPlugins"
                                    :width="chartSize.width"
                                    :height="chartSize.height"
                                    class="w-full"
                                />
                                <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                            </div>
                        </div>
                    </div>

                    <div v-show="transferReportTab === 'table'" class="overflow-x-auto">
                        <table class="min-w-full border-collapse border border-gray-300">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300 align-middle">Facility Name</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Total Transfers</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Completed Transfers</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Rejected Transfers</th>
                                    <th colspan="4" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Transfer Reasons</th>
                                    <th colspan="4" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Transfer Type</th>
                                </tr>
                                <tr class="bg-gray-100">
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Wrong Item</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Overstock</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Soon to Expire</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Slow Moving</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Warehouse to Facility</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Warehouse to Warehouse</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Facility to Warehouse</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Facility to Facility</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <tr v-for="(row, index) in filteredRows" :key="index" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">{{ row.facility_name }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.total_transfers) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.completed_transfers) }} ({{ row.completed_pct }}%)</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.rejected_transfers) }} ({{ row.rejected_pct }}%)</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.reason_wrong_item) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.reason_overstock) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.reason_soon_to_expire) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.reason_slow_moving) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.type_warehouse_to_facility) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.type_warehouse_to_warehouse) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.type_facility_to_warehouse) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.type_facility_to_facility) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Procurement Report: Tabs (Charts | Table) -->
                <div v-else-if="isProcurementReport && filteredRows.length > 0" class="space-y-4">
                    <nav class="flex gap-4" aria-label="Tabs">
                        <button
                            type="button"
                            @click="procurementReportTab = 'charts'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="procurementReportTab === 'charts' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Charts
                        </button>
                        <button
                            type="button"
                            @click="procurementReportTab = 'table'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="procurementReportTab === 'table' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Table
                        </button>
                    </nav>

                    <div v-show="procurementReportTab === 'charts'" class="space-y-10">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                            <div class="bg-white p-8 pt-10">
                                <h3 class="text-center text-lg font-bold text-black mb-10">POs Status</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="procurementStatusChartData.labels?.length"
                                        type="bar"
                                        :data="procurementStatusChartData"
                                        :options="procurementStatusChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                            <div class="bg-white p-8 pt-10">
                                <h3 class="text-center text-lg font-bold text-black mb-10">POs Delivery Status</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="procurementDeliveryChartData.labels?.length"
                                        type="bar"
                                        :data="procurementDeliveryChartData"
                                        :options="procurementDeliveryChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white p-8 pt-10 max-w-2xl mx-auto">
                            <h3 class="text-center text-lg font-bold text-black mb-10">POs QTY Fulfillment</h3>
                            <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                <Chart
                                    v-if="procurementQtyChartData.labels?.length"
                                    type="bar"
                                    :data="procurementQtyChartData"
                                    :options="procurementQtyChartOptions"
                                    :plugins="chartPlugins"
                                    :width="chartSize.width"
                                    :height="chartSize.height"
                                    class="w-full"
                                />
                                <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                            </div>
                        </div>
                    </div>

                    <div v-show="procurementReportTab === 'table'" class="overflow-x-auto">
                        <table class="min-w-full border-collapse border border-gray-300">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300 align-middle">Warehouse Name</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Total POs</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Completed POs</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Rejected POs</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Avg Lead Time</th>
                                    <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">POs Delivery Status</th>
                                    <th colspan="3" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">POs Items Fulfillment Rate</th>
                                    <th colspan="3" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Order QTY Fulfillment Rate</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Total POs Cost</th>
                                </tr>
                                <tr class="bg-gray-100">
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Ontime</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Late</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Good</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Fair</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Poor</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Good</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Fair</th>
                                    <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Poor</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <tr v-for="(row, index) in filteredRows" :key="index" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">{{ row.warehouse_name }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.total_pos) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.completed_pos) }} ({{ row.completed_pct }}%)</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.rejected_pos) }} ({{ row.rejected_pct }}%)</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.avg_lead_time_days }} days</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.delivery_ontime_count) }} ({{ row.delivery_ontime_pct }}%)</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.delivery_late_count) }} ({{ row.delivery_late_pct }}%)</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.items_good_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.items_fair_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.items_poor_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.qty_good_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.qty_fair_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ row.qty_poor_pct }}%</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">${{ formatNum(row.total_pos_cost) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Asset Report: Tabs (Charts | Table) -->
                <div v-else-if="isAssetReport && filteredRows.length > 0" class="space-y-4">
                    <nav class="flex gap-4" aria-label="Tabs">
                        <button type="button" @click="assetReportTab = 'charts'" class="py-2 px-1 font-medium text-sm transition-colors" :class="assetReportTab === 'charts' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'">Charts</button>
                        <button type="button" @click="assetReportTab = 'table'" class="py-2 px-1 font-medium text-sm transition-colors" :class="assetReportTab === 'table' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'">Table</button>
                    </nav>
                    <div v-show="assetReportTab === 'charts'" class="space-y-10">
                        <div class="bg-white p-8 pt-10 max-w-2xl">
                            <h3 class="text-center text-lg font-bold text-black mb-10">Assets Category</h3>
                            <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                <Chart v-if="assetReportChartData.labels?.length" type="bar" :data="assetReportChartData" :options="assetReportChartOptions" :plugins="chartPlugins" :width="chartSize.width" :height="chartSize.height" class="w-full" />
                                <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                            </div>
                        </div>
                    </div>
                    <div v-show="assetReportTab === 'table'" class="overflow-x-auto">
                        <table class="min-w-full border-collapse border border-gray-300">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300 align-middle">Facility Name</th>
                                    <th rowspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300 align-middle">Total Assets</th>
                                    <th v-for="cat in assetReportCategoryColumns" :key="cat" colspan="3" class="px-2 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">{{ cat }}</th>
                                </tr>
                                <tr class="bg-gray-50">
                                    <template v-for="cat in assetReportCategoryColumns" :key="'sub-' + cat">
                                        <th class="px-2 py-1 text-center text-xs font-medium text-gray-500 border border-gray-300 w-20">Total</th>
                                        <th class="px-2 py-1 text-center text-xs font-medium text-gray-500 border border-gray-300 w-20">Functioning</th>
                                        <th class="px-2 py-1 text-center text-xs font-medium text-gray-500 border border-gray-300 w-20">Not Functioning</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <tr v-for="(row, index) in filteredRows" :key="index" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">{{ row.facility_name }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.total_assets) }}</td>
                                    <template v-for="cat in assetReportCategoryColumns" :key="cat">
                                        <td class="px-2 py-1 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row[assetReportCategoryKey(cat) + '_total']) }}</td>
                                        <td class="px-2 py-1 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row[assetReportCategoryKey(cat) + '_functioning']) }}</td>
                                        <td class="px-2 py-1 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row[assetReportCategoryKey(cat) + '_not_functioning']) }}</td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Expiry Report: Tabs (Charts | Table) -->
                <div v-else-if="isExpiryReport && filteredRows.length > 0" class="space-y-4">
                    <nav class="flex gap-4" aria-label="Tabs">
                        <button
                            type="button"
                            @click="expiryReportTab = 'charts'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="expiryReportTab === 'charts' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Charts
                        </button>
                        <button
                            type="button"
                            @click="expiryReportTab = 'table'"
                            class="py-2 px-1 font-medium text-sm transition-colors"
                            :class="expiryReportTab === 'table' ? 'text-emerald-600' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Table
                        </button>
                    </nav>

                    <!-- Expiry Report Chart -->
                    <div v-show="expiryReportTab === 'charts'" class="space-y-10">
                        <div class="max-w-2xl">
                            <div class="bg-white p-8 pt-10">
                                <h3 class="text-center text-lg font-bold text-black mb-10">Expiring Status</h3>
                                <div class="min-h-[220px] w-full mt-2" style="position: relative;">
                                    <Chart
                                        v-if="expiryReportChartData.labels?.length"
                                        type="bar"
                                        :data="expiryReportChartData"
                                        :options="expiryReportChartOptions"
                                        :plugins="chartPlugins"
                                        :width="chartSize.width"
                                        :height="chartSize.height"
                                        class="w-full"
                                    />
                                    <div v-else class="absolute inset-0 flex items-center justify-center text-gray-500 text-sm">No data</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Expiry Report Table -->
                    <div v-show="expiryReportTab === 'table'" class="overflow-x-auto">
                    <table class="min-w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th v-if="expiryReportHasMixedTypes" rowspan="3" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300 align-middle">Type</th>
                                <th rowspan="3" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300 align-middle">{{ expiryReportNameColumnHeader }}</th>
                                <th colspan="6" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Expiring Status</th>
                            </tr>
                            <tr class="bg-gray-100">
                                <th colspan="2" class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Expiring within next 1 Year</th>
                                <th colspan="2" class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Expiring within next 6 Months</th>
                                <th colspan="2" class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Expired</th>
                            </tr>
                            <tr class="bg-gray-100">
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Item No.</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Total Value</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Item No.</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Total Value</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Item No.</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">Total Value</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <tr v-for="(row, index) in filteredRows" :key="index" class="hover:bg-gray-50">
                                <td v-if="expiryReportHasMixedTypes" class="px-3 py-2 text-sm text-gray-900 border border-gray-300 capitalize">{{ row.type }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 border border-gray-300">{{ row.name || '–' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.expiring_1_year_item_no) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ (row.expiring_1_year_value != null && row.expiring_1_year_value !== '') ? formatCost(row.expiring_1_year_value) + '$' : '–' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.expiring_6_months_item_no) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ (row.expiring_6_months_value != null && row.expiring_6_months_value !== '') ? formatCost(row.expiring_6_months_value) + '$' : '–' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.expired_item_no) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ (row.expired_value != null && row.expired_value !== '') ? formatCost(row.expired_value) + '$' : '–' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Warehouse Inventory: approval workflow bar (from inventory_report) -->
                <div v-if="filters.report_type === 'warehouse_inventory' && warehouseReportMeta" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-700">Report status:</span>
                    <span
                        :class="{
                            'bg-amber-100 text-amber-800': warehouseReportMeta.report_status === 'pending',
                            'bg-blue-100 text-blue-800': warehouseReportMeta.report_status === 'submitted',
                            'bg-indigo-100 text-indigo-800': warehouseReportMeta.report_status === 'under_review',
                            'bg-green-100 text-green-800': warehouseReportMeta.report_status === 'approved',
                            'bg-red-100 text-red-800': warehouseReportMeta.report_status === 'rejected',
                        }"
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                    >
                        {{ workflowStatusLabel(warehouseReportMeta.report_status) }}
                    </span>
                    <span v-if="warehouseReportMeta.rejection_reason" class="ml-2 flex items-center gap-2">
                        <span class="text-sm text-gray-500">Rejection reason:</span>
                        <span class="text-sm text-gray-700">{{ warehouseReportMeta.rejection_reason }}</span>
                    </span>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <button
                            v-if="warehouseReportMeta.report_can_submit"
                            type="button"
                            class="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                            :disabled="workflowActionLoading"
                            @click="workflowAction('submit')"
                        >
                            Submit for review
                        </button>
                        <button
                            v-if="warehouseReportMeta.report_can_review"
                            type="button"
                            class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                            :disabled="workflowActionLoading"
                            @click="workflowAction('review')"
                        >
                            Mark under review
                        </button>
                        <button
                            v-if="warehouseReportMeta.report_can_approve_reject"
                            type="button"
                            class="rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                            :disabled="workflowActionLoading"
                            @click="workflowAction('approve')"
                        >
                            Approve
                        </button>
                        <button
                            v-if="warehouseReportMeta.report_can_approve_reject"
                            type="button"
                            class="rounded border border-red-600 bg-white px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                            :disabled="workflowActionLoading"
                            @click="showRejectModal = true"
                        >
                            Reject
                        </button>
                    </div>
                </div>
                <!-- Reject reason modal -->
                <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showRejectModal = false">
                    <div class="w-full max-w-md rounded-lg bg-white p-4 shadow-xl">
                        <h3 class="text-lg font-semibold text-gray-900">Reject report</h3>
                        <p class="mt-1 text-sm text-gray-500">Optionally provide a reason for rejection (shown to the report owner).</p>
                        <textarea v-model="rejectReason" rows="3" class="mt-3 w-full rounded border border-gray-300 px-2 py-1.5 text-sm" placeholder="Reason for rejection..."></textarea>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showRejectModal = false">Cancel</button>
                            <button type="button" class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700" :disabled="workflowActionLoading" @click="workflowAction('reject')">Reject report</button>
                        </div>
                    </div>
                </div>
                <!-- Default Inventory Report Table -->
                <div v-else-if="!isProductReport && !isLiquidationDisposalReport && !isExpiryReport && !isFacilitiesReport && !isOrderReport && !isTransferReport && !isProcurementReport && !isAssetReport && !isSubmissionRateReport && reportData.length > 0" class="overflow-x-auto">
                    <table class="min-w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">Item</th>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">Category</th>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">UoM</th>
                                <th colspan="3" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Item Details (batch level)</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Beginning<br>Balance</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">QTY<br>Received</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">QTY<br>Issued</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Adjustments</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Closing<br>Balance</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Total<br>Cost</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Total<br>Closing<br>Balance</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">AMC</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">MOS<br>(Months<br>of Stock)</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Stockout<br>Days</th>
                            </tr>
                            <tr class="bg-gray-50">
                                <th class="px-3 py-1 text-left text-xs font-medium text-gray-600 border border-gray-300">Batch No.:</th>
                                <th class="px-3 py-1 text-left text-xs font-medium text-gray-600 border border-gray-300">Expiry Date</th>
                                <th class="px-3 py-1 text-right text-xs font-medium text-gray-600 border border-gray-300">Unit cost</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">(-)</th>
                                <th class="px-3 py-1 text-center text-xs font-medium text-gray-600 border border-gray-300">(+)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <tr
                                v-for="(row, index) in filteredRows"
                                :key="index"
                                :class="row.is_first_batch ? 'border-t border-gray-400' : ''"
                                class="hover:bg-gray-50"
                            >
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 border border-gray-300 align-top">{{ row.item }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-600 border border-gray-300 align-top">{{ row.category }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-600 border border-gray-300 align-top">{{ row.uom }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600 border border-gray-300 whitespace-nowrap">{{ row.batch_no || '–' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600 border border-gray-300 whitespace-nowrap">{{ formatExpiry(row.expiry_date) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatCost(row.unit_cost) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.beginning_balance) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.qty_received) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.qty_issued) }}</td>
                                <td class="px-3 py-2 text-sm text-right border border-gray-300">
                                    <input v-if="warehouseReportMeta && row.report_item_id" type="number" min="0" class="w-16 px-1 py-0.5 text-right text-sm border border-gray-400 rounded" v-model.number="row.adjustment_neg" @blur="saveReportItemAdjustment(row)" />
                                    <span v-else>{{ formatNum(row.adjustment_neg) }}</span>
                                </td>
                                <td class="px-3 py-2 text-sm text-right border border-gray-300">
                                    <input v-if="warehouseReportMeta && row.report_item_id" type="number" min="0" class="w-16 px-1 py-0.5 text-right text-sm border border-gray-400 rounded" v-model.number="row.adjustment_pos" @blur="saveReportItemAdjustment(row)" />
                                    <span v-else>{{ formatNum(row.adjustment_pos) }}</span>
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.closing_balance) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ formatCost(row.total_cost) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ formatNum(row.total_closing_balance) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ formatNum(row.amc) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-right border border-gray-300 align-top font-medium">{{ row.mos ?? '–' }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-right border border-gray-300 align-top">{{ formatNum(row.stockout_days) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-8 text-center text-gray-500">
                    <template v-if="filters.report_type === 'warehouse_inventory'">
                        Select Warehouse Inventory Report, choose Report Period (e.g. 2026-02), then click Generate Report. Optionally filter by Region, District, or Warehouse.
                    </template>
                    <template v-else>
                        Select report type, report period, and at least one location (Region, District, or Warehouse/Facility), then click Generate Report.
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';
import Chart from 'primevue/chart';
import ChartDataLabels from 'chartjs-plugin-datalabels';

const chartPlugins = [ChartDataLabels];
const chartSize = { width: 300, height: 220 };

const WAREHOUSE_INVENTORY_EMPTY_ROW = {
    product_id: null,
    item: '–',
    category: '–',
    uom: '–',
    batch_no: null,
    expiry_date: null,
    beginning_balance: 0,
    qty_received: 0,
    qty_issued: 0,
    adjustment_neg: 0,
    adjustment_pos: 0,
    closing_balance: 0,
    total_closing_balance: 0,
    amc: 0,
    mos: '–',
    stockout_days: 0,
    unit_cost: 0,
    total_cost: 0,
    warehouse_name: '–',
    facility_name: null,
    rowspan: 1,
    is_first_batch: true,
};

const props = defineProps({
    regions: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    reportTypes: { type: Array, default: () => [] },
    reportPeriodOptions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const now = new Date();
const currentYear = now.getFullYear();
const currentMonth = now.getMonth() + 1;
const defaultMonthYear = `${currentYear}-${String(currentMonth).padStart(2, '0')}`;

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const DEFAULT_REPORT_PERIOD_OPTIONS = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'bi-monthly', label: 'Bi-monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'six_months', label: 'Six months' },
    { value: 'yearly', label: 'Yearly' },
];

// Initialize year and periodValue from props (year, month) or from monthYear
function parseInitialYearMonth() {
    const y = props.filters?.year;
    const m = props.filters?.month;
    if (y != null && m != null) return { year: Number(y), periodValue: Number(m) };
    const my = props.filters?.monthYear ?? defaultMonthYear;
    const [yr, mo] = my.split('-').map(Number);
    return { year: yr || currentYear, periodValue: mo || currentMonth };
}
const initial = parseInitialYearMonth();

const filters = ref({
    region_id: props.filters?.region_id ?? null,
    district_id: props.filters?.district_id ?? null,
    warehouse_or_facility: props.filters?.warehouse_or_facility ?? '',
    report_type: props.filters?.report_type ?? 'warehouse_inventory',
    report_period: props.filters?.report_period ?? 'monthly',
    year: initial.year,
    periodValue: initial.periodValue,
});

const reportPeriodOptionsList = computed(() =>
    (props.reportPeriodOptions?.length ? props.reportPeriodOptions : DEFAULT_REPORT_PERIOD_OPTIONS)
);

// Year dropdown: e.g. current year ± 10
const yearOptions = computed(() => {
    const start = currentYear - 10;
    const end = currentYear + 1;
    const list = [];
    for (let y = end; y >= start; y--) list.push(y);
    return list;
});

// Period options driven by report_period. value = month number to send to API (first month of period for non-monthly).
const periodOptions = computed(() => {
    const period = filters.value.report_period || 'monthly';
    switch (period) {
        case 'monthly':
            return MONTH_NAMES.map((name, i) => ({ value: i + 1, label: name }));
        case 'bi-monthly':
            return [
                { value: 1, label: 'Jan-Feb' },
                { value: 3, label: 'Mar-Apr' },
                { value: 5, label: 'May-Jun' },
                { value: 7, label: 'Jul-Aug' },
                { value: 9, label: 'Sep-Oct' },
                { value: 11, label: 'Nov-Dec' },
            ];
        case 'quarterly':
            return [
                { value: 1, label: 'Jan-Mar' },
                { value: 4, label: 'Apr-Jun' },
                { value: 7, label: 'Jul-Sep' },
                { value: 10, label: 'Oct-Dec' },
            ];
        case 'six_months':
            return [
                { value: 1, label: 'Jan-Jun' },
                { value: 7, label: 'Jul-Dec' },
            ];
        case 'yearly':
            return [{ value: 1, label: 'Full year' }];
        default:
            return MONTH_NAMES.map((name, i) => ({ value: i + 1, label: name }));
    }
});

// When report_period changes, ensure periodValue is valid for the new options
watch(() => filters.value.report_period, () => {
    const opts = periodOptions.value;
    if (!opts.length) return;
    const valid = opts.some(o => o.value === filters.value.periodValue);
    if (!valid) filters.value.periodValue = opts[0].value;
}, { immediate: false });

// Derived monthYear for API (e.g. warehouse workflow) when both year and month are needed
const filtersMonthYear = computed(() => {
    const y = filters.value.year;
    const m = filters.value.periodValue;
    if (!y || !m) return defaultMonthYear;
    return `${y}-${String(m).padStart(2, '0')}`;
});

const reportData = ref([]);
const productReportRows = ref([]);
const categoryColumns = ref([]);
const supplyClassColumns = ref([]);
const generating = ref(false);
        const hasGenerated = ref(false);
        const reportMessage = ref('');
        const searchQuery = ref('');
const productReportTab = ref('table');
const liquidationDisposalTab = ref('table');
const expiryReportTab = ref('table');
const facilitiesReportTab = ref('table');
const facilitiesReportTypeColumns = ref([]);
const orderReportTab = ref('table');
const orderReportSummary = ref({ total_orders: 0, received: 0, rejected: 0, total_delivered: 0, on_time: 0, late: 0 });
const transferReportTab = ref('table');
const transferReportSummary = ref({ total_transfers: 0, received: 0, rejected: 0, reason_wrong_item: 0, reason_overstock: 0, reason_slow_moving: 0, warehouse_to_facility: 0, warehouse_to_warehouse: 0, facility_to_warehouse: 0, facility_to_facility: 0 });
const procurementReportTab = ref('table');
const procurementReportSummary = ref({ total_pos: 0, completed: 0, rejected: 0, total_delivered: 0, on_time: 0, late: 0, total_cost: 0, qty_good: 0, qty_fair: 0, qty_poor: 0 });
const assetReportTab = ref('table');
const assetReportCategoryColumns = ref([]);
const assetReportSummary = ref({ total_assets: 0, by_category: {} });
const warehouseReportMeta = ref(null);
const workflowActionLoading = ref(false);
const showRejectModal = ref(false);
const rejectReason = ref('');

const filteredDistricts = computed(() => {
    const list = props.districts || [];
    if (!filters.value.region_id) return [];
    const regionName = props.regions?.find(r => r.id == filters.value.region_id)?.name;
    if (!regionName) return [];
    return list.filter(d => (d.region || '') === regionName);
});

const filteredWarehouses = computed(() => {
    let list = props.warehouses || [];
    if (filters.value.region_id) {
        const regionName = props.regions?.find(r => r.id == filters.value.region_id)?.name;
        if (regionName) list = list.filter(w => w.region === regionName);
    }
    if (filters.value.district_id) {
        const districtName = props.districts?.find(d => d.id == filters.value.district_id)?.name;
        if (districtName) list = list.filter(w => w.district === districtName);
    }
    return list;
});

const filteredFacilities = computed(() => {
    let list = props.facilities || [];
    if (filters.value.region_id) {
        const regionName = props.regions?.find(r => r.id == filters.value.region_id)?.name;
        if (regionName) list = list.filter(f => f.region === regionName);
    }
    if (filters.value.district_id) {
        const districtName = props.districts?.find(d => d.id == filters.value.district_id)?.name;
        if (districtName) list = list.filter(f => f.district === districtName);
    }
    return list;
});

const filteredRows = computed(() => {
    if (!searchQuery.value.trim()) return reportData.value;
    const q = searchQuery.value.toLowerCase();
    return reportData.value.filter(row => {
        const item = (row.item || '').toLowerCase();
        const facility = (row.facility_name || '').toLowerCase();
        const warehouse = (row.warehouse_name || '').toLowerCase();
        const name = (row.name || '').toLowerCase();
        const district = (row.district_name || '').toLowerCase();
        const whName = (row.warehouse_name || '').toLowerCase();
        return item.includes(q) || facility.includes(q) || warehouse.includes(q) || name.includes(q) || district.includes(q) || whName.includes(q);
    });
});

const filteredProductRows = computed(() => {
    if (!searchQuery.value.trim()) return productReportRows.value;
    const q = searchQuery.value.toLowerCase();
    return productReportRows.value.filter(row => (row.name || '').toLowerCase().includes(q));
});

const isProductReport = computed(() => filters.value.report_type === 'product_report');
const isLiquidationDisposalReport = computed(() => filters.value.report_type === 'liquidation_disposal');
const isExpiryReport = computed(() => filters.value.report_type === 'expiry_report');
const isFacilitiesReport = computed(() => filters.value.report_type === 'facilities_report');
const isOrderReport = computed(() => filters.value.report_type === 'order_report');
const isTransferReport = computed(() => filters.value.report_type === 'transfer_report');
const isProcurementReport = computed(() => filters.value.report_type === 'procurement_report');
const isAssetReport = computed(() => filters.value.report_type === 'asset_report');
const isSubmissionRateReport = computed(() => filters.value.report_type === 'report_submission_rate');

const filteredSubmissionRateRows = computed(() => {
    if (!isSubmissionRateReport.value) return [];
    const rows = reportData.value || [];
    if (!searchQuery.value.trim()) return rows;
    const q = searchQuery.value.toLowerCase();
    return rows.filter(row => (row.facility_name || '').toLowerCase().includes(q));
});

function assetReportCategoryKey(catName) {
    return 'category_' + (catName || '').replace(/\s+/g, '_').toLowerCase();
}

const expiryReportHasMixedTypes = computed(() => {
    if (!isExpiryReport.value || !reportData.value.length) return false;
    const types = new Set(reportData.value.map(r => r.type));
    return types.has('facility') && types.has('warehouse');
});

const expiryReportNameColumnHeader = computed(() => {
    if (!isExpiryReport.value || !reportData.value.length) return 'Name';
    const types = new Set(reportData.value.map(r => r.type));
    if (types.has('facility') && !types.has('warehouse')) return 'Facility Name';
    if (types.has('warehouse') && !types.has('facility')) return 'Warehouse Name';
    return 'Name';
});

const EXPIRY_REPORT_CHART_COLORS = ['rgb(34, 197, 94)', 'rgb(245, 158, 11)', 'rgb(59, 130, 246)'];
const expiryReportChartData = computed(() => {
    const rows = reportData.value;
    if (!rows.length || !isExpiryReport.value) return { labels: [], datasets: [] };
    const within1Year = rows.reduce((s, r) => s + (Number(r.expiring_1_year_item_no) || 0), 0);
    const within6Months = rows.reduce((s, r) => s + (Number(r.expiring_6_months_item_no) || 0), 0);
    const expired = rows.reduce((s, r) => s + (Number(r.expired_item_no) || 0), 0);
    return {
        labels: ['Expiring within next 1 Year', 'Expiring within next 6 Months', 'Expired'],
        datasets: [{
            label: 'Item count',
            data: [within1Year, within6Months, expired],
            backgroundColor: EXPIRY_REPORT_CHART_COLORS,
            borderColor: EXPIRY_REPORT_CHART_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const expiryReportChartOptions = computed(() => {
    const data = expiryReportChartData.value?.datasets?.[0]?.data ?? [];
    const dataMax = data.length ? Math.max(...data) : 0;
    const yMax = Math.max(dataMax + 2, 10);
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 24, right: 12, bottom: 12, left: 6 } },
        indexAxis: 'x',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'top',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 12,
                offset: 8,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: yMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});

const hasAnyData = computed(() => {
    if (isProductReport.value) return productReportRows.value.length > 0;
    return reportData.value.length > 0;
});

// Flat solid colors matching reference: green, yellow-orange, blue, orange
const PRODUCT_REPORT_CHART_COLORS = [
    'rgb(34, 197, 94)',   // green (Drugs)
    'rgb(245, 158, 11)',  // yellow-orange (Medical Equipment)
    'rgb(59, 130, 246)',  // blue (Consumables)
    'rgb(249, 115, 22)',  // orange (Medical Lab Supplies)
    'rgb(139, 92, 246)',  // violet (extra)
];

const productReportCategoryChartData = computed(() => {
    const rows = filteredProductRows.value;
    const cats = categoryColumns.value;
    if (!cats.length) return { labels: [], datasets: [] };
    const data = cats.map(cat => rows.reduce((s, r) => s + (r.categories?.[cat] || 0), 0));
    const colors = cats.map((_, i) => PRODUCT_REPORT_CHART_COLORS[i % PRODUCT_REPORT_CHART_COLORS.length]);
    return {
        labels: cats,
        datasets: [{
            label: 'Products',
            data,
            backgroundColor: colors,
            borderColor: colors,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});

const productReportSupplyClassChartData = computed(() => {
    const rows = filteredProductRows.value;
    const scs = supplyClassColumns.value;
    if (!scs.length) return { labels: [], datasets: [] };
    const data = scs.map(sc => rows.reduce((s, r) => s + (r.supply_classes?.[sc] || 0), 0));
    const colors = scs.map((_, i) => PRODUCT_REPORT_CHART_COLORS[i % PRODUCT_REPORT_CHART_COLORS.length]);
    return {
        labels: scs,
        datasets: [{
            label: 'Products',
            data,
            backgroundColor: colors,
            borderColor: colors,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});

const productReportVerticalBarOptions = computed(() => {
    const data = productReportCategoryChartData.value?.datasets?.[0]?.data ?? [];
    const dataMax = data.length ? Math.max(...data) : 0;
    const yMax = dataMax + 2;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 24, right: 12, bottom: 12, left: 6 } },
        indexAxis: 'x',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'top',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 12,
                offset: 8,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: yMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});

const productReportHorizontalBarOptions = computed(() => {
    const data = productReportSupplyClassChartData.value?.datasets?.[0]?.data ?? [];
    const dataMax = data.length ? Math.max(...data) : 0;
    const xMax = dataMax + 2;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 6, right: 40, bottom: 12, left: 6 } },
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'start',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 8,
                offset: 16,
            },
        },
        scales: {
            x: {
                beginAtZero: true,
                max: xMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            y: {
                grid: { display: false },
                border: { display: false },
                ticks: { autoSkip: false, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});

// Liquidation & Disposal charts: aggregate from report rows
const LIQUIDATION_DISPOSAL_CHART_COLORS = ['rgb(34, 197, 94)', 'rgb(245, 158, 11)'];

const liquidationDisposalStatusChartData = computed(() => {
    const rows = reportData.value;
    if (!rows.length || !isLiquidationDisposalReport.value) return { labels: [], datasets: [] };
    const liquated = rows.reduce((s, r) => s + (Number(r.total_liquated_item_no) || 0), 0);
    const disposed = rows.reduce((s, r) => s + (Number(r.total_disposed_item_no) || 0), 0);
    return {
        labels: ['Total Liquated Items', 'Total Disposed Items'],
        datasets: [{
            label: 'Count',
            data: [liquated, disposed],
            backgroundColor: LIQUIDATION_DISPOSAL_CHART_COLORS,
            borderColor: LIQUIDATION_DISPOSAL_CHART_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});

const liquidationReasonsChartData = computed(() => {
    const rows = reportData.value;
    if (!rows.length || !isLiquidationDisposalReport.value) return { labels: [], datasets: [] };
    const missing = rows.reduce((s, r) => s + (Number(r.liquidation_missing) || 0), 0);
    const lost = rows.reduce((s, r) => s + (Number(r.liquidation_lost) || 0), 0);
    return {
        labels: ['Missing', 'Lost'],
        datasets: [{
            label: 'Count',
            data: [missing, lost],
            backgroundColor: LIQUIDATION_DISPOSAL_CHART_COLORS,
            borderColor: LIQUIDATION_DISPOSAL_CHART_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});

const disposalReasonsChartData = computed(() => {
    const rows = reportData.value;
    if (!rows.length || !isLiquidationDisposalReport.value) return { labels: [], datasets: [] };
    const damage = rows.reduce((s, r) => s + (Number(r.disposal_damage) || 0), 0);
    const expired = rows.reduce((s, r) => s + (Number(r.disposal_expired) || 0), 0);
    return {
        labels: ['Damage', 'Expired'],
        datasets: [{
            label: 'Count',
            data: [damage, expired],
            backgroundColor: LIQUIDATION_DISPOSAL_CHART_COLORS,
            borderColor: LIQUIDATION_DISPOSAL_CHART_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});

const liquidationDisposalChartOptions = computed(() => {
    const statusData = liquidationDisposalStatusChartData.value?.datasets?.[0]?.data ?? [];
    const liqData = liquidationReasonsChartData.value?.datasets?.[0]?.data ?? [];
    const dispData = disposalReasonsChartData.value?.datasets?.[0]?.data ?? [];
    const dataMax = Math.max(
        statusData.length ? Math.max(...statusData) : 0,
        liqData.length ? Math.max(...liqData) : 0,
        dispData.length ? Math.max(...dispData) : 0
    );
    const yMax = Math.max(dataMax + 2, 10);
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 24, right: 12, bottom: 12, left: 6 } },
        indexAxis: 'x',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'top',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 12,
                offset: 8,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: yMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});

// Facilities Report charts
const FACILITIES_REPORT_BAR_COLORS = ['rgb(34, 197, 94)', 'rgb(245, 158, 11)', 'rgb(59, 130, 246)', 'rgb(156, 163, 175)', 'rgb(249, 115, 22)'];
const facilitiesReportTypeChartData = computed(() => {
    const rows = reportData.value;
    if (!rows.length || !isFacilitiesReport.value) return { labels: [], datasets: [] };
    const labels = ['Total Number of Facilities', 'Primary Health Unit', 'Health Center', 'District Hospital', 'Regional Hospital'];
    const data = [
        rows.reduce((s, r) => s + (Number(r.total_facilities) || 0), 0),
        rows.reduce((s, r) => s + (Number(r.primary_health_unit) || 0), 0),
        rows.reduce((s, r) => s + (Number(r.health_center) || 0), 0),
        rows.reduce((s, r) => s + (Number(r.district_hospital) || 0), 0),
        rows.reduce((s, r) => s + (Number(r.regional_hospital) || 0), 0),
    ];
    return {
        labels,
        datasets: [{
            label: 'Count',
            data,
            backgroundColor: FACILITIES_REPORT_BAR_COLORS,
            borderColor: FACILITIES_REPORT_BAR_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const facilitiesReportTypeChartOptions = computed(() => {
    const data = facilitiesReportTypeChartData.value?.datasets?.[0]?.data ?? [];
    const dataMax = data.length ? Math.max(...data) : 0;
    const yMax = Math.max(dataMax + 2, 10);
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 24, right: 12, bottom: 12, left: 6 } },
        indexAxis: 'x',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'top',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 12,
                offset: 8,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: yMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});
const facilitiesReportActivationChartData = computed(() => {
    const rows = reportData.value;
    if (!rows.length || !isFacilitiesReport.value) return { labels: [], datasets: [] };
    const active = rows.reduce((s, r) => s + (Number(r.active) || 0), 0);
    const inactive = rows.reduce((s, r) => s + (Number(r.not_active) || 0), 0);
    return {
        labels: ['Active', 'Inactive'],
        datasets: [{
            data: [active, inactive],
            backgroundColor: ['rgb(34, 197, 94)', 'rgb(249, 115, 22)'],
            borderWidth: 0,
            hoverOffset: 4,
        }],
    };
});
const facilitiesReportDonutOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 400 },
    layout: { padding: 12 },
    plugins: {
        legend: { display: true, position: 'bottom', labels: { font: { size: 11, weight: 'bold' }, color: '#000000', padding: 12 } },
        tooltip: { enabled: true },
        datalabels: {
            color: '#000000',
            font: { weight: 'bold', size: 12 },
            formatter: (v) => v,
        },
    },
}));

// Order Report charts (from summary)
const ORDER_STATUS_CHART_COLORS = ['rgb(59, 130, 246)', 'rgb(34, 197, 94)', 'rgb(245, 158, 11)'];
const orderStatusChartData = computed(() => {
    if (!isOrderReport.value) return { labels: [], datasets: [] };
    const s = orderReportSummary.value;
    const total = Number(s.total_orders) || 0;
    const received = Number(s.received) || 0;
    const rejected = Number(s.rejected) || 0;
    if (total === 0 && received === 0 && rejected === 0) return { labels: [], datasets: [] };
    return {
        labels: ['Total Orders', 'Received', 'Rejected'],
        datasets: [{
            label: 'Count',
            data: [total, received, rejected],
            backgroundColor: ORDER_STATUS_CHART_COLORS,
            borderColor: ORDER_STATUS_CHART_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const orderStatusChartOptions = computed(() => {
    const data = orderStatusChartData.value?.datasets?.[0]?.data ?? [];
    const yMax = data.length ? Math.max(...data) + 2 : 10;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 24, right: 12, bottom: 12, left: 6 } },
        indexAxis: 'x',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'top',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 12,
                offset: 8,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: yMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});
const ORDER_DELIVERY_CHART_COLORS = ['rgb(59, 130, 246)', 'rgb(249, 115, 22)', 'rgb(14, 165, 233)'];
const orderDeliveryChartData = computed(() => {
    if (!isOrderReport.value) return { labels: [], datasets: [] };
    const s = orderReportSummary.value;
    const totalDelivered = Number(s.total_delivered) || 0;
    const onTime = Number(s.on_time) || 0;
    const late = Number(s.late) || 0;
    if (totalDelivered === 0 && onTime === 0 && late === 0) return { labels: [], datasets: [] };
    return {
        labels: ['Total Delivered Orders', 'On-time', 'Late'],
        datasets: [{
            label: 'Count',
            data: [totalDelivered, onTime, late],
            backgroundColor: ORDER_DELIVERY_CHART_COLORS,
            borderColor: ORDER_DELIVERY_CHART_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const orderDeliveryChartOptions = computed(() => {
    const data = orderDeliveryChartData.value?.datasets?.[0]?.data ?? [];
    const xMax = data.length ? Math.max(...data) + 2 : 10;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 6, right: 40, bottom: 12, left: 6 } },
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'start',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 8,
                offset: 16,
            },
        },
        scales: {
            x: {
                beginAtZero: true,
                max: xMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            y: {
                grid: { display: false },
                border: { display: false },
                ticks: { autoSkip: false, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});

// Transfer Report charts
const TRANSFER_STATUS_CHART_COLORS = ['rgb(59, 130, 246)', 'rgb(34, 197, 94)', 'rgb(245, 158, 11)'];
const transferStatusChartData = computed(() => {
    if (!isTransferReport.value) return { labels: [], datasets: [] };
    const s = transferReportSummary.value;
    const total = Number(s.total_transfers) || 0;
    const received = Number(s.received) || 0;
    const rejected = Number(s.rejected) || 0;
    if (total === 0 && received === 0 && rejected === 0) return { labels: [], datasets: [] };
    return {
        labels: ['Total Transfers', 'Received', 'Rejected'],
        datasets: [{
            label: 'Count',
            data: [total, received, rejected],
            backgroundColor: TRANSFER_STATUS_CHART_COLORS,
            borderColor: TRANSFER_STATUS_CHART_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const transferStatusChartOptions = computed(() => {
    const data = transferStatusChartData.value?.datasets?.[0]?.data ?? [];
    const yMax = data.length ? Math.max(...data) + 2 : 10;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 24, right: 12, bottom: 12, left: 6 } },
        indexAxis: 'x',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'top',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 12,
                offset: 8,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: yMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});
const TRANSFER_TYPE_CHART_COLORS = ['rgb(245, 158, 11)', 'rgb(34, 197, 94)', 'rgb(59, 130, 246)', 'rgb(156, 163, 175)'];
const transferTypeChartData = computed(() => {
    if (!isTransferReport.value) return { labels: [], datasets: [] };
    const s = transferReportSummary.value;
    const w2f = Number(s.warehouse_to_facility) || 0;
    const w2w = Number(s.warehouse_to_warehouse) || 0;
    const f2w = Number(s.facility_to_warehouse) || 0;
    const f2f = Number(s.facility_to_facility) || 0;
    if (w2f === 0 && w2w === 0 && f2w === 0 && f2f === 0) return { labels: [], datasets: [] };
    return {
        labels: ['Warehouse to Facility', 'Warehouse to Warehouse', 'Facility to Warehouse', 'Facility to Facility'],
        datasets: [{
            label: 'Count',
            data: [w2f, w2w, f2w, f2f],
            backgroundColor: TRANSFER_TYPE_CHART_COLORS,
            borderColor: TRANSFER_TYPE_CHART_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const transferTypeChartOptions = computed(() => {
    const data = transferTypeChartData.value?.datasets?.[0]?.data ?? [];
    const yMax = data.length ? Math.max(...data) + 2 : 10;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 24, right: 12, bottom: 12, left: 6 } },
        indexAxis: 'x',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'top',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 12,
                offset: 8,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: yMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});
// Transfer Reason: horizontal bar (Wrong Item, Slow Moving Item, Overstock Item)
const TRANSFER_REASON_CHART_COLORS = ['rgb(245, 158, 11)', 'rgb(14, 165, 233)', 'rgb(34, 197, 94)'];
const transferReasonChartData = computed(() => {
    if (!isTransferReport.value) return { labels: [], datasets: [] };
    const s = transferReportSummary.value;
    const wrongItem = Number(s.reason_wrong_item) || 0;
    const slowMoving = Number(s.reason_slow_moving) || 0;
    const overstock = Number(s.reason_overstock) || 0;
    if (wrongItem === 0 && slowMoving === 0 && overstock === 0) return { labels: [], datasets: [] };
    return {
        labels: ['Wrong Item', 'Slow Moving Item', 'Overstock Item'],
        datasets: [{
            label: 'Count',
            data: [wrongItem, slowMoving, overstock],
            backgroundColor: TRANSFER_REASON_CHART_COLORS,
            borderColor: TRANSFER_REASON_CHART_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const transferReasonChartOptions = computed(() => {
    const data = transferReasonChartData.value?.datasets?.[0]?.data ?? [];
    const xMax = data.length ? Math.max(...data) + 2 : 12;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 6, right: 40, bottom: 12, left: 6 } },
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'start',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 8,
                offset: 16,
            },
        },
        scales: {
            x: {
                beginAtZero: true,
                max: xMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 2, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            y: {
                grid: { display: false },
                border: { display: false },
                ticks: { autoSkip: false, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});

// Procurement Report charts
const PROCUREMENT_STATUS_COLORS = ['rgb(59, 130, 246)', 'rgb(34, 197, 94)', 'rgb(245, 158, 11)'];
const procurementStatusChartData = computed(() => {
    if (!isProcurementReport.value) return { labels: [], datasets: [] };
    const s = procurementReportSummary.value;
    const total = Number(s.total_pos) || 0;
    const completed = Number(s.completed) || 0;
    const rejected = Number(s.rejected) || 0;
    if (total === 0 && completed === 0 && rejected === 0) return { labels: [], datasets: [] };
    return {
        labels: ['Total POs', 'Received', 'Rejected'],
        datasets: [{
            label: 'Count',
            data: [total, completed, rejected],
            backgroundColor: PROCUREMENT_STATUS_COLORS,
            borderColor: PROCUREMENT_STATUS_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const procurementStatusChartOptions = computed(() => {
    const data = procurementStatusChartData.value?.datasets?.[0]?.data ?? [];
    const yMax = data.length ? Math.max(...data) + 2 : 10;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 24, right: 12, bottom: 12, left: 6 } },
        indexAxis: 'x',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'top',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 12,
                offset: 8,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: yMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});
const PROCUREMENT_DELIVERY_COLORS = ['rgb(59, 130, 246)', 'rgb(249, 115, 22)', 'rgb(14, 165, 233)'];
const procurementDeliveryChartData = computed(() => {
    if (!isProcurementReport.value) return { labels: [], datasets: [] };
    const s = procurementReportSummary.value;
    const totalDelivered = Number(s.total_delivered) || 0;
    const onTime = Number(s.on_time) || 0;
    const late = Number(s.late) || 0;
    if (totalDelivered === 0 && onTime === 0 && late === 0) return { labels: [], datasets: [] };
    return {
        labels: ['Total Delivered POs', 'On-time', 'Late'],
        datasets: [{
            label: 'Count',
            data: [totalDelivered, onTime, late],
            backgroundColor: PROCUREMENT_DELIVERY_COLORS,
            borderColor: PROCUREMENT_DELIVERY_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const procurementDeliveryChartOptions = computed(() => {
    const data = procurementDeliveryChartData.value?.datasets?.[0]?.data ?? [];
    const xMax = data.length ? Math.max(...data) + 2 : 10;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 6, right: 40, bottom: 12, left: 6 } },
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'start',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 8,
                offset: 16,
            },
        },
        scales: {
            x: {
                beginAtZero: true,
                max: xMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            y: {
                grid: { display: false },
                border: { display: false },
                ticks: { autoSkip: false, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});
const PROCUREMENT_QTY_COLORS = ['rgb(34, 197, 94)', 'rgb(245, 158, 11)', 'rgb(14, 165, 233)'];
const procurementQtyChartData = computed(() => {
    if (!isProcurementReport.value) return { labels: [], datasets: [] };
    const s = procurementReportSummary.value;
    const good = Number(s.qty_good) || 0;
    const fair = Number(s.qty_fair) || 0;
    const poor = Number(s.qty_poor) || 0;
    if (good === 0 && fair === 0 && poor === 0) return { labels: [], datasets: [] };
    return {
        labels: ['Good', 'Fair', 'Poor'],
        datasets: [{
            label: 'POs',
            data: [good, fair, poor],
            backgroundColor: PROCUREMENT_QTY_COLORS,
            borderColor: PROCUREMENT_QTY_COLORS,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const procurementQtyChartOptions = computed(() => {
    const data = procurementQtyChartData.value?.datasets?.[0]?.data ?? [];
    const yMax = data.length ? Math.max(...data) + 2 : 10;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 24, right: 12, bottom: 12, left: 6 } },
        indexAxis: 'x',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'top',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 12,
                offset: 8,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: yMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});

const ASSET_REPORT_CHART_COLORS = ['rgb(34, 197, 94)', 'rgb(245, 158, 11)', 'rgb(59, 130, 246)', 'rgb(14, 165, 233)', 'rgb(139, 92, 246)'];
const assetReportChartData = computed(() => {
    if (!isAssetReport.value) return { labels: [], datasets: [] };
    const byCat = assetReportSummary.value.by_category || {};
    const cats = assetReportCategoryColumns.value.length ? assetReportCategoryColumns.value : Object.keys(byCat);
    if (!cats.length) return { labels: [], datasets: [] };
    const data = cats.map(cat => {
        const v = byCat[cat];
        return typeof v === 'object' && v && 'total' in v ? Number(v.total) || 0 : Number(v) || 0;
    });
    const colors = cats.map((_, i) => ASSET_REPORT_CHART_COLORS[i % ASSET_REPORT_CHART_COLORS.length]);
    return {
        labels: cats,
        datasets: [{
            label: 'Assets',
            data,
            backgroundColor: colors,
            borderColor: colors,
            borderWidth: 0,
            borderRadius: 0,
            barPercentage: 0.65,
            categoryPercentage: 0.8,
        }],
    };
});
const assetReportChartOptions = computed(() => {
    const data = assetReportChartData.value?.datasets?.[0]?.data ?? [];
    const yMax = data.length ? Math.max(...data) + 2 : 10;
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        layout: { padding: { top: 24, right: 12, bottom: 12, left: 6 } },
        indexAxis: 'x',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
            datalabels: {
                anchor: 'end',
                align: 'top',
                color: '#000000',
                font: { weight: 'bold', size: 12 },
                formatter: (v) => v,
                padding: 12,
                offset: 8,
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                max: yMax,
                grid: { color: 'rgba(0,0,0,0.1)', drawTicks: false },
                border: { display: true, color: '#000000' },
                ticks: { precision: 0, stepSize: 1, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 4 },
            },
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10, weight: 'bold' }, color: '#000000', padding: 6 },
            },
        },
    };
});

// Region → District → Warehouse/Facility: clear dependents when parent changes
watch(() => filters.value.region_id, () => {
    filters.value.district_id = null;
    filters.value.warehouse_or_facility = '';
});

watch(() => filters.value.district_id, () => {
    if (!filters.value.district_id) {
        filters.value.warehouse_or_facility = '';
    }
});

watch(() => filters.value.report_type, (reportType) => {
    if (reportType === 'product_report' && filters.value.warehouse_or_facility?.startsWith('warehouse:')) {
        filters.value.warehouse_or_facility = '';
    }
    if (reportType === 'liquidation_disposal' && filters.value.warehouse_or_facility?.startsWith('facility:')) {
        filters.value.warehouse_or_facility = '';
    }
});

const FACILITY_TYPE_LABEL_TO_KEY = {
    'Primary Health Unit': 'primary_health_unit',
    'Health Center': 'health_center',
    'District Hospital': 'district_hospital',
    'Regional Hospital': 'regional_hospital',
};
function facilityTypeValue(row, label) {
    const key = FACILITY_TYPE_LABEL_TO_KEY[label];
    return key != null ? (row[key] ?? 0) : 0;
}

function formatNum(n) {
    if (n == null || n === '') return '–';
    return Number(n).toLocaleString();
}
function formatCost(n) {
    if (n == null || n === '' || Number(n) === 0) return '–';
    return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatExpiry(d) {
    if (!d) return '–';
    if (d.length === 10) return d; // Y-m-d
    return d;
}

function workflowStatusLabel(status) {
    const labels = { pending: 'Pending', submitted: 'Submitted', under_review: 'Under Review', approved: 'Approved', rejected: 'Rejected' };
    return labels[status] || status;
}
async function workflowAction(action) {
    const meta = warehouseReportMeta.value;
    const monthYear = filtersMonthYear.value;
    if (!meta?.report_id || !monthYear) return;
    const routes = {
        submit: { name: 'reports.warehouseMonthly.submit', method: 'put' },
        review: { name: 'reports.warehouseMonthly.review', method: 'put' },
        approve: { name: 'reports.warehouseMonthly.approve', method: 'put' },
        reject: { name: 'reports.warehouseMonthly.reject', method: 'put' },
    };
    const r = routes[action];
    if (!r) return;
    if (action === 'reject') showRejectModal.value = false;
    workflowActionLoading.value = true;
    try {
        const payload = { month_year: monthYear };
        if (action === 'reject') payload.reason = rejectReason.value || null;
        const { data } = await axios.put(route(r.name), payload);
        if (data.message) reportMessage.value = data.message;
        if (data.status) {
            const s = data.status;
            warehouseReportMeta.value = {
                ...meta,
                report_status: s,
                report_can_submit: s === 'pending' || s === 'rejected',
                report_can_review: s === 'submitted',
                report_can_approve_reject: s === 'under_review',
                rejection_reason: action === 'reject' ? (payload.reason || '') : meta.rejection_reason,
            };
            if (action === 'reject') rejectReason.value = '';
        }
        await generateReport();
    } catch (e) {
        reportMessage.value = e.response?.data?.message || 'Action failed.';
        console.error(e);
    } finally {
        workflowActionLoading.value = false;
    }
}

const reportFieldSaving = ref(false);
async function saveReportField(field) {
    const meta = warehouseReportMeta.value;
    if (!meta?.report_id || reportFieldSaving.value) return;
    const payload = {};
    if (field === 'adjustment_neg') payload.adjustment_neg = Number(meta.adjustment_neg) || 0;
    else if (field === 'adjustment_pos') payload.adjustment_pos = Number(meta.adjustment_pos) || 0;
    else if (field === 'months_of_stock') payload.months_of_stock = meta.months_of_stock ?? '';
    else if (field === 'stockout_days') payload.stockout_days = Number(meta.stockout_days) || 0;
    if (Object.keys(payload).length === 0) return;
    reportFieldSaving.value = true;
    try {
        const { data } = await axios.patch(route('reports.inventoryReport.update', meta.report_id), payload);
        if (data.success && data.report) {
            warehouseReportMeta.value = { ...meta, ...payload, adjustment_neg: data.report.negative_adjustment ?? meta.adjustment_neg, adjustment_pos: data.report.positive_adjustment ?? meta.adjustment_pos, months_of_stock: data.report.months_of_stock ?? meta.months_of_stock, stockout_days: data.report.stockout_days ?? meta.stockout_days };
        }
    } catch (e) {
        console.error(e);
    } finally {
        reportFieldSaving.value = false;
    }
}

const reportItemSaving = ref(false);
async function saveReportItemAdjustment(row) {
    if (!row?.report_item_id || reportItemSaving.value) return;
    reportItemSaving.value = true;
    try {
        const { data } = await axios.patch(route('reports.inventoryReportItem.update', row.report_item_id), {
            adjustment_neg: Number(row.adjustment_neg) || 0,
            adjustment_pos: Number(row.adjustment_pos) || 0,
        });
        if (data.success && data.item) {
            const id = row.report_item_id;
            const productId = row.product_id;
            const neg = Number(data.item.negative_adjustment) || 0;
            const pos = Number(data.item.positive_adjustment) || 0;
            const closing = data.item.closing_balance != null ? Number(data.item.closing_balance) : null;
            const totalCost = data.item.total_cost != null ? Number(data.item.total_cost) : null;
            reportData.value = reportData.value.map(r => {
                if (r.report_item_id !== id) return r;
                const next = { ...r, adjustment_neg: neg, adjustment_pos: pos };
                if (closing != null) next.closing_balance = closing;
                if (totalCost != null) next.total_cost = totalCost;
                return next;
            });
            // Recalc product-level total_closing_balance and total_cost: total cost = unit cost × closing balance per row, then sum
            if (productId != null) {
                const productRows = reportData.value.filter(r => r.product_id === productId);
                const sumClosing = productRows.reduce((s, r) => s + (Number(r.closing_balance) || 0), 0);
                const sumCost = productRows.reduce((s, r) => s + ((Number(r.unit_cost) || 0) * (Number(r.closing_balance) || 0)), 0);
                reportData.value = reportData.value.map(r => {
                    if (r.product_id !== productId) return r;
                    return { ...r, total_closing_balance: sumClosing, total_cost: Math.round(sumCost * 100) / 100 };
                });
            }
        }
    } catch (e) {
        console.error(e);
    } finally {
        reportItemSaving.value = false;
    }
}

async function generateReport() {
    generating.value = true;
    hasGenerated.value = true;
    try {
        const year = filters.value.year || currentYear;
        const month = filters.value.periodValue ?? currentMonth;
        const params = {
            report_type: filters.value.report_type || 'warehouse_inventory',
            region_id: filters.value.region_id || undefined,
            district_id: filters.value.district_id || undefined,
            year: year || undefined,
            month: month || undefined,
        };
        if (filters.value.warehouse_or_facility) {
            const [type, id] = filters.value.warehouse_or_facility.split(':');
            if (type === 'warehouse') params.warehouse_id = id;
            if (type === 'facility') params.facility_id = id;
        }
        params.report_period = filters.value.report_period || 'monthly';
        const { data } = await axios.get(route('reports.inventoryReportsUnified.data'), { params });
        reportMessage.value = data.message || '';
        if (data.success) {
            if (filters.value.report_type === 'product_report') {
                const d = data.data || {};
                productReportRows.value = d.rows || [];
                categoryColumns.value = d.category_columns || [];
                supplyClassColumns.value = d.supply_class_columns || [];
                reportData.value = [];
                facilitiesReportTypeColumns.value = [];
                orderReportSummary.value = {};
                transferReportSummary.value = {};
                procurementReportSummary.value = {};
            } else if (filters.value.report_type === 'liquidation_disposal') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
                orderReportSummary.value = {};
                transferReportSummary.value = {};
                procurementReportSummary.value = {};
            } else if (filters.value.report_type === 'expiry_report') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
                orderReportSummary.value = {};
                transferReportSummary.value = {};
                procurementReportSummary.value = {};
            } else if (filters.value.report_type === 'facilities_report') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                facilitiesReportTypeColumns.value = d.facility_type_columns || [];
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                orderReportSummary.value = {};
                transferReportSummary.value = {};
                procurementReportSummary.value = {};
            } else if (filters.value.report_type === 'order_report') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                orderReportSummary.value = d.summary || {};
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
                transferReportSummary.value = {};
                procurementReportSummary.value = {};
            } else if (filters.value.report_type === 'transfer_report') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                transferReportSummary.value = d.summary || {};
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
                orderReportSummary.value = {};
                procurementReportSummary.value = {};
            } else if (filters.value.report_type === 'procurement_report') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                procurementReportSummary.value = d.summary || {};
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
                orderReportSummary.value = {};
                transferReportSummary.value = {};
                assetReportCategoryColumns.value = [];
                assetReportSummary.value = {};
            } else if (filters.value.report_type === 'asset_report') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                assetReportCategoryColumns.value = d.category_columns || [];
                assetReportSummary.value = d.summary || {};
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
                orderReportSummary.value = {};
                transferReportSummary.value = {};
                procurementReportSummary.value = {};
            } else if (filters.value.report_type === 'report_submission_rate') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
                orderReportSummary.value = {};
                transferReportSummary.value = {};
                procurementReportSummary.value = {};
                assetReportCategoryColumns.value = [];
                assetReportSummary.value = {};
            } else {
                let rows = data.data || [];
                if (filters.value.report_type === 'warehouse_inventory' && rows.length === 0) {
                    rows = [WAREHOUSE_INVENTORY_EMPTY_ROW];
                }
                reportData.value = rows;
                warehouseReportMeta.value = (filters.value.report_type === 'warehouse_inventory' && data.report_meta) ? data.report_meta : null;
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
                orderReportSummary.value = {};
                transferReportSummary.value = {};
                procurementReportSummary.value = {};
                assetReportCategoryColumns.value = [];
                assetReportSummary.value = {};
            }
        } else {
            reportData.value = [];
            warehouseReportMeta.value = null;
            productReportRows.value = [];
            categoryColumns.value = [];
            supplyClassColumns.value = [];
            facilitiesReportTypeColumns.value = [];
            orderReportSummary.value = {};
            transferReportSummary.value = {};
            procurementReportSummary.value = {};
            assetReportCategoryColumns.value = [];
            assetReportSummary.value = {};
        }
    } catch (e) {
        reportData.value = [];
        warehouseReportMeta.value = null;
        productReportRows.value = [];
        categoryColumns.value = [];
        supplyClassColumns.value = [];
        console.error(e);
    } finally {
        generating.value = false;
    }
}
</script>
