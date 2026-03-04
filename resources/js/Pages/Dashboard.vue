<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";
import Chart from 'chart.js/auto';
import ChartDataLabels from 'chartjs-plugin-datalabels';
import { Bar, Doughnut, Line, Pie } from 'vue-chartjs';
import dayjs from 'dayjs';
import axios from 'axios';
import Datepicker from 'vue-datepicker-next';
import 'vue-datepicker-next/index.css';

// Register the datalabels plugin
Chart.register(ChartDataLabels);




const props = defineProps({
    dashboardData: {
        type: Object,
        required: true,
        default: () => ({ summary: [] })
    },
    loadSuppliers: {
        type: Array
    },
    orderCard: {
        type: Object,
        required: true,
        default: () => ({ filter: 'PO', counts: { PKL: 0, PO: 0, BO: 0 } })
    },
    productCategoryCard: {
        type: Object,
        required: true,
        default: () => ({ Drugs: 0, Consumable: 0, Lab: 0 })
    },
    transferReceivedCard: {
        type: Number,
        required: true,
        default: 0
    },
    warehouseCountCard: {
        type: Number,
        required: true,
        default: 0
    },
    orderStats: {
        type: Object,
        required: true,
        default: () => ({
            pending: 0, reviewed: 0, approved: 0, in_process: 0, dispatched: 0, received: 0, rejected: 0
        })
    },
    totalApprovedPOCost: {
        type: Number,
        required: true,
        default: 0
    },
    fulfillmentData: {
        type: Array,
        required: true,
        default: () => []
    },
    overallFulfillment: {
        type: Number,
        required: true,
        default: 0
    },
    suppliers: {
        type: Array,
        required: true,
        default: () => []
    },
    ordersDelayedCount: {
        type: Number,
        required: true,
        default: 0
    },
    issuedMonths: Array,
    selectedIssuedMonth: String,
    issuedData: Array,
    warehouseChartData: {
        type: Array,
        required: true,
        default: () => []
    },
    inventoryStatusCounts: {
        type: Array,
        required: false,
        default: () => []
    },
    expiredStats: {
        type: Object,
        required: false,
        default: () => ({
            expired: 0,
            expiring_within_6_months: 0,
            expiring_within_1_year: 0
        })
    },
    assetStats: {
        type: Object,
        required: false,
        default: () => ({
            Furniture: 0,
            IT: 0,
            'Medical equipment': 0,
            Vehicles: 0,
            Others: 0
        })
    },
    assetStatusStats: {
        type: Object,
        required: false,
        default: () => ({
            'In Use': 0,
            'Functioning': 0,
            'Not functioning': 0,
            'Needs Maintenance': 0,
            'Pending Approval': 0,
            'Retired': 0,
            'Disposed': 0
        })
    },
    warehouses: {
        type: Array,
        required: false,
        default: () => []
    }
});



function getCount(abbr) {
    const found = props.dashboardData.summary.find(item => item.label === abbr);
    return found ? found.value : 0;
}

// Date filters
const dateRange = ref([
    dayjs().startOf('month').toDate(),
    dayjs().endOf('month').toDate()
]);

// Date presets for the datepicker
const datePresets = [
    { label: 'Today', value: [new Date(), new Date()] },
    { label: 'Yesterday', value: [dayjs().subtract(1, 'day').toDate(), dayjs().subtract(1, 'day').toDate()] },
    { label: 'Last 7 days', value: [dayjs().subtract(7, 'day').toDate(), new Date()] },
    { label: 'Last 30 days', value: [dayjs().subtract(30, 'day').toDate(), new Date()] },
    { label: 'This month', value: [dayjs().startOf('month').toDate(), dayjs().endOf('month').toDate()] },
    { label: 'Last month', value: [dayjs().subtract(1, 'month').startOf('month').toDate(), dayjs().subtract(1, 'month').endOf('month').toDate()] },
    { label: 'This quarter', value: [dayjs().startOf('quarter').toDate(), dayjs().endOf('quarter').toDate()] },
    { label: 'This year', value: [dayjs().startOf('year').toDate(), dayjs().endOf('year').toDate()] }
];

// Order type filter - convert to object format for Multiselect
const orderTypeOptions = [
    { value: 'PO', label: 'Purchase Order (Approved)' },
    { value: 'PKL', label: 'Packing List' },
    { value: 'BO', label: 'Back Order' }
];

const selectedOrderType = ref(orderTypeOptions.find(opt => opt.value === props.orderCard.filter) || orderTypeOptions[0]);

const orderCounts = computed(() => props.orderCard.counts);
const orderLabels = {
    PO: 'Purchase Order (Approved)',
    PKL: 'Packing List',
    BO: 'Back Order'
};

// Get selected order type value
const selectedOrderTypeValue = computed(() => {
    if (typeof selectedOrderType.value === 'object' && selectedOrderType.value !== null) {
        return selectedOrderType.value.value;
    }
    return selectedOrderType.value;
});

const totalOrders = computed(() => {
    const stats = props.orderStats;
    return (stats.pending || 0) +
           (stats.reviewed || 0) +
           (stats.approved || 0) +
           (stats.in_process || 0) +
           (stats.dispatched || 0) +
           (stats.delivered || 0) +
           (stats.received || 0) +
           (stats.rejected || 0);
});

// Order status configuration for dashboard vertical overview
const orderStatusConfig = [
    { key: 'pending', label: 'Pending', stroke: '#eab308', textClass: 'text-yellow-600', icon: '/assets/images/pending.png' },
    { key: 'reviewed', label: 'Reviewed', stroke: '#22c55e', textClass: 'text-green-600', icon: '/assets/images/review.png' },
    { key: 'approved', label: 'Approved', stroke: '#22c55e', textClass: 'text-green-600', icon: '/assets/images/approved.png' },
    { key: 'in_process', label: 'In Process', stroke: '#3b82f6', textClass: 'text-blue-600', icon: '/assets/images/inprocess.png' },
    { key: 'dispatched', label: 'Dispatched', stroke: '#8b5cf6', textClass: 'text-purple-600', icon: '/assets/images/dispatch.png' },
    { key: 'delivered', label: 'Delivered', stroke: '#f59e42', textClass: 'text-orange-600', icon: '/assets/images/delivery.png' },
    { key: 'received', label: 'Received', stroke: '#6366f1', textClass: 'text-indigo-600', icon: '/assets/images/received.png' },
    { key: 'rejected', label: 'Rejected', stroke: '#ef4444', textClass: 'text-red-600', icon: '/assets/images/rejected.png' }
];

// Tab functionality
const activeTab = ref('warehouse');

// Order Status Chart Filter
const selectedOrderStatus = ref([]);
const orderStatusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'reviewed', label: 'Reviewed' },
    { value: 'approved', label: 'Approved' },
    { value: 'in_process', label: 'In Process' },
    { value: 'dispatched', label: 'Dispatched' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'received', label: 'Received' },
    { value: 'rejected', label: 'Rejected' }
];

const supplierOptions = computed(() => [
  { label: 'All Suppliers', value: '' },
  ...(props.loadSuppliers || []).map(s => ({ label: s, value: s }))
]);
const selectedSupplier = ref(supplierOptions.value[0]);

const filteredFulfillment = computed(() => {
    let selectedValue;
    
    // Handle both object and string values from Multiselect
    if (typeof selectedSupplier.value === 'object' && selectedSupplier.value !== null) {
        selectedValue = selectedSupplier.value.value;
    } else {
        selectedValue = selectedSupplier.value;
    }
    
    if (selectedValue === '' || !selectedValue) { // Changed from 'all' to ''
        return props.overallFulfillment || 0;
    }
    
    const supplierData = props.fulfillmentData.find(item => item.supplier_name === selectedValue);
    return supplierData ? supplierData.fulfillment_percentage : 0;
});

const selectedSupplierLabel = computed(() => {
    let selectedValue;
    
    // Handle both object and string values from Multiselect
    if (typeof selectedSupplier.value === 'object' && selectedSupplier.value !== null) {
        selectedValue = selectedSupplier.value.value;
    } else {
        selectedValue = selectedSupplier.value;
    }
    
    if (selectedValue === '' || !selectedValue) { // Changed from 'all' to ''
        return 'All Suppliers';
    }
    return selectedValue;
});

// Filtered total cost based on supplier selection
const filteredTotalCost = computed(() => {
    let selectedValue;
    
    // Handle both object and string values from Multiselect
    if (typeof selectedSupplier.value === 'object' && selectedSupplier.value !== null) {
        selectedValue = selectedSupplier.value.value;
    } else {
        selectedValue = selectedSupplier.value;
    }
    
    if (selectedValue === '' || !selectedValue) {
        return props.totalApprovedPOCost || 0;
    }
    
    // This would need to be implemented based on your backend data structure
    // For now, we'll return the original value
    // You should filter the total cost based on the selected supplier
    return props.totalApprovedPOCost || 0;
});

// Watch for date range changes
watch(dateRange, (newDateRange) => {
    if (newDateRange && newDateRange.length === 2) {
        console.log('Date range changed:', { 
            from: dayjs(newDateRange[0]).format('YYYY-MM-DD'), 
            to: dayjs(newDateRange[1]).format('YYYY-MM-DD') 
        });
    }
}, { deep: true });

// Watch for order type changes
watch(selectedOrderType, (newValue) => {
    console.log('Order type changed:', newValue);
    // You can emit events here to notify the parent component about order type changes
}, { deep: true });

// Watch for supplier changes
watch(selectedSupplier, (newValue) => {
    console.log('Supplier changed:', newValue);
    // You can emit events here to notify the parent component about supplier changes
}, { deep: true });

// Watch for order status changes
watch(selectedOrderStatus, (newValue) => {
    console.log('Order status changed:', newValue);
}, { deep: true });

// Function for status filter button
const clearAllStatuses = () => {
    selectedOrderStatus.value = [];
};

// Helper function to get date range values
const getDateRangeValues = () => {
    if (dateRange.value && dateRange.value.length === 2) {
        return {
            from: dayjs(dateRange.value[0]).format('YYYY-MM-DD'),
            to: dayjs(dateRange.value[1]).format('YYYY-MM-DD')
        };
    }
    return { from: '', to: '' };
};

// Method to handle filter changes and update data
const handleFilterChange = () => {
    const dateValues = getDateRangeValues();
    const filters = {
        dateFrom: dateValues.from,
        dateTo: dateValues.to,
        orderType: selectedOrderTypeValue.value,
        supplier: getSelectedSupplierValue()
    };
    
    console.log('Filters changed:', filters);
    
    // Make an API call to update the dashboard data based on filters
    router.get('/dashboard', { 
        data: filters,
        preserveState: true,
        preserveScroll: true
    });
};

const warehouseDataType = ref('beginning_balance');
// Available data types: 'beginning_balance','received_quantity','issued_quantity','closing_balance'

const selectedWarehouse = ref('');

const facilityDataType = ref('opening_balance');
// Available data types: 'opening_balance' (Beginning Balance), 'stock_received' (QTY Received), 'stock_issued' (Issued Quantity), 'closing_balance' (Closing Balance), 'positive_adjustments', 'negative_adjustments'

