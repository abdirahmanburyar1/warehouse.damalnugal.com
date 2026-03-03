<template>
  <Head title="Facilities List Report" />
  <AuthenticatedLayout title="Facilities List Report" description="Comprehensive facilities management and analytics" img="/assets/images/report.png">
    
    <!-- Summary Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
              </svg>
            </div>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Total Facilities</p>
            <p class="text-2xl font-bold text-gray-900">{{ summary.total_facilities }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Active Facilities</p>
            <p class="text-2xl font-bold text-gray-900">{{ summary.by_status?.Active || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
              </svg>
            </div>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Districts</p>
            <p class="text-2xl font-bold text-gray-900">{{ Object.keys(summary.by_district || {}).length }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
              </svg>
            </div>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Facility Types</p>
            <p class="text-2xl font-bold text-gray-900">{{ Object.keys(summary.by_type || {}).length }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
      <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filters & Search</h3>
      </div>
      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input 
              v-model="search" 
              type="text" 
              placeholder="Search facilities, types, districts..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
          </div>

          <!-- Region (first: district depends on region) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Region</label>
            <Multiselect
              v-model="region"
              :options="filterOptions.regions || []"
              :multiple="true"
              :searchable="true"
              :close-on-select="false"
              :clear-on-select="false"
              :preserve-search="true"
              placeholder="Select regions..."
              :preselect-first="false"
              :max-height="150"
              track-by="name"
              label="name"
              :custom-label="(option) => option"
              :taggable="false"
            />
          </div>

          <!-- District (dependent on region) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">District</label>
            <Multiselect
              v-model="district"
              :options="districtOptions"
              :multiple="true"
              :searchable="true"
              :close-on-select="false"
              :clear-on-select="false"
              :preserve-search="true"
              :placeholder="region && region.length ? 'Select districts...' : 'Select region(s) first'"
              :preselect-first="false"
              :max-height="150"
              track-by="name"
              label="name"
              :custom-label="(option) => option"
              :taggable="false"
            />
          </div>

          <!-- Facility Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Facility Type</label>
            <Multiselect
              v-model="facility_type"
              :options="filterOptions.facility_types"
              :multiple="true"
              :searchable="true"
              :close-on-select="false"
              :clear-on-select="false"
              :preserve-search="true"
              placeholder="Select facility types..."
              :preselect-first="false"
              :max-height="150"
              track-by="name"
              label="name"
              :custom-label="(option) => option"
              :taggable="false"
            />
          </div>

          <!-- Status -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select 
              v-model="status" 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Statuses</option>
              <option v-for="statusOpt in filterOptions.statuses" :key="statusOpt" :value="statusOpt">{{ statusOpt }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
          <!-- Date From -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Created From</label>
            <input 
              v-model="date_from" 
              type="date" 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Created To</label>
            <input 
              v-model="date_to" 
              type="date" 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
          </div>

          <!-- Per Page -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Per Page</label>
            <select 
              v-model="per_page" 
              @change="props.filters.page = 1"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="10">10 Per Page</option>
              <option value="25">25 Per Page</option>
              <option value="50">50 Per Page</option>
              <option value="100">100 Per Page</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end items-center mt-4">
          <button 
            type="button" 
            @click="clearFilters" 
            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors"
          >
            Clear Filters
          </button>
          <button 
            type="button" 
            @click="exportToExcel" 
            class="ml-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors flex items-center"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Export Excel
          </button>
        </div>
      </div>
    </div>

    <!-- Results Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-900">
          Facilities ({{ facilities.total }} results)
        </h3>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Name</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">District</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Contact</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Cold Storage</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Handled By</th>
              <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Created</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-100">
            <tr v-if="facilities.data.length === 0">
              <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                <div class="flex flex-col items-center">
                  <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                  </svg>
                  <p class="text-lg font-medium text-gray-900 mb-2">No facilities found</p>
                  <p class="text-gray-500">Try adjusting your filters or search criteria</p>
                </div>
              </td>
            </tr>
            <tr v-for="facility in facilities.data" :key="facility.id" class="hover:bg-gray-50 transition-colors duration-150">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-gray-900">{{ facility.name }}</div>
                <div class="text-sm text-gray-500">ID: {{ facility.id }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" 
                      :class="getTypeBadgeClass(facility.type)">
                  {{ facility.type }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ facility.district }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ facility.email }}</div>
                <div class="text-sm text-gray-500">{{ facility.phone }}</div>
                <div class="text-xs text-gray-400 truncate max-w-xs">{{ facility.address }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" 
                      :class="getStatusBadgeClass(facility.status)">
                  {{ facility.status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" 
                      :class="facility.has_cold_storage === 'Yes' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                  {{ facility.has_cold_storage }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ facility.handled_by }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ facility.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex justify-end mt-3 mb-[80px]">
        <TailwindPagination :data="facilities" :limit="2" @pagination-change-page="getResults" />
      </div>
    </div>

  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { TailwindPagination } from "laravel-vue-pagination";
import Multiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import "@/Components/multiselect.css";

const props = defineProps({
  facilities: Object,
  filters: Object,
  filterOptions: Object,
  summary: Object,
});

const search = ref(props.filters?.search || '');
const region = ref(props.filters?.region ? (Array.isArray(props.filters.region) ? props.filters.region : [props.filters.region]) : []);
const district = ref(props.filters?.district ? (Array.isArray(props.filters.district) ? props.filters.district : [props.filters.district]) : []);
const facility_type = ref(props.filters?.facility_type ? (Array.isArray(props.filters.facility_type) ? props.filters.facility_type : [props.filters.facility_type]) : []);
const status = ref(props.filters?.status || '');
const date_from = ref(props.filters?.date_from || '');
const date_to = ref(props.filters?.date_to || '');
const per_page = ref(props.filters?.per_page || 25);

// District options: dependent on region(s) — load from API when region is selected
const districtOptions = ref([]);
async function loadDistrictsForRegions() {
  const regions = region.value && region.value.length ? (Array.isArray(region.value) ? region.value : [region.value]) : [];
  if (regions.length === 0) {
    districtOptions.value = [];
    return;
  }
  const seen = new Set();
  const all = [];
  for (const r of regions) {
    try {
      const { data } = await axios.post(route('districts.get-districts'), { region: r });
      const list = Array.isArray(data) ? data : [];
      list.forEach((d) => { if (!seen.has(d)) { seen.add(d); all.push(d); } });
    } catch (_) {}
  }
  districtOptions.value = all;
}
watch(region, () => {
  district.value = [];
  loadDistrictsForRegions();
}, { deep: true });
onMounted(() => {
  if (region.value && region.value.length) loadDistrictsForRegions();
});

const applyFilters = () => {
  const params = {};
    if(search.value) {
      params.search = search.value;
    }
    if(region.value && region.value.length) {
      params.region = region.value;
    }
    if(facility_type.value) {
      params.facility_type = facility_type.value;
    }
    if(district.value) {
      params.district = district.value;
    }
    if(status.value) {
      params.status = status.value;
    }
    if(date_from.value) {
      params.date_from = date_from.value;
    }
    if(date_to.value) {
      params.date_to = date_to.value;
    }
    if(per_page.value) {
      params.per_page = per_page.value;
    }
    if(props.filters.page) {
      params.page = props.filters.page;
    }
    router.get(route('reports.facilities-list'), params, {
      preserveState: true,
      preserveScroll: true,
      only: ['facilities', 'filterOptions', 'summary'],
    });
  };

const clearFilters = () => {
  search.value = '';
  facility_type.value = [];
  district.value = [];
  status.value = '';
  date_from.value = '';
  date_to.value = '';
  per_page.value = 25;
  applyFilters();
};

const getResults = (page) => {
    props.filters.page = page;
};

const exportToExcel = () => {
  const params = new URLSearchParams({
    search: search.value,
    region: region.value,
    facility_type: facility_type.value,
    district: district.value,
    status: status.value,
    date_from: date_from.value,
    date_to: date_to.value,
    per_page: per_page.value,
  });
  window.open(route('reports.facilities-list') + '?' + params.toString() + '&export=excel', '_blank');
};

const getTypeBadgeClass = (type) => {
  const classes = {
    'Regional Hospital': 'bg-blue-100 text-blue-800',
    'District Hospital': 'bg-green-100 text-green-800',
    'Health Centre': 'bg-purple-100 text-purple-800',
    'Primary Health Unit': 'bg-orange-100 text-orange-800',
  };
  return classes[type] || 'bg-gray-100 text-gray-800';
};

const getStatusBadgeClass = (status) => {
  const classes = {
    'Active': 'bg-green-100 text-green-800',
    'Inactive': 'bg-red-100 text-red-800',
    'Pending': 'bg-yellow-100 text-yellow-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
};

// Watch all filters and auto-apply
watch([
  () => search.value,
  () => region.value,
  () => facility_type.value,
  () => district.value,
  () => status.value,
  () => date_from.value,
  () => date_to.value,
  () => per_page.value,
  () => props.filters.page
], () => applyFilters());
</script> 