<!-- resources/js/Pages/ServiceProfile/Index.vue -->
<template>
    <AppLayout  :breadcrumbs="Reports">
        <div class="p-6 bg-gray-900 text-gray-100 min-h-screen">
            <!-- Page Title -->
            <h1 class="text-3xl font-bold mb-8 tracking-tight">Service Profiles</h1>

            <!-- Flash Messages -->
            <div v-if="$page.props.flash.success"
                class="bg-green-600 text-white p-4 mb-6 rounded-lg shadow-lg transition-all duration-300">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash.error"
                class="bg-red-600 text-white p-4 mb-6 rounded-lg shadow-lg transition-all duration-300">
                {{ $page.props.flash.error }}
            </div>

            <!-- Error Message for Missing Mapbox Token -->
            <div v-if="$page.props.error"
                class="bg-red-600 text-white p-4 mb-6 rounded-lg shadow-lg transition-all duration-300">
                {{ $page.props.error }}
            </div>

            <!-- Round Selection -->
            <div class="mb-6">
                <label for="active_round" class="block mb-2 font-medium text-gray-300">Select Active Round</label>
                <select v-model="activeRound" id="active_round"
                    class="w-full max-w-xs p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                    @change="loadProfilesForRound">
                    <option v-for="(name, id) in rounds" :key="id" :value="id">{{ name }}</option>
                </select>
            </div>

            <!-- Service Profiles List -->
            <div v-if="filteredProfiles.length" class="mb-6">
                <h2 class="text-2xl font-semibold mb-4">Service Profiles for Selected Round</h2>
                <div class="bg-gray-800 rounded-lg shadow-md p-4">
                    <ul class="space-y-2">
                        <li v-for="profile in filteredProfiles" :key="profile.id"
                            class="flex justify-between items-center p-2 bg-gray-700 rounded-lg">
                            <span>Profile #{{ profile.id }} (Fuel Cost: £{{ profile.fuel_cost_per_unit }})</span>
                            <button @click="editProfile(profile.id)"
                                class="px-4 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                Edit
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
            <div v-else-if="activeRound" class="mb-6 text-gray-400">
                No service profiles found for this round. Create a new one below.
            </div>

            <!-- Service Profile Form -->
            <div v-if="!$page.props.error" class="bg-gray-800 rounded-lg shadow-md p-4">
                <form @submit.prevent="submitForm">
                    <!-- Form Inputs in 2x4 Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Row 1 -->
                        <div class="mb-4">
                            <label for="round_id"
                                class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Associated
                                Round</label>
                            <select v-model="form.round_id" id="round_id"
                                class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                required :disabled="isEditing">
                                <option v-for="(name, id) in rounds" :key="id" :value="id">{{ name }}</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="fuel_cost_per_unit"
                                class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Fuel
                                Cost Per Unit (£)</label>
                            <input type="number" step="0.01" v-model="form.fuel_cost_per_unit" id="fuel_cost_per_unit"
                                class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                required />
                        </div>

                        <div class="mb-4">
                            <label for="distance_unit"
                                class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance
                                Unit</label>
                            <select v-model="form.distance_unit" id="distance_unit"
                                class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                                <option value="mile">Mile</option>
                                <option value="km">Kilometer</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="distance_home_to_work"
                                class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance
                                Home to Work (miles)</label>
                            <input type="number" step="0.01" v-model="form.distance_home_to_work"
                                id="distance_home_to_work"
                                class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                required />
                        </div>

                        <!-- Row 2 -->
                        <div class="mb-4">
                            <label for="distance_work_to_start"
                                class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance
                                Work to Start Location (miles)</label>
                            <input type="number" step="0.01" v-model="form.distance_work_to_start"
                                id="distance_work_to_start"
                                class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                required />
                        </div>

                        <div class="mb-4">
                            <label for="distance_end_to_home"
                                class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance
                                End Location to Home (miles)</label>
                            <input type="number" step="0.01" v-model="form.distance_end_to_home"
                                id="distance_end_to_home"
                                class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                required />
                        </div>

                        <div class="mb-4">
                            <label for="loading_time_cost_per_hour"
                                class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Loading
                                Time Cost Per Hour (£)</label>
                            <input type="number" step="0.01" v-model="form.loading_time_cost_per_hour"
                                id="loading_time_cost_per_hour"
                                class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                required />
                        </div>

                        <div class="mb-4">
                            <label for="loading_time_hours"
                                class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Loading
                                Time (Hours)</label>
                            <input type="number" step="0.01" v-model="form.loading_time_hours" id="loading_time_hours"
                                class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                required />
                        </div>
                    </div>

                    <!-- Work Location Selection -->
                    <div class="mb-4">
                        <label for="work_location" class="block mb-2 font-medium text-gray-300">Select Work Location
                            (Depot)</label>
                        <select v-model="selectedWorkLocation" id="work_location"
                            class="w-full max-w-md p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                            @change="setWorkLocation">
                            <option value="">Select a Work Location</option>
                            <option v-for="location in workLocations" :key="location.id" :value="location.id">
                                {{ location.name }} ({{ location.address }})
                            </option>
                        </select>
                    </div>

                    <!-- Map Section -->
                    <div class="mb-4">
                        <label class="block mb-2 font-medium text-gray-300">Set Locations on Map</label>
                        <div v-if="isRecalculating"
                            class="bg-yellow-600 text-white p-2 rounded-lg mb-2 text-center transition-opacity duration-300">
                            Recalculating Distances...
                        </div>
                        <div ref="map" class="w-full h-96 bg-gray-700 rounded-lg"></div>
                        <p class="mt-2 text-sm text-gray-400">
                            Click the map to set Home, Start, and End locations in that order. Work location is set via
                            the dropdown above.
                            <span v-if="!form.id" class="text-yellow-400">Note: Locations will be saved after you save
                                the profile.</span>
                        </p>
                        <div class="mt-2">
                            <button type="button" @click="clearPins"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500 transition-all duration-200"
                                :disabled="isClearing">
                                {{ isClearing ? 'Clearing...' : clearFeedback || 'Clear Pins' }}
                            </button>
                            <div v-if="moveFeedback"
                                class="mt-2 w-full bg-green-600 text-white p-2 rounded-lg text-center text-lg font-semibold transition-opacity duration-300">
                                {{ moveFeedback }}
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-between">
                        <div v-if="isEditing">
                            <button type="submit"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200">
                                Update Profile
                            </button>
                            <button type="button" @click="deleteProfile"
                                class="ml-4 px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200">
                                Delete Profile
                            </button>
                        </div>
                        <div v-else>
                            <button type="submit"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200">
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
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    serviceProfiles: Array,
    profile: Object,
    rounds: Object,
    flash: Object,
    initialLocations: Array,
    mapboxAccessToken: String,
    isEditing: {
        type: Boolean,
        default: false,
    },
    error: String,
});