// Facility filtering
const facilities = ref([]);
const selectedFacility = ref(null);

// Chart data state
const localWarehouseChartData = ref([]);
const localFacilityChartData = ref([]);
const chartCount = ref(0);
const facilityChartCount = ref(0);
const categorizedData = ref([]);
const facilityCategorizedData = ref([]);

// Computed property to group charts into rows of 3
const chartRows = computed(() => {
    const rows = [];
    for (let i = 0; i < localWarehouseChartData.value.length; i += 3) {
        rows.push(localWarehouseChartData.value.slice(i, i + 3));
    }
    return rows;
});

// Computed property to group facility charts into rows of 3
const facilityChartRows = computed(() => {
    const rows = [];
    for (let i = 0; i < localFacilityChartData.value.length; i += 3) {
        rows.push(localFacilityChartData.value.slice(i, i + 3));
    }
    return rows;
});

const isLoadingChart = ref(false);
const chartError = ref(null);
const isLoadingFacilityChart = ref(false);
const facilityChartError = ref(null);

const months = Array.from({ length: 12 }, (_, i) =>
  dayjs().subtract(i, 'month').format('YYYY-MM')
);
const issuedMonth = ref(months[1]); // Use previous month as default to match backend
const facilityMonth = ref(months[1]); // Use previous month as default to match backend

watch([
    () => warehouseDataType.value,
    () => issuedMonth.value,
    () => selectedWarehouse.value
], () => {
    handleTracertItems();
});

watch([
    () => facilityDataType.value,
    () => facilityMonth.value,
    () => selectedFacility.value
], () => {
    handleFacilityTracertItems();
});

// Get human-readable label for data type
function getTypeLabel(type) {
    const labels = {
        'beginning_balance': 'Beginning Balance',
        'received_quantity': 'Quantity Received',
        'issued_quantity': 'Quantity Issued', 
        'closing_balance': 'Closing Balance'
    };
    return labels[type] || 'Quantity';
}

// Get human-readable label for facility data type
function getFacilityTypeLabel(type) {
    const labels = {
        'opening_balance': 'Beginning Balance',
        'stock_received': 'QTY Received',
        'stock_issued': 'Issued Quantity', 
        'closing_balance': 'Closing Balance (Calculated)',
        'positive_adjustments': 'Positive Adjustments',
        'negative_adjustments': 'Negative Adjustments'
    };
    return labels[type] || 'Quantity';
}

async function handleTracertItems() {
    isLoadingChart.value = true;
    chartError.value = null;
    
    const query = {};
    if (warehouseDataType.value){
        query.type = warehouseDataType.value;
    } else {
        query.type = 'beginning_balance';
    }
    if (issuedMonth.value){
        query.month = issuedMonth.value;
    }
    if (selectedWarehouse.value){
        query.warehouse_id = selectedWarehouse.value;
    }

    try {
        const response = await axios.post(route('dashboard.warehouse.tracert-items'), query);
        console.log('API Response:', response.data);
        
        if (response.data.success && response.data.chartData && response.data.chartData.charts) {
            // Handle successful response with multiple charts
            const charts = response.data.chartData.charts;
            localWarehouseChartData.value = charts.map(chart => ({
                id: chart.id,
                category: chart.category,
                categoryDisplay: chart.categoryDisplay,
                labels: chart.labels || ['No Data'],
                datasets: [{
                    label: getTypeLabel(warehouseDataType.value),
                    data: chart.data || [0],
                    backgroundColor: chart.backgroundColors || ['rgba(156, 163, 175, 0.8)'],
                    borderColor: chart.borderColors || ['rgba(156, 163, 175, 1)'],
                    borderWidth: 0,
                    borderRadius: { topLeft: 100, topRight: 100, bottomLeft: 0, bottomRight: 0 },
                    borderSkipped: 'bottom'
                }]
            }));
            chartCount.value = response.data.chartData.totalCharts;
            chartError.value = null;
            
            // Store items data if available
            if (response.data.items) {
                categorizedData.value = response.data.items;
            }
        } else {
            // Handle API success but no data
            chartError.value = response.data.message || 'No data available for the selected period';
            localWarehouseChartData.value = [{
                id: 1,
                category: 'No Data',
                categoryDisplay: 'No Data Available',
                labels: ['No Data'],
                datasets: [{
                    label: 'Quantity',
                    data: [0],
                    backgroundColor: ['rgba(156, 163, 175, 0.8)'],
                    borderColor: ['rgba(156, 163, 175, 1)'],
                    borderWidth: 0,
                    borderRadius: { topLeft: 100, topRight: 100, bottomLeft: 0, bottomRight: 0 },
                    borderSkipped: 'bottom'
                }]
            }];
            chartCount.value = 1;
            categorizedData.value = [];
        }
    } catch (error) {
        console.error('Error fetching tracert items:', error);
        chartError.value = error.response?.data?.message || 'Network error occurred while loading data';
        // Set empty chart data on error
        localWarehouseChartData.value = [{
            id: 1,
            category: 'Error',
            categoryDisplay: 'Error Loading Data',
            labels: ['Error'],
                datasets: [{
                label: 'Quantity',
                data: [0],
                backgroundColor: ['rgba(239, 68, 68, 0.8)'],
                borderColor: ['rgba(239, 68, 68, 1)'],
                    borderWidth: 0,
                    borderRadius: { topLeft: 100, topRight: 100, bottomLeft: 0, bottomRight: 0 },
                    borderSkipped: 'bottom'
            }]
        }];
        chartCount.value = 1;
    } finally {
        isLoadingChart.value = false;
    }
}

async function handleFacilityTracertItems() {
    isLoadingFacilityChart.value = true;
    facilityChartError.value = null;
    
    const query = {};
    if (facilityDataType.value){
        query.type = facilityDataType.value;
    } else {
        query.type = 'opening_balance';
    }
    if (facilityMonth.value){
        query.month = facilityMonth.value;
    }
    if (selectedFacility.value && selectedFacility.value.id) {
        query.facility_id = selectedFacility.value.id;
    }

    try {
        const response = await axios.post(route('dashboard.facility.tracert-items'), query);
        console.log('Facility API Response:', response.data);
        
        // Update facilities list if provided
        if (response.data.facilities) {
            facilities.value = response.data.facilities;
        }
        
        if (response.data.success && response.data.chartData && response.data.chartData.charts) {
            // Handle successful response with multiple charts
            const charts = response.data.chartData.charts;
            localFacilityChartData.value = charts.map(chart => ({
                id: chart.id,
                category: chart.category,
                categoryDisplay: chart.categoryDisplay,
                labels: chart.labels || ['No Data'],
                datasets: [{
                    label: getFacilityTypeLabel(facilityDataType.value),
                    data: chart.data || [0],
                    backgroundColor: chart.backgroundColors || ['rgba(156, 163, 175, 0.8)'],
                    borderColor: chart.borderColors || ['rgba(156, 163, 175, 1)'],
                    borderWidth: 0,
                    borderRadius: { topLeft: 100, topRight: 100, bottomLeft: 0, bottomRight: 0 },
                    borderSkipped: 'bottom'
                }]
            }));
            facilityChartCount.value = response.data.chartData.totalCharts;
            facilityChartError.value = null;
            
            // Store items data if available
            if (response.data.items) {
                facilityCategorizedData.value = response.data.items;
            }
        } else {
            // Handle API success but no data
            facilityChartError.value = response.data.message || 'No facility data available for the selected period';
            localFacilityChartData.value = [{
                id: 1,
                category: 'No Data',
                categoryDisplay: 'No Data Available',
                labels: ['No Data'],
                datasets: [{
                    label: 'Quantity',
                    data: [0],
                    backgroundColor: ['rgba(156, 163, 175, 0.8)'],
                    borderColor: ['rgba(156, 163, 175, 1)'],
                    borderWidth: 0,
                    borderRadius: { topLeft: 100, topRight: 100, bottomLeft: 0, bottomRight: 0 },
                    borderSkipped: 'bottom'
                }]
            }];
            facilityChartCount.value = 1;
            facilityCategorizedData.value = [];
        }
    } catch (error) {
        console.error('Error fetching facility tracert items:', error);
        facilityChartError.value = error.response?.data?.message || 'Network error occurred while loading facility data';
        
        // Update facilities list if provided in error response
        if (error.response?.data?.facilities) {
            facilities.value = error.response.data.facilities;
        }
        
        // Set empty chart data on error
        localFacilityChartData.value = [{
            id: 1,
            category: 'Error',
            categoryDisplay: 'Error Loading Data',
            labels: ['Error'],
            datasets: [{
                label: 'Quantity',
                data: [0],
                backgroundColor: ['rgba(239, 68, 68, 0.8)'],
                borderColor: ['rgba(239, 68, 68, 1)'],
                borderWidth: 0,
                borderRadius: { topLeft: 100, topRight: 100, bottomLeft: 0, bottomRight: 0 },
                borderSkipped: 'bottom'
            }]
        }];
        facilityChartCount.value = 1;
    } finally {
        isLoadingFacilityChart.value = false;
    }
}

// Update chart data based on API response
function updateChartData(chartData) {
    if (!chartData || !chartData.labels || !chartData.data) {
        localWarehouseChartData.value = {
            labels: ['No Data'],
            datasets: [{
                label: 'Quantity',
                data: [0],
                backgroundColor: ['rgba(156, 163, 175, 0.8)'],
                borderColor: ['rgba(156, 163, 175, 1)'],
                borderWidth: 0
            }]
        };
        return;
    }

    localWarehouseChartData.value = {
        labels: chartData.labels,
        datasets: [{
            label: getDataTypeLabel(warehouseDataType.value),
            data: chartData.data,
            backgroundColor: chartData.backgroundColors || generateColors(chartData.data.length, true),
            borderColor: chartData.borderColors || generateColors(chartData.data.length, false),
            borderWidth: 0,
            borderRadius: { topLeft: 100, topRight: 100, bottomLeft: 0, bottomRight: 0 },
            borderSkipped: 'bottom'
        }]
    };
}

// Get human-readable label for data type
function getDataTypeLabel(type) {
    const labels = {
        'beginning_balance': 'Beginning Balance',
        'received_quantity': 'Quantity Received',
        'issued_quantity': 'Quantity Issued',
        'closing_balance': 'Closing Balance'
    };
    return labels[type] || 'Quantity';
}

