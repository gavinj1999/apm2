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
            <!-- Flash Message -->
            <div v-if="$page.props.flash.success" class="text-green-400 mb-4 text-sm">{{ $page.props.flash.success }}</div>
            <!-- Error Display -->
            <div v-if="$page.props.errors.global" class="text-red-400 mb-4">{{ $page.props.errors.global }}</div>
            <!-- Create Activity Button -->
            <div class="mb-6">
              <button
                @click="openCreateModal"
                class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500"
              >
                Add New Activity
              </button>
            </div>
            <!-- Map -->
            <div v-if="mapboxToken" class="mb-6 h-96">
              <div id="map" class="w-full h-full rounded-md"></div>
            </div>
            <div v-else class="text-yellow-400 mb-4">Mapbox token is missing</div>
            <!-- Calendar -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-300 mb-2">Activities Calendar</label>
              <vue-cal
                :events="calendarEvents"
                :active-date="dateFilter"
                @event-drop="handleEventDrop"
                @event-click="handleEventClick"
                :time-from="7 * 60"
                :time-to="21 * 60 + 30"
                :time-step="30"
                :style="{ height: '300px' }"
                class="bg-gray-700 rounded-md"
                default-view="week"
                :week-starts-on="1"
                hide-view-selector
                :disable-views="['years', 'year', 'month', 'day']"
                editable-events
                :scroll-to="{ hour: 7 }"
              />
            </div>
            <!-- Table -->
            <table class="min-w-full divide-y divide-gray-700">
              <thead class="bg-gray-900">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Activity</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Time</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Latitude</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Longitude</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-gray-800 divide-y divide-gray-700">
                <template v-for="(group, date) in groupedActivities" :key="date">
                  <tr class="bg-gray-900">
                    <td colspan="5" class="px-6 py-2 text-sm font-medium text-gray-300">{{ formatDate(date, 'date') }}</td>
                  </tr>
                  <tr v-for="activity in group" :key="activity.id || activity.activity">
                    <td class="px-6 py-4 whitespace-nowrap text-white text-sm" :class="{ 'italic': activity.is_manual }">
                      {{ activity.activity }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-white text-sm" :class="{ 'italic': activity.is_manual }">
                      {{ activity.datetime ? formatDate(activity.datetime, 'time') : '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-white text-sm" :class="{ 'italic': activity.is_manual }">
                      {{ activity.latitude || '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-white text-sm" :class="{ 'italic': activity.is_manual }">
                      {{ activity.longitude || '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <button
                        v-if="activity.id"
                        @click="openEditModal(activity)"
                        class="text-indigo-400 hover:text-indigo-300 mr-4 text-sm"
                      >
                        Edit
                      </button>
                      <button
                        v-if="activity.id"
                        @click="deleteActivity(activity.id)"
                        class="text-red-400 hover:text-red-300 text-sm"
                      >
                        Delete
                      </button>
                      <button
                        v-if="!activity.id"
                        @click="openManualModal(activity.activity, date)"
                        class="text-green-400 hover:text-green-300 text-sm"
                      >
                        Add
                      </button>
                    </td>
                  </tr>
                </template>
                <tr v-if="!Object.keys(groupedActivities).length">
                  <td colspan="5" class="px-6 py-4 text-center text-gray-300 text-sm">No activities found</td>
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
        <form @submit.prevent="editForm.put(`/activity/${editForm.id}`)">
          <div class="mb-4">
            <label for="editDatetime" class="block text-sm font-medium text-gray-300">Date & Time</label>
            <input
              type="datetime-local"
              id="editDatetime"
              v-model="editForm.datetime"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.datetime" class="text-red-400 text-sm mt-1">{{ $page.props.errors.datetime }}</div>
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
            <div v-if="$page.props.errors.latitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.latitude }}</div>
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
            <div v-if="$page.props.errors.longitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.longitude }}</div>
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
            <div v-if="$page.props.errors.activity" class="text-red-400 text-sm mt-1">{{ $page.props.errors.activity }}</div>
          </div>
          <div class="flex justify-end">
            <button
              type="button"
              @click="showEditModal = false"
              class="mr-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500 text-sm"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500 text-sm"
            >
              Save
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- Create Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-3xl">
        <h3 class="text-lg font-medium text-white mb-4">Add New Activity</h3>
        <form @submit.prevent="createForm.post('/activities')">
          <div class="mb-4">
            <label for="createDatetime" class="block text-sm font-medium text-gray-300">Date & Time</label>
            <input
              type="datetime-local"
              id="createDatetime"
              v-model="createForm.datetime"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.datetime" class="text-red-400 text-sm mt-1">{{ $page.props.errors.datetime }}</div>
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
          <div v-if="mapboxToken" class="mb-4 h-96">
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
            <div v-if="$page.props.errors.latitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.latitude }}</div>
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
            <div v-if="$page.props.errors.longitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.longitude }}</div>
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
            <div v-if="$page.props.errors.activity" class="text-red-400 text-sm mt-1">{{ $page.props.errors.activity }}</div>
          </div>
          <div class="flex justify-end">
            <button
              type="button"
              @click="showCreateModal = false"
              class="mr-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500 text-sm"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500 text-sm"
            >
              Create
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- Manual Entry Modal -->
    <div v-if="showManualModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-gray-800 rounded-lg p-6 w-full max-w-3xl">
        <h3 class="text-lg font-medium text-white mb-4">Add {{ manualForm.activity }} for {{ formatDate(manualDate, 'date') }}</h3>
        <form @submit.prevent="manualForm.post('/activities')">
          <div class="mb-4">
            <label for="manualDatetime" class="block text-sm font-medium text-gray-300">Date & Time</label>
            <input
              type="datetime-local"
              id="manualDatetime"
              v-model="manualForm.datetime"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.datetime" class="text-red-400 text-sm mt-1">{{ $page.props.errors.datetime }}</div>
          </div>
          <!-- Map Search -->
          <div class="mb-4">
            <label for="manualMapSearch" class="block text-sm font-medium text-gray-300">Search Location</label>
            <input
              type="text"
              id="manualMapSearch"
              v-model="manualSearchQuery"
              placeholder="Enter city or address"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            />
          </div>
          <!-- Map -->
          <div v-if="mapboxToken" class="mb-4 h-96">
            <div id="manualMap" class="w-full h-full rounded-md"></div>
          </div>
          <div v-else class="text-yellow-400 mb-4">Mapbox token is missing</div>
          <div class="mb-4">
            <label for="manualLatitude" class="block text-sm font-medium text-gray-300">Latitude</label>
            <input
              type="number"
              id="manualLatitude"
              v-model="manualForm.latitude"
              step="any"
              min="-90"
              max="90"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.latitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.latitude }}</div>
          </div>
          <div class="mb-4">
            <label for="manualLongitude" class="block text-sm font-medium text-gray-300">Longitude</label>
            <input
              type="number"
              id="manualLongitude"
              v-model="manualForm.longitude"
              step="any"
              min="-180"
              max="180"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="$page.props.errors.longitude" class="text-red-400 text-sm mt-1">{{ $page.props.errors.longitude }}</div>
          </div>
          <div class="flex justify-end">
            <button
              type="button"
              @click="showManualModal = false"
              class="mr-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500 text-sm"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500 text-sm"
            >
              Save
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import mapboxgl from 'mapbox-gl';
import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder';
import VueCal from 'vue-cal';
import 'vue-cal/dist/vuecal.css';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
  activities: {
    type: Array,
    default: () => [],
  },
  activityDates: {
    type: Array,
    default: () => [],
  },
  mapboxToken: {
    type: String,
    required: false,
  },
  errors: Object,
  flash: Object,
});

const dateFilter = ref(new Date());
const localActivities = ref(props.activities);
const showEditModal = ref(false);
const showCreateModal = ref(false);
const showManualModal = ref(false);
const searchQuery = ref('');
const manualSearchQuery = ref('');
const highlightedEvent = ref(null);
const highlightedMarker = ref(null);
const markers = ref({});
const manualDate = ref('');
let map = null;
let createMap = null;
let createMarker = null;
let manualMap = null;
let manualMarker = null;

const activityOrder = [
  'Left Home',
  'Arrive Depot',
  'Start Loading',
  'Leave Depot',
  'First Drop',
  'Last Drop',
  'Arrive Home',
];

const createForm = useForm({
  datetime: '',
  latitude: '',
  longitude: '',
  activity: '',
});

const editForm = useForm({
  id: null,
  datetime: '',
  latitude: '',
  longitude: '',
  activity: '',
  is_manual: false,
});

const manualForm = useForm({
  datetime: '',
  latitude: '',
  longitude: '',
  activity: '',
  is_manual: true,
});

const groupedActivities = computed(() => {
  const groups = {};
  const activities = [...props.activities].sort((a, b) => {
    const dateA = new Date(a.datetime).toISOString().split('T')[0];
    const dateB = new Date(b.datetime).toISOString().split('T')[0];
    if (dateA !== dateB) return dateA.localeCompare(dateB);
    return activityOrder.indexOf(a.activity) - activityOrder.indexOf(b.activity);
  });

  activities.forEach((activity) => {
    const date = new Date(activity.datetime).toISOString().split('T')[0];
    if (!groups[date]) groups[date] = [];
    groups[date].push(activity);
  });

  Object.keys(groups).forEach((date) => {
    const existingActivities = groups[date].map((act) => act.activity);
    activityOrder.forEach((activity) => {
      if (!existingActivities.includes(activity)) {
        groups[date].push({
          activity,
          date,
          is_manual: false,
        });
      }
    });
    groups[date].sort((a, b) => activityOrder.indexOf(a.activity) - activityOrder.indexOf(b.activity));
  });

  return groups;
});

const calendarEvents = ref(props.activities.map(activity => ({
  id: activity.id,
  start: new Date(activity.datetime),
  end: new Date(new Date(activity.datetime).getTime() + 30 * 60000),
  title: activity.activity,
  class: 'activity-event',
})));

const formatDate = (dateString, type) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  if (type === 'date') {
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
  } else if (type === 'time') {
    return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });
  }
  return date.toLocaleString();
};

