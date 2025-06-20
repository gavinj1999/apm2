<template>
  <Head title="Activities" />
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-white leading-tight">
        Recorded Activities
      </h2>
    </template>
    <div class="flex-1 flex flex-col">
      <div class="sm:px-2 lg:px-2 flex-1 flex flex-col">
        <div class="bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg flex-1 flex flex-col">
          <div class="p-6 bg-gray-800 border-b border-gray-700 flex-1 flex flex-col">
            <div v-if="$page.props.flash.success" class="text-green-400 mb-4 text-sm">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.errors.global" class="text-red-400 mb-4">{{ $page.props.errors.global }}</div>
            <div class="mb-6 flex space-x-4">
              <button
                @click="showLocationModal = true"
                class="font-bold rounded-lg text-xs w-32 h-8 bg-indigo-600 text-[#ffffff] justify-center"
              >
                Manage Locations
              </button>
              <button
                @click="openCreateModal"
                class="font-bold rounded-lg text-xs w-24 h-8 bg-indigo-600 text-[#ffffff] justify-center"
              >
                Add Activity
              </button>
            </div>
            <ActivityTable
              :activities="localActivities"
              :activity-types="activityTypes"
              :distances="localDistances"
              :locations="locations"
              :is-calculating-distance="isCalculatingDistance"
              @edit-activity="openEditModal"
              @delete-activity="deleteActivity"
              @add-manual="openManualModal"
              @mark-correct="markAsCorrect"
              @calculate-distance="calculateDistance"
              @update-location="updateActivityLocation"
            />
            <LocationModal
              v-if="showLocationModal"
              :locations="locations"
              :mapbox-token="mapboxToken"
              @add-location="addLocation"
              @update-location="updateLocation"
              @delete-location="deleteLocation"
              @close="showLocationModal = false"
            />
            <ActivityFormModal
              v-if="showCreateModal || showEditModal || showManualModal"
              :mode="modalMode"
              :activity="modalActivity"
              :activity-types="activityTypes"
              :locations="locations"
              :mapbox-token="mapboxToken"
              :date="manualDate"
              @submit="handleFormSubmit"
              @close="closeModal"
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import ActivityTable from '@/components/Activity/ActivityTable.vue';
import LocationModal from '@/components/Activity/LocationModal.vue';
import ActivityFormModal from '@/components/Activity/ActivityFormModal.vue';
import axios from 'axios';

const props = defineProps({
  activities: { type: Array, default: () => [] },
  activityDates: { type: Array, default: () => [] },
  activityTypes: { type: Array, default: () => [] },
  locations: { type: Array, default: () => [] },
  distances: { type: Object, default: () => ({}) },
  mapboxToken: { type: String, required: false },
  errors: Object,
  flash: Object,
  csrf: String,
});

const localActivities = ref(props.activities);
const localDistances = ref(props.distances || {});
const showLocationModal = ref(false);
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showManualModal = ref(false);
const modalMode = ref('');
const modalActivity = ref(null);
const manualDate = ref('');

const isCalculatingDistance = ref({});
const isUpdatingActivityLocation = ref({});
const selectedLocation = ref({});

onMounted(() => {
  console.log('Index.vue mounted:', {
    activities: props.activities.length,
    activityIds: props.activities.map(a => a.id),
    distances: JSON.parse(JSON.stringify(props.distances)),
    activityDates: props.activityDates,
    csrf: props.csrf,
  });
  localActivities.value = props.activities || [];
  localDistances.value = props.distances || {};
  initializeSelectedLocations();
});

watch(() => props.distances, (newDistances) => {
  console.log('Distances prop updated:', JSON.parse(JSON.stringify(newDistances)));
  localDistances.value = newDistances || {};
});

const initializeSelectedLocations = () => {
  props.activities.forEach(activity => {
    if (activity.id) {
      selectedLocation.value[activity.id] = findMatchingLocation(activity.latitude, activity.longitude);
    }
  });
};

