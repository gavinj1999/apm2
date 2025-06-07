<!-- resources/js/Pages/ServiceProfile/Index.vue -->
<template>
    <AppLayout :breadcrumbs="['Profile']">
        <div class="p-6 bg-gray-900 text-gray-100 min-h-screen">
            <h1 class="text-3xl font-bold mb-8 tracking-tight">Service Profile</h1>

            <!-- Flash Messages -->
            <div v-if="$page.props.flash.success" class="bg-green-600 text-white p-4 mb-6 rounded-lg shadow-lg transition-all duration-300">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash.error" class="bg-red-600 text-white p-4 mb-6 rounded-lg shadow-lg transition-all duration-300">
                {{ $page.props.flash.error }}
            </div>
            <div v-if="$page.props.error" class="bg-red-600 text-white p-4 mb-6 rounded-lg shadow-lg transition-all duration-300">
                {{ $page.props.error }}
            </div>

            <!-- Cost Summary -->
            <div v-if="form.id || locations.length" class="mb-6 bg-gray-800 rounded-lg shadow-md p-4">
                <h2 class="text-xl font-semibold mb-4">Cost Summary</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="font-medium text-gray-300">Total Fuel Cost:</p>
                        <p class="text-lg">£{{ totalFuelCost }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-gray-300">Loading Time Cost:</p>
                        <p class="text-lg">£{{ loadingTimeCost }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-gray-300">Total Cost:</p>
                        <p class="text-lg">£{{ totalCost }}</p>
                    </div>
                </div>
            </div>

            <!-- Service Profile Form and Map -->
            <div v-if="!$page.props.error" class="bg-gray-800 rounded-lg shadow-md p-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Form Section -->
                <div>
                    <form @submit.prevent="submitForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="mb-4">
                                <label for="fuel_cost_per_unit" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Fuel Cost Per Unit (£)</label>
                                <input type="number" step="0.01" v-model.number="form.fuel_cost_per_unit" id="fuel_cost_per_unit" class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required min="0" @input="validateInput('fuel_cost_per_unit')" />
                                <p v-if="formErrors.fuel_cost_per_unit" class="text-red-400 text-sm mt-1">{{ formErrors.fuel_cost_per_unit }}</p>
                            </div>
                            <div class="mb-4">
                                <label for="distance_unit" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance Unit</label>
                                <select v-model="form.distance_unit" id="distance_unit" class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                                    <option value="mile">Mile</option>
                                    <option value="km">Kilometer</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="distance_home_to_depot" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance Home to Depot</label>
                                <input type="number" step="0.01" v-model.number="form.distance_home_to_depot" id="distance_home_to_depot" class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required readonly />
                            </div>
                            <div class="mb-4">
                                <label for="distance_depot_to_start" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance Depot to Start Rounds</label>
                                <input type="number" step="0.01" v-model.number="form.distance_depot_to_start" id="distance_depot_to_start" class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required readonly />
                            </div>
                            <div class="mb-4">
                                <label for="distance_end_to_home" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Distance End Rounds to Home</label>
                                <input type="number" step="0.01" v-model.number="form.distance_end_to_home" id="distance_end_to_home" class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required readonly />
                            </div>
                            <div class="mb-4">
                                <label for="loading_time_minutes" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Loading Time (Minutes)</label>
                                <input type="number" step="1" v-model.number="form.loading_time_minutes" id="loading_time_minutes" class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required min="0" @input="validateInput('loading_time_minutes')" />
                                <p v-if="formErrors.loading_time_minutes" class="text-red-400 text-sm mt-1">{{ formErrors.loading_time_minutes }}</p>
                            </div>
                            <div class="mb-4">
                                <label for="loading_time_cost_per_hour" class="block mb-2 font-medium text-gray-300 h-10 flex items-center leading-tight">Loading Time Cost Per Hour (£)</label>
                                <input type="number" step="0.01" v-model.number="form.loading_time_cost_per_hour" id="loading_time_cost_per_hour" class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" required min="0" @input="validateInput('loading_time_cost_per_hour')" />
                                <p v-if="formErrors.loading_time_cost_per_hour" class="text-red-400 text-sm mt-1">{{ formErrors.loading_time_cost_per_hour }}</p>
                            </div>
                        </div>

                        <!-- Depot Selection -->
                        <div class="mb-4">
                            <label for="depot_location" class="block mb-2 font-medium text-gray-300">Select Depot</label>
                            <select v-model="selectedDepot" id="depot_location" class="w-full max-w-md p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" @change="setDepot">
                                <option value="">Select a Depot</option>
                                <option v-for="depot in depots" :key="depot.id" :value="depot.id">
                                    {{ depot.name }} ({{ depot.address || 'No address' }})
                                </option>
                            </select>
                        </div>

                        <!-- Add New Depot -->
                        <div class="mb-4" v-if="showAddDepotForm">
                            <label for="new_depot_name" class="block mb-2 font-medium text-gray-300">New Depot Name</label>
                            <input type="text" v-model="newDepotName" id="new_depot_name" class="w-full max-w-md p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" placeholder="Enter depot name" />
                            <button type="button" @click="saveNewDepot" class="mt-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 transition-all duration-200">
                                Save Depot
                            </button>
                            <button type="button" @click="cancelNewDepot" class="mt-2 ml-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all duration-200">
                                Cancel
                            </button>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex justify-between">
                            <div v-if="isEditing">
                                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200">
                                    Update Profile
                                </button>
                            </div>
                            <div v-else>
                                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200">
                                    Save Profile
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Map Section -->
                <div>
                    <label class="block mb-2 font-medium text-gray-300">Set Locations on Map</label>
                    <div class="relative mb-2">
                        <input type="text" v-model="searchQuery" @input="debouncedSearch" placeholder="Search for an address..." class="w-full p-3 bg-gray-700 border border-gray-600 text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" />
                        <div v-if="searchResults.length" class="absolute z-10 w-full bg-gray-800 border border-gray-600 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto">
                            <div v-for="result in searchResults" :key="result.id" @click="selectAddress(result)" class="p-3 hover:bg-gray-700 cursor-pointer text-gray-100">
                                {{ result.place_name }}
                            </div>
                        </div>
                    </div>
                    <div v-if="isRecalculating" class="bg-yellow-600 text-white p-2 rounded-lg mb-2 text-center transition-opacity duration-300">
                        Recalculating Distances...
                    </div>
                    <div ref="map" class="w-full h-[600px] bg-gray-700 rounded-lg"></div>
                    <p class="mt-2 text-sm text-gray-400">
                        Search an address or click the map to set Home, Start Rounds, and End Rounds in that order. Depot is set via the dropdown above or by adding a new depot pin (click map after selecting 'Add New Depot').
                    </p>
                    <div class="mt-2">
                        <button type="button" @click="toggleAddDepot" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 focus:ring-2 focus:ring-yellow-500 transition-all duration-200 mr-2">
                            {{ showAddDepotForm ? 'Cancel Add Depot' : 'Add New Depot' }}
                        </button>
                        <button type="button" @click="clearPins" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500 transition-all duration-200" :disabled="isClearing">
                            {{ isClearing ? 'Clearing...' : clearFeedback || 'Clear Pins' }}
                        </button>
                        <div v-if="moveFeedback" class="mt-2 w-full bg-green-600 text-white p-2 rounded-lg text-center text-lg font-semibold transition-opacity duration-300">
                            {{ moveFeedback }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profiles Table -->
            <div class="mt-8 bg-gray-800 rounded-lg shadow-md p-4">
                <h2 class="text-xl font-semibold mb-4">Your Profiles</h2>
                <div v-if="serviceProfiles.length" class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-700">
                                <th class="p-3 text-gray-300 font-medium">ID</th>
                                <th class="p-3 text-gray-300 font-medium">Fuel Cost (£/unit)</th>
                                <th class="p-3 text-gray-300 font-medium">Distance Unit</th>
                                <th class="p-3 text-gray-300 font-medium">Total Cost (£)</th>
                                <th class="p-3 text-gray-300 font-medium">Created At</th>
                                <th class="p-3 text-gray-300 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="profile in serviceProfiles" :key="profile.id" class="border-t border-gray-600 hover:bg-gray-700">
                                <td class="p-3">{{ profile.id }}</td>
                                <td class="p-3">{{ profile.fuel_cost_per_unit }}</td>
                                <td class="p-3">{{ profile.distance_unit }}</td>
                                <td class="p-3">{{ profile.total_cost }}</td>
                                <td class="p-3">{{ new Date(profile.created_at).toLocaleDateString() }}</td>
                                <td class="p-3 flex space-x-2">
                                    <button @click="editProfile(profile)" class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                        Edit
                                    </button>
                                    <button @click="deleteProfile(profile.id)" class="px-3 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500 transition-all duration-200">
                                        Delete
                                    </button>
                                    <button @click="loadProfile(profile)" class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 transition-all duration-200">
                                        Load
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="text-gray-400 text-center py-4">
                    No profiles found. Create a new profile above!
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { reactive, ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import mapboxgl from 'mapbox-gl';
import AppLayout from '@/layouts/AppLayout.vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import { debounce } from 'lodash';

// Configure Axios for Sanctum
axios.defaults.withCredentials = true;

const props = defineProps({
    serviceProfiles: Array,
    profile: Object,
    flash: Object,
    mapboxAccessToken: String,
    isEditing: {
        type: Boolean,
        default: false,
    },
    error: String,
});

// Form state with parsed numbers
const form = reactive({
    id: props.profile?.id || null,
    fuel_cost_per_unit: parseFloat(props.profile?.fuel_cost_per_unit) || 0,
    distance_unit: props.profile?.distance_unit || 'mile',
    distance_home_to_depot: parseFloat(props.profile?.distance_home_to_depot) || 0,
    distance_depot_to_start: parseFloat(props.profile?.distance_depot_to_start) || 0,
    distance_end_to_home: parseFloat(props.profile?.distance_end_to_home) || 0,
    loading_time_minutes: parseInt(props.profile?.loading_time_minutes) || 0,
    loading_time_cost_per_hour: parseFloat(props.profile?.loading_time_cost_per_hour) || 0,
});

// Form errors for validation
const formErrors = reactive({
    fuel_cost_per_unit: '',
    loading_time_minutes: '',
    loading_time_cost_per_hour: '',
});

// Map and location state
const map = ref(null);
const mapInstance = ref(null);
const costChart = ref(null);
const chartInstance = ref(null);
const locations = ref([]);
const tempLocations = ref([]);
const tempMarkers = ref([]);
const markers = ref([]);
const isClearing = ref(false);
const clearFeedback = ref('');
const moveFeedback = ref('');
const isRecalculating = ref(false);
const depots = ref([]);
const selectedDepot = ref(null);
const showAddDepotForm = ref(false);
const newDepotName = ref('');
const newDepotCoords = ref(null);

// Address search state
const searchQuery = ref('');
const searchResults = ref([]);

// Mapbox access token
mapboxgl.accessToken = props.mapboxAccessToken;

const markerColors = {
    Home: '#22C55E',
    Depot: '#3B82F6',
    'Start Rounds': '#F59E0B',
    'End Rounds': '#EF4444',
};

const locationTypes = ['Home', 'Start Rounds', 'End Rounds'];

// Cost calculations
const totalFuelCost = computed(() => {
    const totalDistance = 
        Number(form.distance_home_to_depot || 0) +
        Number(form.distance_depot_to_start || 0) +
        Number(form.distance_end_to_home || 0);
    const cost = totalDistance * Number(form.fuel_cost_per_unit || 0);
    return isNaN(cost) ? '0.00' : cost.toFixed(2);
});

const loadingTimeCost = computed(() => {
    const hours = Number(form.loading_time_minutes || 0) / 60;
    const cost = hours * Number(form.loading_time_cost_per_hour || 0);
    return isNaN(cost) ? '0.00' : cost.toFixed(2);
});

const totalCost = computed(() => {
    const cost = Number(totalFuelCost.value) + Number(loadingTimeCost.value);
    return isNaN(cost) ? '0.00' : cost.toFixed(2);
});

// Input validation
const validateInput = (field) => {
    formErrors[field] = '';
    if (isNaN(form[field]) || form[field] < 0) {
        formErrors[field] = `${field.replace(/_/g, ' ')} must be a non-negative number`;
        form[field] = 0;
    } else if (field === 'fuel_cost_per_unit' && form[field] > 100) {
        formErrors[field] = `Fuel cost per unit is too large`;
        form[field] = 100;
    } else if (field === 'loading_time_minutes' && form[field] > 1440) {
        formErrors[field] = `Loading time cannot exceed 24 hours`;
        form[field] = 1440;
    } else if (field === 'loading_time_cost_per_hour' && form[field] > 1000) {
        formErrors[field] = `Loading time cost per hour is too large`;
        form[field] = 1000;
    }
};

// Fetch CSRF token with retry
const fetchCsrfToken = async (retries = 3) => {
    for (let i = 0; i < retries; i++) {
        try {
            await axios.get('/sanctum/csrf-cookie');
            return true;
        } catch (error) {
            console.error(`CSRF token fetch attempt ${i + 1} failed:`, error);
            if (i === retries - 1) {
                console.error('Max CSRF token fetch retries reached');
                return false;
            }
            await new Promise(resolve => setTimeout(resolve, 1000)); // Wait 1s before retry
        }
    }
};

// Fetch depots
const fetchDepots = async () => {
    if (!(await fetchCsrfToken())) {
        alert('Failed to initialize authentication. Please log in.');
        router.visit('/login');
        return;
    }

    try {
        const response = await axios.get('/api/locations', {
            params: { type: 'Depot' },
        });
        depots.value = response.data;
        const depotLoc = locations.value.find(loc => loc.type === 'Depot');
        if (depotLoc) {
            const matchingDepot = depots.value.find(depot => 
                depot.latitude === depotLoc.latitude && depot.longitude === depotLoc.longitude
            );
            if (matchingDepot) {
                selectedDepot.value = matchingDepot.id;
            }
        }
    } catch (error) {
        console.error('Error fetching depots:', error);
        if (error.response?.status === 401) {
            alert('Session expired. Please log in again.');
            router.visit('/login');
        } else {
            alert('Failed to fetch depots: ' + (error.response?.data?.error || error.message || 'Unknown error'));
        }
    }
};

// Fetch locations
const fetchLocations = async (profileId) => {
    if (!profileId) return;
    if (!(await fetchCsrfToken())) {
        alert('Failed to initialize authentication. Please log in.');
        router.visit('/login');
        return;
    }

    try {
        const response = await axios.get('/api/locations', {
            params: { service_profile_id: profileId },
        });
        locations.value = response.data;
        markers.value.forEach(marker => marker.remove());
        markers.value = [];
        locations.value.forEach((loc) => {
            const markerElement = createCustomMarkerElement(loc.type, markerColors[loc.type]);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: props.isEditing })
                .setLngLat([loc.longitude, loc.latitude])
                .addTo(mapInstance.value);

            if (props.isEditing) {
                marker.on('dragend', async () => {
                    const { lng, lat } = marker.getLngLat();
                    await updateLocationPosition(loc.id, lat, lng);
                });
            }

            markers.value.push(marker);
        });
        updateMapBounds();
        await recalculateDistances();
    } catch (error) {
        console.error('Error fetching locations:', error);
        if (error.response?.status === 401) {
            alert('Session expired. Please log in again.');
            router.visit('/login');
        } else {
            alert('Failed to fetch locations: ' + (error.response?.data?.error || error.message || 'Unknown error'));
        }
    }
};

// Create custom marker
const createCustomMarkerElement = (type, color) => {
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
    el.innerText = type;
    return el;
};

// Toggle add depot form
const toggleAddDepot = () => {
    showAddDepotForm.value = !showAddDepotForm.value;
    newDepotName.value = '';
    newDepotCoords.value = null;
    searchQuery.value = '';
    searchResults.value = [];
};

// Cancel adding new depot
const cancelNewDepot = () => {
    showAddDepotForm.value = false;
    newDepotName.value = '';
    newDepotCoords.value = null;
    tempMarkers.value.forEach(marker => marker.remove());
    tempMarkers.value = [];
    tempLocations.value = tempLocations.value.filter(loc => loc.type !== 'Depot');
    searchQuery.value = '';
    searchResults.value = [];
};

// Save new depot
const saveNewDepot = async () => {
    if (!newDepotName.value || !newDepotCoords.value) {
        alert('Please provide a depot name and select a location on the map or via search.');
        return;
    }

    if (!(await fetchCsrfToken())) {
        alert('Failed to initialize authentication. Please log in.');
        router.visit('/login');
        return;
    }

    try {
        const response = await axios.post('/api/locations', {
            name: newDepotName.value,
            type: 'Depot',
            latitude: newDepotCoords.value.lat,
            longitude: newDepotCoords.value.lng,
            service_profile_id: form.id || null,
        });

        const newDepot = response.data;
        depots.value.push(newDepot);
        selectedDepot.value = newDepot.id;

        await setDepot();
        showAddDepotForm.value = false;
        newDepotName.value = '';
        newDepotCoords.value = null;
        tempMarkers.value.forEach(marker => marker.remove());
        tempMarkers.value = [];
        tempLocations.value = [];
        searchQuery.value = '';
        searchResults.value = [];
    } catch (error) {
        console.error('Error saving new depot:', error);
        if (error.response?.status === 401) {
            alert('Session expired. Please log in again.');
            router.visit('/login');
        } else {
            alert('Failed to save depot: ' + (error.response?.data?.error || error.message || 'Unknown error'));
        }
    }
};

// Set depot
const setDepot = async () => {
    if (!selectedDepot.value) return;
    const depot = depots.value.find(loc => loc.id === selectedDepot.value);
    if (!depot) return;

    if (!(await fetchCsrfToken())) {
        alert('Failed to initialize authentication. Please log in.');
        router.visit('/login');
        return;
    }

    try {
        const existingDepot = locations.value.find(loc => loc.type === 'Depot');
        if (existingDepot) {
            await axios.delete(`/api/locations/${existingDepot.id}`, {
                params: { service_profile_id: form.id },
            });
            const markerIndex = markers.value.findIndex(marker => marker.getElement().innerText === 'Depot');
            if (markerIndex !== -1) {
                markers.value[markerIndex].remove();
                markers.value.splice(markerIndex, 1);
            }
            locations.value = locations.value.filter(loc => loc.type !== 'Depot');
        }

        if (form.id) {
            const response = await axios.post('/api/locations', {
                name: depot.name,
                type: 'Depot',
                latitude: depot.latitude,
                longitude: depot.longitude,
                service_profile_id: form.id,
            });
            const newLocation = response.data;
            locations.value.push(newLocation);

            const markerElement = createCustomMarkerElement('Depot', markerColors['Depot']);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: props.isEditing })
                .setLngLat([depot.longitude, depot.latitude])
                .addTo(mapInstance.value);

            if (props.isEditing) {
                marker.on('dragend', async () => {
                    const { lng, lat } = marker.getLngLat();
                    await updateLocationPosition(newLocation.id, lat, lng);
                });
            }

            markers.value.push(marker);
        } else {
            const tempLocation = {
                type: 'Depot',
                latitude: depot.latitude,
                longitude: depot.longitude,
            };
            tempLocations.value.push(tempLocation);

            const markerElement = createCustomMarkerElement('Depot', markerColors['Depot']);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
                .setLngLat([depot.longitude, depot.latitude])
                .addTo(mapInstance.value);

            marker.on('dragend', () => {
                const { lng, lat } = marker.getLngLat();
                const tempIndex = tempLocations.value.findIndex(loc => loc.type === 'Depot');
                if (tempIndex !== -1) {
                    tempLocations.value[tempIndex].latitude = lat;
                    tempLocations.value[tempIndex].longitude = lng;
                }
                recalculateDistances();
                updateMapBounds();
            });

            tempMarkers.value.push(marker);
        }

        await recalculateDistances();
        updateMapBounds();
    } catch (error) {
        console.error('Error setting depot:', error);
        if (error.response?.status === 401) {
            alert('Session expired. Please log in again.');
            router.visit('/login');
        } else {
            alert('Failed to set depot: ' + (error.response?.data?.error || error.message || 'Unknown error'));
        }
    }
};

