<template>
  <AppLayout>

    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Recorded Activities
      </h2>
    </template>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Latitude</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Longitude</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="activity in activities" :key="activity.id">
                  <td class="px-6 py-4 whitespace-nowrap">{{ formatDate(activity.datetime) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ activity.latitude }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ activity.longitude }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ activity.activity }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </AppLayout>>
</template>

<script setup>

import { ref, onMounted } from 'vue';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';

const activities = ref([]);

const fetchActivities = async () => {
  try {
    const response = await axios.get('/api/activities');
    activities.value = response.data.data;
  } catch (error) {
    console.error('Error fetching activities:', error);
  }
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString();
};

onMounted(fetchActivities);
</script>