const findMatchingLocation = (latitude, longitude) => {
  if (!latitude || !longitude) return '';
  const tolerance = 0.000001;
  return props.locations.find(loc => 
    Math.abs(parseFloat(loc.latitude) - parseFloat(latitude)) < tolerance &&
    Math.abs(parseFloat(loc.longitude) - parseFloat(longitude)) < tolerance
  )?.id || '';
};

const calculateDistance = async ({ date, segment }) => {
  isCalculatingDistance.value[`${date}-${segment}`] = true;
  props.errors.global = '';
  try {
    const activity = localActivities.value.find(a => {
      const activityDate = new Date(a.datetime).toISOString().split('T')[0];
      return activityDate === date && (
        (segment === 'home_to_depot' && a.activity === 'Left Home') ||
        (segment === 'depot_to_first_drop' && a.activity === 'Leave Depot') ||
        (segment === 'last_drop_to_home' && a.activity === 'Last Drop')
      );
    });
    if (!activity) {
      props.errors.global = `No ${segment.replace('_', ' ')} activity found for ${date}. Please add the activity.`;
      console.warn('Activity not found:', { date, segment });
      return;
    }
    console.log('Sending calculate distance request:', { activity_id: activity.id, date, segment });
    const response = await axios.post('/distances/calculate', {
      activity_id: activity.id,
      date,
      segment,
      _token: props.csrf,
    }, {
      headers: {
        'X-CSRF-TOKEN': props.csrf,
      },
    });
    console.log('Calculate distance response:', JSON.parse(JSON.stringify(response.data)));
    if (response.data.error) {
      props.errors.global = response.data.error;
      console.error('API error:', response.data.error);
    } else {
      localDistances.value = {
        ...localDistances.value,
        [date]: {
          ...(localDistances.value[date] || {}),
          [segment]: response.data.distance,
        },
      };
      console.log('Distance updated in localDistances:', JSON.parse(JSON.stringify(localDistances.value)));
      // Reload distances only
      router.reload({
        only: ['distances'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          localDistances.value = props.distances || {};
          console.log('Distances reloaded after calculation:', JSON.parse(JSON.stringify(localDistances.value)));
        },
      });
    }
  } catch (error) {
    props.errors.global = error.response?.data?.error || 'Failed to calculate distance';
    console.error('Distance calculation error:', error.response?.data || error);
  } finally {
    isCalculatingDistance.value[`${date}-${segment}`] = false;
  }
};

const openCreateModal = () => {
  modalMode.value = 'create';
  modalActivity.value = { activity: '', latitude: null, longitude: null, datetime: new Date().toISOString() };
  showCreateModal.value = true;
};

const openEditModal = (activity) => {
  modalMode.value = 'edit';
  modalActivity.value = { ...activity };
  showEditModal.value = true;
};

const openManualModal = ({ date }) => {
  modalMode.value = 'manual';
  modalActivity.value = { activity: '', latitude: null, longitude: null, datetime: `${date}T00:00:00` };
  manualDate.value = date;
  showManualModal.value = true;
};

const closeModal = () => {
  showCreateModal.value = false;
  showEditModal.value = false;
  showManualModal.value = false;
  modalMode.value = '';
  modalActivity.value = null;
  manualDate.value = '';
  props.errors.global = ''; // Clear errors on close
};

const handleFormSubmit = ({ mode, data }) => {
  const form = useForm(data);
  const isCreating = mode === 'create' || mode === 'manual';
  const isEditing = mode === 'edit';

  if (isFixedLocationActivity(data.activity)) {
    const locationName = getFixedLocationName(data.activity);
    const location = props.locations.find(loc => loc.name === locationName);
    if (!location) {
      props.errors.global = `${locationName} location not found.`;
      return;
    }
    form.latitude = location.latitude;
    form.longitude = location.longitude;
  }

  console.log(`${mode} activity:`, form.data());
  const url = isEditing ? `/activity/${data.id}` : '/activities';
  const method = isEditing ? 'put' : 'post';

  form[method](url, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      closeModal();
      router.reload({
        only: ['activities', 'activityDates'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          localActivities.value = props.activities || [];
          initializeSelectedLocations();
          console.log('Activities reloaded:', localActivities.value.length);
        },
      });
    },
    onError: (errors) => {
      props.errors.global = Object.values(errors).join(', ');
      console.error(`${mode} activity errors:`, errors);
    },
  });
};