// Update location position
const updateLocationPosition = async (locationId, latitude, longitude) => {
    if (!(await fetchCsrfToken())) {
        alert('Failed to initialize authentication. Please log in.');
        router.visit('/login');
        return false;
    }

    try {
        const location = locations.value.find(loc => loc.id === locationId);
        if (!location) {
            console.warn(`Location ID ${locationId} not found. Refreshing locations...`);
            await fetchLocations(form.id);
            return false;
        }

        const response = await axios.put(`/api/locations/${locationId}`, {
            latitude,
            longitude,
            service_profile_id: form.id,
        });

        const locationIndex = locations.value.findIndex(loc => loc.id === locationId);
        if (locationIndex !== -1) {
            locations.value[locationIndex].latitude = latitude;
            locations.value[locationIndex].longitude = longitude;
        }

        await recalculateDistances();
        moveFeedback.value = `Location moved!`;
        setTimeout(() => { moveFeedback.value = ''; }, 2000);

        updateMapBounds();
        return true;
    } catch (error) {
        console.error('Error updating location position:', error);
        if (error.response?.status === 401) {
            alert('Session expired. Please log in again.');
            router.visit('/login');
        } else if (error.response?.status === 404) {
            console.warn(`Location ID ${locationId} not found. Refreshing locations...`);
            await fetchLocations(form.id);
        } else {
            alert('Failed to update location position: ' + (error.response?.data?.error || error.message || 'Unknown error'));
        }
        return false;
    }
};