const handleEventDrop = ({ event }) => {
  const activity = localActivities.value.find(act => act.id === event.id);
  if (!activity) return;

  const newDatetime = event.start.toISOString();
  router.patch(`/activity/${activity.id}`, { datetime: newDatetime }, {
    onSuccess: () => {
      localActivities.value = props.activities;
      updateCalendarEvents();
    },
    onError: (errors) => {
      props.errors.global = errors.datetime || 'Failed to update activity time';
    },
  });
};

const handleEventClick = ({ event }) => {
  clearHighlights();
  highlightedEvent.value = event.id;
  const marker = markers.value[event.id];
  if (marker) {
    highlightedMarker.value = event.id;
    const el = marker.getElement();
    el.style.backgroundColor = '#ff4500';
    el.style.width = '16px';
    el.style.height = '16px';
    map.flyTo({ center: marker.getLngLat(), zoom: 12 });
  }
};

const clearHighlights = () => {
  if (highlightedEvent.value) {
    highlightedEvent.value = null;
  }
  if (highlightedMarker.value) {
    const marker = markers.value[highlightedMarker.value];
    if (marker) {
      const el = marker.getElement();
      el.style.backgroundColor = '#4f46e5';
      el.style.width = '12px';
      el.style.height = '12px';
    }
    highlightedMarker.value = null;
  }
};

