<!-- resources/js/Pages/ServiceProfile/Index.vue -->
<template>
    <AppLayout>
      <div class="p-6 bg-gray-900 text-gray-100 min-h-screen">
        <!-- Page Title -->
        <h1 class="text-3xl font-bold mb-8 tracking-tight">Service Profile</h1>

        <!-- Flash Messages -->
        <div v-if="$page.props.flash.success" class="bg-green-600 text-white p-4 mb-6 rounded-lg shadow-lg transition-all duration-300">
          {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash.error" class="bg-red-600 text-white p-4 mb-6 rounded-lg shadow-lg transition-all duration-300">
          {{ $page.props.flash.error }}
        </div>

        <!-- Service Profile Form -->
        <div class="bg-gray-800 rounded-lg shadow-md p-4">
          <form @submit.prevent="submitForm">
            <!-- Form Inputs in 2x4 Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <!-- Row 1 -->
              <div class="mb-4">
                <label for="round_id" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Associated Round</label>
                <select
                  v-model="form.round_id"
                  id="round_id"
                  class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                  required
                >
                  <option v-for="(name, id) in rounds" :key="id" :value="id">{{ name }}</option>
                </select>
              </div>

              <div class="mb-4">
                <label for="fuel_cost_per_unit" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Fuel Cost Per Unit (£)</label>
                <input
                  type="number"
                  step="0.01"
                  v-model="form.fuel_cost_per_unit"
                  id="fuel_cost_per_unit"
                  class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                  required
                />
              </div>

              <div class="mb-4">
                <label for="distance_unit" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance Unit</label>
                <select
                  v-model="form.distance_unit"
                  id="distance_unit"
                  class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                >
                  <option value="mile">Mile</option>
                  <option value="km">Kilometer</option>
                </select>
              </div>

              <div class="mb-4">
                <label for="distance_home_to_work" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance Home to Work (miles)</label>
                <input
                  type="number"
                  step="0.01"
                  v-model="form.distance_home_to_work"
                  id="distance_home_to_work"
                  class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                  required
                />
              </div>

              <!-- Row 2 -->
              <div class="mb-4">
                <label for="distance_work_to_start" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance Work to Start Location (miles)</label>
                <input
                  type="number"
                  step="0.01"
                  v-model="form.distance_work_to_start"
                  id="distance_work_to_start"
                  class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                  required
                />
              </div>

              <div class="mb-4">
                <label for="distance_end_to_home" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance End Location to Home (miles)</label>
                <input
                  type="number"
                  step="0.01"
                  v-model="form.distance_end_to_home"
                  id="distance_end_to_home"
                  class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                  required
                />
              </div>

              <div class="mb-4">
                <label for="loading_time_cost_per_hour" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Loading Time Cost Per Hour (£)</label>
                <input
                  type="number"
                  step="0.01"
                  v-model="form.loading_time_cost_per_hour"
                  id="loading_time_cost_per_hour"
                  class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                  required
                />
              </div>

              <div class="mb-4">
                <label for="loading_time_hours" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Loading Time (Hours)</label>
                <input
                  type="number"
                  step="0.01"
                  v-model="form.loading_time_hours"
                  id="loading_time_hours"
                  class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                  required
                />
              </div>
            </div>

            <!-- Map Section -->
            <div class="mb-4">
              <label class="block mb-2 font-medium text-gray-300">Set Locations on Map</label>
              <div ref="map" class="w-full h-96 bg-gray-700 rounded-lg"></div>
              <p class="mt-2 text-sm text-gray-400">Click the map to set Home, Work, Start, and End locations in that order. Distances will be calculated in miles.</p>
              <div class="flex items-center space-x-2">
                <button
                  type="button"
                  @click="clearPins"
                  class="mt-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500 transition-all duration-200"
                  :disabled="isClearing"
                >
                  {{ isClearing ? 'Clearing...' : clearFeedback || 'Clear Pins' }}
                </button>
                <span v-if="moveFeedback" class="mt-2 text-sm text-green-400">{{ moveFeedback }}</span>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-between">
              <div v-if="isEditing">
                <button
                  type="submit"
                  class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200"
                >
                  Update Profile
                </button>
                <button
                  type="button"
                  @click="deleteProfile"
                  class="ml-4 px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200"
                >
                  Delete Profile
                </button>
              </div>
              <div v-else>
                <button
                  type="submit"
                  class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200"
                >
                  Save Profile
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  </template>

  <script setup>
  import { reactive, ref, onMounted, onUnmounted } from 'vue';
  import { router } from '@inertiajs/vue3';
  import mapboxgl from 'mapbox-gl';
  import AppLayout from '@/Layouts/AppLayout.vue';
  import axios from 'axios';

  const props = defineProps({
    profile: Object,
    rounds: Object,
    flash: Object,
    initialLocations: Array,
    mapboxAccessToken: String,
    isEditing: {
      type: Boolean,
      default: false,
    },
  });

  const form = reactive({
    id: props.profile?.id || null,
    round_id: props.profile?.round_id || null,
    fuel_cost_per_unit: props.profile?.fuel_cost_per_unit || 0,
    distance_unit: props.profile?.distance_unit || 'mile',
    distance_home_to_work: props.profile?.distance_home_to_work || 0,
    distance_work_to_start: props.profile?.distance_work_to_start || 0,
    distance_end_to_home: props.profile?.distance_end_to_home || 0,
    loading_time_cost_per_hour: props.profile?.loading_time_cost_per_hour || 0,
    loading_time_hours: props.profile?.loading_time_hours || 0,
  });

  const map = ref(null);
  const mapInstance = ref(null);
  const locations = ref(props.initialLocations || []);
  const markers = ref([]); // Store map markers for removal
  const isClearing = ref(false); // Track clearing state
  const clearFeedback = ref(''); // Display feedback after clearing
  const moveFeedback = ref(''); // Display feedback after moving a pin

  // Set Mapbox access token from props
  mapboxgl.accessToken = props.mapboxAccessToken;

  // Marker colors for each location
  const markerColors = {
    Home: '#22C55E', // Green
    Work: '#3B82F6', // Blue
    'Start Location': '#F59E0B', // Orange
    'End Location': '#EF4444', // Red
  };

  // Location names in order
  const locationNames = ['Home', 'Work', 'Start Location', 'End Location'];

  // Haversine formula for distance calculation (in miles)
  const haversineDistance = (coord1, coord2) => {
    const toRadians = (degrees) => (degrees * Math.PI) / 180;
    const R = 3958.8; // Earth's radius in miles
    const dLat = toRadians(coord2.latitude - coord1.latitude);
    const dLng = toRadians(coord2.longitude - coord1.longitude);
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(toRadians(coord1.latitude)) * Math.cos(toRadians(coord2.latitude)) *
      Math.sin(dLng / 2) * Math.sin(dLng / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c; // Distance in miles
  };

  // Fetch CSRF token
  const fetchCsrfToken = async () => {
    try {
      await axios.get('/sanctum/csrf-cookie');
      console.log('CSRF token fetched for API request');
      return true;
    } catch (error) {
      console.error('Error fetching CSRF token:', error);
      return false;
    }
  };

  // Create a custom marker element with the location name
  const createCustomMarkerElement = (name, color) => {
    const el = document.createElement('div');
    el.className = 'custom-marker';
    el.style.backgroundColor = color;
    el.style.color = 'white';
    el.style.padding = '4px 8px';
    el.style.borderRadius = '4px';
    el.style.fontSize = '12px';
    el.style.fontWeight = 'bold';
    el.style.textAlign = 'center';
    el.style.whiteSpace = 'nowrap';
    el.innerText = name;
    return el;
  };

  // Recalculate distances based on current pin positions
  const recalculateDistances = () => {
    const home = locations.value.find((loc) => loc.name === 'Home');
    const work = locations.value.find((loc) => loc.name === 'Work');
    const start = locations.value.find((loc) => loc.name === 'Start Location');
    const end = locations.value.find((loc) => loc.name === 'End Location');

    // Calculate distance_home_to_work
    form.distance_home_to_work = (home && work) ? haversineDistance(home, work).toFixed(2) : 0;

    // Calculate distance_work_to_start
    form.distance_work_to_start = (work && start) ? haversineDistance(work, start).toFixed(2) : 0;

    // Calculate distance_end_to_home
    form.distance_end_to_home = (end && home) ? haversineDistance(end, home).toFixed(2) : 0;
  };

  // Update location position in the backend
  const updateLocationPosition = async (locationId, latitude, longitude) => {
    try {
      // Ensure CSRF token is fresh
      if (!(await fetchCsrfToken())) {
        alert('Failed to initialize request. Please refresh the page.');
        return false;
      }

      const response = await axios.put(`/api/locations/${locationId}`, {
        latitude,
        longitude,
      });

      // Update the local locations array
      const locationIndex = locations.value.findIndex(loc => loc.id === locationId);
      if (locationIndex !== -1) {
        locations.value[locationIndex].latitude = latitude;
        locations.value[locationIndex].longitude = longitude;
      }

      // Recalculate distances after every move
      recalculateDistances();

      moveFeedback.value = `${locations.value[locationIndex].name} moved!`;
      setTimeout(() => { moveFeedback.value = ''; }, 2000);
      return true;
    } catch (error) {
      console.error('Error updating location position:', error);
      if (error.response?.status === 401) {
        alert('Session expired. Please log in again.');
        router.visit('/login');
      } else {
        alert('Failed to update location position: ' + (error.response?.data?.error || error.message || 'Unknown error'));
      }
      return false;
    }
  };

  // Initialize Mapbox
  onMounted(async () => {
    if (!props.mapboxAccessToken) {
      console.error('Mapbox access token is missing');
      alert('Map configuration error. Please contact support.');
      return;
    }

    // Fetch CSRF token on mount
    await fetchCsrfToken();

    mapInstance.value = new mapboxgl.Map({
      container: map.value,
      style: 'mapbox://styles/mapbox/dark-v10', // Dark theme
      center: [-0.1278, 51.5074], // Default: London
      zoom: 9,
    });

    // Load existing pins with custom markers
    locations.value.forEach((loc) => {
      const markerElement = createCustomMarkerElement(loc.name, markerColors[loc.name]);
      const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
        .setLngLat([loc.longitude, loc.latitude])
        .addTo(mapInstance.value);

      // Handle drag end to update position
      marker.on('dragend', async () => {
        const { lng, lat } = marker.getLngLat();
        await updateLocationPosition(loc.id, lat, lng);
      });

      markers.value.push(marker);
    });

    // Handle pin dropping (limit to 4 pins)
    mapInstance.value.on('click', async (e) => {
      if (locations.value.length >= 4) {
        alert('Only four locations can be set. Clear pins to add new ones.');
        return;
      }

      const { lng, lat } = e.lngLat;
      const name = locationNames[locations.value.length];

      // Save to backend
      try {
        // Ensure CSRF token is fresh
        if (!(await fetchCsrfToken())) {
          alert('Failed to initialize request. Please refresh the page.');
          return;
        }

        const response = await axios.post('/api/locations', {
          name,
          latitude: lat,
          longitude: lng,
        });
        const newLocation = response.data;

        locations.value.push(newLocation);

        // Add custom marker with the location name
        const markerElement = createCustomMarkerElement(name, markerColors[name]);
        const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
          .setLngLat([lng, lat])
          .addTo(mapInstance.value);

        // Handle drag end to update position
        marker.on('dragend', async () => {
          const { lng, lat } = marker.getLngLat();
          await updateLocationPosition(newLocation.id, lat, lng);
        });

        markers.value.push(marker);

        // Recalculate distances after adding a new pin
        recalculateDistances();
      } catch (error) {
        console.error('Error saving location:', error);
        if (error.response?.status === 401) {
          alert('Session expired. Please log in again.');
          router.visit('/login');
        } else if (error.response?.status === 404) {
          alert('Service profile not found. Please save your profile first.');
        } else if (error.response?.status === 422) {
          alert('No rounds available. Please create a round first.');
        } else if (error.response?.status === 500) {
          alert('Failed to save location: ' + (error.response?.data?.error || 'Server error. Please ensure a round is created and try again, or contact support.'));
        } else {
          alert('Failed to save location: ' + (error.response?.data?.error || error.message || 'Unknown error'));
        }
      }
    });
  });

  // Cleanup map on unmount
  onUnmounted(() => {
    if (mapInstance.value) mapInstance.value.remove();
  });

  // Clear pins with feedback
  const clearPins = async () => {
    if (locations.value.length === 0) {
      clearFeedback.value = 'No pins to clear';
      setTimeout(() => { clearFeedback.value = ''; }, 2000);
      return;
    }

    try {
      isClearing.value = true;
      clearFeedback.value = '';

      // Ensure CSRF token is fresh
      if (!(await fetchCsrfToken())) {
        alert('Failed to initialize request. Please refresh the page.');
        return;
      }

      // Attempt to delete all locations, even if some fail
      const deletePromises = locations.value.map(async (loc) => {
        try {
          await axios.delete(`/api/locations/${loc.id}`);
          return { id: loc.id, success: true };
        } catch (error) {
          console.error(`Failed to delete location ${loc.id}:`, error);
          return { id: loc.id, success: false, error: error.message };
        }
      });

      const results = await Promise.all(deletePromises);

      // Check for any failures
      const failedDeletions = results.filter(result => !result.success);
      if (failedDeletions.length > 0) {
        const errorMessages = failedDeletions.map(result => `Location ${result.id}: ${result.error}`).join('; ');
        throw new Error(`Some locations could not be deleted: ${errorMessages}`);
      }

      // Remove all markers from the map
      markers.value.forEach(marker => marker.remove());
      markers.value = [];

      // Clear locations and reset form fields
      locations.value = [];
      form.distance_home_to_work = 0;
      form.distance_work_to_start = 0;
      form.distance_end_to_home = 0;

      // Show success feedback
      clearFeedback.value = 'Pins cleared!';
      setTimeout(() => { clearFeedback.value = ''; }, 2000);
    } catch (error) {
      console.error('Error clearing pins:', error);
      if (error.response?.status === 401) {
        alert('Session expired. Please log in again.');
        router.visit('/login');
      } else {
        alert('Failed to clear pins: ' + (error.message || 'Unknown error'));
      }
    } finally {
      isClearing.value = false;
    }
  };

  // Submit form
  const submitForm = () => {
    const route = props.isEditing ? 'service-profile.update' : 'service-profile.store';
    const method = props.isEditing ? 'put' : 'post';
    const url = props.isEditing ? `/service-profile/${form.id}` : '/service-profile';

    router[method](url, form, {
      onSuccess: () => {
        // Form submission successful, handled by flash message
      },
      onError: (errors) => {
        console.error('Form submission errors:', errors);
        alert('Failed to save profile: ' + Object.values(errors).join(', '));
      },
    });
  };

  // Delete profile
  const deleteProfile = () => {
    if (confirm('Are you sure you want to delete this profile?')) {
      router.delete(`/service-profile/${form.id}`, {
        onSuccess: () => {
          // Deletion successful, handled by flash message
        },
        onError: (errors) => {
          console.error('Deletion errors:', errors);
          alert('Failed to delete profile: ' + Object.values(errors).join(', '));
        },
      });
    }
  };
  </script>

  <style scoped>
  /* Ensure map container is styled correctly */
  .map-container {
    position: relative;
    width: 100%;
    height: 400px;
  }

  /* Optional: Adjust custom marker positioning if needed */
  .custom-marker {
    transform: translate(-50%, -50%); /* Center the marker on the coordinates */
    cursor: move; /* Indicate that the marker is draggable */
  }
  </style>