// Recalculate distances using Mapbox Directions API
const recalculateDistances = async () => {
    isRecalculating.value = true;
    const home = locations.value.find(loc => loc.type === 'Home') || tempLocations.value.find(loc => loc.type === 'Home');
    const depot = locations.value.find(loc => loc.type === 'Depot') || tempLocations.value.find(loc => loc.type === 'Depot');
    const start = locations.value.find(loc => loc.type === 'Start Rounds') || tempLocations.value.find(loc => loc.type === 'Start Rounds');
    const end = locations.value.find(loc => loc.type === 'End Rounds') || tempLocations.value.find(loc => loc.type === 'End Rounds');

    try {
        if (home && depot) {
            const distance = await getRoadDistance([home.longitude, home.latitude], [depot.longitude, depot.latitude]);
            form.distance_home_to_depot = parseFloat(distance.toFixed(2));
        } else {
            form.distance_home_to_depot = 0;
        }

        if (depot && start) {
            const distance = await getRoadDistance([depot.longitude, depot.latitude], [start.longitude, start.latitude]);
            form.distance_depot_to_start = parseFloat(distance.toFixed(2));
        } else {
            form.distance_depot_to_start = 0;
        }

        if (end && home) {
            const distance = await getRoadDistance([end.longitude, end.latitude], [home.longitude, home.latitude]);
            form.distance_end_to_home = parseFloat(distance.toFixed(2));
        } else {
            form.distance_end_to_home = 0;
        }
    } catch (error) {
        console.error('Error recalculating distances:', error);
        alert('Failed to recalculate distances: ' + (error.message || 'Unknown error'));
    } finally {
        isRecalculating.value = false;
    }
};