// Generate colors for chart
function generateColors(count, isBackground = true) {
    const professionalGradients = [
        // Modern professional gradient colors
        isBackground ? 'rgba(99, 102, 241, 0.9)' : 'rgba(99, 102, 241, 1)', // Indigo
        isBackground ? 'rgba(16, 185, 129, 0.9)' : 'rgba(16, 185, 129, 1)', // Emerald
        isBackground ? 'rgba(245, 158, 11, 0.9)' : 'rgba(245, 158, 11, 1)', // Amber
        isBackground ? 'rgba(239, 68, 68, 0.9)' : 'rgba(239, 68, 68, 1)',   // Red
        isBackground ? 'rgba(139, 92, 246, 0.9)' : 'rgba(139, 92, 246, 1)', // Purple
        isBackground ? 'rgba(236, 72, 153, 0.9)' : 'rgba(236, 72, 153, 1)', // Pink
        isBackground ? 'rgba(6, 182, 212, 0.9)' : 'rgba(6, 182, 212, 1)',   // Cyan
        isBackground ? 'rgba(34, 197, 94, 0.9)' : 'rgba(34, 197, 94, 1)',   // Green
        isBackground ? 'rgba(251, 146, 60, 0.9)' : 'rgba(251, 146, 60, 1)', // Orange
        isBackground ? 'rgba(168, 85, 247, 0.9)' : 'rgba(168, 85, 247, 1)', // Violet
        isBackground ? 'rgba(59, 130, 246, 0.9)' : 'rgba(59, 130, 246, 1)', // Blue
        isBackground ? 'rgba(20, 184, 166, 0.9)' : 'rgba(20, 184, 166, 1)', // Teal
        isBackground ? 'rgba(245, 101, 101, 0.9)' : 'rgba(245, 101, 101, 1)', // Rose
        isBackground ? 'rgba(132, 204, 22, 0.9)' : 'rgba(132, 204, 22, 1)', // Lime
        isBackground ? 'rgba(251, 191, 36, 0.9)' : 'rgba(251, 191, 36, 1)'  // Yellow
    ];
    
    const colors = [];
    for (let i = 0; i < count; i++) {
        colors.push(professionalGradients[i % professionalGradients.length]);
    }
    return colors;
}

// Generate colors for category charts
function generateCategoryColors(count) {
    const baseColors = [
        { background: 'rgba(59, 130, 246, 0.8)', border: 'rgba(59, 130, 246, 1)' }, // Blue
        { background: 'rgba(16, 185, 129, 0.8)', border: 'rgba(16, 185, 129, 1)' }, // Green
        { background: 'rgba(245, 158, 11, 0.8)', border: 'rgba(245, 158, 11, 1)' }, // Yellow
        { background: 'rgba(239, 68, 68, 0.8)', border: 'rgba(239, 68, 68, 1)' }, // Red
        { background: 'rgba(139, 92, 246, 0.8)', border: 'rgba(139, 92, 246, 1)' }, // Purple
        { background: 'rgba(236, 72, 153, 0.8)', border: 'rgba(236, 72, 153, 1)' }, // Pink
        { background: 'rgba(14, 165, 233, 0.8)', border: 'rgba(14, 165, 233, 1)' }, // Sky
        { background: 'rgba(34, 197, 94, 0.8)', border: 'rgba(34, 197, 94, 1)' }, // Emerald
        { background: 'rgba(251, 146, 60, 0.8)', border: 'rgba(251, 146, 60, 1)' }, // Orange
        { background: 'rgba(168, 85, 247, 0.8)', border: 'rgba(168, 85, 247, 1)' }  // Violet
    ];

    const backgroundColors = [];
    const borderColors = [];

    for (let i = 0; i < count; i++) {
        const colorIndex = i % baseColors.length;
        backgroundColors.push(baseColors[colorIndex].background);
        borderColors.push(baseColors[colorIndex].border);
    }

    return {
        background: backgroundColors,
        border: borderColors
    };
}

// Chart options for different chart types
const doughnutChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '50%',
    plugins: {
        legend: { 
            display: true,
            position: 'bottom',
            labels: {
                padding: 25,
                usePointStyle: true,
                font: {
                    size: 14,
                    weight: '600',
                    family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
                },
                generateLabels: function(chart) {
                    const data = chart.data;
                    if (data.labels.length && data.datasets.length) {
                        return data.labels.map((label, i) => {
                            const dataset = data.datasets[0];
                            const value = dataset.data[i];
                            const total = dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            
                            return {
                                text: `${label}: ${value.toLocaleString()} (${percentage}%)`,
                                fillStyle: dataset.backgroundColor[i],
                                strokeStyle: dataset.borderColor[i],
                                lineWidth: 2,
                                pointStyle: 'circle',
                                hidden: false,
                                index: i
                            };
                        });
                    }
                    return [];
                }
            }
        },
        tooltip: { 
            enabled: true,
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#333333',
            bodyColor: '#333333',
            borderColor: 'rgba(0, 0, 0, 0.1)',
            borderWidth: 0,
            cornerRadius: 6,
            padding: 10,
            displayColors: true,
            titleFont: {
                size: 13,
                weight: '600'
            },
            bodyFont: {
                size: 12
            },
            callbacks: {
                title: function(context) {
                    return context[0].label;
                },
                label: function(context) {
                    const value = context.parsed;
                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                    return `${value.toLocaleString()} items (${percentage}%)`;
                }
            }
        },
        datalabels: {
            display: true,
            color: '#ffffff',
            font: {
                weight: 'bold',
                size: 20,
                family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },
            formatter: function(value, context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                return value > 0 ? `${percentage}%` : '';
            },
            textAlign: 'center',
            textBaseline: 'middle',
            textShadowColor: 'rgba(0, 0, 0, 0.8)',
            textShadowBlur: 3,
            textShadowOffsetX: 2,
            textShadowOffsetY: 2,
            backgroundColor: 'rgba(0, 0, 0, 0.3)',
            borderColor: 'rgba(255, 255, 255, 0.8)',
            borderWidth: 1,
            borderRadius: 8,
            padding: 6
        }
    },
    animation: {
        animateRotate: true,
        animateScale: true,
        duration: 1200,
        easing: 'easeOutCubic'
    },
    layout: {
        padding: {
            top: 30,
            bottom: 20,
            left: 20,
            right: 20
        }
    }
};

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            display: false 
        },
        tooltip: { 
            enabled: true,
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            titleColor: '#f8fafc',
            bodyColor: '#f1f5f9',
            borderColor: 'rgba(99, 102, 241, 0.3)',
            borderWidth: 1,
            cornerRadius: 12,
            padding: 16,
            displayColors: true,
            titleFont: {
                size: 14,
                weight: '600',
                family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },
            bodyFont: {
                size: 13,
                weight: '500',
                family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },
            callbacks: {
                title: function(context) {
                    return context[0].label;
                },
                label: function(context) {
                    return formatLargeNumberForTooltip(context.parsed.y);
                }
            }
        },
        datalabels: {
            display: true,
            anchor: 'center',
            align: 'center',
            color: '#ffffff',
            font: {
                weight: 'bold',
                size: 12,
                family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },
            formatter: function(value, context) {
                return value > 0 ? formatLargeNumber(value) : '';
            },
            padding: 0,
            textShadowColor: 'rgba(0, 0, 0, 0.3)',
            textShadowBlur: 2,
            textShadowOffsetX: 1,
            textShadowOffsetY: 1
        }
    },
    scales: {
        y: { 
            beginAtZero: true,
            grid: {
                display: true,
                color: 'rgba(148, 163, 184, 0.1)',
                drawBorder: false,
                lineWidth: 1
            },
            ticks: {
                callback: function(value) {
                    return formatLargeNumber(value);
                },
                font: {
                    size: 11,
                    weight: '600',
                    family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
                },
                padding: 12,
                color: '#64748b'
            }
        },
        x: {
            grid: {
                display: false
            },
            border: { display: false },
            ticks: {
                maxRotation: 45,
                minRotation: 0,
                font: {
                    size: 11,
                    weight: '600',
                    family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
                },
                padding: 12,
                color: '#64748b'
            }
        }
    },
    animation: {
        duration: 1200,
        easing: 'easeOutCubic',
        delay: (context) => {
            return context.dataIndex * 100;
        }
    },
    hover: {
        animationDuration: 200
    },
    layout: {
        padding: {
            top: 20,
            bottom: 10,
            left: 10,
            right: 10
        }
    }
};

const horizontalBarChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y', // This makes it horizontal
    plugins: {
        legend: { 
            display: false 
        },
        tooltip: { 
            enabled: true,
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#333333',
            bodyColor: '#333333',
            borderColor: 'rgba(0, 0, 0, 0.1)',
            borderWidth: 0,
            cornerRadius: 6,
            padding: 10,
            displayColors: true,
            titleFont: {
                size: 13,
                weight: '600'
            },
            bodyFont: {
                size: 12
            },
            callbacks: {
                title: function(context) {
                    // Get the full name from the chart data
                    const dataIndex = context[0].dataIndex;
                    const chartData = warehouseFacilitiesChartData.value;
                    const fullName = chartData.fullNames[dataIndex] || context[0].label;
                    return fullName;
                },
                label: function(context) {
                    return formatLargeNumberForTooltip(context.parsed.x);
                }
            }
        },
        datalabels: {
            display: true,
            anchor: 'center',
            align: 'center',
            color: '#ffffff',
            font: {
                weight: 'bold',
                size: 11,
                family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },
            formatter: function(value, context) {
                return value > 0 ? formatLargeNumber(value) : '';
            },
            padding: 0,
            textShadowColor: 'rgba(0, 0, 0, 0.4)',
            textShadowBlur: 2,
            textShadowOffsetX: 1,
            textShadowOffsetY: 1
        }
    },
    scales: {
        x: { 
            beginAtZero: true,
            grid: { display: false },
            border: { display: false },
            ticks: {
                callback: function(value) {
                    return formatLargeNumber(value);
                },
                font: {
                    size: 11,
                    weight: '500',
                    family: 'Segoe UI, Arial, sans-serif'
                },
                padding: 6
            }
        },
        y: {
            grid: { display: false },
            border: { display: false },
            ticks: {
                font: {
                    size: 11,
                    weight: '600',
                    family: 'Segoe UI, Arial, sans-serif'
                },
                padding: 6
            }
        }
    },
    animation: {
        duration: 1200,
        easing: 'easeOutCubic'
    },
    layout: {
        padding: {
            top: 20,
            bottom: 10,
            left: 10,
            right: 10
        }
    }
};

const orderChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            display: false 
        },
        tooltip: { 
            enabled: true,
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#333333',
            bodyColor: '#333333',
            borderColor: 'rgba(0, 0, 0, 0.1)',
            borderWidth: 0,
            cornerRadius: 6,
            padding: 10,
            displayColors: true,
            titleFont: {
                size: 13,
                weight: '600'
            },
            bodyFont: {
                size: 12
            },
            callbacks: {
                title: function(context) {
                    return context[0].label;
                },
                label: function(context) {
                    return `${formatLargeNumberForTooltip(context.parsed.y)} orders`;
                }
            }
        },
        datalabels: {
            display: true,
            anchor: 'end',
            align: 'top',
            color: '#374151',
            font: {
                weight: 'bold',
                size: 12,
                family: 'Segoe UI, Arial, sans-serif'
            },
            formatter: function(value) {
                return value > 0 ? formatLargeNumber(value) : '';
            },
            padding: 6
        }
    },
    scales: {
        y: { 
            beginAtZero: true,
            grid: { display: false },
            border: { display: false },
            ticks: {
                callback: function(value) {
                    return formatLargeNumber(value);
                },
                font: {
                    size: 10,
                    weight: '500',
                    family: 'Segoe UI, Arial, sans-serif'
                },
                padding: 4
            }
        },
        x: {
            grid: { display: false },
            border: { display: false },
            ticks: {
                font: {
                    size: 10,
                    weight: '600',
                    family: 'Segoe UI, Arial, sans-serif'
                },
                padding: 4,
                callback: function(value, index, values) {
                    // Add custom labels for each order type
                    const labels = [
                        'Purchase Orders',
                        'Packing Lists',
                        'Back Orders'
                    ];
                    return labels[index] || value;
                }
            }
        }
    },
    animation: {
        duration: 1200,
        easing: 'easeOutCubic'
    },
    layout: {
        padding: {
            top: 20,
            bottom: 10,
            left: 10,
            right: 10
        }
    }
};

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            display: false 
        },
        tooltip: { 
            enabled: true,
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: 'white',
            bodyColor: 'white',
            borderColor: 'rgba(255, 255, 255, 0.1)',
            borderWidth: 0,
            callbacks: {
                label: function(context) {
                    return formatLargeNumberForTooltip(context.parsed.y);
                }
            }
        },
        datalabels: {
            display: true,
            anchor: 'center',
            align: 'center',
            color: '#ffffff',
            font: {
                weight: 'bold',
                size: 10,
                family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },
            formatter: function(value, context) {
                return value > 0 ? formatLargeNumber(value) : '';
            },
            padding: 4,
            backgroundColor: 'rgba(0, 0, 0, 0.7)',
            borderColor: 'rgba(255, 255, 255, 0.2)',
            borderWidth: 1,
            borderRadius: 4
        }
    },
    scales: {
        y: { 
            beginAtZero: true,
            grid: { display: false },
            border: { display: false },
            ticks: {
                callback: function(value) {
                    return formatLargeNumber(value);
                }
            }
        },
        x: {
            grid: { display: false },
            border: { display: false },
            ticks: {
                maxRotation: 45,
                minRotation: 0
            }
        }
    }
};

const issuedChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            display: false 
        },
        tooltip: { 
            enabled: true,
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: 'white',
            bodyColor: 'white',
            borderColor: 'rgba(255, 255, 255, 0.1)',
            borderWidth: 0,
            callbacks: {
                label: function(context) {
                    return formatLargeNumberForTooltip(context.parsed.y);
                }
            }
        },
        datalabels: {
            display: true,
            anchor: 'center',
            align: 'center',
            color: '#ffffff',
            font: {
                weight: 'bold',
                size: 11,
                family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },
            formatter: function(value, context) {
                return value > 0 ? formatLargeNumber(value) : '';
            },
            padding: 0,
            textShadowColor: 'rgba(0, 0, 0, 0.3)',
            textShadowBlur: 2,
            textShadowOffsetX: 1,
            textShadowOffsetY: 1
        }
    },
    scales: {
        y: { 
            beginAtZero: true,
            grid: { display: false },
            border: { display: false },
            ticks: {
                callback: function(value) {
                    return formatLargeNumber(value);
                }
            }
        },
        x: {
            grid: { display: false },
            border: { display: false },
            ticks: {
                maxRotation: 45,
                minRotation: 0
            }
        }
    },
    layout: {
        padding: {
            top: 10,
            bottom: 0,
            left: 0,
            right: 0
        }
    },
    elements: {
        bar: {
            borderWidth: 0,
            borderSkipped: 'bottom',
            borderRadius: { topLeft: 8, topRight: 8, bottomLeft: 0, bottomRight: 0 },
            maxBarThickness: 35,
            barPercentage: 0.7,
            categoryPercentage: 0.8,
            hoverBackgroundColor: function(context) {
                const chart = context.chart;
                const {ctx, chartArea} = chart;
                if (!chartArea) return null;
                
                const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                const baseColor = context.parsed.y > 0 ? 'rgba(99, 102, 241, 0.8)' : 'rgba(239, 68, 68, 0.8)';
                gradient.addColorStop(0, baseColor);
                gradient.addColorStop(1, baseColor.replace('0.8', '1'));
                return gradient;
            }
        }
    },
    animation: {
        duration: 1200,
        easing: 'easeOutCubic',
        delay: (context) => {
            return context.dataIndex * 100;
        }
    },
    hover: {
        animationDuration: 200
    }
};

const orderStatusChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            display: false 
        },
        tooltip: { 
            enabled: true,
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#333333',
            bodyColor: '#333333',
            borderColor: 'rgba(0, 0, 0, 0.1)',
            borderWidth: 1,
            cornerRadius: 6,
            padding: 10,
            displayColors: true,
            titleFont: {
                size: 13,
                weight: '600'
            },
            bodyFont: {
                size: 12
            },
            callbacks: {
                title: function(context) {
                    return context[0].label;
                },
                label: function(context) {
                    return `${formatLargeNumberForTooltip(context.parsed.y)} orders`;
                }
            }
        },
        datalabels: {
            display: true,
            anchor: 'center',
            align: 'center',
            color: '#ffffff',
            font: {
                weight: 'bold',
                size: 14,
                family: 'Segoe UI, Arial, sans-serif'
            },
            formatter: function(value, context) {
                return value > 0 ? formatLargeNumber(value) : '';
            },
            padding: 0
        }
    },
    scales: {
        y: { 
            beginAtZero: true,
            grid: { display: false },
            border: { display: false },
            ticks: {
                callback: function(value) {
                    return formatLargeNumber(value);
                },
                font: {
                    size: 11,
                    weight: '500',
                    family: 'Segoe UI, Arial, sans-serif'
                },
                padding: 6
            }
        },
        x: {
            grid: { display: false },
            border: { display: false },
            ticks: {
                font: {
                    size: 11,
                    weight: '600',
                    family: 'Segoe UI, Arial, sans-serif'
                },
                padding: 6,
                maxRotation: 45,
                minRotation: 0
            }
        }
    },
    animation: {
        duration: 1200,
        easing: 'easeOutCubic'
    },
    layout: {
        padding: {
            top: 20,
            bottom: 10,
            left: 10,
            right: 10
        }
    }
};

const fulfillmentBarChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            display: false 
        },
        tooltip: { 
            enabled: true,
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#333333',
            bodyColor: '#333333',
            borderColor: 'rgba(0, 0, 0, 0.1)',
            borderWidth: 1,
            cornerRadius: 6,
            padding: 10,
            displayColors: true,
            titleFont: {
                size: 13,
                weight: '600'
            },
            bodyFont: {
                size: 12
            },
            callbacks: {
                title: function(context) {
                    return context[0].label;
                },
                label: function(context) {
                    return `${context.parsed.y.toFixed(1)}% fulfillment rate`;
                }
            }
        },
        datalabels: {
            display: true,
            anchor: 'center',
            align: 'center',
            color: '#ffffff',
            font: {
                weight: 'bold',
                size: 14,
                family: 'Segoe UI, Arial, sans-serif'
            },
            formatter: function(value, context) {
                return value > 0 ? `${value.toFixed(1)}%` : '';
            },
            padding: 0,
            textShadowColor: 'rgba(0, 0, 0, 0.3)',
            textShadowBlur: 2,
            textShadowOffsetX: 1,
            textShadowOffsetY: 1
        }
    },
    scales: {
        y: { 
            beginAtZero: true,
            max: 110,
            grid: {
                display: false,
                drawBorder: false
            },
            border: { display: false },
            ticks: {
                callback: function(value) {
                    return `${value}%`;
                },
                font: {
                    size: 11,
                    weight: '500',
                    family: 'Segoe UI, Arial, sans-serif'
                },
                padding: 6
            }
        },
        x: {
            grid: {
                display: false
            },
            ticks: {
                font: {
                    size: 11,
                    weight: '600',
                    family: 'Segoe UI, Arial, sans-serif'
                },
                padding: 6,
                maxRotation: 45,
                minRotation: 0
            }
        }
    },
    animation: {
        duration: 1200,
        easing: 'easeOutCubic'
    },
    layout: {
        padding: {
            top: 40,
            bottom: 10,
            left: 10,
            right: 10
        }
    }
};

// Utility function to format large numbers
function formatLargeNumber(value) {
    if (value === null || value === undefined) return '0';
    
    const num = parseFloat(value);
    if (isNaN(num)) return '0';
    
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    } else {
        return num.toLocaleString();
    }
}

// Utility function to format large numbers for tooltips (showing full value)
function formatLargeNumberForTooltip(value) {
    if (value === null || value === undefined) return '0';
    
    const num = parseFloat(value);
    if (isNaN(num)) return '0';
    
    return num.toLocaleString();
}

// Load initial data on component mount
onMounted(() => {
    handleTracertItems();
    handleFacilityTracertItems();
});

// Helper function to get selected supplier value
const getSelectedSupplierValue = () => {
    if (typeof selectedSupplier.value === 'object' && selectedSupplier.value !== null) {
        return selectedSupplier.value.value;
    }
    return selectedSupplier.value;
};

// Helper function to get selected order status values
const getSelectedOrderStatusValues = () => {
    if (Array.isArray(selectedOrderStatus.value)) {
        return selectedOrderStatus.value.map(item => item.value);
    }
    return [];
};

// Filtered computed properties based on supplier selection
const filteredProductCategoryCard = computed(() => {
    const selectedValue = getSelectedSupplierValue();
    if (selectedValue === '' || !selectedValue) {
        return props.productCategoryCard;
    }
    // This would need to be implemented based on your backend data structure
    // For now, return the original value
    return props.productCategoryCard;
});

const filteredWarehouseCountCard = computed(() => {
    const selectedValue = getSelectedSupplierValue();
    if (selectedValue === '' || !selectedValue) {
        return props.warehouseCountCard;
    }
    // This would need to be implemented based on your backend data structure
    // For now, return the original value
    return props.warehouseCountCard;
});

const filteredOrderCounts = computed(() => {
    const selectedValue = getSelectedSupplierValue();
    if (selectedValue === '' || !selectedValue) {
        return props.orderCard.counts;
    }
    // This would need to be implemented based on your backend data structure
    // For now, return the original value
    return props.orderCard.counts;
});

const filteredTransferReceivedCard = computed(() => {
    const selectedValue = getSelectedSupplierValue();
    if (selectedValue === '' || !selectedValue) {
        return props.transferReceivedCard;
    }
    // This would need to be implemented based on your backend data structure
    // For now, return the original value
    return props.transferReceivedCard;
});

const filteredOrdersDelayedCount = computed(() => {
    const selectedValue = getSelectedSupplierValue();
    if (selectedValue === '' || !selectedValue) {
        return props.ordersDelayedCount;
    }
    // This would need to be implemented based on your backend data structure
    // For now, return the original value
    return props.ordersDelayedCount;
});

