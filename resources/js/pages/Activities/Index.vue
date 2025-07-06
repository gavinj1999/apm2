<!-- resources/js/Pages/Activities/Index.vue -->
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
              <a
                href="/delivery-settings"
                class="font-bold rounded-lg text-xs w-24 h-8 bg-indigo-600 text-[#ffffff] flex items-center justify-center"
              >
                Delivery Settings
              </a>
            </div>
            <ActivityTable
              :activities="localActivities"
              :activity-types="activityTypes"
              :distances="localDistances"
              :locations="locations"
              :settings="settings"
              :is-calculating-distance="isCalculatingDistance"
              @edit-activity="openEditModal"
              @delete-activity="deleteActivity"
              @add-manual="openManualModal"
              @mark-correct="markAsCorrect"
              @calculate-distance="calculateDistance"
              @update-location="updateActivityLocation"
              @add-start-loading="openStartLoadingModal"
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
              v-if="showCreateModal || showEditModal || showManualModal || showStartLoadingModal"
              :mode="modalMode"
              :activity="modalActivity"
              :activity-types="activityTypes"
              :locations="locations"
              :mapbox-token="mapboxToken"
              :date="modalDate"
              :rounds="startLoadingRounds"
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
  settings: { type: Object, default: () => ({}) },
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
const showStartLoadingModal = ref(false);
const modalMode = ref('');
const modalActivity = ref(null);
const modalDate = ref('');
const startLoadingRounds = ref(1);
const isCalculatingDistance = ref({});
const isUpdatingActivityLocation = ref({});
const selectedLocation = ref({});

