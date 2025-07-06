<!-- resources/js/components/Activity/ActivityFormModal.vue -->
<template>
  <div class="fixed top-1/4 left-1/2 transform -translate-x-1/2 w-full max-w-md z-50">
    <div class="bg-gray-800 bg-opacity-90 rounded-lg shadow-lg p-6">
      <h2 class="text-lg font-semibold text-white mb-4">
        {{ mode === 'edit' ? 'Edit Activity' : mode === 'start-loading' ? 'Add Start Loading' : 'Add Activity' }}
      </h2>
      <form @submit.prevent="submitForm">
        <div class="mb-4">
          <label for="activity" class="block text-sm font-medium text-gray-300">Activity Type</label>
          <select
            v-model="form.activity"
            id="activity"
            class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500"
            required
            :disabled="mode === 'start-loading' || (mode === 'manual' && form.activity)"
          >
            <option value="" disabled>Select an activity</option>
            <option v-for="type in activityTypes" :key="type.name" :value="type.name">{{ type.name }}</option>
          </select>
        </div>
        <div class="mb-4">
          <label for="datetime" class="block text-sm font-medium text-gray-300">Date and Time</label>
          <input
            v-model="form.datetime"
            type="datetime-local"
            id="datetime"
            class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500"
            required
            :disabled="mode === 'start-loading'"
          />
        </div>
        <div class="mb-4" v-if="mode === 'start-loading'">
          <label for="rounds" class="block text-sm font-medium text-gray-300">Number of Rounds</label>
          <select
            v-model.number="form.rounds"
            id="rounds"
            class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500"
            required
          >
            <option :value="1">1</option>
            <option :value="2">2</option>
            <option :value="3">3</option>
            <option :value="4">4</option>
            <option :value="5">5</option>
          </select>
        </div>
        <div class="mb-4">
          <label for="latitude" class="block text-sm font-medium text-gray-300">Latitude</label>
          <input
            v-model.number="form.latitude"
            type="number"
            id="latitude"
            step="any"
            class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500"
            required
            :disabled="isFixedLocation"
          />
        </div>
        <div class="mb-4">
          <label for="longitude" class="block text-sm font-medium text-gray-300">Longitude</label>
          <input
            v-model.number="form.longitude"
            type="number"
            id="longitude"
            step="any"
            class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500"
            required
            :disabled="isFixedLocation"
          />
        </div>
        <div class="mb-4" v-if="mode !== 'edit'">
          <label for="is_manual" class="flex items-center">
            <input
              v-model="form.is_manual"
              type="checkbox"
              id="is_manual"
              class="rounded border-gray-600 text-indigo-500 focus:ring-indigo-500"
              :disabled="mode === 'start-loading' || mode === 'manual'"
            />
            <span class="ml-2 text-sm text-gray-300">Manual Entry</span>
          </label>
        </div>
        <div v-if="error" class="text-red-400 text-sm mb-4">{{ error }}</div>
        <div class="flex justify-end space-x-2">
          <button
            type="button"
            @click="close"
            class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-500"
            :disabled="isSubmitting"
          >
            {{ isSubmitting ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  mode: { type: String, required: true },
  activity: { type: Object, default: () => ({}) },
  activityTypes: { type: Array, default: () => [] },
  locations: { type: Array, default: () => [] },
  mapboxToken: { type: String, default: '' },
  date: { type: String, default: '' },
  rounds: { type: Number, default: 1 },
});

const emit = defineEmits(['submit', 'close']);

const form = ref({
  id: props.activity.id || null,
  activity: props.activity.activity || '',
  datetime: props.activity.datetime || (props.date ? `${props.date}T00:00:00` : new Date().toISOString().slice(0, 16)),
  latitude: props.activity.latitude || null,
  longitude: props.activity.longitude || null,
  is_manual: props.activity.is_manual || false,
  rounds: props.rounds || 1,
});

const error = ref('');
const isSubmitting = ref(false);

const isFixedLocation = computed(() => {
  return ['Left Home', 'Arrive Depot', 'Start Loading', 'Leave Depot', 'Arrive Home'].includes(form.value.activity);
});

watch(() => [form.value.activity, form.value.rounds], async ([newActivity, newRounds]) => {
  console.log('Activity or rounds changed:', { activity: newActivity, rounds: newRounds });
  if (newActivity === 'Start Loading' && props.mode === 'start-loading') {
    const leaveDepot = await fetchLeaveDepotTime(props.date);
    if (!leaveDepot) {
      error.value = `No Leave Depot activity found for ${props.date}`;
      form.value.datetime = '';
      return;
    }
    const depot = props.locations.find(loc => loc.name === 'Depot');
    if (!depot) {
      error.value = 'Depot location not found';
      console.error('Depot location not found');
      return;
    }
    form.value.latitude = Number(depot.latitude);
    form.value.longitude = Number(depot.longitude);
    const leaveTime = new Date(leaveDepot.datetime);
    const minutesToSubtract = newRounds * 20;
    const startLoadingTime = new Date(leaveTime.getTime() - minutesToSubtract * 60 * 1000);
    form.value.datetime = startLoadingTime.toISOString().slice(0, 16);
    form.value.is_manual = true;
  } else if (isFixedLocation.value) {
    const locationName = newActivity.includes('Home') ? 'Home' : 'Depot';
    const location = props.locations.find(loc => loc.name === locationName);
    if (location) {
      form.value.latitude = Number(location.latitude);
      form.value.longitude = Number(location.longitude);
      console.log('Set fixed location coordinates:', { latitude: form.value.latitude, longitude: form.value.longitude });
    } else {
      error.value = `${locationName} location not found`;
      console.error('Location not found:', locationName);
    }
  } else {
    form.value.latitude = null;
    form.value.longitude = null;
  }
});

const fetchLeaveDepotTime = async (date) => {
  try {
    const response = await axios.get('/activities', {
      params: { date },
    });
    const activities = response.data.activities || [];
    return activities.find(a => {
      const activityDate = new Date(a.datetime).toISOString().split('T')[0];
      return activityDate === date && a.activity === 'Leave Depot';
    });
  } catch (err) {
    console.error('Error fetching Leave Depot:', err);
    return null;
  }
};

const submitForm = () => {
  console.log('Submitting form:', form.value);
  error.value = '';
  isSubmitting.value = true;

  if (!form.value.activity || !form.value.datetime || form.value.latitude === null || form.value.longitude === null) {
    error.value = 'Please fill in all required fields.';
    isSubmitting.value = false;
    console.error('Form validation failed:', form.value);
    return;
  }

  emit('submit', {
    mode: props.mode,
    data: {
      id: form.value.id,
      activity: form.value.activity,
      datetime: form.value.datetime,
      latitude: Number(form.value.latitude),
      longitude: Number(form.value.longitude),
      is_manual: form.value.is_manual,
    },
  });

  isSubmitting.value = false;
};

const close = () => {
  console.log('Emitting close event');
  emit('close');
};
</script>