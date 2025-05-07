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

        <!-- Filters -->
        <div class="bg-gray-800 rounded-lg shadow-md p-6 mb-8">
          <h2 class="text-xl font-semibold mb-4 text-gray-100">Report Filters</h2>
          <form @submit.prevent="fetchReport" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Period Filter -->
            <div class="relative">
              <label for="periods" class="block mb-2 font-medium text-gray-300 text-lg">Select Periods</label>
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
            <!-- Round Filter -->
            <div>
              <label for="rounds" class="block mb-2 font-medium text-gray-300 text-lg">Select Round</label>
              <select
                v-model="selectedRoundId"
                class="w-full p-3 bg-gray-800 border border-gray-700 text-gray-100 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Rounds</option>
                <option v-for="round in rounds" :key="round.id" :value="round.id">
                  {{ round.name }}
                </option>
              </select>
            </div>
            <!-- Parcel Type Filter -->
            <div>
              <label for="parcel_types" class="block mb-2 font-medium text-gray-300 text-lg">Select Parcel Type</label>
              <select
                v-model="selectedParcelTypeId"
                class="w-full p-3 bg-gray-800 border border-gray-700 text-gray-100 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All Parcel Types</option>
                <option v-for="type in parcelTypes" :key="type.id" :value="type.id">
                  {{ type.name }}
                </option>
              </select>
            </div>
            <!-- Submit Button -->
            <div class="flex items-end">
              <button
                type="submit"
                class="w-full p-3 bg-blue-500 text-white rounded-lg shadow-sm hover:bg-blue-600 focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                :disabled="isLoading"
              >
                Generate Report
              </button>
            </div>
          </form>
        </div>

        <!-- Export Button -->
        <div class="mb-8">
          <button
            @click="exportToCsv"
            class="p-3 bg-green-500 text-white rounded-lg shadow-sm hover:bg-green-600 focus:ring-2 focus:ring-green-500 transition-all duration-200"
            :disabled="isLoading || !totalSummary.total_parcels"
          >
            Export to CSV
          </button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="flex justify-center items-center mb-8">
          <img src="/images/loading.gif" alt="Loading..." class="w-12 h-12" />
        </div>

        <!-- Charts and Report Data -->
        <div v-else class="space-y-8">
          <!-- Charts -->
          <div class="mb-8 flex space-x-4">
            <!-- Income and Profit Summary Bar Chart (50% width) -->
            <div class="w-1/2 h-[400px] bg-gray-800 rounded-lg shadow-md p-6">
              <h2 class="text-xl font-semibold mb-4 text-gray-100 tracking-tight">Income and Profit Summary by Period</h2>
              <div v-if="!isBarChartDataValid" class="text-gray-400 text-center italic">
                No income data available for any periods.
              </div>
              <BarChartCanvas v-else-if="isChartDataReady" :chart-data="barChartData" :options="barChartOptions" />
            </div>
            <!-- Income Trend Line Chart (50% width) -->
            <div class="w-1/2 h-[400px] bg-gray-800 rounded-lg shadow-md p-6">
              <h2 class="text-xl font-semibold mb-4 text-gray-100 tracking-tight">Income Trend Over Time</h2>
              <div v-if="!isLineChartDataValid" class="text-gray-400 text-center italic">
                No income data available for selected periods.
              </div>
              <LineChartCanvas v-else-if="isChartDataReady" :chart-data="lineChartData" :options="lineChartOptions" />
            </div>
          </div>
          <!-- Parcel Type Pie Chart -->
          <div class="h-[400px] bg-gray-800 rounded-lg shadow-md p-6 flex flex-col items-center">
            <h2 class="text-xl font-semibold mb-4 text-gray-100 tracking-tight">Parcel Type Distribution</h2>
            <div v-if="!isPieChartDataValid" class="text-gray-400 text-center italic">
              No parcel type data available for selected periods. Try selecting a different period.
            </div>
            <PieChartCanvas v-else-if="isChartDataReady" :chart-data="pieChartData" :options="pieChartOptions" />
          </div>

          <!-- Report Data -->
          <div v-if="Object.keys(reportData).length === 0 && totalSummary.total_parcels === 0" class="text-gray-400 text-center italic py-8 bg-gray-800 rounded-lg shadow-md">
            No data available for the selected periods. Try selecting a different period.
          </div>
          <div v-else class="space-y-6">
            <!-- Total Summary Card -->
            <div v-if="totalSummary.total_parcels > 0" class="bg-gray-800 rounded-lg shadow-md p-6 transition-all duration-200 hover:shadow-lg">
              <h2 class="text-xl font-semibold mb-4 text-gray-100 tracking-tight">
                {{ totalSummary.name }} - {{ totalSummary.period_name }}
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
                      <td class="py-3 px-6 text-gray-100">{{ totalSummary.total_parcels }}</td>
                    </tr>
                    <tr class="border-t border-gray-700 hover:bg-gray-700 transition-colors">
                      <td class="py-3 px-6 text-gray-100 font-medium">Total Income (£)</td>
                      <td class="py-3 px-6 text-gray-100">{{ totalSummary.total_income.toFixed(2) }}</td>
                    </tr>
                    <tr class="border-t border-gray-700 hover:bg-gray-700 transition-colors">
                      <td class="py-3 px-6 text-gray-100 font-medium">Total Cost (£)</td>
                      <td class="py-3 px-6 text-gray-100">{{ totalSummary.total_cost.toFixed(2) }}</td>
                    </tr>
                    <tr class="border-t border-gray-700 hover:bg-gray-700 transition-colors">
                      <td class="py-3 px-6 text-gray-100 font-medium">Total Profit (£)</td>
                      <td class="py-3 px-6 text-gray-100" :class="totalSummary.total_profit >= 0 ? 'text-green-400' : 'text-red-400'">
                        {{ totalSummary.total_profit.toFixed(2) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
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
                      v-for="(packet, index) in totalSummary.packet_types"
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

            <!-- Delivery Performance Table -->
            <div v-if="deliveryPerformance.length > 0" class="bg-gray-800 rounded-lg shadow-md p-6 transition-all duration-200 hover:shadow-lg">
              <h2 class="text-xl font-semibold mb-4 text-gray-100 tracking-tight">Delivery Performance</h2>
              <div class="overflow-x-auto">
                <table class="min-w-full table-auto border-separate border-spacing-0">
                  <thead>
                    <tr class="bg-gray-700 text-gray-300 text-sm uppercase tracking-wider">
                      <th class="py-3 px-6 text-left rounded-tl-lg">Date</th>
                      <th class="py-3 px-6 text-left">Round</th>
                      <th class="py-3 px-6 text-left">Parcel Type</th>
                      <th class="py-3 px-6 text-left">Parcels</th>
                      <th class="py-3 px-6 text-left">Income (£)</th>
                      <th class="py-3 px-6 text-left rounded-tr-lg">Profit (£)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(entry, index) in deliveryPerformance"
                      :key="index"
                      class="border-t border-gray-700 hover:bg-gray-700 transition-colors"
                    >
                      <td class="py-3 px-6 text-gray-100">{{ entry.date }}</td>
                      <td class="py-3 px-6 text-gray-100">{{ entry.round_name }}</td>
                      <td class="py-3 px-6 text-gray-100">{{ entry.parcel_type }}</td>
                      <td class="py-3 px-6 text-gray-100">{{ entry.parcels }}</td>
                      <td class="py-3 px-6 text-gray-100">{{ entry.income.toFixed(2) }}</td>
                      <td class="py-3 px-6 text-gray-100" :class="entry.profit >= 0 ? 'text-green-400' : 'text-red-400'">
                        {{ entry.profit.toFixed(2) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Per Round and Period Cards (Collapsible) -->
            <div v-if="reportData.length > 0" class="bg-gray-800 rounded-lg shadow-md p-6 transition-all duration-200 hover:shadow-lg">
              <button
                @click="toggleRoundSummaries"
                class="w-full flex justify-between items-center text-xl font-semibold text-gray-100 tracking-tight focus:outline-none"
              >
                <span>Round Summaries</span>
                <svg
                  class="w-6 h-6 text-gray-400 transition-transform duration-200"
                  :class="{ 'rotate-180': areRoundSummariesExpanded }"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
              </button>
              <div v-if="areRoundSummariesExpanded" class="mt-4 space-y-6">
                <div v-for="(data, index) in reportData" :key="`${data.round_id}-${data.period_id}`" class="border-t border-gray-700 pt-4">
                  <h2 class="text-lg font-semibold mb-4 text-gray-100 tracking-tight">
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
                        <tr class="border-t border-gray-700 hover:bg-gray-700 transition-colors">
                          <td class="py-3 px-6 text-gray-100 font-medium">Total Cost (£)</td>
                          <td class="py-3 px-6 text-gray-100">{{ data.total_cost.toFixed(2) }}</td>
                        </tr>
                        <tr class="border-t border-gray-700 hover:bg-gray-700 transition-colors">
                          <td class="py-3 px-6 text-gray-100 font-medium">Total Profit (£)</td>
                          <td class="py-3 px-6 text-gray-100" :class="data.total_profit >= 0 ? 'text-green-400' : 'text-red-400'">
                            {{ data.total_profit.toFixed(2) }}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
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
        </div>
      </div>
    </AppLayout>
  </template>

  <script setup lang="ts">
  import { ref, watch, computed, onMounted, nextTick } from 'vue';
  import { router } from '@inertiajs/vue3';
  import AppLayout from '@/Layouts/AppLayout.vue';
  import BarChartCanvas from '@/Components/BarChartCanvas.vue';
  import PieChartCanvas from '@/Components/PieChartCanvas.vue';
  import LineChartCanvas from '@/Components/LineChartCanvas.vue';
  import { toRaw } from 'vue';

  const props = defineProps({
    availablePeriods: Object,
    selectedPeriods: Array,
    reportData: Object,
    totalSummary: Object,
    incomeByPeriod: Array,
    pieChartData: Object,
    rounds: Array,
    parcelTypes: Array,
    deliveryPerformance: Array,
    flash: Object,
  });

  const selectedPeriods = ref(props.selectedPeriods);
  const selectedRoundId = ref('');
  const selectedParcelTypeId = ref('');
  const isLoading = ref(false);
  const isDropdownOpen = ref(false);
  const areRoundSummariesExpanded = ref(false);
  const isBarChartDataValid = ref(false);
  const isPieChartDataValid = ref(false);
  const isLineChartDataValid = ref(false);
  const isChartDataReady = ref(false);
  const barChartData = ref({
    labels: [],
    datasets: [],
  });
  const pieChartData = ref({
    labels: [],
    datasets: [],
  });
  const lineChartData = ref({
    labels: [],
    datasets: [],
  });

  // Compute the labels of selected periods for display
  const selectedPeriodLabels = computed(() => {
    return selectedPeriods.value.map(periodId => props.availablePeriods[periodId]);
  });

  // Initialize chart data
  const updateChartData = async () => {
    await nextTick();

    // Bar Chart Data (Income and Profit)
    const incomeByPeriod = Array.isArray(props.incomeByPeriod) ? props.incomeByPeriod : [];
    const barLabels = incomeByPeriod.map(item => item.period_name || 'Unknown');
    const incomeDataValues = incomeByPeriod.map(item => item.income || 0);
    const profitDataValues = incomeByPeriod.map(item => item.profit || 0);
    barChartData.value = {
      labels: barLabels,
      datasets: [
        {
          label: 'Income (£)',
          backgroundColor: '#3B82F6',
          data: incomeDataValues,
        },
        {
          label: 'Profit (£)',
          backgroundColor: '#10B981',
          data: profitDataValues,
        },
      ],
    };
    isBarChartDataValid.value = barLabels.length > 0 && (incomeDataValues.some(val => val > 0) || profitDataValues.some(val => val !== 0));

    // Pie Chart Data
    const pieDataRaw = toRaw(props.pieChartData) || { labels: [], data: [] };
    const pieLabels = Array.isArray(pieDataRaw.labels) ? [...pieDataRaw.labels] : [];
    const pieDataValues = Array.isArray(pieDataRaw.data) ? [...pieDataRaw.data] : [];
    pieChartData.value = {
      labels: pieLabels,
      datasets: [
        {
          backgroundColor: [
            '#3B82F6',
            '#EF4444',
            '#10B981',
            '#F59E0B',
            '#8B5CF6',
            '#EC4899',
            '#14B8A6',
            '#F97316',
          ],
          data: pieDataValues,
        },
      ],
    };
    isPieChartDataValid.value = pieLabels.length > 0 && pieDataValues.length > 0;

    // Line Chart Data (Income Trend)
    const performance = Array.isArray(props.deliveryPerformance) ? props.deliveryPerformance : [];
    const lineLabels = performance.map(entry => entry.date).filter((v, i, a) => a.indexOf(v) === i); // Unique dates
    const lineDataValues = lineLabels.map(date => {
      return performance
        .filter(entry => entry.date === date)
        .reduce((sum, entry) => sum + entry.income, 0);
    });
    lineChartData.value = {
      labels: lineLabels,
      datasets: [
        {
          label: 'Income (£)',
          borderColor: '#3B82F6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          data: lineDataValues,
          fill: true,
        },
      ],
    };
    isLineChartDataValid.value = lineLabels.length > 0 && lineDataValues.some(val => val > 0);

    // Mark the chart data as ready
    isChartDataReady.value = true;
  };

  // Chart Options
  const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: {
        beginAtZero: true,
        title: {
          display: true,
          text: 'Amount (£)',
          color: '#D1D5DB',
        },
        ticks: { color: '#D1D5DB' },
        grid: { color: '#4B5563' },
      },
      x: {
        title: {
          display: true,
          text: 'Period',
          color: '#D1D5DB',
        },
        ticks: { color: '#D1D5DB' },
        grid: { color: '#4B5563' },
      },
    },
    plugins: {
      legend: { labels: { color: '#D1D5DB' } },
      tooltip: { backgroundColor: '#1F2937', titleColor: '#D1D5DB', bodyColor: '#D1D5DB' },
    },
  };

  const pieChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'right',
        labels: { color: '#D1D5DB', padding: 20, font: { size: 14 }, boxWidth: 20 },
      },
      tooltip: { backgroundColor: '#1F2937', titleColor: '#D1D5DB', bodyColor: '#D1D5DB' },
    },
  };

  const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: {
        beginAtZero: true,
        title: { display: true, text: 'Income (£)', color: '#D1D5DB' },
        ticks: { color: '#D1D5DB' },
        grid: { color: '#4B5563' },
      },
      x: {
        title: { display: true, text: 'Date', color: '#D1D5DB' },
        ticks: { color: '#D1D5DB' },
        grid: { color: '#4B5563' },
      },
    },
    plugins: {
      legend: { labels: { color: '#D1D5DB' } },
      tooltip: { backgroundColor: '#1F2937', titleColor: '#D1D5DB', bodyColor: '#D1D5DB' },
    },
  };

  // Export to CSV
  const exportToCsv = () => {
    const headers = ['Date,Round,Parcel Type,Parcels,Income (£),Profit (£)'];
    const rows = props.deliveryPerformance.map(entry =>
      `${entry.date},${entry.round_name},${entry.parcel_type},${entry.parcels},${entry.income.toFixed(2)},${entry.profit.toFixed(2)}`
    );
    const csvContent = headers.concat(rows).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `delivery_report_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
  };

  // Fetch report with filters
  const fetchReport = () => {
    isLoading.value = true;
    areRoundSummariesExpanded.value = false;
    router.get('/reports', {
      periods: selectedPeriods.value,
      round_id: selectedRoundId.value,
      parcel_type_id: selectedParcelTypeId.value,
    }, {
      preserveState: true,
      onFinish: () => {
        isLoading.value = false;
        updateChartData();
      },
    });
  };

  // Debug chart data
  watch(() => [props.incomeByPeriod, props.pieChartData, props.deliveryPerformance], () => {
    isChartDataReady.value = false;
    updateChartData();
  }, { immediate: true });

  // Watch for filter changes
  watch([selectedPeriods, selectedRoundId, selectedParcelTypeId], () => {
    fetchReport();
  });

  // Close dropdown when clicking outside
  const closeDropdown = (event: Event) => {
    if (!(event.target as HTMLElement).closest('.relative')) {
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

  const toggleDropdown = () => {
    isDropdownOpen.value = !isDropdownOpen.value;
  };

  const toggleRoundSummaries = () => {
    areRoundSummariesExpanded.value = !areRoundSummariesExpanded.value;
  };

  // Initial load
  onMounted(() => {
    updateChartData();
    if (props.selectedPeriods.length) {
      fetchReport();
    }
  });
  </script>

  <style scoped>
  table tbody tr { transition: background-color 0.2s ease; }
  .bg-gray-800:hover { transform: translateY(-2px); }
  table { border-collapse: separate; border-spacing: 0; width: 100%; }
  th, td { border-bottom: 1px solid #4B5563; }
  th:first-child, td:first-child { border-left: none; }
  th:last-child, td:last-child { border-right: none; }
  .rounded-tl-lg { border-top-left-radius: 0.5rem; }
  .rounded-tr-lg { border-top-right-radius: 0.5rem; }
  .max-h-60 { max-height: 15rem; }
  </style>