// Log props to debug
console.log('Props received in ServiceProfile/Index.vue:', {
    mapboxAccessToken: props.mapboxAccessToken,
    error: props.error,
    allProps: props,
});

const form = reactive({
    id: props.profile?.id || null,
    round_id: props.profile?.round_id || Object.keys(props.rounds)[0] || null,
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
const tempLocations = ref([]); // Temporary storage for locations before profile is saved
const tempMarkers = ref([]); // Temporary storage for markers before profile is saved
const markers = ref([]); // Store map markers for removal
const isClearing = ref(false); // Track clearing state
const clearFeedback = ref(''); // Display feedback after clearing
const moveFeedback = ref(''); // Display feedback after moving a pin
const isRecalculating = ref(false); // Track recalculation state
const activeRound = ref(Object.keys(props.rounds)[0] || null); // Default to first round
const filteredProfiles = ref([]); // Filtered profiles for the active round
const workLocations = ref([]); // List of work locations
const selectedWorkLocation = ref(null); // Selected work location ID

// Set Mapbox access token from props
mapboxgl.accessToken = props.mapboxAccessToken;

// Marker colors for each location
const markerColors = {
    Home: '#22C55E', // Green
    Work: '#3B82F6', // Blue
    'Start Location': '#F59E0B', // Orange
    'End Location': '#EF4444', // Red
};

// Location names in order (excluding Work since it's set via dropdown)
const locationNames = ['Home', 'Start Location', 'End Location'];

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

// Fetch work locations
const fetchWorkLocations = async () => {
    try {
        const response = await axios.get('/api/work-locations');
        workLocations.value = response.data;
    } catch (error) {
        console.error('Error fetching work locations:', error);
        alert('Failed to fetch work locations: ' + (error.response?.data?.error || error.message || 'Unknown error'));
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
    isRecalculating.value = true;
    const home = locations.value.find((loc) => loc.name === 'Home') || tempLocations.value.find((loc) => loc.name === 'Home');
    const work = locations.value.find((loc) => loc.name === 'Work') || tempLocations.value.find((loc) => loc.name === 'Work');
    const start = locations.value.find((loc) => loc.name === 'Start Location') || tempLocations.value.find((loc) => loc.name === 'Start Location');
    const end = locations.value.find((loc) => loc.name === 'End Location') || tempLocations.value.find((loc) => loc.name === 'End Location');

    // Calculate distance_home_to_work
    form.distance_home_to_work = (home && work) ? haversineDistance(home, work).toFixed(2) : 0;

    // Calculate distance_work_to_start
    form.distance_work_to_start = (work && start) ? haversineDistance(work, start).toFixed(2) : 0;

    // Calculate distance_end_to_home
    form.distance_end_to_home = (end && home) ? haversineDistance(end, home).toFixed(2) : 0;

    setTimeout(() => { isRecalculating.value = false; }, 500); // Simulate a short delay for visibility
};

// Set Work location based on dropdown selection
const setWorkLocation = async () => {
    if (!selectedWorkLocation.value) return;

    const workLocation = workLocations.value.find(loc => loc.id === selectedWorkLocation.value);
    if (!workLocation) return;

    try {
        // Ensure CSRF token is fresh
        if (!(await fetchCsrfToken())) {
            alert('Failed to initialize request. Please refresh the page.');
            return;
        }

        // Remove existing Work pin if it exists
        const existingWork = locations.value.find(loc => loc.name === 'Work');
        if (existingWork) {
            await axios.delete(`/api/locations/${existingWork.id}`, {
                params: { service_profile_id: form.id },
            });
            const markerIndex = markers.value.findIndex(marker => marker.getElement().innerText === 'Work');
            if (markerIndex !== -1) {
                markers.value[markerIndex].remove();
                markers.value.splice(markerIndex, 1);
            }
            locations.value = locations.value.filter(loc => loc.name !== 'Work');
        } else {
            const tempWorkIndex = tempLocations.value.findIndex(loc => loc.name === 'Work');
            if (tempWorkIndex !== -1) {
                tempMarkers.value[tempWorkIndex].remove();
                tempMarkers.value.splice(tempWorkIndex, 1);
                tempLocations.value.splice(tempWorkIndex, 1);
            }
        }

        if (form.id) {
            // Add new Work pin to backend if profile exists
            const response = await axios.post('/api/locations', {
                name: 'Work',
                latitude: workLocation.latitude,
                longitude: workLocation.longitude,
                service_profile_id: form.id,
            });
            const newLocation = response.data;

            locations.value.push(newLocation);

            const markerElement = createCustomMarkerElement('Work', markerColors['Work']);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
                .setLngLat([workLocation.longitude, workLocation.latitude])
                .addTo(mapInstance.value);

            marker.on('dragend', async () => {
                const { lng, lat } = marker.getLngLat();
                await updateLocationPosition(newLocation.id, lat, lng);
            });

            markers.value.push(marker);
        } else {
            // Add to temporary storage if profile doesn't exist yet
            const tempLocation = {
                name: 'Work',
                latitude: workLocation.latitude,
                longitude: workLocation.longitude,
            };
            tempLocations.value.push(tempLocation);

            const markerElement = createCustomMarkerElement('Work', markerColors['Work']);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
                .setLngLat([workLocation.longitude, workLocation.latitude])
                .addTo(mapInstance.value);

            marker.on('dragend', () => {
                const { lng, lat } = marker.getLngLat();
                const tempIndex = tempLocations.value.findIndex(loc => loc.name === 'Work');
                if (tempIndex !== -1) {
                    tempLocations.value[tempIndex].latitude = lat;
                    tempLocations.value[tempIndex].longitude = lng;
                }
                recalculateDistances();
            });

            tempMarkers.value.push(marker);
        }

        recalculateDistances();
    } catch (error) {
        console.error('Error setting Work location:', error);
        alert('Failed to set Work location: ' + (error.response?.data?.error || error.message || 'Unknown error'));
    }
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
        }, {
            params: { service_profile_id: form.id },
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

// Save temporary locations after profile creation
const saveTempLocations = async (profileId) => {
    try {
        for (const tempLocation of tempLocations.value) {
            const response = await axios.post('/api/locations', {
                name: tempLocation.name,
                latitude: tempLocation.latitude,
                longitude: tempLocation.longitude,
                service_profile_id: profileId,
            });
            const newLocation = response.data;
            locations.value.push(newLocation);

            // Replace temporary marker with permanent one
            const tempIndex = tempLocations.value.indexOf(tempLocation);
            const tempMarker = tempMarkers.value[tempIndex];
            tempMarker.remove();

            const markerElement = createCustomMarkerElement(tempLocation.name, markerColors[tempLocation.name]);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
                .setLngLat([tempLocation.longitude, tempLocation.latitude])
                .addTo(mapInstance.value);

            marker.on('dragend', async () => {
                const { lng, lat } = marker.getLngLat();
                await updateLocationPosition(newLocation.id, lat, lng);
            });

            markers.value.push(marker);
        }

        // Clear temporary storage
        tempLocations.value = [];
        tempMarkers.value = [];
    } catch (error) {
        console.error('Error saving temporary locations:', error);
        alert('Failed to save temporary locations: ' + (error.response?.data?.error || error.message || 'Unknown error'));
    }
};

// Load profiles for the selected round
const loadProfilesForRound = () => {
    filteredProfiles.value = props.serviceProfiles.filter(profile => profile.round_id === activeRound.value);
    if (filteredProfiles.value.length > 0) {
        editProfile(filteredProfiles.value[0].id);
    } else {
        form.id = null;
        form.round_id = activeRound.value;
        form.fuel_cost_per_unit = 0;
        form.distance_unit = 'mile';
        form.distance_home_to_work = 0;
        form.distance_work_to_start = 0;
        form.distance_end_to_home = 0;
        form.loading_time_cost_per_hour = 0;
        form.loading_time_hours = 0;
        locations.value = [];
        markers.value.forEach(marker => marker.remove());
        markers.value = [];
        tempLocations.value = [];
        tempMarkers.value.forEach(marker => marker.remove());
        tempMarkers.value = [];
        selectedWorkLocation.value = null;
    }
};

// Edit a service profile
const editProfile = async (profileId) => {
    try {
        const response = await router.get(`/service-profile/${profileId}/edit`);
    } catch (error) {
        console.error('Error loading profile for editing:', error);
        alert('Failed to load profile for editing: ' + (error.message || 'Unknown error'));
    }
};

// Initialize Mapbox
onMounted(async () => {
    // Skip map initialization if there's an error (e.g., missing token)
    if (props.error) {
        console.warn('Skipping Mapbox initialization due to error:', props.error);
        return;
    }

    if (!props.mapboxAccessToken) {
        console.error('Mapbox access token is missing');
        alert('Map configuration error: Mapbox access token is missing. Please contact support.');
        return;
    }

    // Fetch CSRF token on mount
    await fetchCsrfToken();

    // Fetch work locations
    await fetchWorkLocations();

    // Load profiles for the initial active round
    if (activeRound.value) {
        loadProfilesForRound();
    }

    mapInstance.value = new mapboxgl.Map({
        container: map.value,
        style: 'mapbox://styles/mapbox/dark-v10', // Dark theme
        center: [-0.1278, 51.5074], // Default: London (will be overridden if pins exist)
        zoom: 9,
    });

    // Load existing pins with custom markers
    const bounds = new mapboxgl.LngLatBounds();
    locations.value.forEach((loc) => {
        const markerElement = createCustomMarkerElement(loc.name, markerColors[loc.name]);
        const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
            .setLngLat([loc.longitude, loc.latitude])
            .addTo(mapInstance.value);

        // Extend bounds to include this pin
        bounds.extend([loc.longitude, loc.latitude]);

        // Handle drag end to update position
        marker.on('dragend', async () => {
            const { lng, lat } = marker.getLngLat();
            await updateLocationPosition(loc.id, lat, lng);
        });

        markers.value.push(marker);
    });

    // Fit map to bounds if there are locations
    if (locations.value.length > 0) {
        mapInstance.value.fitBounds(bounds, {
            padding: 50, // Add padding around the bounds
            maxZoom: 15, // Limit max zoom to avoid too close a view
        });
    }

    // Handle pin dropping (limit to 3 pins: Home, Start Location, End Location)
    mapInstance.value.on('click', async (e) => {
        const nonWorkLocations = (form.id ? locations.value : tempLocations.value).filter(loc => loc.name !== 'Work');
        if (nonWorkLocations.length >= 3) {
            alert('Only three locations (Home, Start, End) can be set. Work location is set via the dropdown. Clear pins to add new ones.');
            return;
        }

        const { lng, lat } = e.lngLat;
        const name = locationNames[nonWorkLocations.length];

        if (form.id) {
            // Save to backend if profile exists
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
                    service_profile_id: form.id,
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
        } else {
            // Store in temporary array if profile doesn't exist yet
            const tempLocation = {
                name,
                latitude: lat,
                longitude: lng,
            };
            tempLocations.value.push(tempLocation);

            const markerElement = createCustomMarkerElement(name, markerColors[name]);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
                .setLngLat([lng, lat])
                .addTo(mapInstance.value);

            marker.on('dragend', () => {
                const { lng, lat } = marker.getLngLat();
                const tempIndex = tempLocations.value.findIndex(loc => loc.name === name);
                if (tempIndex !== -1) {
                    tempLocations.value[tempIndex].latitude = lat;
                    tempLocations.value[tempIndex].longitude = lng;
                }
                recalculateDistances();
            });

            tempMarkers.value.push(marker);

            recalculateDistances();
        }
    });
});

// Cleanup map on unmount
onUnmounted(() => {
    if (mapInstance.value) mapInstance.value.remove();
});

// Clear pins with feedback
const clearPins = async () => {
    if (locations.value.length === 0 && tempLocations.value.length === 0) {
        clearFeedback.value = 'No pins to clear';
        setTimeout(() => { clearFeedback.value = ''; }, 2000);
        return;
    }

    try {
        isClearing.value = true;
        clearFeedback.value = '';

        if (form.id) {
            // Ensure CSRF token is fresh
            if (!(await fetchCsrfToken())) {
                alert('Failed to initialize request. Please refresh the page.');
                return;
            }

            // Attempt to delete all locations from backend if profile exists
            const deletePromises = locations.value.map(async (loc) => {
                try {
                    await axios.delete(`/api/locations/${loc.id}`, {
                        params: { service_profile_id: form.id },
                    });
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

            // Clear locations
            locations.value = [];
        }

        // Clear temporary locations and markers
        tempMarkers.value.forEach(marker => marker.remove());
        tempMarkers.value = [];
        tempLocations.value = [];

        // Reset form fields
        form.distance_home_to_work = 0;
        form.distance_work_to_start = 0;
        form.distance_end_to_home = 0;
        selectedWorkLocation.value = null;

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
        onSuccess: (page) => {
            if (!props.isEditing) {
                // When creating a new profile, set the form.id and save temporary locations
                const newProfileId = page.props.profile?.id || page.props.serviceProfiles?.find(p => p.round_id === form.round_id)?.id;
                if (newProfileId) {
                    form.id = newProfileId;
                    saveTempLocations(newProfileId);
                }
            }
            activeRound.value = form.round_id;
            loadProfilesForRound();
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
                activeRound.value = form.round_id;
                loadProfilesForRound();
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
    transform: translate(-50%, -50%);
    /* Center the marker on the coordinates */
    cursor: move;
    /* Indicate that the marker is draggable */
}
</style>