const updateCalendarEvents = () => {
  calendarEvents.value = props.activities.map(activity => ({
    id: activity.id,
    start: new Date(activity.datetime),
    end: new Date(new Date(activity.datetime).getTime() + 30 * 60000),
    title: activity.activity,
    class: 'activity-event',
  }));
};

const openEditModal = (activity) => {
  editForm.setData({
    id: activity.id,
    datetime: new Date(activity.datetime).toISOString().slice(0, 16),
    latitude: activity.latitude,
    longitude: activity.longitude,
    activity: activity.activity,
    is_manual: activity.is_manual,
  });
  showEditModal.value = true;
};

const openCreateModal = () => {
  createForm.reset();
  createForm.datetime = new Date().toISOString().slice(0, 16);
  searchQuery.value = '';
  showCreateModal.value = true;
};

const openManualModal = (activity, date) => {
  manualForm.reset();
  manualForm.activity = activity;
  manualForm.datetime = `${date}T07:00`;
  manualForm.is_manual = true;
  manualSearchQuery.value = '';
  manualDate.value = date;
  showManualModal.value = true;
};

const deleteActivity = (id) => {
  if (!confirm('Are you sure you want to delete this activity?')) return;
  router.delete(`/activity/${id}`, {
    onSuccess: () => {
      localActivities.value = props.activities;
      router.get('/activities', {}, { preserveState: true });
    },
  });
};

const actions = ref(activityOrder);

const initMap = () => {
  if (!props.mapboxToken) {
    props.errors.global = 'Mapbox token is missing';
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    map = new mapboxgl.Map({
      container: 'map',
      style: 'mapbox://styles/mapbox/dark-v11',
      center: [-0.1278, 51.5074],
      zoom: 10,
    });

    map.on('load', () => {
      updateMapMarkers();
    });
  } catch (err) {
    props.errors.global = `Error initializing map: ${err.message}`;
    console.error(err);
  }
};