// Get road distance using Mapbox Directions API
const getRoadDistance = async (origin, destination) => {
    const unit = form.distance_unit === 'mile' ? 'imperial' : 'metric';
    const url = `https://api.mapbox.com/directions/v5/mapbox/driving/${origin[0]},${origin[1]};${destination[0]},${destination[1]}?access_token=${mapboxgl.accessToken}&geometries=geojson&units=${unit}`;
    const response = await axios.get(url);
    const distance = response.data.routes[0].distance / (unit === 'imperial' ? 1609.34 : 1000); // Convert meters to miles or km
    return distance;
};

// Search addresses using Mapbox Geocoding API
const searchAddresses = async () => {
    if (!searchQuery.value) {
        searchResults.value = [];
        return;
    }

    try {
        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(searchQuery.value)}.json?access_token=${mapboxgl.accessToken}&autocomplete=true&limit=5`;
        const response = await axios.get(url);
        searchResults.value = response.data.features;
    } catch (error) {
        console.error('Error searching addresses:', error);
        searchResults.value = [];
        alert('Failed to search addresses: ' + (error.message || 'Unknown error'));
    }
};

// Debounced search to prevent excessive API calls
const debouncedSearch = debounce(searchAddresses, 300);

// Select an address from search results
const selectAddress = async (result) => {
    const [lng, lat] = result.geometry.coordinates;
    searchQuery.value = result.place_name;
    searchResults.value = [];

    if (!props.isEditing && form.id) {
        alert('Please edit the profile to modify locations.');
        return;
    }

    if (showAddDepotForm.value) {
        newDepotCoords.value = { lng, lat };
        newDepotName.value = result.place_name;
        tempMarkers.value.forEach(marker => marker.remove());
        tempMarkers.value = [];
        tempLocations.value = tempLocations.value.filter(loc => loc.type !== 'Depot');

        const tempLocation = {
            type: 'Depot',
            latitude: lat,
            longitude: lng,
        };
        tempLocations.value.push(tempLocation);

        const markerElement = createCustomMarkerElement('Depot', markerColors['Depot']);
        const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
            .setLngLat([lng, lat])
            .addTo(mapInstance.value);

        marker.on('dragend', () => {
            const { lng, lat } = marker.getLngLat();
            newDepotCoords.value = { lng, lat };
            const tempIndex = tempLocations.value.findIndex(loc => loc.type === 'Depot');
            if (tempIndex !== -1) {
                tempLocations.value[tempIndex].latitude = lat;
                tempLocations.value[tempIndex].longitude = lng;
            }
        });

        tempMarkers.value.push(marker);
        mapInstance.value.flyTo({ center: [lng, lat], zoom: 15 });
        return;
    }

    const nonDepotLocations = (form.id ? locations.value : tempLocations.value).filter(loc => loc.type !== 'Depot');
    if (nonDepotLocations.length >= 3) {
        alert('Only three locations (Home, Start Rounds, End Rounds) can be set. Depot is set via the dropdown.');
        return;
    }

    const type = locationTypes[nonDepotLocations.length];

    if (form.id) {
        if (!(await fetchCsrfToken())) {
            alert('Failed to initialize authentication. Please log in.');
            router.visit('/login');
            return;
        }

        try {
            const response = await axios.post('/api/locations', {
                name: result.place_name,
                type,
                latitude: lat,
                longitude: lng,
                service_profile_id: form.id,
            });
            const newLocation = response.data;
            locations.value.push(newLocation);

            const markerElement = createCustomMarkerElement(type, markerColors[type]);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: props.isEditing })
                .setLngLat([lng, lat])
                .addTo(mapInstance.value);

            if (props.isEditing) {
                marker.on('dragend', async () => {
                    const { lng, lat } = marker.getLngLat();
                    await updateLocationPosition(newLocation.id, lat, lng);
                });
            }

            markers.value.push(marker);
            await recalculateDistances();
            updateMapBounds();
            moveFeedback.value = `${type} pin placed!`;
            setTimeout(() => { moveFeedback.value = ''; }, 2000);
        } catch (error) {
            console.error('Error saving location:', error);
            if (error.response?.status === 401) {
                alert('Session expired. Please log in again.');
                router.visit('/login');
            } else if (error.response?.status === 404) {
                alert('Service profile not found. Please save your profile first.');
            } else {
                alert('Failed to save location: ' + (error.response?.data?.error || error.message || 'Unknown error'));
            }
        }
    } else {
        const tempLocation = {
            type,
            latitude: lat,
            longitude: lng,
        };
        tempLocations.value.push(tempLocation);

        const markerElement = createCustomMarkerElement(type, markerColors[type]);
        const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
            .setLngLat([lng, lat])
            .addTo(mapInstance.value);

        marker.on('dragend', () => {
            const { lng, lat } = marker.getLngLat();
            const tempIndex = tempLocations.value.findIndex(loc => loc.type === type);
            if (tempIndex !== -1) {
                tempLocations.value[tempIndex].latitude = lat;
                tempLocations.value[tempIndex].longitude = lng;
            }
            recalculateDistances();
            updateMapBounds();
        });

        tempMarkers.value.push(marker);
        await recalculateDistances();
        updateMapBounds();
        moveFeedback.value = `${type} pin placed!`;
        setTimeout(() => { moveFeedback.value = ''; }, 2000);
    }

    mapInstance.value.flyTo({ center: [lng, lat], zoom: 15 });
};

// Save temporary locations
const saveTempLocations = async (profileId) => {
    if (!(await fetchCsrfToken())) {
        alert('Failed to initialize authentication. Please log in.');
        router.visit('/login');
        return;
    }

    try {
        for (const tempLocation of tempLocations.value) {
            const response = await axios.post('/api/locations', {
                name: tempLocation.name || tempLocation.type,
                type: tempLocation.type,
                latitude: tempLocation.latitude,
                longitude: tempLocation.longitude,
                service_profile_id: profileId,
            });
            const newLocation = response.data;
            locations.value.push(newLocation);

            const tempIndex = tempLocations.value.indexOf(tempLocation);
            const tempMarker = tempMarkers.value[tempIndex];
            tempMarker.remove();

            const markerElement = createCustomMarkerElement(tempLocation.type, markerColors[tempLocation.type]);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: props.isEditing })
                .setLngLat([tempLocation.longitude, tempLocation.latitude])
                .addTo(mapInstance.value);

            if (props.isEditing) {
                marker.on('dragend', async () => {
                    const { lng, lat } = marker.getLngLat();
                    await updateLocationPosition(newLocation.id, lat, lng);
                });
            }

            markers.value.push(marker);
        }

        tempLocations.value = [];
        tempMarkers.value = [];

        updateMapBounds();
    } catch (error) {
        console.error('Error saving temporary locations:', error);
        if (error.response?.status === 401) {
            alert('Session expired. Please log in again.');
            router.visit('/login');
        } else {
            alert('Failed to save temporary locations: ' + (error.response?.data?.error || error.message || 'Unknown error'));
        }
    }
};

// Update map bounds to fit all pins
const updateMapBounds = () => {
    if (!mapInstance.value) return;

    const allLocations = [...locations.value, ...tempLocations.value];
    if (allLocations.length > 0) {
        const bounds = new mapboxgl.LngLatBounds();
        allLocations.forEach(loc => bounds.extend([loc.longitude, loc.latitude]));
        mapInstance.value.fitBounds(bounds, { padding: 50, maxZoom: 15 });
    } else {
        mapInstance.value.setCenter([-0.1278, 51.5074]).setZoom(9); // Default: London
    }
};

// Load a profile
const loadProfile = async (profile) => {
    Object.assign(form, {
        id: profile.id,
        fuel_cost_per_unit: parseFloat(profile.fuel_cost_per_unit),
        distance_unit: profile.distance_unit,
        distance_home_to_depot: parseFloat(profile.distance_home_to_depot),
        distance_depot_to_start: parseFloat(profile.distance_depot_to_start),
        distance_end_to_home: parseFloat(profile.distance_end_to_home),
        loading_time_minutes: parseInt(profile.loading_time_minutes),
        loading_time_cost_per_hour: parseFloat(profile.loading_time_cost_per_hour),
    });

    tempLocations.value = [];
    tempMarkers.value.forEach(marker => marker.remove());
    tempMarkers.value = [];
    await fetchLocations(profile.id);
    moveFeedback.value = `Profile ${profile.id} loaded!`;
    setTimeout(() => { moveFeedback.value = ''; }, 2000);
};

// Edit a profile
const editProfile = async (profile) => {
    await loadProfile(profile);
    router.get(`/service-profile/${profile.id}/edit`, {}, {
        preserveState: true,
        onSuccess: () => {
            moveFeedback.value = `Editing Profile ${profile.id}!`;
            setTimeout(() => { moveFeedback.value = ''; }, 2000);
        },
    });
};

// Delete a profile
const deleteProfile = async (profileId) => {
    if (confirm('Are you sure you want to delete this profile?')) {
        try {
            await router.delete(`/service-profile/${profileId}`, {
                onSuccess: () => {
                    if (form.id === profileId) {
                        Object.assign(form, {
                            id: null,
                            fuel_cost_per_unit: 0,
                            distance_unit: 'mile',
                            distance_home_to_depot: 0,
                            distance_depot_to_start: 0,
                            distance_end_to_home: 0,
                            loading_time_minutes: 0,
                            loading_time_cost_per_hour: 0,
                        });
                        locations.value = [];
                        markers.value.forEach(marker => marker.remove());
                        markers.value = [];
                        tempLocations.value = [];
                        tempMarkers.value.forEach(marker => marker.remove());
                        tempMarkers.value = [];
                        selectedDepot.value = null;
                        updateMapBounds();
                    }
                    router.reload({ only: ['serviceProfiles'] }); // Refresh profiles list
                    moveFeedback.value = `Profile ${profileId} deleted!`;
                    setTimeout(() => { moveFeedback.value = ''; }, 2000);
                },
                onError: (errors) => {
                    console.error('Deletion errors:', errors);
                    alert('Failed to delete profile: ' + Object.values(errors).join(', '));
                },
            });
        } catch (error) {
            console.error('Error deleting profile:', error);
            alert('Failed to delete profile: ' + (error.message || 'Unknown error'));
        }
    }
};

// Initialize Mapbox
onMounted(async () => {
    if (props.error) {
        console.warn('Skipping Mapbox initialization due to error:', props.error);
        return;
    }

    if (!props.mapboxAccessToken) {
        console.error('Mapbox access token is missing');
        alert('Map configuration error: Mapbox access token is missing. Please contact support.');
        return;
    }

    if (!(await fetchCsrfToken())) {
        alert('Failed to initialize authentication. Please log in.');
        router.visit('/login');
        return;
    }

    await fetchDepots();

    // Fetch locations for the current profile
    if (props.profile?.id) {
        await fetchLocations(props.profile.id);
    }

    mapInstance.value = new mapboxgl.Map({
        container: map.value,
        style: 'mapbox://styles/mapbox/dark-v10',
        center: [-0.1278, 51.5074], // Default: London
        zoom: 9,
    });

    // Load existing pins with custom markers
    locations.value.forEach((loc) => {
        const markerElement = createCustomMarkerElement(loc.type, markerColors[loc.type]);
        const marker = new mapboxgl.Marker({ element: markerElement, draggable: props.isEditing })
            .setLngLat([loc.longitude, loc.latitude])
            .addTo(mapInstance.value);

        if (props.isEditing) {
            marker.on('dragend', async () => {
                const { lng, lat } = marker.getLngLat();
                await updateLocationPosition(loc.id, lat, lng);
            });
        }

        markers.value.push(marker);
    });

    // Recalculate distances on load
    await recalculateDistances();

    // Fit map to all pins
    updateMapBounds();

    // Map click handler
    mapInstance.value.on('click', async (e) => {
        if (!props.isEditing && form.id) {
            alert('Please edit the profile to modify locations.');
            return;
        }

        if (showAddDepotForm.value) {
            newDepotCoords.value = e.lngLat;
            tempMarkers.value.forEach(marker => marker.remove());
            tempMarkers.value = [];
            tempLocations.value = tempLocations.value.filter(loc => loc.type !== 'Depot');

            const tempLocation = {
                type: 'Depot',
                latitude: e.lngLat.lat,
                longitude: e.lngLat.lng,
            };
            tempLocations.value.push(tempLocation);

            const markerElement = createCustomMarkerElement('Depot', markerColors['Depot']);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
                .setLngLat([e.lngLat.lng, e.lngLat.lat])
                .addTo(mapInstance.value);

            marker.on('dragend', () => {
                const { lng, lat } = marker.getLngLat();
                newDepotCoords.value = { lng, lat };
                const tempIndex = tempLocations.value.findIndex(loc => loc.type === 'Depot');
                if (tempIndex !== -1) {
                    tempLocations.value[tempIndex].latitude = lat;
                    tempLocations.value[tempIndex].longitude = lng;
                }
            });

            tempMarkers.value.push(marker);
            mapInstance.value.flyTo({ center: [e.lngLat.lng, e.lngLat.lat], zoom: 15 });
            return;
        }

        const nonDepotLocations = (form.id ? locations.value : tempLocations.value).filter(loc => loc.type !== 'Depot');
        if (nonDepotLocations.length >= 3) {
            alert('Only three locations (Home, Start Rounds, End Rounds) can be set. Depot is set via the dropdown.');
            return;
        }

        const { lng, lat } = e.lngLat;
        const type = locationTypes[nonDepotLocations.length];

        if (form.id) {
            if (!(await fetchCsrfToken())) {
                alert('Failed to initialize authentication. Please log in.');
                router.visit('/login');
                return;
            }

            try {
                const response = await axios.post('/api/locations', {
                    name: type,
                    type,
                    latitude: lat,
                    longitude: lng,
                    service_profile_id: form.id,
                });
                const newLocation = response.data;
                locations.value.push(newLocation);

                const markerElement = createCustomMarkerElement(type, markerColors[type]);
                const marker = new mapboxgl.Marker({ element: markerElement, draggable: props.isEditing })
                    .setLngLat([lng, lat])
                    .addTo(mapInstance.value);

                if (props.isEditing) {
                    marker.on('dragend', async () => {
                        const { lng, lat } = marker.getLngLat();
                        await updateLocationPosition(newLocation.id, lat, lng);
                    });
                }

                markers.value.push(marker);
                await recalculateDistances();
                updateMapBounds();
                moveFeedback.value = `${type} pin placed!`;
                setTimeout(() => { moveFeedback.value = ''; }, 2000);
            } catch (error) {
                console.error('Error saving location:', error);
                if (error.response?.status === 401) {
                    alert('Session expired. Please log in again.');
                    router.visit('/login');
                } else if (error.response?.status === 404) {
                    alert('Service profile not found. Please save your profile first.');
                } else {
                    alert('Failed to save location: ' + (error.response?.data?.error || error.message || 'Unknown error'));
                }
            }
        } else {
            const tempLocation = {
                type,
                latitude: lat,
                longitude: lng,
            };
            tempLocations.value.push(tempLocation);

            const markerElement = createCustomMarkerElement(type, markerColors[type]);
            const marker = new mapboxgl.Marker({ element: markerElement, draggable: true })
                .setLngLat([lng, lat])
                .addTo(mapInstance.value);

            marker.on('dragend', () => {
                const { lng, lat } = marker.getLngLat();
                const tempIndex = tempLocations.value.findIndex(loc => loc.type === type);
                if (tempIndex !== -1) {
                    tempLocations.value[tempIndex].latitude = lat;
                    tempLocations.value[tempIndex].longitude = lng;
                }
                recalculateDistances();
                updateMapBounds();
            });

            tempMarkers.value.push(marker);
            await recalculateDistances();
            updateMapBounds();
            moveFeedback.value = `${type} pin placed!`;
            setTimeout(() => { moveFeedback.value = ''; }, 2000);
        }

        mapInstance.value.flyTo({ center: [lng, lat], zoom: 15 });
    });

    // Initialize Chart.js for cost breakdown
    if (costChart.value) {
        chartInstance.value = new Chart(costChart.value, {
            type: 'pie',
            data: {
                labels: ['Fuel Cost', 'Loading Time Cost'],
                datasets: [{
                    label: 'Cost Breakdown',
                    data: [parseFloat(totalFuelCost.value), parseFloat(loadingTimeCost.value)],
                    backgroundColor: ['#22C55E', '#3B82F6'],
                    borderColor: ['#1E3A8A', '#15803D'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: '#D1D5DB' }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += '£' + context.parsed.toFixed(2);
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
});

// Update chart when costs change
watch([totalFuelCost, loadingTimeCost], () => {
    if (chartInstance.value) {
        chartInstance.value.data.datasets[0].data = [
            parseFloat(totalFuelCost.value) || 0,
            parseFloat(loadingTimeCost.value) || 0
        ];
        chartInstance.value.update();
    }
});

onMounted(async () => {
if (props.error) {
        console.warn('Skipping initialization due to error:', props.error);
        return;
    }
    if (!props.mapboxAccessToken) {
        console.error('Mapbox access token missing');
        alert('Map configuration error: Mapbox access token missing.');
        return;
    }
    try {
        const response = await axios.get('/api/user');
        console.log('Authenticated user:', response.data);
    } catch (error) {
        console.error('Auth check failed:', {
            status: error.response?.status,
            data: error.response?.data
        });
        alert('Authentication check failed. Redirecting to login...');
        router.visit('/login');
        return;
    }
    if (!(await fetchCsrfToken())) {
        alert('Failed to initialize authentication. Please log in.');
        router.visit('/login');
        return;
    }
    await fetchDepots();
    if (props.profile?.id) {
        await fetchLocations(props.profile.id);
    }
    if (mapInstance.value) mapInstance.value.remove();
    if (chartInstance.value) chartInstance.value.destroy();

});

const clearPins = async () => {
    if (locations.value.length === 0 && tempLocations.value.length === 0) {
        clearFeedback.value = 'No pins to clear';
        setTimeout(() => { clearFeedback.value = ''; }, 2000);
        return;
    }

    if (!(await fetchCsrfToken())) {
        alert('Failed to initialize authentication. Please log in.');
        router.visit('/login');
        return;
    }

    try {
        isClearing.value = true;
        clearFeedback.value = '';

        if (form.id) {
            console.log('Clearing locations for profile:', form.id, 'Locations:', locations.value);
            const deletePromises = locations.value.map(async (loc) => {
                try {
                    await axios.delete(`/api/locations/${loc.id}`);
                    return { id: loc.id, success: true };
                } catch (error) {
                    console.error(`Failed to delete location ${loc.id}:`, error);
                    return { id: loc.id, success: false, error: error.message || 'Unknown error' };
                }
            });

            const results = await Promise.all(deletePromises);
            const failedDeletions = results.filter(result => !result.success);
            if (failedDeletions.length > 0) {
                const errorMessages = failedDeletions.map(result => `Location ${result.id}: ${result.error}`).join('; ');
                console.warn('Failed deletions:', errorMessages);
                throw new Error(`Some locations could not be deleted: ${errorMessages}`);
            }

            markers.value.forEach(marker => marker.remove());
            markers.value = [];
            locations.value = [];
        }

        tempMarkers.value.forEach(marker => marker.remove());
        tempMarkers.value = [];
        tempLocations.value = [];

        form.distance_home_to_depot = 0;
        form.distance_depot_to_start = 0;
        form.distance_end_to_home = 0;
        selectedDepot.value = null;

        clearFeedback.value = 'Pins cleared!';
        setTimeout(() => { clearFeedback.value = ''; }, 2000);

        updateMapBounds();
    } catch (error) {
        console.error('Error clearing pins:', error);
        if (error.response?.status === 401) {
            alert('Session expired. Please log in again.');
            router.visit('/login');
        } else {
            alert('Failed to clear pins: ' + (error.message || 'Some locations could not be deleted due to permission issues. Please try again or contact support.'));
            await fetchLocations(form.id); // Refresh locations to sync state
        }
    } finally {
        isClearing.value = false;
    }
};

const submitForm = () => {
    // Validate form inputs
    let hasErrors = false;
    Object.keys(formErrors).forEach(key => {
        validateInput(key);
        if (formErrors[key]) hasErrors = true;
    });

    if (hasErrors) {
        alert('Please correct the form errors before submitting.');
        return;
    }

    const route = props.isEditing ? 'service-profile.update' : 'service-profile.store';
    const method = props.isEditing ? 'put' : 'post';
    const url = props.isEditing ? `/service-profile/${form.id}` : '/service-profile';

    // Store form data temporarily
    const formBackup = { ...form };

    router[method](url, {
        ...form,
        total_fuel_cost: parseFloat(totalFuelCost.value) || 0,
        total_loading_cost: parseFloat(loadingTimeCost.value) || 0,
        total_cost: parseFloat(totalCost.value) || 0,
    }, {
        onSuccess: (page) => {
            if (!props.isEditing) {
                const newProfileId = page.props.profile?.id;
                if (newProfileId) {
                    form.id = newProfileId;
                    saveTempLocations(newProfileId);
                }
            }
            updateMapBounds();
            moveFeedback.value = props.isEditing ? 'Profile updated!' : 'Profile saved!';
            setTimeout(() => { moveFeedback.value = ''; }, 2000);
            router.reload({ only: ['serviceProfiles'] }); // Refresh profiles list
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors);
            // Restore form data on error
            Object.assign(form, formBackup);
            alert('Failed to save profile: ' + Object.values(errors).join(', '));
        },
    });
};
</script>

<style scoped>
.map-container {
    position: relative;
    width: 100%;
    height: 600px;
}

.custom-marker {
    transform: translate(-50%, -50%);
    cursor: move;
}

/* Ensure grid columns are balanced */
.grid-cols-2 > div {
    min-width: 0; /* Prevent overflow */
}

/* Adjust map height to match form */
.map-section {
    display: flex;
    flex-direction: column;
    height: 100%;
}
</style>