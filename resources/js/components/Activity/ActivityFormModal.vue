<template>
  <div class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
    <div class="bg-gray-800 rounded-lg p-6 w-full" :class="{ 'max-w-md': mode === 'edit', 'max-w-3xl': mode !== 'edit' }">
      <h3 class="text-lg font-medium text-white mb-4">
        {{ mode === 'edit' ? 'Edit Activity' : mode === 'manual' ? `Add ${getAlias(form.activity)} for ${formatDate(date, 'date')}` : 'Add New Activity' }}
      </h3>
      <form @submit.prevent="submitForm">
        <div class="mb-4">
          <label for="datetime" class="block text-sm font-medium text-gray-300">Date & Time</label>
          <input
            type="datetime-local"
            id="datetime"
            v-model="form.datetime"
            class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            required
          />
          <div v-if="errors.datetime" class="text-red-400 text-sm mt-1">{{ errors.datetime }}</div>
        </div>
        <div v-if="!isFixedLocationActivity(form.activity)">
          <!-- Map Search -->
          <div class="mb-4 relative">
            <label for="mapSearch" class="block text-sm font-medium text-gray-300">Search Location</label>
            <input
              type="text"
              id="mapSearch"
              v-model="searchQuery"
              @input="debounceSearch"
              placeholder="Enter city or address"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            />
            <div v-if="searchError" class="text-red-400 text-sm mt-1">{{ searchError }}</div>
            <ul
              v-if="addressSuggestions.length"
              class="absolute z-10 w-full bg-gray-700 border border-gray-600 rounded-md mt-1 max-h-48 overflow-y-auto"
            >
              <li
                v-for="suggestion in addressSuggestions"
                :key="suggestion.id"
                @click="selectAddress(suggestion)"
                class="px-4 py-2 text-white text-sm hover:bg-gray-600 cursor-pointer"
              >
                {{ suggestion.place_name }}
              </li>
            </ul>
          </div>
          <!-- Map -->
          <div v-if="mapboxToken" class="mb-4 h-96">
            <div :id="mapId" class="w-full h-full rounded-md"></div>
          </div>
          <div v-else class="text-yellow-400 mb-4">Mapbox token is missing</div>
          <div class="mb-4">
            <label for="latitude" class="block text-sm font-medium text-gray-300">Latitude</label>
            <input
              type="number"
              id="latitude"
              v-model="form.latitude"
              step="any"
              min="-90"
              max="90"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="errors.latitude" class="text-red-400 text-sm mt-1">{{ errors.latitude }}</div>
          </div>
          <div class="mb-4">
            <label for="longitude" class="block text-sm font-medium text-gray-300">Longitude</label>
            <input
              type="number"
              id="longitude"
              v-model="form.longitude"
              step="any"
              min="-180"
              max="180"
              class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              required
            />
            <div v-if="errors.longitude" class="text-red-400 text-sm mt-1">{{ errors.longitude }}</div>
          </div>
        </div>
        <div v-else class="mb-4">
          <p class="text-gray-300 text-sm">Location will be set to {{ getFixedLocationName(form.activity) }}</p>
          <div v-if="locationError" class="text-red-400 text-sm mt-1">{{ locationError }}</div>
        </div>
        <div class="mb-4">
          <label for="activity" class="block text-sm font-medium text-gray-300">Activity</label>
          <select
            id="activity"
            v-model="form.activity"
            class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            required
            :disabled="mode === 'manual'"
          >
            <option v-for="type in activityTypes" :key="type.name" :value="type.name">{{ type.name }}</option>
          </select>
          <div v-if="errors.activity" class="text-red-400 text-sm mt-1">{{ errors.activity }}</div>
        </div>
        <div class="flex justify-end">
          <button
            type="button"
            @click="$emit('close')"
            class="mr-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500 text-sm"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500 flex items-center text-sm"
            :class="{ 'opacity-50 cursor-not-allowed': isSubmitting }"
          >
            <span v-if="isSubmitting" class="inline-block w-4 h-4 mr-2 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            {{ isSubmitting ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import mapboxgl from 'mapbox-gl';
import MapboxGeocoder from '@mapbox/mapbox-gl-geocoder';
import axios from 'axios';
import { debounce } from 'lodash';

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => ['create', 'edit', 'manual'].includes(value),
  },
  activity: Object,
  activityTypes: Array,
  locations: Array,
  mapboxToken: String,
  date: String,
});

const emit = defineEmits(['submit', 'close']);

const form = ref({
  id: null,
  datetime: '',
  latitude: '',
  longitude: '',
  activity: '',
  is_manual: props.mode === 'manual',
});
const searchQuery = ref('');
const addressSuggestions = ref([]);
const searchError = ref('');
const locationError = ref('');
const isSubmitting = ref(false);
const errors = ref({});
let map = null;
let marker = null;

const mapId = computed(() => props.mode === 'create' ? 'createMap' : 'manualMap');