const updateMapMarkers = () => {
  if (!map) return;

  Object.values(markers.value).forEach(marker => marker.remove());
  markers.value = {};

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
          Time: ${formatDate(activity.datetime, 'time')}<br>
          Lat: ${activity.latitude}<br>
          Lng: ${activity.longitude}
        </div>
      `);

      const marker = new mapboxgl.Marker({ element: el, draggable: true })
        .setLngLat([activity.longitude, activity.latitude])
        .setPopup(popup)
        .addTo(map);

      marker.on('dragend', () => {
        const lngLat = marker.getLngLat();
        router.patch(`/activity/${activity.id}`, {
          latitude: lngLat.lat,
          longitude: lngLat.lng,
        }, {
          onSuccess: () => {
            localActivities.value = props.activities;
            updateCalendarEvents();
          },
          onError: (errors) => {
            props.errors.global = errors.latitude || errors.longitude || 'Failed to update activity location';
          },
        });
      });

      el.addEventListener('click', () => {
        clearHighlights();
        highlightedMarker.value = activity.id;
        el.style.backgroundColor = '#ff4500';
        el.style.width = '16px';
        el.style.height = '16px';
        highlightedEvent.value = activity.id;
      });

      markers.value[activity.id] = marker;
      bounds.extend([activity.longitude, activity.latitude]);
    });

    map.fitBounds(bounds, { padding: 50 });
  }
};

const initCreateMap = () => {
  if (!props.mapboxToken) {
    props.errors.global = 'Mapbox token is missing';
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    createMap = new mapboxgl.Map({
      container: 'createMap',
      style: 'mapbox://styles/mapbox/dark-v11',
      center: [-0.1278, 51.5074],
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
        createForm.latitude = lat;
        createForm.longitude = lng;
        updateCreateMarker(lng, lat);
      });

      createMap.on('click', (e) => {
        createForm.latitude = e.lngLat.lat;
        createForm.longitude = e.lngLat.lng;
        updateCreateMarker(e.lngLat.lng, e.lngLat.lat);
      });
    });
  } catch (err) {
    props.errors.global = `Error initializing create map: ${err.message}`;
    console.error(err);
  }
};

const initManualMap = () => {
  if (!props.mapboxToken) {
    props.errors.global = 'Mapbox token is missing';
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    manualMap = new mapboxgl.Map({
      container: 'manualMap',
      style: 'mapbox://styles/mapbox/dark-v11',
      center: [-0.1278, 51.5074],
      zoom: 10,
    });

    manualMap.on('load', () => {
      const geocoder = new MapboxGeocoder({
        accessToken: props.mapboxToken,
        mapboxgl: mapboxgl,
      });

      manualMap.addControl(geocoder);

      geocoder.on('result', (e) => {
        const [lng, lat] = e.result.center;
        manualForm.latitude = lat;
        manualForm.longitude = lng;
        updateManualMarker(lng, lat);
      });

      manualMap.on('click', (e) => {
        manualForm.latitude = e.lngLat.lat;
        manualForm.longitude = e.lngLat.lng;
        updateManualMarker(e.lngLat.lng, e.lngLat.lat);
      });
    });
  } catch (err) {
    props.errors.global = `Error initializing manual map: ${err.message}`;
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

const updateManualMarker = (lng, lat) => {
  if (manualMarker) manualMarker.remove();
  const el = document.createElement('div');
  el.className = 'marker';
  el.style.backgroundColor = '#4f46e5';
  el.style.width = '12px';
  el.style.height = '12px';
  el.style.borderRadius = '50%';
  el.style.border = '2px solid white';

  manualMarker = new mapboxgl.Marker(el)
    .setLngLat([lng, lat])
    .addTo(manualMap);
};

onMounted(() => {
  console.log('Props received:', props);
  try {
    localActivities.value = props.activities || [];
    initMap();
    updateCalendarEvents();
  } catch (err) {
    props.errors.global = `Error initializing component: ${err.message}`;
    console.error(err);
  }
});

watch(localActivities, () => {
  updateMapMarkers();
  updateCalendarEvents();
});

watch(showCreateModal, (newVal) => {
  if (newVal) {
    setTimeout(() => initCreateMap(), 0);
  }
});

watch(showManualModal, (newVal) => {
  if (newVal) {
    setTimeout(() => initManualMap(), 0);
  }
});

watch(dateFilter, () => {
  router.get(
    '/activities',
    { date: dateFilter.value.toISOString().split('T')[0] },
    {
      preserveState: true,
      onSuccess: () => {
        localActivities.value = props.activities;
        updateCalendarEvents();
      },
    }
  );
});
</script>

<style>
#map, #createMap, #manualMap { height: 100%; }
.vuecal__event.activity-event { background-color: #4f46e5; color: white; font-size: 0.75rem; padding: 2px; }
.vuecal__event.activity-event.highlighted { background-color: #ff4500; }
.vuecal__cell--selected { background-color: rgba(79, 70, 229, 0.2); }
.vuecal__time-cell { font-size: 0.75rem; }
.vuecal__cell-content { font-size: 0.75rem; }
</style>