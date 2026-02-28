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
            <!-- Filters: five in one row, then Generate Report button with period input to its right -->
            <div class="bg-emerald-50/90 border border-emerald-200 rounded-lg shadow-sm p-6 mb-6">
                <!-- Row 1: five filters with labels above -->
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
                            :disabled="isProductReport && !filters.district_id"
                        >
                            <option :value="''">{{ isProductReport && !filters.district_id ? 'Select District first' : (isProductReport ? 'Facility' : 'Warehouse/Facility') }}</option>
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Report Period</label>
                        <div class="mt-1 block w-full rounded-md border border-gray-300 bg-white shadow-sm overflow-hidden">
                            <input
                                v-model="filters.monthYear"
                                type="month"
                                class="block w-full min-h-[38px] py-2 px-3 focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm border-0 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>
                <!-- Row 2: Generate Report button below first three filters (aligned with Report Period to its right in row 1) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-5 mt-4 items-end">
                    <div class="lg:col-span-3 flex items-end">
                        <button
                            type="button"
                            @click="generateReport"
                            :disabled="generating"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2.5 bg-emerald-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-emerald-700 focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
                        >
                            <span v-if="generating">Generating...</span>
                            <span v-else>Generate Report</span>
                        </button>
                    </div>
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
                <div v-else-if="!hasAnyData && hasGenerated" class="p-8 text-center text-gray-500">
                    {{ reportMessage || 'No data found for the selected filters. Try a different report type or period.' }}
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

                <!-- Default Inventory Report Table -->
                <div v-else-if="!isProductReport && !isLiquidationDisposalReport && !isExpiryReport && !isFacilitiesReport && reportData.length > 0" class="overflow-x-auto">
                    <table class="min-w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">Item</th>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">Category</th>
                                <th rowspan="2" class="px-3 py-2 text-left text-xs font-bold text-gray-700 border border-gray-300">UoM</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Item Details</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Beginning<br>Balance</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">QTY<br>Received</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">QTY<br>Issued</th>
                                <th colspan="2" class="px-3 py-2 text-center text-xs font-bold text-gray-700 border border-gray-300">Adjust<br>ments</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Closing<br>Balance</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Total<br>Closing<br>Balance</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">AMC</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">MOS<br>(Months<br>of Stock)</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Stockout<br>Days</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Unit<br>cost</th>
                                <th rowspan="2" class="px-3 py-2 text-right text-xs font-bold text-gray-700 border border-gray-300">Total<br>Cost</th>
                            </tr>
                            <tr class="bg-gray-50">
                                <th class="px-3 py-1 text-left text-xs font-medium text-gray-600 border border-gray-300">Batch No.:</th>
                                <th class="px-3 py-1 text-left text-xs font-medium text-gray-600 border border-gray-300">Expiry Date</th>
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
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.beginning_balance) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.qty_received) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.qty_issued) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.adjustment_neg) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.adjustment_pos) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300">{{ formatNum(row.closing_balance) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ formatNum(row.total_closing_balance) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ formatNum(row.amc) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ row.mos ?? '–' }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top">{{ formatNum(row.stockout_days) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top">{{ formatCost(row.unit_cost) }}</td>
                                <td v-if="row.is_first_batch" :rowspan="row.rowspan" class="px-3 py-2 text-sm text-gray-900 text-right border border-gray-300 align-top font-medium">{{ formatCost(row.total_cost) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-8 text-center text-gray-500">
                    Select at least one location filter (Region, District, or Warehouse/Facility), then click Generate Report.
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

const props = defineProps({
    regions: { type: Array, default: () => [] },
    districts: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    reportTypes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const now = new Date();
const defaultMonthYear = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

const filters = ref({
    region_id: props.filters?.region_id ?? null,
    district_id: props.filters?.district_id ?? null,
    warehouse_or_facility: props.filters?.warehouse_or_facility ?? '',
    report_type: props.filters?.report_type ?? 'warehouse_inventory',
    monthYear: props.filters?.monthYear ?? defaultMonthYear,
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
        return item.includes(q) || facility.includes(q) || warehouse.includes(q) || name.includes(q) || district.includes(q);
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

watch(() => filters.value.region_id, () => {
    filters.value.district_id = null;
    if (filters.value.warehouse_or_facility?.startsWith('facility:')) {
        filters.value.warehouse_or_facility = '';
    }
});

watch(() => filters.value.district_id, () => {
    if (!filters.value.district_id && filters.value.warehouse_or_facility?.startsWith('facility:')) {
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

async function generateReport() {
    generating.value = true;
    hasGenerated.value = true;
    try {
        const monthYear = filters.value.monthYear || defaultMonthYear;
        const [year, month] = monthYear.split('-').map(Number);
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
            } else if (filters.value.report_type === 'liquidation_disposal') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
            } else if (filters.value.report_type === 'expiry_report') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
            } else if (filters.value.report_type === 'facilities_report') {
                const d = data.data || {};
                reportData.value = d.rows || [];
                facilitiesReportTypeColumns.value = d.facility_type_columns || [];
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
            } else {
                reportData.value = data.data || [];
                productReportRows.value = [];
                categoryColumns.value = [];
                supplyClassColumns.value = [];
                facilitiesReportTypeColumns.value = [];
            }
        } else {
            reportData.value = [];
            productReportRows.value = [];
            categoryColumns.value = [];
            supplyClassColumns.value = [];
            facilitiesReportTypeColumns.value = [];
        }
    } catch (e) {
        reportData.value = [];
        productReportRows.value = [];
        categoryColumns.value = [];
        supplyClassColumns.value = [];
        console.error(e);
    } finally {
        generating.value = false;
    }
}
</script>