const updateActivityLocation = ({ activityId, locationId }) => {
  if (!locationId) {
    selectedLocation.value[activityId] = '';
    return;
  }
  isUpdatingActivityLocation.value[activityId] = true;
  props.errors.global = '';
  const location = props.locations.find(loc => loc.id === parseInt(locationId));
  if (!location) {
    props.errors.global = 'Selected location not found';
    isUpdatingActivityLocation.value[activityId] = false;
    selectedLocation.value[activityId] = '';
    return;
  }
  console.log(`Updating activity ${activityId} to location ${location.name}:`, { latitude: location.latitude, longitude: location.longitude });
  router.patch(`/activity/${activityId}`, {
    latitude: location.latitude,
    longitude: location.longitude,
  }, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        only: ['activities'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          localActivities.value = props.activities || [];
          initializeSelectedLocations();
        },
      });
    },
    onError: (errors) => {
      props.errors.global = Object.values(errors).join(', ') || 'Failed to update activity location';
      console.error('Update activity location errors:', errors);
      selectedLocation.value[activityId] = findMatchingLocation(
        props.activities.find(act => act.id === activityId)?.latitude,
        props.activities.find(act => act.id === activityId)?.longitude
      );
    },
    onFinish: () => {
      isUpdatingActivityLocation.value[activityId] = false;
    },
  });
};

const addLocation = (location) => {
  router.post('/locations', location, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        only: ['locations'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          initializeSelectedLocations();
        },
      });
    },
    onError: (errors) => {
      props.errors.global = Object.values(errors).join(', ') || 'Failed to add location';
      console.error('Add location errors:', errors);
    },
  });
};

const updateLocation = ({ id, data }) => {
  router.put(`/locations/${id}`, data, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        only: ['locations'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          initializeSelectedLocations();
        },
      });
    },
    onError: (errors) => {
      props.errors.global = Object.values(errors).join(', ') || 'Failed to add location';
      console.error('Update location errors:', errors);
    },
  });
};

const deleteLocation = (location) => {
  if (!confirm(`Delete location ${location.name}?`)) return;
  router.delete(`/locations/${location.id}`, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        only: ['locations'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          initializeSelectedLocations();
        },
      });
    },
    onError: (errors) => {
      props.errors.global = Object.values(errors).join(', ') || 'Failed to delete location';
      console.error('Delete location errors:', errors);
    },
  });
};

const deleteActivity = (id) => {
  if (!confirm('Are you sure you want to delete this activity?')) return;
  router.delete(`/activity/${id}`, {
    onSuccess: () => {
      localActivities.value = props.activities || [];
      initializeSelectedLocations();
      router.reload({
        only: ['activities'],
        preserveState: true,
        preserveScroll: true,
      });
    },
  });
};

const markAsCorrect = (activity) => {
  if (!confirm('Mark this as the correct activity and delete duplicates?')) return;
  router.post(`/activity/${activity.id}/mark-as-correct`, { id: activity.id }, {
    onSuccess: () => {
      localActivities.value = props.activities || [];
      initializeSelectedLocations();
      router.reload({
        only: ['activities'],
        preserveState: true,
        preserveScroll: true,
      });
    },
    onError: (errors) => {
      props.errors.global = errors.message || 'Failed to mark activity as correct';
    },
  });
};

const isFixedLocationActivity = (activityName) => {
  return ['Left Home', 'Arrive Depot', 'Start Loading', 'Leave Depot', 'Arrive Home'].includes(activityName);
};

const getFixedLocationName = (activityName) => {
  if (['Left Home', 'Arrive Home'].includes(activityName)) return 'Home';
  if (['Arrive Depot', 'Start Loading', 'Leave Depot'].includes(activityName)) return 'Depot';
  return '';
};
</script>