onMounted(() => {
  console.log('Index.vue mounted:', {
    activities: props.activities.length,
    activityIds: props.activities.map(a => a.id),
    distances: JSON.parse(JSON.stringify(props.distances)),
    settings: JSON.parse(JSON.stringify(props.settings)),
    activityDates: props.activityDates,
    csrf: props.csrf,
  });
  localActivities.value = props.activities || [];
  localDistances.value = props.distances || {};
  initializeSelectedLocations();
  autoAddStartLoading();
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

const calculateDistance = async ({ date, segment, useEstimate }) => {
  isCalculatingDistance.value[`${date}-${segment}`] = true;
  props.errors.global = '';
  try {
    if (useEstimate) {
      const response = await axios.post('/distances/calculate', {
        date,
        segment,
        use_estimate: true,
        _token: props.csrf,
      }, {
        headers: { 'X-CSRF-TOKEN': props.csrf },
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
    } else {
      const activity = localActivities.value.find(a => {
        const activityDate = new Date(a.datetime).toISOString().split('T')[0];
        return activityDate === date && (
          (segment === 'home_to_depot' && a.activity === 'Left Home') ||
          (segment === 'depot_to_first_drop' && a.activity === 'Leave Depot') ||
          (segment === 'last_drop_to_home' && a.activity === 'Last Drop')
        );
      });
      if (!activity) {
        props.errors.global = `No ${segment.replace('_', ' ')} activity found for ${date}. Please add the activity or use estimate.`;
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
        headers: { 'X-CSRF-TOKEN': props.csrf },
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
    }
  } catch (error) {
    props.errors.global = error.response?.data?.error || 'Failed to calculate distance';
    console.error('Distance calculation error:', error.response?.data || error);
  } finally {
    isCalculatingDistance.value[`${date}-${segment}`] = false;
  }
};

const openCreateModal = () => {
  console.log('Opening create modal');
  modalMode.value = 'create';
  modalActivity.value = { activity: '', latitude: null, longitude: null, datetime: new Date().toISOString().slice(0, 16) };
  showCreateModal.value = true;
};

const openEditModal = (activity) => {
  console.log('Opening edit modal for activity:', activity);
  modalMode.value = 'edit';
  modalActivity.value = { ...activity };
  showEditModal.value = true;
};

const openManualModal = ({ date, activity }) => {
  console.log('Opening manual modal for date:', date, 'activity:', activity || 'generic');
  modalMode.value = 'manual';
  modalActivity.value = { 
    activity: activity || '', 
    latitude: null, 
    longitude: null, 
    datetime: `${date}T00:00:00`,
    is_manual: true 
  };
  modalDate.value = date;
  showManualModal.value = true;
};

const openStartLoadingModal = ({ date, rounds }) => {
  console.log('Opening start loading modal for date:', date, 'with rounds:', rounds);
  modalMode.value = 'start-loading';
  startLoadingRounds.value = rounds;
  const leaveDepot = localActivities.value.find(a => {
    const activityDate = new Date(a.datetime).toISOString().split('T')[0];
    return activityDate === date && a.activity === 'Leave Depot';
  });
  if (!leaveDepot) {
    props.errors.global = `No Leave Depot activity found for ${date}`;
    console.warn('No Leave Depot activity found:', date);
    return;
  }
  const depot = props.locations.find(loc => loc.name === 'Depot');
  if (!depot) {
    props.errors.global = 'Depot location not found';
    console.error('Depot location not found');
    return;
  }
  const leaveTime = new Date(leaveDepot.datetime);
  const minutesToSubtract = rounds * 20;
  const startLoadingTime = new Date(leaveTime.getTime() - minutesToSubtract * 60 * 1000);
  modalActivity.value = {
    activity: 'Start Loading',
    latitude: depot.latitude,
    longitude: depot.longitude,
    datetime: startLoadingTime.toISOString().slice(0, 16),
    is_manual: true,
  };
  modalDate.value = date;
  showStartLoadingModal.value = true;
};

const closeModal = () => {
  console.log('Closing modal');
  showCreateModal.value = false;
  showEditModal.value = false;
  showManualModal.value = false;
  showStartLoadingModal.value = false;
  modalMode.value = '';
  modalActivity.value = null;
  modalDate.value = '';
  startLoadingRounds.value = 1;
  props.errors.global = '';
};

const handleFormSubmit = ({ mode, data }) => {
  console.log('Handling form submit:', { mode, data });
  const form = useForm({
    id: data.id || null,
    activity: data.activity,
    datetime: data.datetime,
    latitude: data.latitude,
    longitude: data.longitude,
    is_manual: data.is_manual || false,
  });

  const isCreating = mode === 'create' || mode === 'manual' || mode === 'start-loading';
  const isEditing = mode === 'edit';

  if (isFixedLocationActivity(data.activity)) {
    const locationName = getFixedLocationName(data.activity);
    const location = props.locations.find(loc => loc.name === locationName);
    if (!location) {
      props.errors.global = `${locationName} location not found.`;
      console.error('Location not found:', locationName);
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
      console.log('Form submit success');
      closeModal();
      router.reload({
        only: ['activities', 'activityDates', 'distances'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          localActivities.value = props.activities || [];
          localDistances.value = props.distances || {};
          initializeSelectedLocations();
          console.log('Activities reloaded:', localActivities.value.length);
          console.log('Distances reloaded:', JSON.parse(JSON.stringify(localDistances.value)));
          autoAddStartLoading();
        },
      });
    },
    onError: (errors) => {
      props.errors.global = Object.values(errors).join(', ');
      console.error(`${mode} activity errors:`, errors);
    },
  });
};

const autoAddStartLoading = () => {
  console.log('Running autoAddStartLoading');
  const dates = Object.keys(groupedActivities());
  dates.forEach(date => {
    const activities = groupedActivities()[date];
    const hasStartLoading = activities.some(a => a.activity === 'Start Loading');
    const hasLeaveDepot = activities.some(a => a.activity === 'Leave Depot');
    const firstDrops = activities.filter(a => a.activity === 'First Drop').length;
    const rounds = firstDrops > 0 ? firstDrops : 1;

    if (!hasStartLoading && hasLeaveDepot) {
      console.log(`Auto-adding Start Loading for ${date} with ${rounds} round(s)`);
      openStartLoadingModal({ date, rounds });
    }
  });
};

const groupedActivities = () => {
  const groups = {};
  localActivities.value.forEach(activity => {
    if (!activity || !activity.datetime) {
      console.warn('Invalid activity or datetime:', activity);
      return;
    }
    const date = new Date(activity.datetime).toISOString().split('T')[0];
    if (!groups[date]) groups[date] = [];
    groups[date].push(activity);
  });
  return groups;
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
      props.errors.global = Object.values(errors).join(', ') || 'Failed to update location';
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