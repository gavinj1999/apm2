<template>
  <Head title="Activities" />
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-white leading-tight">
        Recorded Activities
      </h2>
    </template>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-gray-800 border-b border-gray-700">
            <!-- Error Display -->
            <div v-if="error" class="text-red-400 mb-4">{{ error }}</div>
            <!-- Create Activity Button -->
            <div class="mb-6">
              <button
                @click="openCreateModal"
                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500"
              >
                Add New Activity
              </button>
            </div>
            <!-- Calendar -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-300 mb-2">Select Date</label>
              <vue-cal
                :events="calendarEvents"
                :active-date="dateFilter"
                @event-click="selectDate"
                :style="{ height: '300px' }"
                class="bg-gray-700 rounded-md"
                :disable-views="['years', 'year', 'month']"
              />
            </div>
            <!-- Map -->
            <div v-if="mapboxToken" class="mb-6 h-96">
              <div id="map" class="w-full h-full rounded-md"></div>
            </div>
            <div v-else class="text-yellow-400 mb-4">Mapbox token is missing</div>
            <!-- Table -->
            <table class="min-w-full divide-y divide-gray-700">
              <thead class="bg-gray-900">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date & Time</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Latitude</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Longitude</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Activity</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-gray-800 divide-y divide-gray-700">
                <tr v-for="activity in localActivities" :key="activity.id">
                  <td class="px-6 py-4 whitespace-nowrap text-white">{{ formatDate(activity.datetime) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-white">{{ activity.latitude }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-white">{{ activity.longitude }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-white">{{ activity.activity }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <button @click="openEditModal(activity)" class="text-indigo-400 hover:text-indigo-300 mr-4">Edit</button>
                    <button @click="deleteActivity(activity.id)" class="text-red-400 hover:text-red-300">Delete</button>
                  </td>
                </tr>
                <tr v-if="!localActivities.length">
                  <td colspan="5" class="px-6 py-4 text-center text-gray-300">No activities found</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <!-- Edit Modal -->
    <div v-if="showEditModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-medium text-white mb-4">Edit Activity</h3>
        <form @submit.prevent="updateActivity">
          <div class="mb-4">
            <label for="editDatetime" class="block text-sm font-medium text-gray-300">Date & Time</label>
            <input
              type="datetime-local"
              id="editDatetime"
              v-model="editForm.datetime"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
          </div>
          <div class="mb-4">
            <label for="editLatitude" class="block text-sm font-medium text-gray-300">Latitude</label>
            <input
              type="number"
              id="editLatitude"
              v-model="editForm.latitude"
              step="any"
              min="-90"
              max="90"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
          </div>
          <div class="mb-4">
            <label for="editLongitude" class="block text-sm font-medium text-gray-300">Longitude</label>
            <input
              type="number"
              id="editLongitude"
              v-model="editForm.longitude"
              step="any"
              min="-180"
              max="180"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
          </div>
          <div class="mb-4">
            <label for="editActivity" class="block text-sm font-medium text-gray-300">Activity</label>
            <select
              id="editActivity"
              v-model="editForm.activity"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            >
              <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
            </select>
          </div>
          <div class="flex justify-end">
            <button
              type="button"
              @click="showEditModal = false"
              class="mr-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500"
            >
              Save
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- Create Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-lg">
        <h3 class="text-lg font-medium text-white mb-4">Add New Activity</h3>
        <form @submit.prevent="createActivity">
          <div class="mb-4">
            <label for="createDatetime" class="block text-sm font-medium text-gray-300">Date & Time</label>
            <input
              type="datetime-local"
              id="createDatetime"
              v-model="createForm.datetime"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
          </div>
          <!-- Map Search -->
          <div class="mb-4">
            <label for="mapSearch" class="block text-sm font-medium text-gray-300">Search Location</label>
            <input
              type="text"
              id="mapSearch"
              v-model="searchQuery"
              placeholder="Enter city or address"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            />
          </div>
          <!-- Map -->
          <div v-if="mapboxToken" class="mb-4 h-64">
            <div id="createMap" class="w-full h-full rounded-md"></div>
          </div>
          <div v-else class="text-yellow-400 mb-4">Mapbox token is missing</div>
          <div class="mb-4">
            <label for="createLatitude" class="block text-sm font-medium text-gray-300">Latitude</label>
            <input
              type="number"
              id="createLatitude"
              v-model="createForm.latitude"
              step="any"
              min="-90"
              max="90"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
          </div>
          <div class="mb-4">
            <label for="createLongitude" class="block text-sm font-medium text-gray-300">Longitude</label>
            <input
              type="number"
              id="createLongitude"
              v-model="createForm.longitude"
              step="any"
              min="-180"
              max="180"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
          </div>
          <div class="mb-4">
            <label for="createActivity" class="block text-sm font-medium text-gray-300">Activity</label>
            <select
              id="createActivity"
              v-model="createForm.activity"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            >
              <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
            </select>
          </div>
          <div class="flex justify-end">
            <button
              type="button"
              @click="showCreateModal = false"
              class="mr-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500"
            >
              Create
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import mapboxgl from 'mapbox-gl';
import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder';
import VueCal from 'vue-cal';
import 'vue-cal/dist/vuecal.css';
import AppLayout from '@/layouts/AppLayout.vue';

// Configure Axios to include credentials
axios.defaults.withCredentials = true;

const props = defineProps({
  activities: {
    type: Array,
    default: () => [],
  },
  mapboxToken: {
    type: String,
    required: false,
  },
});

const dateFilter = ref(new Date());
const localActivities = ref([]);
const calendarEvents = ref([]);
const showEditModal = ref(false);
const showCreateModal = ref(false);
const editForm = ref({
  id: null,
  datetime: '',
  latitude: '',
  longitude: '',
  activity: '',
});
const createForm = ref({
  datetime: '',
  latitude: '',
  longitude: '',
  activity: '',
});
const searchQuery = ref('');
const error = ref(null);
let map = null;
let createMap = null;
let createMarker = null;

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString();
};

const fetchCsrfToken = async () => {
  try {
    await axios.get('/sanctum/csrf-cookie');
  } catch (err) {
    error.value = 'Failed to fetch CSRF token';
    console.error(err);
  }
};

const fetchActivities = async () => {
  try {
    const response = await axios.get('/api/activities', {
      params: { date: dateFilter.value.toISOString().split('T')[0] },
    });
    localActivities.value = response.data.data;
  } catch (err) {
    if (err.response?.status === 401) {
      error.value = 'Unauthorized: Please log in to view activities';
    } else {
      error.value = `Error fetching activities: ${err.message}`;
    }
    console.error(err);
  }
};

const fetchActivityDates = async () => {
  try {
    const response = await axios.get('/api/activity-dates');
    calendarEvents.value = response.data.map(date => ({
      start: date,
      end: date,
      class: 'activity-date',
    }));
  } catch (err) {
    if (err.response?.status === 401) {
      error.value = 'Unauthorized: Please log in to view activity dates';
    } else {
      error.value = `Error fetching activity dates: ${err.message}`;
    }
    console.error(err);
  }
};

const selectDate = (event) => {
  dateFilter.value = new Date(event.start);
  fetchActivities();
};

const openEditModal = (activity) => {
  editForm.value = {
    id: activity.id,
    datetime: new Date(activity.datetime).toISOString().slice(0, 16),
    latitude: activity.latitude,
    longitude: activity.longitude,
    activity: activity.activity,
  };
  showEditModal.value = true;
};

const updateActivity = async () => {
  try {
    const response = await axios.put(`/api/activity/${editForm.value.id}`, editForm.value);
    localActivities.value = localActivities.value.map((act) =>
      act.id === response.data.data.id ? response.data.data : act
    );
    showEditModal.value = false;
  } catch (err) {
    if (err.response?.status === 401) {
      error.value = 'Unauthorized: Please log in to update activities';
    } else {
      error.value = `Error updating activity: ${err.message}`;
    }
    console.error(err);
  }
};

const openCreateModal = () => {
  createForm.value = {
    datetime: new Date().toISOString().slice(0, 16),
    latitude: '',
    longitude: '',
    activity: '',
  };
  searchQuery.value = '';
  showCreateModal.value = true;
};

const createActivity = async () => {
  try {
    const response = await axios.post('/api/activities', createForm.value);
    localActivities.value.push(response.data.data);
    showCreateModal.value = false;
    fetchActivityDates(); // Refresh calendar
  } catch (err) {
    if (err.response?.status === 401) {
      error.value = 'Unauthorized: Please log in to create activities';
    } else {
      error.value = `Error creating activity: ${err.message}`;
    }
    console.error(err);
  }
};

const deleteActivity = async (id) => {
  if (!confirm('Are you sure you want to delete this activity?')) return;
  try {
    await axios.delete(`/api/activity/${id}`);
    localActivities.value = localActivities.value.filter((act) => act.id !== id);
    fetchActivityDates(); // Refresh calendar
  } catch (err) {
    if (err.response?.status === 401) {
      error.value = 'Unauthorized: Please log in to delete activities';
    } else {
      error.value = `Error deleting activity: ${err.message}`;
    }
    console.error(err);
  }
};

const actions = ref(['Left Home', 'Arrive Depot', 'Start Loading', 'Leave Depot', 'First Drop', 'Last Drop', 'Arrive Home']);

const initMap = () => {
  if (!props.mapboxToken) {
    error.value = 'Mapbox token is missing';
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    map = new mapboxgl.Map({
      container: 'map',
      style: 'mapbox://styles/mapbox/dark-v11',
      center: [-0.1278, 51.5074], // Default to London
      zoom: 10,
    });

    map.on('load', () => {
      updateMapMarkers();
    });
  } catch (err) {
    error.value = `Error initializing map: ${err.message}`;
    console.error(err);
  }
};

const initCreateMap = () => {
  if (!props.mapboxToken) {
    error.value = 'Mapbox token is missing';
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    createMap = new mapboxgl.Map({
      container: 'createMap',
      style: 'mapbox://styles/mapbox/dark-v11',
      center: [-0.1278, 51.5074], // Default to London
      zoom: 10,
    });

    createMap.on('load', () => {
      const geocoder = new MapboxGeocoder({
        accessToken: props.mapboxToken,
        mapboxgl: mapboxgl,
      });

      createMap.addControl(geocoder);

      geocoder.on('result', (e) => {
        const [lng, lat] = e.result.center;
        createForm.value.latitude = lat;
        createForm.value.longitude = lng;
        updateCreateMarker(lng, lat);
      });

      createMap.on('click', (e) => {
        createForm.value.latitude = e.lngLat.lat;
        createForm.value.longitude = e.lngLat.lng;
        updateCreateMarker(e.lngLat.lng, e.lngLat.lat);
      });
    });
  } catch (err) {
    error.value = `Error initializing create map: ${err.message}`;
    console.error(err);
  }
};

const updateCreateMarker = (lng, lat) => {
  if (createMarker) createMarker.remove();
  const el = document.createElement('div');
  el.className = 'marker';
  el.style.backgroundColor = '#4f46e5';
  el.style.width = '12px';
  el.style.height = '12px';
  el.style.borderRadius = '50%';
  el.style.border = '2px solid white';

  createMarker = new mapboxgl.Marker(el)
    .setLngLat([lng, lat])
    .addTo(createMap);
};

const updateMapMarkers = () => {
  if (!map) return;

  const existingMarkers = document.querySelectorAll('.mapboxgl-marker');
  existingMarkers.forEach((marker) => marker.remove());

  if (localActivities.value.length > 0) {
    const bounds = new mapboxgl.LngLatBounds();
    localActivities.value.forEach((activity) => {
      const el = document.createElement('div');
      el.className = 'marker';
      el.style.backgroundColor = '#4f46e5';
      el.style.width = '12px';
      el.style.height = '12px';
      el.style.borderRadius = '50%';
      el.style.border = '2px solid white';

      const popup = new mapboxgl.Popup({ offset: 25 }).setHTML(`
        <div class="text-gray-800">
          <strong>${activity.activity}</strong><br>
          Time: ${formatDate(activity.datetime)}<br>
          Lat: ${activity.latitude}<br>
          Lng: ${activity.longitude}
        </div>
      `);

      new mapboxgl.Marker(el)
        .setLngLat([activity.longitude, activity.latitude])
        .setPopup(popup)
        .addTo(map);

      bounds.extend([activity.longitude, activity.latitude]);
    });

    map.fitBounds(bounds, { padding: 50 });
  }
};

onMounted(async () => {
  console.log('Props received:', props);
  try {
    await fetchCsrfToken(); // Fetch CSRF token first
    localActivities.value = props.activities || [];
    fetchActivities();
    fetchActivityDates();
    initMap();
  } catch (err) {
    error.value = `Error initializing component: ${err.message}`;
    console.error(err);
  }
});

watch(localActivities, () => {
  updateMapMarkers();
});

watch(showCreateModal, (newVal) => {
  if (newVal) {
    setTimeout(() => initCreateMap(), 0); // Ensure DOM is ready
  }
});

watch(dateFilter, () => {
  fetchActivities();
});
</script>

<style>
#map, #createMap { height: 100%; }
.vuecal__event.activity-date { background-color: #4f46e5; border-radius: 50%; height: 8px; width: 8px; }
</style>