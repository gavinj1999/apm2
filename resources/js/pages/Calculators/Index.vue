<template>
    <AppLayout>


      <template #header>
        <h1 class="text-2xl font-bold text-gray-800">Courier Rate Calculator</h1>
      </template>

      <template #content>
        <div class="container mx-auto p-4">
          <div class="bg-white shadow-md rounded-lg p-6">
            <CourierCalculator />
          </div>
        </div>
      </template>
    <div class="courier-calculator font-poppins max-w-full mx-auto p-4 sm:p-6">


      <!-- Title -->
      <div  class="text-center mb-6">
        <h1 class="text-2xl sm:text-3xl font-semibold text-gray-800">Courier Rate Calculator</h1>
      </div>

      <!-- Progress Steps -->
      <div class="flex justify-center mb-6">
        <div class="flex flex-wrap gap-2 sm:gap-4">
          <div
            v-for="(step, index) in steps"
            :key="index"
            class="px-4 py-2 rounded-full text-sm sm:text-base font-medium transition-colors"
            :class="{
              'bg-blue-600 text-white': currentSection === sections[index],
              'bg-gray-200 text-gray-600': currentSection !== sections[index],
            }"
          >
            {{ step }}
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="p-4 sm:p-6">
        <div class="max-w-2xl mx-auto">
          <!-- Parcel Rates Section -->
          <div v-show="currentSection === 'parcelRates'" class="section">
            <div class="mb-4">
              <label for="standardRate" class="block mb-1 font-medium text-gray-700">Enter Standard Rate (£):</label>
              <input
                type="number"
                id="standardRate"
                v-model.number="standardRate"
                placeholder="e.g., 0.50, 0.75, 1.00, 1.20"
                step="0.01"
                class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <button
              class="w-full py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              @click="calculateRates"
            >
              Calculate Rates
            </button>

            <table class="w-full mt-6 border-collapse">
              <thead>
                <tr>
                  <th class="p-2 text-left bg-gray-100 font-semibold text-gray-700">Banding</th>
                  <th class="p-2 text-left bg-gray-100 font-semibold text-gray-700">Rate (£)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(rate, key) in rates" :key="key">
                  <td class="p-2 border-b border-gray-200">{{ key }}</td>
                  <td class="p-2 border-b border-gray-200">{{ rate || '-' }}</td>
                </tr>
              </tbody>
            </table>
            <button
              class="w-full py-2 mt-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              @click="nextSection('estimatedPay')"
            >
              Next
            </button>
          </div>

          <!-- Estimated Pay Section -->
          <div v-show="currentSection === 'estimatedPay'" class="section">
            <p class="mb-4 text-gray-700">Enter the number of deliveries for each banding:</p>
            <div class="mb-6">
              <div>
                <form id="estimatedPayForm">
                  <div
                    v-for="(count, key) in deliveryCounts"
                    :key="key"
                    class="mb-4"
                  >
                    <label :for="`${key}Count`" class="block mb-1 font-medium text-gray-700">{{ key }}:</label>
                    <input
                      type="number"
                      :id="`${key}Count`"
                      v-model.number="deliveryCounts[key]"
                      :placeholder="`Number of ${key}`"
                      min="0"
                      class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                </form>
              </div>
            </div>

            <div class="mb-4">
              <h3 class="text-lg font-semibold text-gray-800">Total Deliveries:</h3>
              <span class="text-gray-600">{{ totalDeliveries }}</span>
            </div>
            <div class="mb-4">
              <h3 class="text-lg font-semibold text-gray-800">Total Daily Pay:</h3>
              <span class="text-gray-600">£{{ totalEstimatedPay.toFixed(2) }}</span>
            </div>
            <div class="mb-4">
              <h4 class="text-base font-medium text-gray-800">
                Average Rate <span class="text-gray-600">£{{ averageRate.toFixed(2) }}</span> Per Parcel.
              </h4>
            </div>
            <button
              class="w-full py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              @click="nextSection('scanningAndLoading')"
            >
              Next
            </button>
          </div>

          <!-- Other Sections (Scanning and Loading, Travel and Time, etc.) -->
          <!-- Follow the same pattern with Tailwind classes -->

          <!-- Summary Section -->
          <div v-show="currentSection === 'payPerHour'" class="section">
            <div class="mt-6">
              <h3 class="text-lg font-semibold text-gray-800">Daily Summary</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                <div class="text-center p-4 bg-gray-50 rounded-md">
                  <h4 class="text-base font-medium text-gray-700">Total Deliveries</h4>
                  <span class="text-gray-600">{{ totalDeliveries }}</span>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-md">
                  <h4 class="text-base font-medium text-gray-700">Total Daily Pay</h4>
                  <span class="text-gray-600">£{{ totalEstimatedPay.toFixed(2) }}</span>
                </div>
                <!-- Add other summary items -->
              </div>
            </div>

            <div class="text-center p-6 bg-gray-100 rounded-lg mt-6">
              <h3 class="text-lg font-semibold text-gray-800">We ❤️ Couriers</h3>
              <p class="mt-2">
                <a
                  href="https://www.facebook.com/groups/551190417334602/"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-blue-600 hover:underline"
                >
                  EVRi Couriers Support Group
                </a>
              </p>
            </div>

            <div class="text-center mt-6">
              <button
                class="w-full sm:w-auto px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                @click="resetCalculator"
              >
                Start Again
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
</AppLayout>
  </template>

  <script setup>
  import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
  import Chart from 'chart.js/auto';
  import AppLayout from '@/layouts/AppLayout.vue';
  // Reactive state
  const isMobile = ref(window.innerWidth <= 768);
  const currentSection = ref('parcelRates');
  const standardRate = ref(null);
  const rates = reactive({
    Postables: null,
    'Small Packets': null,
    Packets: null,
    'Hanging Garments': null,
    Heavy: null,
    Collections: null,
  });
  const deliveryCounts = reactive({
    Postables: 0,
    'Small Packets': 0,
    Packets: 0,
    Standards: 0,
    'Hanging Garments': 0,
    Heavy: 0,
    Collections: 0,
  });
  const selectedCourier = ref(null);
  const ratePerMile = ref(0.287);
  const includeTimeOnTask = ref('no');
  const chartInstance = ref(null);

  // Static data
  const steps = [
    '1. My Rates',
    '2. Daily Pay',
    '3. Time On Task',
    '4. Time And Travel',
    '5. Expenses Per Mile',
    '6. Summary',
  ];
  const sections = [
    'parcelRates',
    'estimatedPay',
    'scanningAndLoading',
    'travelAndTime',
    'expensesPerMile',
    'payPerHour',
  ];

  // Computed properties
  const totalDeliveries = computed(() =>
    Object.values(deliveryCounts).reduce((sum, count) => sum + (count || 0), 0)
  );

  const totalEstimatedPay = computed(() => {
    let total = 0;
    for (const [key, count] of Object.entries(deliveryCounts)) {
      const rate = rates[key] || 0;
      total += count * rate;
    }
    return total;
  });

  const averageRate = computed(() =>
    totalDeliveries.value ? totalEstimatedPay.value / totalDeliveries.value : 0
  );

  // Methods
  const calculateRates = () => {
    const rate = standardRate.value;
    if (!rate) return;

    Object.assign(rates, {
      Postables: (rate * 0.6).toFixed(2),
      'Small Packets': (rate * 0.8).toFixed(2),
      Packets: rate.toFixed(2),
      'Hanging Garments': (rate * 1.2).toFixed(2),
      Heavy: (rate * 1.5).toFixed(2),
      Collections: (rate * 1.0).toFixed(2),
    });
  };

  const nextSection = (section) => {
    currentSection.value = section;
  };

  const selectCourier = (courier) => {
    selectedCourier.value = courier;
  };

  const resetCalculator = () => {
    standardRate.value = null;
    Object.keys(rates).forEach((key) => (rates[key] = null));
    Object.keys(deliveryCounts).forEach((key) => (deliveryCounts[key] = 0));
    currentSection.value = 'parcelRates';
    selectedCourier.value = null;
  };

  const initChart = () => {
    const ctx = document.getElementById('bandingChart')?.getContext('2d');
    if (ctx) {
      chartInstance.value = new Chart(ctx, {
        type: 'pie',
        data: {
          labels: Object.keys(deliveryCounts),
          datasets: [
            {
              data: Object.values(deliveryCounts),
              backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF',
                '#FF9F40',
                '#C9CBCF',
              ],
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'top' },
          },
        },
      });
    }
  };

  const toggleWorkings = () => {
    // Implement workings toggle logic
  };

  const toggleWorkingsSummary = () => {
    // Implement summary workings toggle logic
  };

  // Lifecycle hooks
  onMounted(() => {
    // Initialize Google Analytics
    window.dataLayer = window.dataLayer || [];
    function gtag() {
      window.dataLayer.push(arguments);
    }
    gtag('js', new Date());
    gtag('config', 'G-XMSYF48Q66');

    // Initialize chart
    initChart();

    // Update mobile detection on resize
    const handleResize = () => {
      isMobile.value = window.innerWidth <= 768;
    };
    window.addEventListener('resize', handleResize);
  });

  onUnmounted(() => {
    window.removeEventListener('resize', () => {
      isMobile.value = window.innerWidth <= 768;
    });
    if (chartInstance.value) {
      chartInstance.value.destroy();
    }
  });

  // Watch deliveryCounts to update chart
  watch(
    () => ({ ...deliveryCounts }),
    (newCounts) => {
      if (chartInstance.value) {
        chartInstance.value.data.datasets[0].data = Object.values(newCounts);
        chartInstance.value.update();
      }
    },
    { deep: true }
  );
  </script>

  <style scoped>
  /* Minimal scoped CSS for Tailwind overrides or custom styles */
  .font-poppins {
    font-family: 'Poppins', sans-serif;
  }
  </style>