const inStockCount = computed(() => props.inventoryStatusCounts.find(s => s.status === 'in_stock')?.count || 0);
const lowStockCount = computed(() => props.inventoryStatusCounts.find(s => s.status === 'low_stock')?.count || 0);
const outOfStockCount = computed(() => props.inventoryStatusCounts.find(s => s.status === 'out_of_stock')?.count || 0);

// Chart data for top cards - dynamically generated from categories
const productCategoryChartData = computed(() => {
    const categoryData = filteredProductCategoryCard.value;
    const labels = Object.keys(categoryData);
    const data = Object.values(categoryData);
    
    // Generate colors dynamically based on number of categories
    const colors = generateCategoryColors(labels.length);
    
    return {
        labels: labels,
        datasets: [{
            data: data,
            backgroundColor: colors.background,
            borderColor: colors.border,
            borderWidth: 1,
            hoverBackgroundColor: colors.background.map(color => color.replace('0.8', '1')),
            hoverBorderColor: colors.border,
            hoverBorderWidth: 2
        }]
    };
});

const warehouseFacilitiesChartData = computed(() => {
    // Get the actual data from dashboardData.summary and use abbreviated labels
    const facilityData = props.dashboardData.summary.map(item => {
        // Map colors based on facility type
        const colorMap = {
            'WH': 'rgba(68, 114, 196, 1)', // Blue for Warehouses
            'HC': 'rgba(237, 125, 49, 1)', // Orange for Health Centre
            'PHU': 'rgba(165, 165, 165, 1)', // Gray for Primary Health Unit
            'DH': 'rgba(112, 173, 71, 1)', // Green for District Hospital
            'RH': 'rgba(255, 192, 0, 1)', // Yellow for Regional Hospital
            'MT': 'rgba(91, 155, 213, 1)', // Light Blue for Mobile Team
        };

        return {
            label: item.label, // Use abbreviated label (WH, HC, PHU, etc.)
            fullName: item.fullName, // Store full name for tooltip
            count: item.value,
            color: colorMap[item.label] || 'rgba(68, 114, 196, 1)' // Default blue
        };
    });

    // Sort by count in descending order (highest to lowest)
    const sortedData = [...facilityData].sort((a, b) => b.count - a.count);

    return {
        labels: sortedData.map(item => item.label),
        fullNames: sortedData.map(item => item.fullName), // Store full names in order
        datasets: [{
            label: 'Facility Count',
            data: sortedData.map(item => item.count),
            backgroundColor: sortedData.map(item => item.color),
            borderColor: sortedData.map(item => item.color),
            borderWidth: 0,
            borderRadius: 6,
            hoverBackgroundColor: sortedData.map(item => item.color),
            hoverBorderColor: sortedData.map(item => item.color),
            hoverBorderWidth: 0
        }]
    };
});

const orderChartData = computed(() => ({
    labels: ['PO', 'PKL', 'BO'],
    datasets: [{
        label: 'Orders',
        data: [
            filteredOrderCounts.value.PO || 0,
            filteredOrderCounts.value.PKL || 0,
            filteredOrderCounts.value.BO || 0
        ],
        backgroundColor: [
            'rgba(68, 114, 196, 1)',  // Excel Blue for Purchase Orders
            'rgba(237, 125, 49, 1)',  // Excel Orange for Packing Lists
            'rgba(165, 165, 165, 1)'  // Excel Gray for Back Orders
        ],
        borderColor: [
            'rgba(68, 114, 196, 1)',
            'rgba(237, 125, 49, 1)',
            'rgba(165, 165, 165, 1)'
        ],
        borderWidth: 0,
        borderRadius: { topLeft: 100, topRight: 100, bottomLeft: 0, bottomRight: 0 },
        borderSkipped: 'bottom',
        maxBarThickness: 40,
        barPercentage: 0.6,
        categoryPercentage: 0.6,
        hoverBackgroundColor: [
            'rgba(68, 114, 196, 1)',
            'rgba(237, 125, 49, 1)',
            'rgba(165, 165, 165, 1)'
        ],
        hoverBorderColor: [
            'rgba(68, 114, 196, 1)',
            'rgba(237, 125, 49, 1)',
            'rgba(165, 165, 165, 1)'
        ],
        hoverBorderWidth: 0
    }]
}));

const transferChartData = computed(() => ({
    labels: ['Received'],
    datasets: [{
        label: 'Transfers',
        data: [filteredTransferReceivedCard.value || 0],
        backgroundColor: 'rgba(20, 184, 166, 0.8)',
        borderColor: 'rgba(20, 184, 166, 1)',
        borderWidth: 0
    }]
}));

const costChartData = computed(() => ({
    labels: ['Total Cost'],
    datasets: [{
        label: 'Cost',
        data: [filteredTotalCost.value || 0],
        backgroundColor: 'rgba(75, 85, 99, 0.8)',
        borderColor: 'rgba(75, 85, 99, 1)',
        borderWidth: 0
    }]
}));

const fulfillmentChartData = computed(() => {
    // Get top 10 suppliers with their fulfillment rates
    const topSuppliers = (props.fulfillmentData || [])
        .sort((a, b) => b.fulfillment_percentage - a.fulfillment_percentage)
        .slice(0, 10);
    
    return {
        labels: topSuppliers.map(supplier => supplier.supplier_name || 'Unknown'),
        datasets: [{
            label: 'Fulfillment Rate (%)',
            data: topSuppliers.map(supplier => supplier.fulfillment_percentage || 0),
            backgroundColor: [
                'rgba(68, 114, 196, 1)',  // Excel Blue
                'rgba(237, 125, 49, 1)',  // Excel Orange
                'rgba(165, 165, 165, 1)', // Excel Gray
                'rgba(255, 192, 0, 1)',   // Excel Yellow
                'rgba(112, 173, 71, 1)',  // Excel Green
                'rgba(91, 155, 213, 1)',  // Light Blue
                'rgba(255, 102, 0, 1)',   // Orange
                'rgba(128, 128, 128, 1)', // Gray
                'rgba(0, 176, 80, 1)',    // Green
                'rgba(0, 112, 192, 1)'    // Blue
            ],
            borderColor: [
                'rgba(68, 114, 196, 1)',
                'rgba(237, 125, 49, 1)',
                'rgba(165, 165, 165, 1)',
                'rgba(255, 192, 0, 1)',
                'rgba(112, 173, 71, 1)',
                'rgba(91, 155, 213, 1)',
                'rgba(255, 102, 0, 1)',
                'rgba(128, 128, 128, 1)',
                'rgba(0, 176, 80, 1)',
                'rgba(0, 112, 192, 1)'
            ],
            borderWidth: 0,
            borderRadius: { topLeft: 100, topRight: 100, bottomLeft: 0, bottomRight: 0 },
            borderSkipped: 'bottom',
            maxBarThickness: 50,
            barPercentage: 0.8,
            categoryPercentage: 0.8,
            hoverBackgroundColor: [
                'rgba(68, 114, 196, 1)',
                'rgba(237, 125, 49, 1)',
                'rgba(165, 165, 165, 1)',
                'rgba(255, 192, 0, 1)',
                'rgba(112, 173, 71, 1)',
                'rgba(91, 155, 213, 1)',
                'rgba(255, 102, 0, 1)',
                'rgba(128, 128, 128, 1)',
                'rgba(0, 176, 80, 1)',
                'rgba(0, 112, 192, 1)'
            ],
            hoverBorderColor: [
                'rgba(68, 114, 196, 1)',
                'rgba(237, 125, 49, 1)',
                'rgba(165, 165, 165, 1)',
                'rgba(255, 192, 0, 1)',
                'rgba(112, 173, 71, 1)',
                'rgba(91, 155, 213, 1)',
                'rgba(255, 102, 0, 1)',
                'rgba(128, 128, 128, 1)',
                'rgba(0, 176, 80, 1)',
                'rgba(0, 112, 192, 1)'
            ],
            hoverBorderWidth: 0
        }]
    };
});

// Computed properties for fulfillment summary stats
const topPerformerName = computed(() => {
    const topSupplier = (props.fulfillmentData || [])
        .sort((a, b) => b.fulfillment_percentage - a.fulfillment_percentage)[0];
    return topSupplier?.supplier_name || 'N/A';
});

const topPerformerRate = computed(() => {
    const topSupplier = (props.fulfillmentData || [])
        .sort((a, b) => b.fulfillment_percentage - a.fulfillment_percentage)[0];
    return topSupplier ? topSupplier.fulfillment_percentage.toFixed(1) : '0.0';
});

const totalSuppliers = computed(() => {
    return (props.fulfillmentData || []).length;
});

const lowestPerformerName = computed(() => {
    const sorted = (props.fulfillmentData || [])
        .filter(s => typeof s.fulfillment_percentage === 'number')
        .sort((a, b) => a.fulfillment_percentage - b.fulfillment_percentage);
    return sorted.length > 0 ? sorted[0].supplier_name : 'N/A';
});

const lowestPerformerRate = computed(() => {
    const sorted = (props.fulfillmentData || [])
        .filter(s => typeof s.fulfillment_percentage === 'number')
        .sort((a, b) => a.fulfillment_percentage - b.fulfillment_percentage);
    return sorted.length > 0 ? sorted[0].fulfillment_percentage.toFixed(1) : '0.0';
});

const delayedChartData = computed(() => ({
    labels: ['Delayed Orders'],
    datasets: [{
        label: 'Count',
        data: [filteredOrdersDelayedCount.value || 0],
        backgroundColor: 'rgba(20, 184, 166, 0.8)',
        borderColor: 'rgba(20, 184, 166, 1)',
        borderWidth: 2
    }]
}));

// Order Status Chart Data
const orderStatusChartData = computed(() => {
    const statusData = [
        { key: 'pending', label: 'Pending', color: 'rgba(245, 158, 11, 1)' },
        { key: 'reviewed', label: 'Reviewed', color: 'rgba(59, 130, 246, 1)' },
        { key: 'approved', label: 'Approved', color: 'rgba(16, 185, 129, 1)' },
        { key: 'in_process', label: 'In Process', color: 'rgba(168, 85, 247, 1)' },
        { key: 'dispatched', label: 'Dispatched', color: 'rgba(236, 72, 153, 1)' },
        { key: 'delivered', label: 'Delivered', color: 'rgba(34, 197, 94, 1)' },
        { key: 'received', label: 'Received', color: 'rgba(6, 182, 212, 1)' },
        { key: 'rejected', label: 'Rejected', color: 'rgba(239, 68, 68, 1)' }
    ];

    // Filter data based on selected statuses
    let filteredData = statusData;
    const selectedValues = getSelectedOrderStatusValues();
    if (selectedValues.length > 0) {
        filteredData = statusData.filter(item => selectedValues.includes(item.key));
    }

    return {
        labels: filteredData.map(item => item.label),
        datasets: [{
            label: 'Order Count',
            data: filteredData.map(item => props.orderStats[item.key] || 0),
            backgroundColor: filteredData.map(item => item.color),
            borderColor: filteredData.map(item => item.color),
            borderWidth: 0,
            borderRadius: 6,
            hoverBackgroundColor: filteredData.map(item => item.color),
            hoverBorderColor: filteredData.map(item => item.color),
            hoverBorderWidth: 0
        }]
    };
});