const getAlias = (activityName) => {
  const type = props.activityTypes.find((t) => t.name === activityName);
  return type ? type.alias : activityName;
};

const isFixedLocationActivity = (activityName) => {
  return ['Left Home', 'Arrive Depot', 'Start Loading', 'Leave Depot', 'Arrive Home'].includes(activityName);
};

const getFixedLocationName = (activityName) => {
  if (['Left Home', 'Arrive Home'].includes(activityName)) return 'Home';
  if (['Arrive Depot', 'Start Loading', 'Leave Depot'].includes(activityName)) return 'Depot';
  return '';
};

const formatDate = (dateString, type) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  const options = {
    timeZone: 'Europe/London',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  };
  if (type === 'date') {
    return date.toLocaleDateString('en-GB', options);
  }
  return date.toLocaleString('en-GB', options);
};

onMounted(() => {
  if (props.activity) {
    form.value = {
      id: props.activity.id || null,
      datetime: props.activity.datetime ? new Date(props.activity.datetime).toISOString().slice(0, 16) : (props.date ? `${props.date}T07:00` : new Date().toISOString().slice(0, 16)),
      latitude: props.activity.latitude || '',
      longitude: props.activity.longitude || '',
      activity: props.activity.activity || '',
      is_manual: props.mode === 'manual' || props.activity.is_manual || false,
    };
  } else {
    form.value.datetime = new Date().toISOString().slice(0, 16);
  }
  if (!isFixedLocationActivity(form.value.activity)) {
    nextTick(() => initMap());
  }
});

const debounceSearch = debounce(async () => {
  if (!props.mapboxToken) {
    searchError.value = 'Mapbox token is missing';
    addressSuggestions.value = [];
    return;
  }

  if (!searchQuery.value.trim()) {
    addressSuggestions.value = [];
    searchError.value = '';
    return;
  }

  try {
    console.log('Searching for:', searchQuery.value);
    const response = await axios.get('https://api.mapbox.com/geocoding/v5/mapbox.places/' + encodeURIComponent(searchQuery.value) + '.json', {
      params: {
        access_token: props.mapboxToken,
        proximity: '-2.490,53.074',
        country: 'GB',
        autocomplete: true,
        limit: 5,
      },
    });
    addressSuggestions.value = response.data.features;
    searchError.value = '';
  } catch (error) {
    console.error('Error fetching address suggestions:', error);
    if (error.response) {
      searchError.value = `Search failed: ${error.response.status} ${error.response.statusText}`;
    } else {
      searchError.value = 'Search failed: Unable to connect to Mapbox API';
    }
    addressSuggestions.value = [];
  }
}, 300);

const selectAddress = (suggestion) => {
  const [lng, lat] = suggestion.center;
  form.value.latitude = lat;
  form.value.longitude = lng;
  updateMarker(lng, lat);
  addressSuggestions.value = [];
  searchQuery.value = suggestion.place_name;
  searchError.value = '';
};

const initMap = () => {
  if (!props.mapboxToken) {
    searchError.value = 'Mapbox token is missing';
    return;
  }

  const container = document.getElementById(mapId.value);
  if (!container) {
    console.warn(`${mapId.value} container not found`);
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    map = new mapboxgl.Map({
      container: mapId.value,
      style: 'mapbox://styles/mapbox/dark-v11',
      center: [-2.490, 53.074],
      zoom: 10,
    });

    map.on('load', () => {
      const geocoder = new MapboxGeocoder({
        accessToken: props.mapboxToken,
        mapboxgl: mapboxgl,
      });

      map.addControl(geocoder);

      geocoder.on('result', (e) => {
        const [lng, lat] = e.result.center;
        form.value.latitude = lat;
        form.value.longitude = lng;
        updateMarker(lng, lat);
      });

      map.on('click', (e) => {
        form.value.latitude = e.lngLat.lat;
        form.value.longitude = e.lngLat.lng;
        updateMarker(e.lngLat.lng, e.lngLat.lat);
      });
    });
  } catch (err) {
    searchError.value = `Map initialization failed: ${err.message}`;
    console.error(err);
  }
};

const updateMarker = (lng, lat) => {
  if (marker) marker.remove();
  const el = document.createElement('div');
  el.className = 'marker';
  el.style.backgroundColor = '#4f46e5';
  el.style.width = '12px';
  el.style.height = '12px';
  el.style.borderRadius = '50%';
  el.style.border = '2px solid white';

  marker = new mapboxgl.Marker(el).setLngLat([lng, lat]).addTo(map);
};

const submitForm = () => {
  isSubmitting.value = true;
  errors.value = {};
  emit('submit', { mode: props.mode, data: form.value });
  isSubmitting.value = false;
};

watch(() => form.activity, () => {
  if (!isFixedLocationActivity(form.activity)) {
    nextTick(() => initMap());
  }
});
</script>

<style>
#createMap, #manualMap {
  height: 100%;
}
</style>