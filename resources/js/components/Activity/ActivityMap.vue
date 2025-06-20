<template>
  <div v-if="mapboxToken" class="mb-6 h-[768px]">
    <div class="flex items-center mb-2">
      <label for="mapStyle" class="block text-sm font-medium text-gray-300 mr-2">Map Style</label>
      <select
        id="mapStyle"
        v-model="localMapStyle"
        class="rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-1"
        @change="$emit('style-changed', localMapStyle)"
      >
        <option v-for="style in mapStyles" :key="style.id" :value="style.id">{{ style.name }}</option>
      </select>
    </div>
    <div id="map" class="w-full h-full rounded-md"></div>
  </div>
  <div v-else class="text-yellow-400 mb-4">Mapbox token is missing</div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import mapboxgl from 'mapbox-gl';

const props = defineProps({
  activities: { type: Array, default: () => [] },
  activityTypes: { type: Array, default: () => [] },
  mapboxToken: { type: String, required: false },
  selectedMapStyle: { type: String, default: 'mapbox://styles/mapbox/dark-v11' },
});

const emit = defineEmits(['style-changed', 'marker-clicked']);

const localMapStyle = ref(props.selectedMapStyle);
const map = ref(null);
const markers = ref({});
const highlightedMarker = ref(null);

const mapStyles = [
  { id: 'mapbox://styles/mapbox/dark-v11', name: 'Dark' },
  { id: 'mapbox://styles/mapbox/light-v11', name: 'Light' },
  { id: 'mapbox://styles/mapbox/streets-v12', name: 'Streets' },
  { id: 'mapbox://styles/mapbox/satellite-streets-v12', name: 'Satellite' },
];

const getColor = (activityName) => {
  const type = props.activityTypes.find((t) => t.name === activityName);
  return type?.color || '#4f46e5';
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  const options = {
    timeZone: 'Europe/London',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  };
  return date.toLocaleString('en-GB', options);
};

const initMap = async () => {
  if (!props.mapboxToken) {
    console.error('Mapbox token missing');
    return;
  }

  await nextTick();

  const container = document.getElementById('map');
  if (!container) {
    console.error('Map container not found');
    return;
  }

  try {
    mapboxgl.accessToken = props.mapboxToken;
    map.value = new mapboxgl.Map({
      container: 'map',
      style: localMapStyle.value,
      center: [-2.488984, 53.073154], // Default to Home coordinates
      zoom: 10,
    });

    map.value.on('load', updateMapMarkers);
  } catch (err) {
    console.error('Map initialization error:', err);
  }
};

const updateMapMarkers = () => {
  if (!map.value) return;

  // Clear existing markers
  Object.values(markers.value).forEach((marker) => marker.remove());
  markers.value = {};

  if (props.activities.length > 0) {
    const bounds = new mapboxgl.LngLatBounds();
    props.activities.forEach((activity) => {
      if (!activity.latitude || !activity.longitude) {
        console.warn('Invalid coordinates for activity:', activity);
        return;
      }

      const el = document.createElement('div');
      el.className = 'marker';
      el.style.backgroundColor = getColor(activity.activity);
      el.style.width = '12px';
      el.style.height = '12px';
      el.style.borderRadius = '50%';
      el.style.border = '2px solid white';

      const popup = new mapboxgl.Popup({ offset: 25 }).setHTML(`
        <div class="text-gray-800 p-2 text-sm">
          <strong>${activity.activity}</strong><br>
          Time: ${formatDate(activity.datetime)}<br>
          Lat: ${activity.latitude}<br>
          Lng: ${activity.longitude}
        </div>
      `);

      const marker = new mapboxgl.Marker({ element: el })
        .setLngLat([parseFloat(activity.longitude), parseFloat(activity.latitude)])
        .setPopup(popup)
        .addTo(map.value);

      el.addEventListener('click', () => {
        clearHighlights();
        highlightedMarker.value = activity.id;
        el.style.backgroundColor = '#ff4500';
        el.style.width = '16px';
        el.style.height = '16px';
        emit('marker-clicked', activity.id);
      });

      markers.value[activity.id] = marker;
      bounds.extend([parseFloat(activity.longitude), parseFloat(activity.latitude)]);
    });

    if (!bounds.isEmpty()) {
      map.value.fitBounds(bounds, { padding: 50 });
    }
  }
};

const clearHighlights = () => {
  if (highlightedMarker.value) {
    const marker = markers.value[highlightedMarker.value];
    if (marker) {
      const el = marker.getElement();
      const activity = props.activities.find((act) => act.id === highlightedMarker.value);
      el.style.backgroundColor = activity ? getColor(activity.activity) : '#4f46e5';
      el.style.width = '12px';
      el.style.height = '12px';
    }
    highlightedMarker.value = null;
  }
};

watch(localMapStyle, (newStyle) => {
  if (map.value) {
    map.value.setStyle(newStyle);
    map.value.on('style.load', updateMapMarkers);
  }
});

watch(() => props.activities, () => {
  console.log('Activities updated for map:', props.activities.length);
  updateMapMarkers();
}, { deep: true });

onMounted(() => {
  initMap();
});
</script>

<style>
#map {
  height: 100%;
}
</style>