// Expired statistics chart data
const expiredChartData = computed(() => {
    const data = {
        labels: ['Expired', 'Expiring in 6 Months', 'Expiring in 1 Year'],
        datasets: [{
            data: [
                props.expiredStats?.expired || 0,
                props.expiredStats?.expiring_within_6_months || 0,
                props.expiredStats?.expiring_within_1_year || 0
            ],
            backgroundColor: [
                'rgba(75, 85, 99, 1)',    // Gray-600 for expired (from Expired/Index.vue)
                'rgba(236, 72, 153, 1)',  // Pink-500 for 6 months (from Expired/Index.vue)
                'rgba(251, 146, 60, 1)'   // Orange-400 for 1 year (from Expired/Index.vue)
            ],
            borderColor: [
                'rgba(75, 85, 99, 1)',       // Gray-600 for expired
                'rgba(236, 72, 153, 1)',     // Pink-500 for 6 months
                'rgba(251, 146, 60, 1)'      // Orange-400 for 1 year
            ],
            borderWidth: 0,
            hoverBackgroundColor: [
                'rgba(75, 85, 99, 1)',
                'rgba(236, 72, 153, 1)',
                'rgba(251, 146, 60, 1)'
            ],
            hoverBorderColor: [
                'rgba(75, 85, 99, 1)',
                'rgba(236, 72, 153, 1)',
                'rgba(251, 146, 60, 1)'
            ],
            hoverBorderWidth: 0
        }]
    };
    
    return data;
});

// Asset Status Chart Data (show all indicators even if zero)
const assetStatusChartData = computed(() => {
    const statusData = [
        { key: 'In Use', color: 'rgba(68, 114, 196, 1)' },      // Excel Blue
        { key: 'Functioning', color: 'rgba(34, 197, 94, 1)' },  // Green
        { key: 'Not functioning', color: 'rgba(249, 115, 22, 1)' }, // Orange
        { key: 'Needs Maintenance', color: 'rgba(237, 125, 49, 1)' }, // Excel Orange
        { key: 'Pending Approval', color: 'rgba(255, 192, 0, 1)' },   // Excel Yellow
        { key: 'Retired', color: 'rgba(128, 128, 128, 1)' },    // Gray
        { key: 'Disposed', color: 'rgba(165, 165, 165, 1)' }    // Excel Gray
    ];

    return {
        labels: statusData.map(status => status.key),
        datasets: [{
            label: 'Asset Status',
            data: statusData.map(status => props.assetStatusStats?.[status.key] || 0),
            backgroundColor: statusData.map(status => status.color),
            borderColor: statusData.map(status => status.color),
            borderWidth: 0,
            borderRadius: 6,
            hoverBackgroundColor: statusData.map(status => status.color),
            hoverBorderColor: statusData.map(status => status.color),
            hoverBorderWidth: 0
        }]
    };
});

// Asset Status Chart Options
const assetStatusChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: 'white',
            bodyColor: 'white',
            borderColor: 'rgba(255, 255, 255, 0.1)',
            borderWidth: 1,
            cornerRadius: 8,
            displayColors: true,
            callbacks: {
                label: function(context) {
                    return `${context.label}: ${context.parsed.y} assets`;
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: { display: false },
            border: { display: false },
            ticks: {
                color: '#6b7280',
                font: {
                    size: 12
                }
            },
            max: function(context) {
                const max = Math.max(...context.chart.data.datasets[0].data);
                return max > 0 ? max * 1.2 : 10;
            }
        },
        x: {
            grid: { display: false },
            border: { display: false },
            ticks: {
                color: '#6b7280',
                font: {
                    size: 12
                }
            }
        }
    },
    layout: {
        padding: {
            top: 20,
            bottom: 20
        }
    }
};

// Asset Statistics Cards Row
const assetStatsCards = computed(() => [
    {
        title: 'Furniture',
        value: props.assetStats?.Furniture || 0,
        icon: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z',
        gradient: 'linear-gradient(135deg, #60a5fa 0%, #a7f3d0 100%)'
    },
    {
        title: 'IT Equipment',
        value: props.assetStats?.IT || 0,
        icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        gradient: 'linear-gradient(135deg, #a78bfa 0%, #f0abfc 100%)'
    },
    {
        title: 'Medical Equipment',
        value: props.assetStats?.['Medical equipment'] || 0,
        icon: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
        gradient: 'linear-gradient(135deg, #6ee7b7 0%, #fef08a 100%)'
    },
    {
        title: 'Vehicles',
        value: props.assetStats?.Vehicles || 0,
        icon: 'M8 7V3a4 4 0 118 0v4m-4 6v6m-4-6h8m-8 0H4',
        gradient: 'linear-gradient(135deg, #fdba74 0%, #fca5a5 100%)'
    },
    {
        title: 'Others',
        value: props.assetStats?.Others || 0,
        icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
        gradient: 'linear-gradient(135deg, #d1d5db 0%, #f3f4f6 100%)'
    }
]);

