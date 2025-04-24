<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import HomeNav from '@/components/HomeNav.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import L from 'leaflet';

// Geolocation
const location = ref<{ lat: number; lon: number } | null>(null);
const locationError = ref<string | null>(null);

// Weather
const weather = ref<any>(null);
const weatherError = ref<string | null>(null);

// Traffic
const traffic = ref<any[]>([]);
const trafficError = ref<string | null>(null);

// Time
const currentTime = ref<string>(new Date().toLocaleTimeString());
let timeInterval: number | null = null;

// Map
const mapContainer = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
const apiKey = import.meta.env.VITE_OPENWEATHERMAP_API_KEY; // Add to .env as VITE_OPENWEATHERMAP_API_KEY

// Fetch geolocation
const getLocation = () => {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        location.value = {
          lat: position.coords.latitude,
          lon: position.coords.longitude,
        };
        fetchWeather();
        fetchTraffic();
        initMap();
      },
      (error) => {
        locationError.value = 'Unable to retrieve location. Using default (London, UK).';
        location.value = { lat: 51.5074, lon: -0.1278 }; // Default: London
        fetchWeather();
        fetchTraffic();
        initMap();
      }
    );
  } else {
    locationError.value = 'Geolocation is not supported by this browser.';
    location.value = { lat: 51.5074, lon: -0.1278 };
    fetchWeather();
    fetchTraffic();
    initMap();
  }
};

// Fetch weather data
const fetchWeather = async () => {
  if (!location.value) return;
  try {
    const response = await axios.get('/api/weather', {
      params: { lat: location.value.lat, lon: location.value.lon },
    });
    console.log('Weather API response:', response.data); // Debug log
    if (response.data && response.data.main) {
      weather.value = response.data;
    } else {
      weatherError.value = 'Invalid weather data received.';
    }
  } catch (error: any) {
    console.error('Weather API error:', error); // Debug log
    weatherError.value = error.response?.data?.message || 'Failed to fetch weather data.';
  }
};

// Fetch traffic data
const fetchTraffic = async () => {
  if (!location.value) return;
  try {
    const response = await axios.get('/api/traffic', {
      params: { lat: location.value.lat, lon: location.value.lon, radius: 16093 },
    });
    console.log('Traffic API response:', response.data); // Debug log
    traffic.value = response.data.incidents || [];
  } catch (error: any) {
    console.error('Traffic API error:', error); // Debug log
    trafficError.value = error.response?.data?.message || 'Failed to fetch traffic data.';
  }
};

// Initialize Leaflet map
const initMap = () => {
  if (!mapContainer.value || !location.value) return;

  // Initialize map
  map = L.map(mapContainer.value).setView([location.value.lat, location.value.lon], 10);

  // Add base tile layer (OpenStreetMap)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map);

  // Add OpenWeatherMap precipitation layer
  L.tileLayer(`https://tile.openweathermap.org/map/precipitation_new/{z}/{x}/{y}.png?appid=${apiKey}`, {
    attribution: '© <a href="https://openweathermap.org/">OpenWeatherMap</a>',
    opacity: 0.7,
  }).addTo(map);

  // Add marker for user's location
  L.marker([location.value.lat, location.value.lon])
    .addTo(map)
    .bindPopup('Your Location')
    .openPopup();
};

// Update time every second
const updateTime = () => {
  currentTime.value = new Date().toLocaleTimeString();
};

// Lifecycle hooks
onMounted(() => {
  getLocation();
  timeInterval = window.setInterval(updateTime, 1000);
});

onUnmounted(() => {
  if (timeInterval) clearInterval(timeInterval);
  if (map) map.remove(); // Clean up map
});
</script>

<template>
  <Head title="Welcome">
    <link rel="preconnect" href="https://rsms.me/" />
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  </Head>

  <div
    class="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC] lg:p-8"
  >
    <!-- Navigation -->
    <header
      class="mb-6 w-full max-w-[335px] text-sm lg:max-w-4xl"
    >
      <HomeNav />
    </header>

    <!-- Main Content -->
    <main
      class="flex w-full max-w-[335px] flex-col items-center justify-center lg:max-w-4xl lg:grow"
    >
      <!-- Current Time -->
      <section
        class="mb-6 w-full rounded-lg bg-white p-6 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"
      >
        <h2 class="mb-2 text-xl font-medium">Current Time</h2>
        <p class="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
          {{ currentTime }}
        </p>
      </section>

      <!-- Weather -->
      <section
        class="mb-6 w-full rounded-lg bg-white p-6 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"
      >
        <h2 class="mb-2 text-xl font-medium">Local Weather</h2>
        <div v-if="locationError" class="mb-2 text-red-500 text-sm">
          {{ locationError }}
        </div>
        <div v-if="weatherError" class="mb-2 text-red-500 text-sm">
          {{ weatherError }}
        </div>
        <div v-else class="space-y-4">
          <!-- Weather Map -->
          <div
            ref="mapContainer"
            class="h-64 w-full rounded-lg lg:h-96"
          ></div>
          <!-- Weather Details -->
          <div
            v-if="weather && weather.main && weather.weather?.length"
            class="text-[13px] text-[#706f6c] dark:text-[#A1A09A]"
          >
            <p>Location: {{ weather.name || 'Unknown' }}</p>
            <p>Temperature: {{ weather.main.temp }}°C</p>
            <p>Condition: {{ weather.weather[0].description || 'N/A' }}</p>
            <p>Humidity: {{ weather.main.humidity || 'N/A' }}%</p>
          </div>
          <div v-else class="flex items-center text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
            <svg
              class="animate-spin h-5 w-5 mr-2"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              />
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
              />
            </svg>
            Loading weather...
          </div>
        </div>
      </section>

      <!-- Traffic Issues -->
      <section
        class="mb-6 w-full rounded-lg bg-white p-6 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"
      >
        <h2 class="mb-2 text-xl font-medium">Traffic Issues (10-mile radius)</h2>
        <div v-if="trafficError" class="mb-2 text-red-500 text-sm">
          {{ trafficError }}
        </div>
        <div v-if="traffic.length > 0" class="space-y-4">
          <div
            v-for="incident in traffic"
            :key="incident.properties.id"
            class="text-[13px] text-[#706f6c] dark:text-[#A1A09A]"
          >
            <p><strong>Type:</strong> {{ incident.type || 'N/A' }}</p>
            <p>
              <strong>Description:</strong>
              {{ incident.properties.events[0]?.description || 'No description' }}
            </p>
            <p>
              <strong>Delay:</strong>
              {{ incident.properties.delay || 'Unknown' }} seconds
            </p>
            <p>
              <strong>Start:</strong>
              {{
                incident.properties.startTime
                  ? new Date(incident.properties.startTime).toLocaleString()
                  : 'N/A'
              }}
            </p>
          </div>
        </div>
        <div v-else class="text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
          No traffic issues reported in your area.
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer
      class="mt-12 w-full bg-[#1b1b18] py-4 text-center text-sm text-white dark:bg-[#161615]"
    >
      <p>© {{ new Date().getFullYear() }} Your App. All rights reserved.</p>
    </footer>
  </div>
</template>
