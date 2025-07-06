<template>
  <Head title="Delivery Settings" />
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-white leading-tight">
        Delivery Settings
      </h2>
    </template>
    <div class="flex-1 flex flex-col">
      <div class="sm:px-2 lg:px-2 flex-1 flex flex-col">
        <div class="bg-gray-800 rounded-lg shadow-sm flex-1 flex flex-col">
          <div class="p-6 bg-gray-800 border-b border-gray-700 flex-1 flex flex-col">
            <div v-if="$page.props.flash.success" class="text-green-400 mb-4 text-sm">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.errors.global" class="text-red-400 mb-4">{{ $page.props.errors.global }}</div>
            <form @submit.prevent="submitForm">
              <div class="mb-4">
                <label for="home_to_depot_distance" class="block text-sm font-medium text-gray-300">Home to Depot Distance (mi)</label>
                <input
                  v-model.number="form.home_to_depot_distance"
                  type="number"
                  id="home_to_depot_distance"
                  step="any"
                  class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500"
                  required
                />
              </div>
              <div class="mb-4">
                <label for="first_drop_distance" class="block text-sm font-medium text-gray-300">First Drop Distance (mi)</label>
                <input
                  v-model.number="form.first_drop_distance"
                  type="number"
                  id="first_drop_distance"
                  step="any"
                  class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500"
                  required
                />
              </div>
              <div class="mb-4">
                <label for="last_drop_distance" class="block text-sm font-medium text-gray-300">Last Drop Distance (mi)</label>
                <input
                  v-model.number="form.last_drop_distance"
                  type="number"
                  id="last_drop_distance"
                  step="any"
                  class="mt-1 block w-full rounded-md bg-gray-700 border-gray-600 text-white focus:border-indigo-500 focus:ring-indigo-500"
                  required
                />
              </div>
              <div class="flex justify-end">
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
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  errors: Object,
  flash: Object,
  csrf: String,
});

const form = useForm({
  home_to_depot_distance: props.settings.home_to_depot_distance || 10.0,
  first_drop_distance: props.settings.first_drop_distance || 10.0,
  last_drop_distance: props.settings.last_drop_distance || 10.0,
});

const isSubmitting = ref(false);

const submitForm = () => {
  isSubmitting.value = true;
  form.post('/delivery-settings', {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      console.log('Delivery settings saved');
      isSubmitting.value = false;
    },
    onError: (errors) => {
      console.error('Delivery settings save errors:', errors);
      isSubmitting.value = false;
    },
  });
};
</script>