// Helper function to navigate to a task
const navigateToTask = (route) => {
    router.visit(route);
};
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout title="Dashboard" description="Welcome to the dashboard">
        <!-- Modern Gradient Dashboard Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
            <!-- Teal Card -->
            <Link :href="route('supplies.purchase_order')" class="block">
                <div class="relative overflow-hidden rounded-lg cursor-pointer">
                    <div class="absolute inset-0" style="background: linear-gradient(45deg, #00D79F 0%, #37FFCB 54%, #DCFFF6 100%);"></div>
                    <div class="relative p-4">

                        <!-- Content -->
                        <div class="flex flex-col">
                            <h3 class="text-sm font-medium text-gray-800 mb-1">Total Cost of Supplies</h3>
                            <div class="text-2xl font-bold text-gray-900">{{ (filteredTotalCost || 0).toLocaleString() }}</div>
                            <div class="text-xs font-light text-gray-700 mt-1">{{ new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</div>
                </div>
                        
                        <!-- Icon in bottom-right corner -->
                        <div class="absolute bottom-3 right-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                        </div>
            </Link>

            <!-- Blue Card - Delayed Orders -->
            <Link :href="route('orders.index')" class="block">
                <div class="relative overflow-hidden rounded-lg cursor-pointer">
                    <div class="absolute inset-0" style="background: linear-gradient(45deg, #007BFF 0%, #6FB9FF 50%, #D0E7FF 100%);"></div>
                    <div class="relative p-4">
                        <!-- Content -->
                        <div class="flex flex-col">
                            <h3 class="text-sm font-medium text-white mb-1">Delayed Orders</h3>
                            <div class="text-2xl font-bold text-white">{{ filteredOrdersDelayedCount || 0 }}</div>
                            <div class="text-xs font-light text-white mt-1">{{ new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</div>
                        </div>
                        
                        <!-- Icon in bottom-right corner -->
                        <div class="absolute bottom-3 right-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    </div>
            </Link>

            <!-- Gray Card -->
            <Link :href="route('transfers.index')" class="block">
                <div class="relative overflow-hidden rounded-lg cursor-pointer">
                    <div class="absolute inset-0" style="background: linear-gradient(45deg, #5F5C65 0%, #888892 55%, #E7E2F2 100%);"></div>
                    <div class="relative p-4">

                        <!-- Content -->
                        <div class="flex flex-col">
                            <h3 class="text-sm font-medium text-white mb-1">Transfers</h3>
                            <div class="text-2xl font-bold text-white">{{ filteredTransferReceivedCard || 0 }}</div>
                            <div class="text-xs font-light text-gray-200 mt-1">{{ new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</div>
                        </div>
                        
                        <!-- Icon in bottom-right corner -->
                        <div class="absolute bottom-3 right-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                        </div>
                    </div>
                    </div>
            </Link>

            <!-- Orange Card - Low Stock -->
            <Link :href="route('inventories.index')" class="block">
                <div class="relative overflow-hidden rounded-lg cursor-pointer">
                    <div class="absolute inset-0" style="background: linear-gradient(45deg, #FF8500 0%, #FFB15C 31%, #FFDBB7 100%);"></div>
                    <div class="relative p-4">
                        <!-- Content -->
                        <div class="flex flex-col">
                            <h3 class="text-sm font-medium text-white mb-1">Low Stock</h3>
                            <div class="text-2xl font-bold text-white">{{ lowStockCount || 0 }}</div>
                            <div class="text-xs font-light text-white mt-1">{{ new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</div>
                        </div>
                        
                        <!-- Icon in bottom-right corner -->
                        <div class="absolute bottom-3 right-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                    </div>
                    </div>
            </Link>

            <!-- Red Card - Out of Stock -->
            <Link :href="route('inventories.index')" class="block">
                <div class="relative overflow-hidden rounded-lg cursor-pointer">
                    <div class="absolute inset-0" style="background: linear-gradient(45deg, #DC2626 0%, #FF8A8A 50%, #FFE5E5 100%);"></div>
                    <div class="relative p-4">
                        <!-- Content -->
                        <div class="flex flex-col">
                            <h3 class="text-sm font-medium text-white mb-1">Out of Stock</h3>
                            <div class="text-2xl font-bold text-white">{{ outOfStockCount || 0 }}</div>
                            <div class="text-xs font-light text-white mt-1">{{ new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</div>
                        </div>
                        
                        <!-- Icon in bottom-right corner -->
                        <div class="absolute bottom-3 right-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                    </div>
                    </div>
            </Link>
            </div>

     

        <!-- Tabs, Total Cost, and Order Statistics Row -->
        <div class="flex flex-col lg:flex-row justify-between items-start gap-6 mb-6">
            <!-- Tabs Section -->
            <div class="flex-1 lg:mr-8 w-full">
                <!-- Tab Navigation -->
                <div class="flex border-b border-gray-200 mb-6">
                    <button
                        @click="activeTab = 'warehouse'"
                        :class="[
                            'px-6 py-3 text-sm font-medium transition-all duration-200 relative',
                            activeTab === 'warehouse'
                                ? 'text-indigo-600 border-b-2 border-indigo-600'
                                : 'text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]"
                    >
                        Warehouse
                    </button>
                    <button
                        @click="activeTab = 'facilities'"
                        :class="[
                            'px-6 py-3 text-sm font-medium transition-all duration-200 relative',
                            activeTab === 'facilities'
                                ? 'text-indigo-600 border-b-2 border-indigo-600'
                                : 'text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]"
                    >
                        Facilities
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="min-h-[400px]">
                    <!-- Warehouse Tab -->
                    <div v-if="activeTab === 'warehouse'" class="">
                        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div class="flex flex-wrap gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1 flex items-center gap-2">
                                            <span>📅</span>
                                            <span>Month</span>
                                        </label>
                                        <input type="month" v-model="issuedMonth" class="border-2 border-gray-300 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500 transition-colors" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1 flex items-center gap-2">
                                            <span>📊</span>
                                            <span>Data Type</span>
                                        </label>
                                        <select v-model="warehouseDataType" class="border-2 border-gray-300 rounded-lg px-3 py-2 min-w-[180px] focus:border-indigo-500 focus:ring-indigo-500 transition-colors">
                                            <option value="beginning_balance">Beginning Balance</option>
                                            <option value="received_quantity">QTY Received</option>
                                            <option value="issued_quantity">QTY Issued</option>
                                            <option value="closing_balance">Closing Balance</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1 flex items-center gap-2">
                                            <span>🏢</span>
                                            <span>Warehouse</span>
                                        </label>
                                        <select v-model="selectedWarehouse" class="border-2 border-gray-300 rounded-lg px-3 py-2 min-w-[200px] focus:border-indigo-500 focus:ring-indigo-500 transition-colors">
                                            <option value="">All Warehouses</option>
                                            <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                                                {{ warehouse.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- Chart Container -->
                            <div class="relative mt-6" :class="chartCount > 1 ? 'min-h-96' : 'h-80'">
                                <!-- Loading State -->
                                <div v-if="isLoadingChart" class="absolute inset-0 flex items-center justify-center bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-2">
                                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                                        <span class="text-gray-600">Loading chart data...</span>
                                    </div>
                                </div>
                                <!-- Error State -->
                                <div v-else-if="chartError" class="absolute inset-0 flex items-center justify-center bg-red-50 rounded-lg">
                                    <div class="text-center">
                                        <div class="text-red-600 font-medium">{{ chartError }}</div>
                                        <button @click="handleTracertItems" class="mt-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                            Retry
                                        </button>
                                    </div>
                                </div>
                                <!-- Charts Grid -->
                                <div v-else class="h-full">
                                    <!-- Single Chart -->
                                    <div v-if="chartCount === 1" class="h-full">
                                        <div class="mb-3 text-center">
                                            <h3 class="text-lg font-semibold text-gray-800 bg-gray-50 px-4 py-2 rounded-md border inline-block">
                                                {{ localWarehouseChartData[0]?.categoryDisplay || localWarehouseChartData[0]?.category || 'Unknown Category' }}
                                            </h3>
                                        </div>
                                        <div class="h-64">
                                            <Bar :data="localWarehouseChartData[0]" :options="issuedChartOptions" />
                                        </div>
                                    </div>
                                    <!-- Multiple Charts Grid - 3 charts per row -->
                                    <div v-else class="space-y-6">
                                        <div v-for="(chartRow, rowIndex) in chartRows" :key="'row-' + rowIndex" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                            <div v-for="chart in chartRow" :key="chart.id" class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                                                <!-- Category Title -->
                                                <div class="mb-3 flex items-start">
                                                    <span class="text-sm font-semibold text-gray-700">
                                                        {{ chart.categoryDisplay || chart.category || 'Unknown Category' }}
                                                    </span>
                                                </div>
                                                <div class="h-64">
                                                    <Bar :data="chart" :options="issuedChartOptions" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        

                    </div>
                    <!-- Facilities Tab -->
                    <div v-if="activeTab === 'facilities'" class="">
                        <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                <div class="flex flex-col lg:flex-row gap-4 flex-1">
                                    <div class="flex-1">
                                        <label class="block text-sm font-semibold text-gray-700 mb-1 flex items-center gap-2">
                                            <span>🏥</span>
                                            <span>Facility</span>
                                        </label>
                                        <Multiselect
                                            v-model="selectedFacility"
                                            :options="facilities"
                                            :searchable="true"
                                            :close-on-select="true"
                                            :show-labels="false"
                                            label="name"
                                            track-by="id"
                                            placeholder="Select facility..."
                                            class="w-full"
                                        />
                                    </div>
                                    <div class="lg:w-48">
                                        <label class="block text-sm font-semibold text-gray-700 mb-1 flex items-center gap-2">
                                            <span>📅</span>
                                            <span>Month</span>
                                        </label>
                                        <input type="month" v-model="facilityMonth" class="border-2 border-gray-300 rounded-lg px-3 py-2 w-full focus:border-indigo-500 focus:ring-indigo-500 transition-colors" />
                                    </div>
                                    <div class="lg:w-48">
                                        <label class="block text-sm font-semibold text-gray-700 mb-1 flex items-center gap-2">
                                            <span>📊</span>
                                            <span>Data Type</span>
                                        </label>
                                        <select v-model="facilityDataType" class="border-2 border-gray-300 rounded-lg px-3 py-2 w-full focus:border-indigo-500 focus:ring-indigo-500 transition-colors">
                                            <option value="opening_balance">Beginning Balance</option>
                                            <option value="stock_received">QTY Received</option>
                                            <option value="stock_issued">Issued Quantity</option>
                                            <option value="closing_balance">Closing Balance</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- Chart Container -->
                            <div class="relative mt-6" :class="facilityChartCount > 1 ? 'min-h-96' : 'h-80'">
                                <!-- Loading State -->
                                <div v-if="isLoadingFacilityChart" class="absolute inset-0 flex items-center justify-center bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-2">
                                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                                        <span class="text-gray-600">Loading facility chart data...</span>
                                    </div>
                                </div>
                                <!-- Error State -->
                                <div v-else-if="facilityChartError" class="absolute inset-0 flex items-center justify-center bg-red-50 rounded-lg">
                                    <div class="text-center">
                                        <div class="text-red-600 font-medium">{{ facilityChartError }}</div>
                                        <button @click="handleFacilityTracertItems" class="mt-2 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                            Retry
                                        </button>
                                    </div>
                                </div>
                                <!-- Charts Grid -->
                                <div v-else class="h-full">
                                    <!-- Single Chart -->
                                    <div v-if="facilityChartCount === 1" class="h-full">
                                        <div class="mb-3 text-center">
                                            <h3 class="text-lg font-semibold text-gray-800 bg-gray-50 px-4 py-2 rounded-md border inline-block">
                                                {{ localFacilityChartData[0]?.categoryDisplay || localFacilityChartData[0]?.category || 'Unknown Category' }}
                                            </h3>
                                        </div>
                                        <div class="h-64">
                                            <Bar :data="localFacilityChartData[0]" :options="issuedChartOptions" />
                                        </div>
                                    </div>
                                    <!-- Multiple Charts Grid - 3 charts per row -->
                                    <div v-else class="space-y-6">
                                        <div v-for="(chartRow, rowIndex) in facilityChartRows" :key="'facility-row-' + rowIndex" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                            <div v-for="chart in chartRow" :key="'facility-' + chart.id" class="bg-white rounded-lg border border-gray-200 p-4 shadow-sm">
                                                <!-- Category Title -->
                                                <div class="mb-3 flex items-start">
                                                    <span class="text-sm font-semibold text-gray-700">
                                                        {{ chart.categoryDisplay || chart.category || 'Unknown Category' }}
                                                    </span>
                                                </div>
                                                <div class="h-64">
                                                    <Bar :data="chart" :options="issuedChartOptions" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>

        <!-- Date Range Filter -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 mb-6">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">Select Date Range</span>
                </div>
                <Datepicker
                    v-model="dateRange"
                    range
                    :enable-time-picker="false"
                    :format="{ year: 'numeric', month: 'short', day: 'numeric' }"
                    :placeholder="'Select date range'"
                    :preview-format="'MMM DD, YYYY'"
                    :teleport="true"
                    :auto-apply="true"
                    :min-date="new Date('2020-01-01')"
                    :max-date="new Date('2030-12-31')"
                    :presets="datePresets"
                    class="w-full max-w-md"
                />
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Product Categories Chart -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8">
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-gray-900">Product Categories</h3>
                      </div>
                <div class="h-96 px-4 py-2">
                    <Doughnut :data="productCategoryChartData" :options="doughnutChartOptions" />
                    </div>
                </div>

            <!-- Warehouse/Facilities Chart -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Warehouse & Facilities</h3>
                            </div>
                <div class="h-64">
                    <Bar :key="JSON.stringify(warehouseFacilitiesChartData)" :data="warehouseFacilitiesChartData" :options="horizontalBarChartOptions" />
                        </div>
                            </div>

            <!-- Orders Chart -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Supplies</h3>
                        </div>
                <div class="h-64">
                    <Bar :data="orderChartData" :options="orderChartOptions" />
                            </div>
                        </div>
                    </div>


        <!-- Fulfillment Chart Row -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
            <!-- Fulfillment Chart - Takes 10 columns -->
            <div class="lg:col-span-10 bg-white rounded-xl shadow-lg border border-gray-200 p-4">
                <div class="mb-3">
                    <h3 class="text-xl font-bold text-gray-900">Top 10 Suppliers - Fulfillment Rate</h3>
                    <p class="text-sm text-gray-600 mt-1">Supplier performance based on fulfillment percentage</p>
                </div>
                <div class="h-72">
                    <Bar :data="fulfillmentChartData" :options="fulfillmentBarChartOptions" />
                </div>
            </div>

            <!-- Summary Stats - Takes 2 columns -->
            <div class="lg:col-span-2 space-y-3">
                <!-- Lowest Performer Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3">
                        <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-gray-900">Lowest Performer</h3>
                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                                </svg>
                            </div>
                    <div class="text-lg font-semibold text-gray-900">{{ lowestPerformerName }}</div>
                    <div class="text-xl font-bold text-red-600">{{ lowestPerformerRate }}%</div>
                        </div>
                
                <!-- Top Performer Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-gray-900">Top Performer</h3>
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                        </svg>
                        </div>
                    <div class="text-lg font-semibold text-gray-900">{{ topPerformerName }}</div>
                    <div class="text-xl font-bold text-green-600">{{ topPerformerRate }}%</div>
                </div>

                <!-- Total Suppliers Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3">
                        <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-gray-900">Total Suppliers</h3>
                        <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                    <div class="text-2xl font-bold text-purple-600">{{ totalSuppliers }}</div>
                    <div class="text-xs text-gray-600 mt-1">Active suppliers</div>
                        </div>
                        </div>
        </div>

        <!-- Expired Statistics Chart Row -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
            <!-- Expired Chart - Takes 8 columns -->
            <div class="lg:col-span-8 bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Expiry Status Overview</h3>
                    <p class="text-sm text-gray-600 mt-1">Items by expiry status and timeline</p>
                </div>
                <div class="h-80 px-4 py-2">
                    <Doughnut :data="expiredChartData" :options="doughnutChartOptions" />
                    </div>
                </div>

			<!-- Quick Actions - Takes 4 columns -->
			<div class="lg:col-span-4">
				<!-- Section Header -->
				<div class="mb-4">
					<h3 class="text-lg font-semibold text-gray-900 mb-1">Quick Actions</h3>
					<p class="text-sm text-gray-600">Access frequently used features</p>
				</div>
				
				<!-- Quick Actions Grid -->
				<div class="grid grid-cols-1 gap-3 h-full">
					<!-- Create Purchase Order -->
					<Link :href="route('supplies.purchase_order')" class="group">
						<div class="relative overflow-hidden rounded-xl bg-white border border-gray-200 shadow-sm hover:shadow-lg transition-all duration-300 p-5">
							<!-- Gradient Accent -->
							<div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-emerald-500 to-teal-600"></div>
							
							<div class="flex items-center justify-between">
								<div class="flex-1">
									<div class="flex items-center space-x-3">
										<!-- Icon -->
										<div class="flex-shrink-0 w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center group-hover:bg-emerald-100 transition-colors duration-200">
											<svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
											</svg>
										</div>
										
										<!-- Content -->
										<div class="flex-1">
											<h4 class="text-base font-semibold text-gray-900 group-hover:text-emerald-700 transition-colors">Create Purchase Order</h4>
											<p class="text-sm text-gray-600 mt-1">Request new supplies and materials</p>
										</div>
									</div>
								</div>
								
								<!-- Arrow -->
								<div class="flex-shrink-0">
									<svg class="w-5 h-5 text-gray-400 group-hover:text-emerald-600 group-hover:translate-x-1 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
									</svg>
								</div>
							</div>
						</div>
					</Link>
					
					<!-- Manage Orders -->
					<Link :href="route('orders.index')" class="group">
						<div class="relative overflow-hidden rounded-xl bg-white border border-gray-200 shadow-sm hover:shadow-lg transition-all duration-300 p-5">
							<!-- Gradient Accent -->
							<div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-indigo-500 to-blue-600"></div>
							
							<div class="flex items-center justify-between">
								<div class="flex-1">
									<div class="flex items-center space-x-3">
										<!-- Icon -->
										<div class="flex-shrink-0 w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center group-hover:bg-indigo-100 transition-colors duration-200">
											<svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
											</svg>
										</div>
										
										<!-- Content -->
										<div class="flex-1">
											<h4 class="text-base font-semibold text-gray-900 group-hover:text-indigo-700 transition-colors">Manage Orders</h4>
											<p class="text-sm text-gray-600 mt-1">View and track order status</p>
										</div>
									</div>
								</div>
								
								<!-- Arrow -->
								<div class="flex-shrink-0">
									<svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-600 group-hover:translate-x-1 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
									</svg>
								</div>
							</div>
						</div>
					</Link>
					
					<!-- Transfer Items -->
					<Link :href="route('transfers.index')" class="group">
						<div class="relative overflow-hidden rounded-xl bg-white border border-gray-200 shadow-sm hover:shadow-lg transition-all duration-300 p-5">
							<!-- Gradient Accent -->
							<div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-violet-500 to-purple-600"></div>
							
							<div class="flex items-center justify-between">
								<div class="flex-1">
									<div class="flex items-center space-x-3">
										<!-- Icon -->
										<div class="flex-shrink-0 w-12 h-12 bg-violet-50 rounded-xl flex items-center justify-center group-hover:bg-violet-100 transition-colors duration-200">
											<svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
											</svg>
										</div>
										
										<!-- Content -->
										<div class="flex-1">
											<h4 class="text-base font-semibold text-gray-900 group-hover:text-violet-700 transition-colors">Transfer Items</h4>
											<p class="text-sm text-gray-600 mt-1">Move inventory between locations</p>
										</div>
									</div>
								</div>
								
								<!-- Arrow -->
								<div class="flex-shrink-0">
									<svg class="w-5 h-5 text-gray-400 group-hover:text-violet-600 group-hover:translate-x-1 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
									</svg>
								</div>
							</div>
						</div>
					</Link>
					
					<!-- View Reports -->
					<Link :href="route('reports.index')" class="group">
						<div class="relative overflow-hidden rounded-xl bg-white border border-gray-200 shadow-sm hover:shadow-lg transition-all duration-300 p-5">
							<!-- Gradient Accent -->
							<div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-amber-500 to-orange-600"></div>
							
							<div class="flex items-center justify-between">
								<div class="flex-1">
									<div class="flex items-center space-x-3">
										<!-- Icon -->
										<div class="flex-shrink-0 w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center group-hover:bg-amber-100 transition-colors duration-200">
											<svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
											</svg>
										</div>
										
										<!-- Content -->
										<div class="flex-1">
											<h4 class="text-base font-semibold text-gray-900 group-hover:text-amber-700 transition-colors">View Reports</h4>
											<p class="text-sm text-gray-600 mt-1">Access analytics and insights</p>
										</div>
									</div>
								</div>
								
								<!-- Arrow -->
								<div class="flex-shrink-0">
									<svg class="w-5 h-5 text-gray-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
									</svg>
								</div>
							</div>
						</div>
					</Link>
				</div>
			</div>
                </div>

        <!-- Order Status Overview (Vertical) -->
        <div class="bg-white rounded-xl p-6 mb-6">
            <div class="mb-3">
                <h3 class="text-xl font-bold text-gray-900">Order Status Overview</h3>
                <p class="text-sm text-gray-600 mt-1">Live distribution of orders</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
                <div
                    v-for="cfg in orderStatusConfig"
                    :key="cfg.key"
                    class="flex items-center justify-center gap-3 p-3 rounded-lg transition-all"
                >
                    <div class="flex items-center">
                        <!-- Status Icon -->
                        <div class="w-12 h-12 flex items-center justify-center mr-3">
                            <img :src="cfg.icon" :alt="cfg.label" class="w-8 h-8" />
                        </div>
                        <!-- Circular Progress -->
                        <div class="w-14 h-14 relative mr-2">
                            <svg class="w-14 h-14 transform -rotate-90">
                                <circle cx="28" cy="28" r="24" fill="none" stroke="#e2e8f0" stroke-width="4" />
                                <circle
                                    cx="28"
                                    cy="28"
                                    r="24"
                                    fill="none"
                                    :stroke="cfg.stroke"
                                    stroke-width="4"
                                    :stroke-dasharray="(props.orderStats[cfg.key] === totalOrders && totalOrders > 0) ? '150.72 150.72' : `${((props.orderStats[cfg.key] || 0) / totalOrders) * 150.72} 150.72`"
                                />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span :class="['text-xs font-bold', cfg.textClass]">
                                    {{ totalOrders > 0 ? Math.round(((props.orderStats[cfg.key] || 0) / totalOrders) * 100) : 0 }}%
                                </span>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-base font-semibold text-gray-900">{{ props.orderStats[cfg.key] || 0 }}</div>
                            <div class="text-xs text-gray-600">{{ cfg.label }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Asset Statistics Row -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
            <!-- Asset Status Chart - Takes 5 columns -->
            <div class="lg:col-span-5 bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                <div class="mb-3">
                    <h3 class="text-xl font-bold text-gray-900">Asset Status Overview</h3>
                    <p class="text-sm text-gray-600 mt-1">Assets by current status</p>
                </div>
                <div class="h-64">
                    <Bar :data="assetStatusChartData" :options="assetStatusChartOptions" />
                </div>
            </div>
            
            <!-- Asset Category Cards - Takes 7 columns -->
            <div class="lg:col-span-7">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Row 1: Furniture and IT -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 group hover:shadow-lg transition-all duration-300 cursor-pointer overflow-hidden">
                        <div class="p-6 flex flex-col items-center justify-center h-32 relative">
                            <!-- Background Pattern -->
                            <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-indigo-50 opacity-60"></div>
                            <!-- Icon -->
                            <div class="relative z-10 mb-3">
                                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"></path>
                                    </svg>
                                </div>
                            </div>
                            <!-- Content -->
                            <div class="relative z-10 text-center">
                                <div class="text-2xl font-bold text-gray-900 mb-1">{{ props.assetStats?.Furniture || 0 }}</div>
                                <div class="text-sm font-medium text-gray-600">Furniture</div>
                            </div>
                            <!-- Hover Effect -->
                            <div class="absolute inset-0 bg-blue-500 opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 group hover:shadow-lg transition-all duration-300 cursor-pointer overflow-hidden">
                        <div class="p-6 flex flex-col items-center justify-center h-32 relative">
                            <!-- Background Pattern -->
                            <div class="absolute inset-0 bg-gradient-to-br from-purple-50 to-pink-50 opacity-60"></div>
                            <!-- Icon -->
                            <div class="relative z-10 mb-3">
                                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            </div>
                            <!-- Content -->
                            <div class="relative z-10 text-center">
                                <div class="text-2xl font-bold text-gray-900 mb-1">{{ props.assetStats?.IT || 0 }}</div>
                                <div class="text-sm font-medium text-gray-600">IT Equipment</div>
                            </div>
                            <!-- Hover Effect -->
                            <div class="absolute inset-0 bg-purple-500 opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                        </div>
                    </div>

                    <!-- Row 2: Medical Equipment and Vehicles -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 group hover:shadow-lg transition-all duration-300 cursor-pointer overflow-hidden">
                        <div class="p-6 flex flex-col items-center justify-center h-32 relative">
                            <!-- Background Pattern -->
                            <div class="absolute inset-0 bg-gradient-to-br from-green-50 to-emerald-50 opacity-60"></div>
                            <!-- Icon -->
                            <div class="relative z-10 mb-3">
                                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <!-- Content -->
                            <div class="relative z-10 text-center">
                                <div class="text-2xl font-bold text-gray-900 mb-1">{{ props.assetStats?.['Medical equipment'] || 0 }}</div>
                                <div class="text-sm font-medium text-gray-600">Medical Equipment</div>
                            </div>
                            <!-- Hover Effect -->
                            <div class="absolute inset-0 bg-green-500 opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 group hover:shadow-lg transition-all duration-300 cursor-pointer overflow-hidden">
                        <div class="p-6 flex flex-col items-center justify-center h-32 relative">
                            <!-- Background Pattern -->
                            <div class="absolute inset-0 bg-gradient-to-br from-orange-50 to-red-50 opacity-60"></div>
                            <!-- Icon -->
                            <div class="relative z-10 mb-3">
                                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4m-4 6v6m-4-6h8m-8 0H4"></path>
                                    </svg>
                                </div>
                            </div>
                            <!-- Content -->
                            <div class="relative z-10 text-center">
                                <div class="text-2xl font-bold text-gray-900 mb-1">{{ props.assetStats?.Vehicles || 0 }}</div>
                                <div class="text-sm font-medium text-gray-600">Vehicles</div>
                            </div>
                            <!-- Hover Effect -->
                            <div class="absolute inset-0 bg-orange-500 opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Row 3: Others Card - Takes full width (2 columns) -->
                <div class="mt-4">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 group hover:shadow-lg transition-all duration-300 cursor-pointer overflow-hidden">
                        <div class="p-6 flex flex-col items-center justify-center h-32 relative">
                            <!-- Background Pattern -->
                            <div class="absolute inset-0 bg-gradient-to-br from-gray-50 to-slate-50 opacity-60"></div>
                            <!-- Icon -->
                            <div class="relative z-10 mb-3">
                                <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                            </div>
                            <!-- Content -->
                            <div class="relative z-10 text-center">
                                <div class="text-2xl font-bold text-gray-900 mb-1">{{ props.assetStats?.Others || 0 }}</div>
                                <div class="text-sm font-medium text-gray-600">Others</div>
                            </div>
                            <!-- Hover Effect -->
                            <div class="absolute inset-0 bg-gray-500 opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
