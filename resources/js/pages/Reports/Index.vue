<template>
<AppLayout>
<div class="p-6 bg-gray-900 text-gray-100 min-h-screen">
      <!-- Page Title -->
      <h1 class="text-3xl font-bold mb-8 tracking-tight">Delivery Reports</h1>

      <!-- Flash Messages -->
      <div v-if="$page.props.flash.success" class="bg-green-600 text-white p-4 mb-6 rounded-lg shadow-lg transition-all duration-300">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash.error" class="bg-red-600 text-white p-4 mb-6 rounded-lg shadow-lg transition-all duration-300">
        {{ $page.props.flash.error }}
      </div>

      <!-- Period Filter Dropdown -->
      <div class="relative mb-8 max-w-md">
        <label for="periods" class="block mb-2 font-medium text-gray-300 text-lg">Select Periods</label>
        <div class="relative">
          <button
            @click="toggleDropdown"
            class="w-full p-3 bg-gray-800 border border-gray-700 text-gray-100 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 flex justify-between items-center"
          >
            <span>{{ selectedPeriodLabels.length ? selectedPeriodLabels.join(', ') : 'Select Periods' }}</span>
            <svg
              class="w-5 h-5 text-gray-400 transition-transform duration-200"
              :class="{ 'rotate-180': isDropdownOpen }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="isDropdownOpen"
            class="absolute z-10 mt-2 w-full bg-gray-800 border border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto"
          >
            <div class="grid grid-cols-3 gap-2 p-4">
              <label
                v-for="(label, periodId) in availablePeriods"
                :key="periodId"
                class="flex items-center p-2 hover:bg-gray-700 rounded-md cursor-pointer transition-all duration-200"
              >
                <input
                  type="checkbox"
                  :value="periodId"
                  v-model="selectedPeriods"
                  class="w-4 h-4 text-blue-500 bg-gray-700 border-gray-600 rounded focus:ring-blue-500 focus:ring-2"
                />
                <span class="ml-2 text-gray-100">{{ label }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="flex justify-center items-center mb-8">
        <img src="/images/loading.gif" alt="Loading..." class="w-12 h-12" />
      </div>

      <!-- Report Data -->
      <div v-else class="space-y-8">
        <div v-if="Object.keys(reportData).length === 0" class="text-gray-400 text-center italic py-8 bg-gray-800 rounded-lg shadow-md">
          No data available for the selected periods.
        </div>
        <div v-else class="space-y-6">
          <div v-for="(data, index) in reportData" :key="`${data.round_id}-${data.period_id}`" class="bg-gray-800 rounded-lg shadow-md p-6 transition-all duration-200 hover:shadow-lg">
            <!-- Round and Period Summary -->
            <h2 class="text-xl font-semibold mb-4 text-gray-100 tracking-tight">
              {{ data.name }} - {{ data.period_name }}
            </h2>
            <div class="overflow-x-auto mb-6">
              <table class="min-w-full table-auto border-separate border-spacing-0">
                <thead>
                  <tr class="bg-gray-700 text-gray-300 text-sm uppercase tracking-wider">
                    <th class="py-3 px-6 text-left rounded-tl-lg">Metric</th>
                    <th class="py-3 px-6 text-left rounded-tr-lg">Value</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="border-t border-gray-700 hover:bg-gray-700 transition-colors">
                    <td class="py-3 px-6 text-gray-100 font-medium">Total Parcels</td>
                    <td class="py-3 px-6 text-gray-100">{{ data.total_parcels }}</td>
                  </tr>
                  <tr class="border-t border-gray-700 hover:bg-gray-700 transition-colors">
                    <td class="py-3 px-6 text-gray-100 font-medium">Total Income (£)</td>
                    <td class="py-3 px-6 text-gray-100">{{ data.total_income.toFixed(2) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Packet Type Breakdown -->
            <div class="overflow-x-auto">
              <h3 class="text-lg font-medium mb-3 text-gray-300 tracking-tight">Packet Type Breakdown</h3>
              <table class="min-w-full table-auto border-separate border-spacing-0">
                <thead>
                  <tr class="bg-gray-700 text-gray-300 text-sm uppercase tracking-wider">
                    <th class="py-3 px-6 text-left rounded-tl-lg">Packet Type</th>
                    <th class="py-3 px-6 text-left">Total Parcels</th>
                    <th class="py-3 px-6 text-left">Income (£)</th>
                    <th class="py-3 px-6 text-left rounded-tr-lg">Percentage</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(packet, index) in data.packet_types"
                    :key="index"
                    class="border-t border-gray-700 hover:bg-gray-700 transition-colors"
                  >
                    <td class="py-3 px-6 text-gray-100">{{ packet.name }}</td>
                    <td class="py-3 px-6 text-gray-100">{{ packet.total }}</td>
                    <td class="py-3 px-6 text-gray-100">{{ packet.income.toFixed(2) }}</td>
                    <td class="py-3 px-6 text-gray-100">{{ packet.percentage.toFixed(2) }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
</AppLayout>
  </template>

  <script setup>
  import { ref, watch, computed } from 'vue';
  import { router } from '@inertiajs/vue3';
  import AppLayout from '@/layouts/AppLayout.vue';
  const props = defineProps({
    availablePeriods: Object,
    selectedPeriods: Array,
    reportData: Object,
    flash: Object,
  });

  const selectedPeriods = ref(props.selectedPeriods);
  const isLoading = ref(false);
  const isDropdownOpen = ref(false);

  // Compute the labels of selected periods for display
  const selectedPeriodLabels = computed(() => {
    return selectedPeriods.value.map(periodId => props.availablePeriods[periodId]);
  });

  const toggleDropdown = () => {
    isDropdownOpen.value = !isDropdownOpen.value;
  };

  // Close dropdown when clicking outside
  const closeDropdown = (event) => {
    if (!event.target.closest('.relative')) {
      isDropdownOpen.value = false;
    }
  };

  // Add event listener to close dropdown on outside click
  watch(isDropdownOpen, (newValue) => {
    if (newValue) {
      document.addEventListener('click', closeDropdown);
    } else {
      document.removeEventListener('click', closeDropdown);
    }
  });

  // Watch for period changes
  watch(selectedPeriods, (newPeriods) => {
    isLoading.value = true;
    router.get('/reports', { periods: newPeriods }, {
      preserveState: true,
      onFinish: () => {
        isLoading.value = false;
      },
    });
  });

  // Set initial loading state if periods change on mount
  if (props.selectedPeriods.length) {
    isLoading.value = true;
    setTimeout(() => {
      isLoading.value = false;
    }, 500); // Simulate initial load
  }
  </script>

  <style scoped>
  /* Smooth transitions for table rows */
  table tbody tr {
    transition: background-color 0.2s ease;
  }

  /* Hover effect for cards */
  .bg-gray-800:hover {
    transform: translateY(-2px);
  }

  /* Ensure tables have a clean look */
  table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
  }

  th, td {
    border-bottom: 1px solid #4B5563;
  }

  th:first-child, td:first-child {
    border-left: none;
  }

  th:last-child, td:last-child {
    border-right: none;
  }

  .rounded-tl-lg {
    border-top-left-radius: 0.5rem;
  }

  .rounded-tr-lg {
    border-top-right-radius: 0.5rem;
  }

  /* Dropdown styling */
  .max-h-60 {
    max-height: 15rem;
  }
  </style>
