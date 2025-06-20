<template>
  <table class="min-w-full divide-y divide-gray-700">
    <thead class="bg-gray-900">
      <tr>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Home to Depot (mi)</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Depot to First Drop (mi)</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Last Drop to Home (mi)</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Total Distance (mi)</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
      </tr>
    </thead>
    <tbody class="bg-gray-800 divide-y divide-gray-700">
      <template v-for="(group, date) in groupedActivities" :key="date">
        <tr class="bg-gray-900">
          <td class="px-6 py-2 text-sm font-sm text-gray-300 cursor-pointer" @click="toggleExpand(date)">
            <div class="flex items-center">
              <ChevronDownIcon v-if="isExpanded[date]" class="w-4 h-4 mr-2" />
              <ChevronRightIcon v-else class="w-4 h-4 mr-2" />
              {{ formatDate(date, 'date') }}
            </div>
          </td>
          <td class="px-6 py-2 text-sm font-sm text-gray-300 text-center">
            {{ localDistances[normalizeDate(date)]?.home_to_depot ? Number(localDistances[normalizeDate(date)].home_to_depot).toFixed(2) : '-' }}
          </td>
          <td class="px-6 py-2 text-sm font-sm text-gray-300 text-center">
            {{ localDistances[normalizeDate(date)]?.depot_to_first_drop ? Number(localDistances[normalizeDate(date)].depot_to_first_drop).toFixed(2) : '-' }}
          </td>
          <td class="px-6 py-2 text-sm font-sm text-gray-300 text-center">
            {{ localDistances[normalizeDate(date)]?.last_drop_to_home ? Number(localDistances[normalizeDate(date)].last_drop_to_home).toFixed(2) : '-' }}
          </td>
          <td class="px-6 py-2 text-sm font-sm text-gray-300 text-center">
            {{ calculateTotalDistance(date) }}
          </td>
          <td class="px-6 py-2 flex items-center space-x-2">
            <button
              v-if="!localDistances[normalizeDate(date)]?.home_to_depot"
              @click="$emit('calculate-distance', { date, segment: 'home_to_depot' })"
              :disabled="isCalculatingDistance[`${date}-home_to_depot`]"
              class="text-blue-400 hover:text-blue-300 text-sm flex items-center"
            >
              <span v-if="isCalculatingDistance[`${date}-home_to_depot`]" class="inline-block w-3 h-3 mr-1 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              Home to Depot
            </button>
            <button
              v-if="!localDistances[normalizeDate(date)]?.depot_to_first_drop"
              @click="$emit('calculate-distance', { date, segment: 'depot_to_first_drop' })"
              :disabled="isCalculatingDistance[`${date}-depot_to_first_drop`]"
              class="text-blue-400 hover:text-blue-300 text-sm flex items-center"
            >
              <span v-if="isCalculatingDistance[`${date}-depot_to_first_drop`]" class="inline-block w-3 h-3 mr-1 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              Depot to First
            </button>
            <button
              v-if="!localDistances[normalizeDate(date)]?.last_drop_to_home"
              @click="$emit('calculate-distance', { date, segment: 'last_drop_to_home' })"
              :disabled="isCalculatingDistance[`${date}-last_drop_to_home`]"
              class="text-blue-400 hover:text-blue-300 text-sm flex items-center"
            >
              <span v-if="isCalculatingDistance[`${date}-last_drop_to_home`]" class="inline-block w-3 h-3 mr-1 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              Last to Home
            </button>
            <button
              @click="$emit('add-manual', { date })"
              class="text-green-400 hover:text-green-300 text-sm"
            >
              Add Activity
            </button>
          </td>
        </tr>
        <tr v-if="isExpanded[date]" v-for="activity in group" :key="activity.id || activity.activity" class="bg-gray-800">
          <td class="px-6 py-4 whitespace-nowrap text-white text-sm">{{ activity.id || '-' }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-white text-sm">{{ activity.datetime ? formatDate(activity.datetime, 'time') : '-' }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-white text-sm">{{ activity.latitude || '-' }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-white text-sm">{{ activity.longitude || '-' }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-white text-sm">{{ activity.activity }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-white text-sm">{{ activity.is_manual ? 'Yes' : 'No' }}</td>
          <td class="px-6 py-4 whitespace-nowrap flex items-center space-x-2">
            <button
              v-if="activity.id"
              @click="$emit('edit-activity', activity)"
              class="text-indigo-400 hover:text-indigo-300 text-sm"
            >
              Edit
            </button>
            <button
              v-if="activity.id"
              @click="$emit('delete-activity', activity.id)"
              class="text-red-400 hover:text-red-300 text-sm"
            >
              Delete
            </button>
          </td>
        </tr>
      </template>
      <tr v-if="!Object.keys(groupedActivities).length">
        <td :colspan="6" class="px-6 py-4 text-center text-gray-300 text-sm">No activities found</td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
  activities: { type: Array, default: () => [] },
  distances: { type: Object, default: () => ({}) },
  isCalculatingDistance: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['edit-activity', 'delete-activity', 'calculate-distance', 'add-manual']);

const isExpanded = ref({});
const localDistances = computed(() => {
  console.log('Computing localDistances:', JSON.parse(JSON.stringify(props.distances)));
  return props.distances || {};
});

// Normalize date keys to Y-m-d
const normalizeDate = (date) => {
  return date.split(' ')[0]; // Strips timestamp (e.g., '2025-06-09 00:00:00' → '2025-06-09')
};

// Calculate total distance for a date
const calculateTotalDistance = (date) => {
  const normalizedDate = normalizeDate(date);
  const distances = localDistances.value[normalizedDate] || {};
  const total = [
    distances.home_to_depot || 0,
    distances.depot_to_first_drop || 0,
    distances.last_drop_to_home || 0,
  ].reduce((sum, val) => sum + Number(val), 0);
  return total > 0 ? total.toFixed(2) : '-';
};

// Format date with local timezone (BST)
const formatDate = (dateString, type) => {
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
  if (type === 'date') {
    return date.toLocaleDateString('en-GB', { ...options, day: '2-digit', month: '2-digit', year: 'numeric' });
  } else if (type === 'time') {
    return date.toLocaleTimeString('en-GB', { ...options, hour: '2-digit', minute: '2-digit' });
  }
  return date.toLocaleString('en-GB', options);
};

// Group activities by date
const groupedActivities = computed(() => {
  const groups = {};
  props.activities.forEach((activity) => {
    if (!activity || !activity.datetime) {
      console.warn('Invalid activity or datetime:', activity);
      return;
    }
    const date = new Date(activity.datetime);
    if (isNaN(date.getTime())) {
      console.warn('Invalid datetime for activity:', activity);
      return;
    }
    const dateKey = date.toISOString().split('T')[0];
    if (!groups[dateKey]) groups[dateKey] = [];
    groups[dateKey].push(activity);
  });
  return groups;
});

const toggleExpand = (date) => {
  isExpanded.value[date] = !isExpanded.value[date];
};

onMounted(() => {
  console.log('ActivityTable mounted:', {
    activities: props.activities.length,
    activityIds: props.activities.map(a => a.id),
    distances: JSON.parse(JSON.stringify(props.distances)),
    groupedDays: Object.keys(groupedActivities.value),
  });
});

watch(() => props.distances, (newDistances) => {
  console.log('Distances updated in ActivityTable:', JSON.parse(JSON.stringify(newDistances)));
});
</script>