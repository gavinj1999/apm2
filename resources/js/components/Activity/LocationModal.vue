<template>
  <div class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50">
    <div class="bg-gray-800 rounded-lg p-6 w-full max-w-4xl">
      <h3 class="text-lg font-medium text-white mb-4">Manage Preset Locations</h3>
      <!-- Add Location Form -->
      <div class="mb-6">
        <h4 class="text-md font-semibold text-white mb-2">Add New Location</h4>
        <div class="flex space-x-4 mb-4">
          <input
            v-model="newLocation.name"
            placeholder="Location Name (e.g., Home, Depot)"
            class="border rounded p-2 border-gray-600 bg-gray-700 text-white flex-1"
          />
          <input
            v-model="newLocation.latitude"
            type="number"
            step="any"
            placeholder="Latitude"
            class="border rounded p-2 border-gray-600 bg-gray-700 text-white w-32"
          />
          <input
            v-model="newLocation.longitude"
            type="number"
            step="any"
            placeholder="Longitude"
            class="border rounded p-2 border-gray-600 bg-gray-700 text-white w-32"
          />
          <button
            @click="addLocation"
            :disabled="isAddingLocation"
            class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-500 flex items-center"
            :class="{ 'opacity-50 cursor-not-allowed': isAddingLocation }"
          >
            <span v-if="isAddingLocation" class="inline-block w-4 h-4 mr-2 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            {{ isAddingLocation ? 'Adding...' : 'Add' }}
          </button>
        </div>
        <div v-if="locationError" class="text-red-400 text-sm mb-2">{{ locationError }}</div>
        <!-- Map Search with Suggestions -->
        <div class="mb-4 relative">
          <label for="locationMapSearch" class="block text-sm font-medium text-gray-300">Search Location</label>
          <input
            type="text"
            id="locationMapSearch"
            v-model="locationSearchQuery"
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
        <div v-if="mapboxToken" class="mb-4 h-64">
          <div id="locationMap" class="w-full h-full rounded-md"></div>
        </div>
        <div v-else class="text-yellow-400 mb-4">Mapbox token is missing</div>
      </div>
      <!-- Locations Table -->
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-gray-900">
            <th class="border border-gray-600 p-2 text-left text-xs font-medium text-gray-300 uppercase">Name</th>
            <th class="border border-gray-600 p-2 text-left text-xs font-medium text-gray-300 uppercase">Latitude</th>
            <th class="border border-gray-600 p-2 text-left text-xs font-medium text-gray-300 uppercase">Longitude</th>
            <th class="border border-gray-600 p-2 text-left text-xs font-medium text-gray-300 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="location in locations" :key="location.id" class="hover:bg-gray-700">
            <td class="border border-gray-600 p-2">
              <input
                v-if="editingLocationId === location.id"
                v-model="editLocationForm.name"
                class="border rounded p-1 w-full bg-gray-700 text-white"
              />
              <span v-else class="text-white text-sm">{{ location.name }}</span>
            </td>
            <td class="border border-gray-600 p-2">
              <input
                v-if="editingLocationId === location.id"
                v-model="editLocationForm.latitude"
                type="number"
                step="any"
                class="border rounded p-1 w-full bg-gray-700 text-white"
              />
              <span v-else class="text-white text-sm">{{ location.latitude }}</span>
            </td>
            <td class="border border-gray-600 p-2">
              <input
                v-if="editingLocationId === location.id"
                v-model="editLocationForm.longitude"
                type="number"
                step="any"
                class="border rounded p-1 w-full bg-gray-700 text-white"
              />
              <span v-else class="text-white text-sm">{{ location.longitude }}</span>
            </td>
            <td class="border border-gray-600 p-2">
              <button
                v-if="editingLocationId === location.id"
                @click="updateLocation"
                :disabled="isUpdatingLocation"
                class="bg-indigo-600 text-white px-2 py-1 rounded hover:bg-indigo-500 flex items-center text-sm"
                :class="{ 'opacity-50 cursor-not-allowed': isUpdatingLocation }"
              >
                <span v-if="isUpdatingLocation" class="inline-block w-3 h-3 mr-1 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                {{ isUpdatingLocation ? 'Saving...' : 'Save' }}
              </button>
              <button
                v-if="editingLocationId === location.id"
                @click="cancelEditLocation"
                class="bg-gray-600 text-white px-2 py-1 rounded hover:bg-gray-500 ml-2 text-sm"
              >
                Cancel
              </button>
              <button
                v-else
                @click="startEditLocation(location)"
                class="bg-indigo-600 text-white px-2 py-1 rounded hover:bg-indigo-500 text-sm"
              >
                Edit
              </button>
              <button
                @click="$emit('delete-location', location)"
                :disabled="isDeletingLocation"
                class="bg-red-600 text-white px-2 py-1 rounded hover:bg-red-500 ml-2 flex items-center text-sm"
                :class="{ 'opacity-50 cursor-not-allowed': isDeletingLocation }"
              >
                <span v-if="isDeletingLocation" class="inline-block w-3 h-3 mr-1 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                {{ isDeletingLocation ? 'Deleting...' : 'Delete' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="flex justify-end mt-4">
        <button
          @click="$emit('close')"
          class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500 text-sm"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import mapboxgl from 'mapbox-gl';
import axios from 'axios';
import { debounce } from 'lodash';

const props = defineProps({
  locations: Array,
  mapboxToken: String,
});

const emit = defineEmits(['add-location', 'update-location', 'delete-location', 'close']);

const newLocation = ref({
  name: '',
  latitude: '',
  longitude: '',
});
const editingLocationId = ref(null);
const editLocationForm = ref({
  name: '',
  latitude: '',
  longitude: '',
});
const locationSearchQuery = ref('');
const addressSuggestions = ref([]);
const searchError = ref('');
const locationError = ref('');
const isAddingLocation = ref(false);
const isUpdatingLocation = ref(false);
const isDeletingLocation = ref(false);
let locationMap = null;
let locationMarker = null;

const addLocation = () => {
  emit('add-location', newLocation.value);
  newLocation.value = { name: '', latitude: '', longitude: '' };
  locationSearchQuery.value = '';
  addressSuggestions.value = [];
  searchError.value = '';
};

const startEditLocation = (location) => {
  editingLocationId.value = location.id;
  editLocationForm.value = {
    name: location.name,
    latitude: location.latitude,
    longitude: location.longitude,
  };
  locationSearchQuery.value = '';
  addressSuggestions.value = [];
  searchError.value = '';
  locationError.value = '';
  nextTick(() => initLocationMap());
  if (location.latitude && location.longitude) {
    updateLocationMarker(location.longitude, location.latitude);
  }
};

const updateLocation = () => {
  emit('update-location', { id: editingLocationId.value, data: editLocationForm.value });
  editingLocationId.value = null;
  editLocationForm.value = { name: '', latitude: '', longitude: '' };
  locationSearchQuery.value = '';
  addressSuggestions.value = [];
  searchError.value = '';
};

const cancelEditLocation = () => {
  editingLocationId.value = null;
  editLocationForm.value = { name: '', latitude: '', longitude: '' };
  locationSearchQuery.value = '';
  addressSuggestions.value = [];
  searchError.value = '';
  locationError.value = '';
};

const debounceSearch = debounce(async () => {
  if (!props.mapboxToken) {
    searchError.value = 'Mapbox token is missing';
    addressSuggestions.value = [];
    return;
  }

  if (!locationSearchQuery.value.trim()) {
    addressSuggestions.value = [];
    searchError.value = '';
    return;
  }

  try {
    console.log('Searching for:', locationSearchQuery.value);
    const response = await axios.get('https://api.mapbox.com/geocoding/v5/mapbox.places/' + encodeURIComponent(locationSearchQuery.value) + '.json', {
      params: {
        access_token: props.mapboxToken,
        proximity: '-2.490,53.074',
        country: 'GB',
        autocomplete: true,
        limit: 5,
      },
    });
    console.log('API response:', response.data);
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
  if (editingLocationId.value) {
    editLocationForm.value.latitude = lat;
    editLocationForm.value.longitude = lng;
  } else {
    newLocation.value.latitude = lat;
    newLocation.value.longitude = lng;
  }
  updateLocationMarker(lng, lat);
  addressSuggestions.value = [];
  locationSearchQuery.value = suggestion.place_name;
  searchError.value = '';
};

const initLocationMap = () => {
  if (!props.mapboxToken) {
    searchError.value = 'Mapbox token is missing';
    return;
  }

  const container = document.getElementById('locationMap');
  if (!container) {
    console.warn('Location map container not found');
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    locationMap = new mapboxgl.Map({
      container: 'locationMap',
      style: 'mapbox://styles/mapbox/dark-v11',
      center: [-2.490, 53.074],
      zoom: 10,
    });

    locationMap.on('load', () => {
      locationMap.on('click', (e) => {
        if (editingLocationId.value) {
          editLocationForm.value.latitude = e.lngLat.lat;
          editLocationForm.value.longitude = e.lngLat.lng;
        } else {
          newLocation.value.latitude = e.lngLat.lat;
          newLocation.value.longitude = e.lngLat.lng;
        }
        updateLocationMarker(e.lngLat.lng, e.lngLat.lat);
        addressSuggestions.value = [];
        searchError.value = '';
      });
    });
  } catch (err) {
    searchError.value = `Map initialization failed: ${err.message}`;
    console.error(err);
  }
};

const updateLocationMarker = (lng, lat) => {
  if (locationMarker) locationMarker.remove();
  const el = document.createElement('div');
  el.className = 'marker';
  el.style.backgroundColor = '#4f46e5';
  el.style.width = '12px';
  el.style.height = '12px';
  el.style.borderRadius = '50%';
  el.style.border = '2px solid white';

  locationMarker = new mapboxgl.Marker(el).setLngLat([lng, lat]).addTo(locationMap);
  locationMap.flyTo({ center: [lng, lat], zoom: 14 });
};

onMounted(() => {
  nextTick(() => initLocationMap());
});
</script>

<style>
#locationMap {
  height: 100%;
}
</style>